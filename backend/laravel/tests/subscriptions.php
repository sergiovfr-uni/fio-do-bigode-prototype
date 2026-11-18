<?php
// Integration checks against an isolated, in-memory database. Never uses production credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionEntitlementService;
use App\Services\ListingEntitlementService;
use Illuminate\Validation\ValidationException;
config(['database.default'=>'sqlite', 'database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
DB::purge('sqlite');
Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_admin')->default(false); $t->timestamps(); });
Schema::create('plans', function (Blueprint $t) { $t->id(); $t->string('slug'); $t->string('name'); $t->decimal('monthly_price',10,2)->default(0); $t->boolean('active')->default(true); $t->boolean('admin_only')->default(false); $t->boolean('complimentary')->default(false); $t->integer('active_listing_limit')->default(5); $t->integer('direct_deal_limit')->default(5); $t->timestamps(); });
(require __DIR__.'/../database/migrations/2026_11_18_000041_add_plan_audience.php')->up();
Schema::create('subscriptions', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('plan_id'); $t->string('status'); $t->timestamp('trial_ends_at')->nullable(); $t->timestamp('current_period_ends_at')->nullable(); $t->string('gateway')->nullable(); $t->string('external_id')->nullable(); $t->timestamps(); });
Schema::create('listings', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('status'); });
Schema::create('deals', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('origin'); $t->string('status'); });
Schema::create('deal_invitations', function (Blueprint $t) { $t->id(); $t->integer('created_by'); $t->string('status'); $t->timestamp('expires_at')->nullable(); });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function blocked(callable $callback): bool { try { $callback(); return false; } catch (ValidationException $e) { return isset($e->errors()['plan']); } }
$user = User::create(['name'=>'Trial']);
$trial = Plan::create(['slug'=>'trial','name'=>'Trial']);
$controller = new App\Http\Controllers\Api\AuthController;
$provision = new ReflectionMethod($controller, 'ensureFreeTrial');
$provision->invoke($controller, $user);
$sub = $user->subscriptions()->first();
check($sub->trial_ends_at->equalTo(now()->addDays(60)), 'New trial expires in exactly 60 days');
$service = new SubscriptionEntitlementService;
check($service->current($user) !== null, 'Unexpired trial grants access');
Carbon::setTestNow(now()->addDays(60));
check($service->current($user) === null, 'Access expires at the deadline');
check(blocked(fn () => (new ListingEntitlementService)->assertCanPublish($user)), 'Expired trial blocks new listings');
check(blocked(fn () => $service->assertCanCreateDirectDeal($user)), 'Expired trial blocks new direct negotiations');
$provision->invoke($controller, $user);
check($user->subscriptions()->count() === 1, 'Login never renews expired trial');
check($sub->fresh()->effective_status === 'expired', 'API reports expired trial');
$gift = Plan::create(['slug'=>'influenciador','name'=>'Cortesia','complimentary'=>true,'admin_only'=>true]);
$complimentary = Subscription::create(['user_id'=>$user->id,'plan_id'=>$gift->id,'status'=>'active','gateway'=>'admin']);
check($service->current($user)?->id === $complimentary->id, 'Admin courtesy works without expiration');
check(!$user->fresh()->is_admin, 'Courtesy does not grant admin access');
$complimentary->update(['gateway'=>null]);
check($service->current($user) === null, 'Indefinite courtesy requires admin origin');
$complimentary->update(['gateway'=>'admin','current_period_ends_at'=>now()->subSecond()]);
check($service->current($user) === null, 'Dated courtesy expires normally');
$complimentary->update(['current_period_ends_at'=>null]);
DB::table('deal_invitations')->insert(array_fill(0, 5, ['created_by'=>$user->id,'status'=>'pending','expires_at'=>now()->addDay()]));
check(blocked(fn () => $service->assertCanCreateDirectDeal($user)), 'Pending invitations consume negotiation quota');
DB::table('deal_invitations')->update(['expires_at'=>now()->subSecond()]);
$service->assertCanCreateDirectDeal($user);
check(true, 'Expired invitations free negotiation quota');
$gift->update(['active'=>false]);
check($service->current($user) === null, 'Disabled plan grants no new operations');
Carbon::setTestNow();

// Plan audiences remain isolated even when requests bypass the admin form.
Schema::create('admin_audit_logs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('admin_user_id')->nullable();$t->string('action');$t->string('entity_type');$t->string('entity_id');$t->json('before_data')->nullable();$t->json('after_data')->nullable();$t->string('reason')->nullable();$t->string('ip_hash')->nullable();$t->string('user_agent_hash')->nullable();$t->timestamps();});
$admin=User::create(['name'=>'Admin']);$admin->forceFill(['is_admin'=>true])->save();
$ops=new App\Http\Controllers\Api\AdminOperationsController;
function planRequest(array $data,User $user):Illuminate\Http\Request{$r=Illuminate\Http\Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user);return $r;}
function planRejected(callable $fn):bool{try{$fn();return false;}catch(Illuminate\Validation\ValidationException $e){return true;}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){return $e->getStatusCode()===422;}}
$data=['slug'=>'juridico-essencial','name'=>'Jurídico Essencial','audience'=>'legal_partner','monthly_price'=>149,'active'=>true,'admin_only'=>false];
$response=$ops->createPlan(planRequest($data,$admin));$legal=Plan::findOrFail($response->getData()->id);
check($legal->audience==='legal_partner' && $legal->admin_only && $legal->active_listing_limit===0 && $legal->direct_deal_limit===0,'Legal office plan creates without consumer quotas and is admin-only');
check($trial->fresh()->audience==='user' && $gift->fresh()->audience==='user','Migration preserves audiences of existing trial and courtesy plans');
check(planRejected(fn()=>$ops->assignPlan(planRequest(['plan_id'=>$legal->id,'status'=>'active','days'=>30,'reason'=>'Test legal isolation'],$admin),$user)),'Legal plan cannot be assigned to a regular app user');
check(planRejected(fn()=>$ops->updatePlan(planRequest([...$data,'audience'=>'user','active_listing_limit'=>10,'direct_deal_limit'=>10],$admin),$legal)),'Existing plan audience cannot silently change');
$ops->updatePlan(planRequest([...$data,'name'=>'Jurídico Atualizado','monthly_price'=>199],$admin),$legal);
check($legal->fresh()->name==='Jurídico Atualizado' && (float)$legal->fresh()->monthly_price===199.0,'Legal plan name and price remain editable');
$courtesy=$ops->createPlan(planRequest([...$data,'slug'=>'juridico-cortesia','monthly_price'=>0,'complimentary'=>true],$admin));
check(Plan::find($courtesy->getData()->id)->complimentary,'Legal courtesy is allowed with zero monthly price');
check(planRejected(fn()=>$ops->createPlan(planRequest([...$data,'slug'=>'invalid-courtesy','complimentary'=>true],$admin))),'Paid courtesy remains invalid');
check(planRejected(fn()=>$ops->createPlan(planRequest([...$data,'slug'=>'invalid-audience','audience'=>'anything'],$admin))),'Unknown audience is rejected');
$bad=Subscription::create(['user_id'=>$user->id,'plan_id'=>$legal->id,'status'=>'active','current_period_ends_at'=>now()->addDays(30),'gateway'=>'admin']);
check(!$bad->isUsable(),'Legal office plan never grants consumer subscription access');
check(DB::table('admin_audit_logs')->count()===3,'Legal plan creation and editing preserve audit evidence');
