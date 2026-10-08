<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Deal;
use App\Http\Controllers\Api\AuthController;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'cache.default'=>'array']);DB::purge('sqlite');
Carbon::setTestNow('2026-10-08 12:00:00');
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->string('account_status')->default('active');$t->timestamps();});
Schema::create('personal_access_tokens',function(Blueprint $t){$t->id();$t->morphs('tokenable');$t->string('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "PASS: $msg\n";}
function rejected(callable $fn,int $status):bool{try{$fn();return false;}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){return $e->getStatusCode()===$status;}}
function challengeRequest(string $id,string $code):Request{return Request::create('/','POST',['challenge_id'=>$id,'code'=>$code]);}
$controller=new AuthController;
$user=User::create(['name'=>'Isolated Test','email'=>'test@example.com','password'=>'unused']);
$id=(string)Illuminate\Support\Str::uuid();$deadline=now()->addMinutes(5)->timestamp;
Cache::put('2fa:'.$id,['user_id'=>$user->id,'code'=>'123456','attempts'=>0,'expires_at'=>$deadline],300);
Carbon::setTestNow(now()->addMinutes(4));
check(rejected(fn()=>$controller->verifyTwoFactor(challengeRequest($id,'000000')),422),'Wrong OTP is rejected');
check(Cache::get('2fa:'.$id)['expires_at']===$deadline,'Wrong attempt cannot extend OTP validity');
Carbon::setTestNow(now()->addMinutes(2));
check(rejected(fn()=>$controller->verifyTwoFactor(challengeRequest($id,'123456')),422),'Expired OTP cannot issue token');
$id=(string)Illuminate\Support\Str::uuid();$user->forceFill(['account_status'=>'blocked'])->save();
Cache::put('2fa:'.$id,['user_id'=>$user->id,'code'=>'123456','attempts'=>0,'expires_at'=>now()->addMinutes(5)->timestamp],300);
check(rejected(fn()=>$controller->verifyTwoFactor(challengeRequest($id,'123456')),403),'Account blocked after password entry cannot finish login');
check(DB::table('personal_access_tokens')->count()===0,'Blocked account receives no token');
$user->forceFill(['account_status'=>'active'])->save();$id=(string)Illuminate\Support\Str::uuid();
Cache::put('2fa:'.$id,['user_id'=>$user->id,'code'=>'123456','attempts'=>0,'expires_at'=>now()->addMinutes(5)->timestamp],300);
check($controller->verifyTwoFactor(challengeRequest($id,'123456'))->getStatusCode()===200,'Valid OTP allows active account login');
check(Carbon::parse(DB::table('personal_access_tokens')->first()->expires_at)->equalTo(now()->addDays(30)),'New user token expires in 30 days');
check(rejected(fn()=>$controller->verifyTwoFactor(challengeRequest($id,'123456')),422),'OTP cannot be reused');
$deal=(new Deal)->forceFill(['id'=>1,'seller_id'=>100,'buyer_id'=>101]);$r=Request::create('/');$r->setUserResolver(fn()=>$user);
check(rejected(fn()=>(new App\Http\Controllers\Api\DealDocumentController)->download($r,$deal,1),403),'Unrelated user cannot download deal document');
check(rejected(fn()=>(new App\Http\Controllers\Api\ContractController)->download($r,$deal),403),'Unrelated user cannot download contract');
$env=$app['env'];$app['env']='production';
check(rejected(fn()=>(new App\Http\Controllers\Api\ComplianceController)->submitKyc($r),404),'Simulated KYC is unavailable in production');$app['env']=$env;
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
for($i=0;$i<6;$i++){$r=Request::create('/api/v1/auth/login','POST',['email'=>($i%2?'ATTACK@example.com':'attack@example.com'),'password'=>'not-a-password'],[],[],['REMOTE_ADDR'=>'192.0.2.'.($i+1),'HTTP_ACCEPT'=>'application/json']);$res=$kernel->handle($r);$kernel->terminate($r,$res);}
check($res->getStatusCode()===429,'Per-account throttle survives case changes and rotating IPs');
$r=Request::create('/api/v1/deals/1/documents/1/download','GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
check($kernel->handle($r)->getStatusCode()===401,'Anonymous document request requires authentication');
Carbon::setTestNow();
