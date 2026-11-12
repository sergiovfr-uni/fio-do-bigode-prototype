<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\EarlyPayoffController;
use App\Models\Deal;
use App\Models\User;
use App\Services\DealEventService;
use App\Services\DischargeTermService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

config(['database.default'=>'sqlite', 'database.connections.sqlite'=>[
    'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
]]);
DB::purge('sqlite');
Storage::fake('local');

foreach ([
    '2026_08_19_000001_create_mvp_core_tables.php',
    '2026_08_19_000002_create_password_reset_and_documents_tables.php',
    '2026_08_19_000003_create_installments_and_wallet_tables.php',
    '2026_08_20_000008_create_notifications_and_deal_events.php',
    '2026_08_25_000022_add_installment_receipts_and_reminder_keys.php',
    '2026_08_26_000029_create_deal_ratings_table.php',
    '2026_11_12_000037_create_early_payoffs.php',
] as $file) (require __DIR__.'/../database/migrations/'.$file)->up();

Schema::table('deals', function (Blueprint $t) {
    $t->timestamp('formalized_at')->nullable();
    $t->timestamp('paid_off_at')->nullable();
    $t->string('title')->nullable();
});
Schema::table('deal_documents', fn (Blueprint $t) => $t->binary('content_blob')->nullable());

function checkPayoff(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function failsPayoff(callable $fn, int $status): bool
{
    try { $fn(); } catch (HttpException $e) { return $e->getStatusCode() === $status; }
    return false;
}
function payoffRequest(User $user, array $data = []): Request
{
    $request = Request::create('/', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    return $request;
}
[$seller, $buyer, $outsider] = array_map(fn ($id) => User::create([
    'name'=>'Pessoa '.$id, 'cpf'=>str_pad((string) $id, 11, '0', STR_PAD_LEFT),
    'email'=>'person'.$id.'@example.test', 'phone'=>'3100000000', 'password'=>'unused',
]), [1, 2, 3]);
$deal = Deal::create([
    'seller_id'=>$seller->id, 'buyer_id'=>$buyer->id, 'title'=>'Venda de veículo',
    'formalized_at'=>now(), 'status'=>'active', 'total_amount'=>'5300.00',
]);
$deal->installments()->create(['number'=>1,'amount'=>'300.00','due_date'=>today(),'status'=>'paid','paid_at'=>now()]);
$second = $deal->installments()->create(['number'=>2,'amount'=>'2000.00','due_date'=>today()->addMonth()]);
$deal->installments()->create(['number'=>3,'amount'=>'3000.00','due_date'=>today()->addMonths(2)]);
$api = new EarlyPayoffController();
$events = new DealEventService();
$terms = new DischargeTermService();
$start = fn ($actor, $cents) => $api->store(payoffRequest($actor, ['amount_cents'=>$cents]), $deal->fresh(), $events)->getData();
$act = fn ($actor, $id, $action, $extra = []) => $api->respond(
    payoffRequest($actor, ['action'=>$action, ...$extra]), $deal->fresh(), $id, $events, $terms
)->getData();

checkPayoff(failsPayoff(fn () => $start($seller, 470000), 403), 'Recebedor não pode iniciar');
checkPayoff(failsPayoff(fn () => $api->show(payoffRequest($outsider), $deal), 403), 'Terceiros não acessam o acordo');
checkPayoff(failsPayoff(fn () => $start($buyer, 500001), 422), 'Valor não pode superar o saldo registrado');
$id = $start($buyer, 470000)->payoff->id;
checkPayoff(failsPayoff(fn () => $start($buyer, 470000), 409), 'Só uma solicitação em andamento');
checkPayoff($act($seller, $id, 'full_amount')->payoff->status === 'countered', 'Recebedor pode manter o saldo integral');
checkPayoff(failsPayoff(fn () => $act($seller, $id, 'accept'), 403), 'Só o pagador aceita a contraproposta integral');
checkPayoff($act($buyer, $id, 'decline')->payoff->status === 'declined' && $deal->fresh()->status === 'active',
    'Recusa preserva o parcelamento original');
$id = $start($buyer, 470000)->payoff->id;
$second->update(['due_date'=>today()->addMonths(3)]);
checkPayoff(failsPayoff(fn () => $act($seller, $id, 'accept'), 409), 'Cronograma alterado exige nova proposta');
$act($buyer, $id, 'cancel');
$id = $start($buyer, 470000)->payoff->id;
$accept = $act($seller, $id, 'accept');
checkPayoff((int) $accept->payoff->agreed_cents === 470000 && $deal->fresh()->status === 'active', 'Aceite não quita o acordo');
checkPayoff(failsPayoff(fn () => $act($seller, $id, 'confirm_receipt'), 403), 'Confirmação exige comprovante');
$receipt = ['file_name'=>'recibo.pdf','file_base64'=>base64_encode("%PDF-1.4\n% test receipt\n")];
checkPayoff($act($buyer, $id, 'submit_receipt', $receipt)->payoff->status === 'receipt_submitted',
    'Pagador pode enviar comprovante');
checkPayoff(failsPayoff(fn () => $act($buyer, $id, 'confirm_receipt'), 403), 'Pagador não confirma o próprio recebimento');
$final = $act($seller, $id, 'confirm_receipt');
checkPayoff($final->payoff->status === 'confirmed' && $deal->fresh()->status === 'paid_off', 'Recebedor encerra após confirmação');
checkPayoff($deal->installments()->where('external_payment_id', 'early-payoff-'.$id)->count() === 2,
    'Somente as duas parcelas em aberto são baixadas');
checkPayoff(DB::table('wallet_transactions')->count() === 0, 'Registro de pagamento externo não cria movimento na carteira');
$term = DB::table('deal_documents')->find($final->payoff->term_id);
checkPayoff($term->type === 'discharge_term' && str_starts_with($term->content_blob, '%PDF'), 'Termo disponível antes de avaliações');
checkPayoff($act($seller, $id, 'confirm_receipt')->payoff->term_id === $final->payoff->term_id
    && DB::table('deal_documents')->where('type','discharge_term')->count() === 1, 'Confirmação repetida não duplica termo');
