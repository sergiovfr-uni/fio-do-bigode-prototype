<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\User;
use App\Services\DealEventService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DealInvitationController extends Controller
{
    public function index(Request $request)
    {
        $items = DB::table('deal_invitations')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        // Registros antigos de homologação podem conter convites duplicados.
        // Para a API/UI, mantemos apenas um convite pendente por negociação equivalente.
        $seenPending = [];
        $filtered = $items->filter(function ($i) use (&$seenPending) {
            if ($i->status !== 'pending') return true;

            $key = $i->fingerprint ?: $this->legacyFingerprint($i);
            if (isset($seenPending[$key])) return false;
            $seenPending[$key] = true;
            return true;
        })->values();

        return $filtered->map(fn($i) => [
            'code'=>$i->code,'title'=>$i->title,'description'=>$i->description,'status'=>$i->status,
            'invitee_name'=>$i->invitee_name,'invitee_email'=>$i->invitee_email,'invitee_phone'=>$i->invitee_phone,
            'initiator_role'=>$i->initiator_role,'total_amount'=>$i->total_amount,'down_payment'=>$i->down_payment,
            'installments'=>$i->installments,'monthly_interest'=>$i->monthly_interest,'expires_at'=>$i->expires_at,
            'invite_url'=>'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?invite='.$i->code,
        ]);
    }

    public function show(string $code)
    {
        $invite = DB::table('deal_invitations')->where('code', strtoupper($code))->first();
        abort_unless($invite && $invite->status === 'pending' && (!$invite->expires_at || now()->lte($invite->expires_at)), 404, 'Convite inválido ou expirado.');
        $creator = User::find($invite->created_by);

        return response()->json([
            'code'=>$invite->code,'title'=>$invite->title,'description'=>$invite->description,
            'total_amount'=>$invite->total_amount,'down_payment'=>$invite->down_payment,'installments'=>$invite->installments,
            'monthly_interest'=>$invite->monthly_interest,'initiator_role'=>$invite->initiator_role,
            'created_by'=>['name'=>$creator?->name,'reputation_score'=>$creator?->reputation_score,'kyc_status'=>$creator?->kyc_status],
            'expires_at'=>$invite->expires_at,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de criar uma negociação.');

        // Homologação: negociações diretas ficam sem limite para permitir validação completa da jornada.

        $data = $request->validate([
            'initiator_role'=>['required','in:seller,buyer'],'invitee_name'=>['nullable','string','max:160'],
            'invitee_email'=>['nullable','email','max:255'],'invitee_phone'=>['nullable','string','max:20'],
            'title'=>['required','string','max:180'],'description'=>['required','string','max:5000'],
            'total_amount'=>['required','numeric','min:0.01'],'down_payment'=>['nullable','numeric','min:0'],
            'installments'=>['required','integer','min:1','max:120'],'monthly_interest'=>['nullable','numeric','min:0','max:20'],
        ]);

        $fingerprint = $this->fingerprint($user->id, $data);

        // Se a mesma negociação já possui convite pendente, reutilizamos o código existente.
        $existing = DB::table('deal_invitations')
            ->where('created_by', $user->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($fingerprint, $data) {
                $q->where('fingerprint', $fingerprint)
                    ->orWhere(function ($legacy) use ($data) {
                        $legacy->whereNull('fingerprint')
                            ->where('initiator_role', $data['initiator_role'])
                            ->where('title', $data['title'])
                            ->where('description', $data['description'])
                            ->where('total_amount', $data['total_amount'])
                            ->where('down_payment', $data['down_payment'] ?? 0)
                            ->where('installments', $data['installments'])
                            ->where('monthly_interest', $data['monthly_interest'] ?? 0)
                            ->where('invitee_email', $data['invitee_email'] ?? null)
                            ->where('invitee_phone', $data['invitee_phone'] ?? null);
                    });
            })
            ->orderByDesc('id')
            ->first();

        if ($existing && (!$existing->expires_at || now()->lte($existing->expires_at))) {
            return response()->json($this->inviteResponse($existing, true));
        }

        $code = $this->newCode();
        $expires = now()->addDays(7);

        try {
            DB::table('deal_invitations')->insert([
                'code'=>$code,'fingerprint'=>$fingerprint,'created_by'=>$user->id,'initiator_role'=>$data['initiator_role'],
                'invitee_name'=>$data['invitee_name']??null,'invitee_email'=>$data['invitee_email']??null,'invitee_phone'=>$data['invitee_phone']??null,
                'title'=>$data['title'],'description'=>$data['description'],'total_amount'=>$data['total_amount'],
                'down_payment'=>$data['down_payment']??0,'installments'=>$data['installments'],'monthly_interest'=>$data['monthly_interest']??0,
                'status'=>'pending','expires_at'=>$expires,'created_at'=>now(),'updated_at'=>now(),
            ]);
        } catch (QueryException $e) {
            // Proteção contra duplo toque/retry concorrente: a constraint única vence a corrida.
            if ((string)$e->getCode() !== '23000') throw $e;
            $existing = DB::table('deal_invitations')->where('fingerprint', $fingerprint)->where('status','pending')->first();
            if (!$existing) throw $e;
            return response()->json($this->inviteResponse($existing, true));
        }

        $invite = DB::table('deal_invitations')->where('code', $code)->first();

$this->sendInvitationEmail($invite, $user);
$registeredInvitee = $invite->invitee_email
    ? User::whereRaw('LOWER(email) = ?', [mb_strtolower($invite->invitee_email)])->first()
    : null;
if ($registeredInvitee && $registeredInvitee->id !== $user->id) {
    DB::table('notifications')->insert([
        'user_id'=>$registeredInvitee->id,'deal_id'=>null,'type'=>'deal_invitation_received',
        'title'=>'Novo convite de negociação','message'=>$user->name.' relacionou você à negociação '.$invite->title.'.',
        'data'=>json_encode(['invite_code'=>$invite->code,'invite_url'=>$this->inviteResponse($invite, false)['invite_url']], JSON_UNESCAPED_UNICODE),
        'created_at'=>now(),'updated_at'=>now(),
    ]);
}

return response()->json($this->inviteResponse($invite, false), 201);
    }

    public function accept(Request $request, string $code, DealEventService $events)
    {
        $user = $request->user();
        abort_unless($user->kyc_status === 'verified', 403, 'Conclua o KYC antes de entrar na negociação.');
        $inviteCreatorId = null;

        $deal = DB::transaction(function () use ($code, $user, &$inviteCreatorId) {
            $invite = DB::table('deal_invitations')->where('code', strtoupper($code))->lockForUpdate()->first();
            abort_unless($invite && $invite->status === 'pending' && (!$invite->expires_at || now()->lte($invite->expires_at)), 404, 'Convite inválido ou expirado.');
            abort_if((int)$invite->created_by === (int)$user->id, 422, 'O criador não pode aceitar o próprio convite.');
            $inviteCreatorId = (int)$invite->created_by;

            $sellerId = $invite->initiator_role === 'seller' ? $invite->created_by : $user->id;
            $buyerId = $invite->initiator_role === 'buyer' ? $invite->created_by : $user->id;
            $deal = Deal::create([
                'seller_id'=>$sellerId,'buyer_id'=>$buyerId,'initiator_id'=>$invite->created_by,'listing_id'=>null,'origin'=>'direct','title'=>$invite->title,'description'=>$invite->description,
                'status'=>'proposal_sent','total_amount'=>$invite->total_amount,'down_payment'=>$invite->down_payment,
                'installments'=>$invite->installments,'monthly_interest'=>$invite->monthly_interest,
            ]);
            DealOffer::create([
                'deal_id'=>$deal->id,'created_by'=>$invite->created_by,'type'=>'proposal','total_amount'=>$invite->total_amount,
                'down_payment'=>$invite->down_payment,'installments'=>$invite->installments,'monthly_interest'=>$invite->monthly_interest,'status'=>'pending',
            ]);
            DB::table('deal_invitations')->where('id',$invite->id)->update(['status'=>'accepted','accepted_by'=>$user->id,'accepted_at'=>now(),'updated_at'=>now()]);
            return $deal;
        });

        $events->record($deal,$user->id,'invite_accepted',['code'=>strtoupper($code)]);
        $events->notify($deal,$inviteCreatorId,'invite_accepted','Convite aceito',$user->name.' entrou na negociação '.$deal->title.'.',['deal_id'=>$deal->id]);

        return response()->json($deal->load(['seller:id,name,reputation_score','buyer:id,name,reputation_score','offers']), 201);
    }
private function sendInvitationEmail(object $invite, User $creator): void
{
    if (!$invite->invitee_email || !env('RESEND_API_KEY')) {
        return;
    }

    $inviteUrl = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?invite='.$invite->code;

    $name = htmlspecialchars(
        (string) ($invite->invitee_name ?: 'Olá'),
        ENT_QUOTES,
        'UTF-8'
    );

    $creatorName = htmlspecialchars(
        (string) $creator->name,
        ENT_QUOTES,
        'UTF-8'
    );

    $title = htmlspecialchars(
        (string) $invite->title,
        ENT_QUOTES,
        'UTF-8'
    );

    $description = htmlspecialchars(
        (string) $invite->description,
        ENT_QUOTES,
        'UTF-8'
    );

    $amount = 'R$ '.number_format(
        (float) $invite->total_amount,
        2,
        ',',
        '.'
    );

    $downPayment = 'R$ '.number_format(
        (float) $invite->down_payment,
        2,
        ',',
        '.'
    );

    try {
        Http::withToken(env('RESEND_API_KEY'))
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from' => 'Fio do Bigode <naoresponda@nofiodobigode.app.br>',
                'to' => [$invite->invitee_email],
                'subject' => $creator->name.' convidou você para uma negociação',
                'html' => "
                    <div style='font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#111'>
                        <div style='background:#111;padding:30px;text-align:center;color:#d3a42f'>
                            <h1 style='margin:0'>FIO DO BIGODE</h1>
                        </div>

                        <div style='padding:30px'>
                            <h2>{$name}, você recebeu um convite.</h2>

                            <p>
                                <strong>{$creatorName}</strong>
                                convidou você para participar de uma negociação no Fio do Bigode.
                            </p>

                            <div style='border:1px solid #ddd;border-radius:14px;padding:20px;margin:25px 0'>
                                <strong>{$title}</strong>

                                <p>{$description}</p>

                                <p><strong>Valor:</strong> {$amount}</p>
                                <p><strong>Entrada:</strong> {$downPayment}</p>
                                <p><strong>Parcelas:</strong> {$invite->installments}</p>
                                <p><strong>Juros/mês:</strong> {$invite->monthly_interest}%</p>
                                <p><strong>Código:</strong> {$invite->code}</p>
                            </div>

                            <a
                                href='{$inviteUrl}'
                                style='display:block;background:#111;color:#fff;text-decoration:none;
                                padding:16px;border-radius:12px;text-align:center;font-weight:bold'
                            >
                                Ver negociação
                            </a>

                            <p style='font-size:12px;color:#777;margin-top:25px'>
                                Este convite expira em 7 dias.
                                Antes de participar da negociação, será necessário concluir
                                seu cadastro e validação de identidade.
                            </p>
                        </div>
                    </div>
                ",
            ])
            ->throw();

    } catch (\Throwable $e) {
        report($e);
    }
}
    private function inviteResponse(object $invite, bool $reused): array
    {
        return [
            'code'=>$invite->code,
            'invite_url'=>'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?invite='.$invite->code,
            'expires_at'=>$invite->expires_at,
            'reused'=>$reused,
            'message'=>$reused
                ? 'Esta negociação já possui um convite pendente. O mesmo código foi reutilizado.'
                : 'Convite criado. Compartilhe o link ou o código com a outra parte.',
        ];
    }

    private function fingerprint(int $userId, array $data): string
    {
        $payload = [
            'created_by'=>$userId,
            'initiator_role'=>$data['initiator_role'],
            'invitee_name'=>mb_strtolower(trim((string)($data['invitee_name'] ?? ''))),
            'invitee_email'=>mb_strtolower(trim((string)($data['invitee_email'] ?? ''))),
            'invitee_phone'=>preg_replace('/\D+/', '', (string)($data['invitee_phone'] ?? '')),
            'title'=>mb_strtolower(trim($data['title'])),
            'description'=>trim($data['description']),
            'total_amount'=>number_format((float)$data['total_amount'], 2, '.', ''),
            'down_payment'=>number_format((float)($data['down_payment'] ?? 0), 2, '.', ''),
            'installments'=>(int)$data['installments'],
            'monthly_interest'=>number_format((float)($data['monthly_interest'] ?? 0), 4, '.', ''),
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private function legacyFingerprint(object $invite): string
    {
        return hash('sha256', json_encode([
            'created_by'=>(int)$invite->created_by,
            'initiator_role'=>$invite->initiator_role,
            'invitee_name'=>mb_strtolower(trim((string)$invite->invitee_name)),
            'invitee_email'=>mb_strtolower(trim((string)$invite->invitee_email)),
            'invitee_phone'=>preg_replace('/\D+/', '', (string)$invite->invitee_phone),
            'title'=>mb_strtolower(trim((string)$invite->title)),
            'description'=>trim((string)$invite->description),
            'total_amount'=>number_format((float)$invite->total_amount, 2, '.', ''),
            'down_payment'=>number_format((float)$invite->down_payment, 2, '.', ''),
            'installments'=>(int)$invite->installments,
            'monthly_interest'=>number_format((float)$invite->monthly_interest, 4, '.', ''),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private function newCode(): string
    {
        do { $code = strtoupper(Str::random(8)); } while (DB::table('deal_invitations')->where('code',$code)->exists());
        return $code;
    }
}
