<?php
// Isolated integration checks: no production database or credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\AdminOperationsController;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
DB::purge('sqlite');
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->boolean('is_admin')->default(false);$t->timestamps();});
(require __DIR__.'/../database/migrations/2026_08_19_000004_create_advertising_tables.php')->up();
(require __DIR__.'/../database/migrations/2026_08_27_000031_create_admin_audit_logs_and_expand_campaign_media.php')->up();
(require __DIR__.'/../database/migrations/2026_08_27_000033_create_community_partners.php')->up();
(require __DIR__.'/../database/migrations/2026_10_08_000036_create_partner_applications.php')->up();
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "PASS: $msg\n";}
function rejected(callable $fn,int $status=422):bool{try{$fn();return false;}catch(Illuminate\Validation\ValidationException $e){return $status===422;}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){return $e->getStatusCode()===$status;}}
function request(array $data,?User $user=null):Request{$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user);return $r;}
$public=new PartnerApplicationController;
$data=['name'=>'Test Creator','email'=>'Creator@example.com','phone'=>'+5511999999999','platform'=>'instagram','profile_url'=>'https://example.com/creator','consent'=>true];
check($public->store(request($data))->getStatusCode()===201,'Public interest is accepted');
check(DB::table('partner_applications')->count()===1 && DB::table('partner_applications')->first()->status==='new','Interest starts new and stores consent');
$duplicate=$data;$duplicate['email']='creator@example.com';$duplicate['name']='Overwrite';
check($public->store(request($duplicate))->getStatusCode()===201 && DB::table('partner_applications')->count()===1 && DB::table('partner_applications')->first()->name==='Test Creator','Duplicate email neither leaks nor overwrites');
check(rejected(fn()=>$public->store(request([...$data,'consent'=>false]))),'Consent is required');
check(rejected(fn()=>$public->store(request([...$data,'profile_url'=>'javascript:alert(1)']))),'Unsafe profile URL is rejected');
check(DB::table('users')->count()===0 && DB::table('community_partners')->count()===0,'Application does not create an account or publish a partner');
$middleware=new App\Http\Middleware\EnsureUserIsAdmin;
check(rejected(fn()=>$middleware->handle(request([]),fn()=>response('ok')),403),'Admin middleware blocks anonymous access');
$member=User::create(['name'=>'Member']);
check(rejected(fn()=>$middleware->handle(request([],$member),fn()=>response('ok')),403),'Admin middleware blocks ordinary members');
$admin=User::create(['name'=>'Admin']);$admin->forceFill(['is_admin'=>true])->save();
$operations=new AdminOperationsController;
$reason=['confirmation'=>'EXCLUIR','reason'=>'Remove isolated test record'];
$advertiser=DB::table('advertisers')->insertGetId(['name'=>'Test advertiser']);
$campaign=DB::table('campaigns')->insertGetId(['advertiser_id'=>$advertiser,'name'=>'Test campaign','headline'=>'Test','starts_at'=>now(),'ends_at'=>now()->addDay()]);
DB::table('ad_events')->insert(['campaign_id'=>$campaign,'type'=>'impression','occurred_at'=>now()]);
check(rejected(fn()=>$operations->deleteCampaign(request([...$reason,'confirmation'=>'NO'],$admin),$campaign)),'Deletion requires exact confirmation');
check(rejected(fn()=>$operations->deleteAdvertiser(request($reason,$admin),$advertiser)) && DB::table('advertisers')->count()===1,'Linked advertiser cannot be deleted');
$operations->deleteCampaign(request($reason,$admin),$campaign);
check(DB::table('campaigns')->count()===0 && DB::table('ad_events')->count()===0 && DB::table('advertisers')->count()===1,'Campaign deletion clears only its events and preserves advertiser');
$operations->deleteAdvertiser(request($reason,$admin),$advertiser);
check(DB::table('advertisers')->count()===0,'Unlinked advertiser can be deleted');
$p1=DB::table('community_partners')->insertGetId(['name'=>'Delete me','platform'=>'instagram','profile_url'=>'https://example.com/1']);
$p2=DB::table('community_partners')->insertGetId(['name'=>'Keep me','platform'=>'instagram','profile_url'=>'https://example.com/2']);
$operations->deleteCommunityPartner(request($reason,$admin),$p1);
check(DB::table('community_partners')->count()===1 && DB::table('community_partners')->where('id',$p2)->exists(),'Partner deletion preserves other partners');
$operations->updatePartnerApplication(request(['status'=>'approved','reason'=>'Reviewed isolated application'],$admin),1);
check(DB::table('partner_applications')->first()->status==='approved' && DB::table('community_partners')->count()===1,'Application status does not automatically publish a partner');
check(DB::table('admin_audit_logs')->count()===4,'All successful admin changes retain audit evidence');
