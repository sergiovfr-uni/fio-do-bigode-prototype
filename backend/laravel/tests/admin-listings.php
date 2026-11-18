<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Listing;
use App\Http\Controllers\Api\AdminOperationsController;
use App\Http\Controllers\Api\ListingCategoryController;
if (getenv('ADMIN_LISTINGS_MYSQL_PORT') !== false) {
    $port=getenv('ADMIN_LISTINGS_MYSQL_PORT');
    if(getenv('GITHUB_ACTIONS')!=='true'||!ctype_digit($port))throw new RuntimeException('Disposable CI MySQL only');
    config(['database.default'=>'admin_listing_ci','database.connections.admin_listing_ci'=>['driver'=>'mysql','host'=>'127.0.0.1','port'=>(int)$port,'database'=>'fdb_admin_listing_ci','username'=>'fdb_ci','password'=>'fdb_ci_only','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
    if(Schema::hasTable('users'))throw new RuntimeException('Expected empty disposable database');
} else {
    config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);DB::purge('sqlite');
}
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->nullable();$t->string('kyc_status')->nullable();$t->boolean('is_admin')->default(false);$t->timestamps();});
Schema::create('listings',function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->foreignId('seller_id')->constrained('users');$t->string('category',60);$t->string('title');$t->text('description');$t->text('cover_image')->nullable();$t->decimal('price',14,2);$t->boolean('accepts_trade')->default(false);$t->string('status');$t->timestamp('published_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
Schema::create('admin_audit_logs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('admin_user_id')->nullable();$t->string('action');$t->string('entity_type');$t->string('entity_id');$t->json('before_data')->nullable();$t->json('after_data')->nullable();$t->string('reason')->nullable();$t->string('ip_hash')->nullable();$t->string('user_agent_hash')->nullable();$t->timestamps();});
(require __DIR__.'/../database/migrations/2026_11_18_000038_admin_listings_and_categories.php')->up();
(require __DIR__.'/../database/migrations/2026_11_18_000039_preserve_legacy_listing_categories.php')->up();
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function req($data,$user){$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>$user);return $r;}
function rejected($fn){try{$fn();return false;}catch(Illuminate\Validation\ValidationException $e){return true;}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){return $e->getStatusCode()===422;}}
$admin=User::create(['name'=>'Admin']);$admin->forceFill(['is_admin'=>true])->save();
$member=User::create(['name'=>'Member']);
$ops=new AdminOperationsController;$categories=new ListingCategoryController;
$data=['category'=>'Outros','title'=>'Admin announcement','description'=>'Test listing','cover_image'=>'data:image/png;base64,aA==','price'=>100,'status'=>'published'];
check(DB::table('listing_categories')->whereIn('name',['Imóvel','Serviço'])->where('active',true)->count()===2,'Legacy app categories remain available');
try {(new App\Http\Controllers\Api\ListingController)->store(req([...$data,'seller_id'=>null],$member),new App\Services\ListingEntitlementService);throw new RuntimeException('Unverified user published');}
catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Normal user cannot bypass KYC using an empty seller');}
$response=$ops->createListing(req($data,$admin));$listing=Listing::first();
check($response->getStatusCode()===201 && $listing->seller_id===null && $listing->status==='published','Admin can publish without a seller');
check(rejected(fn()=>(new App\Http\Controllers\Api\DealController)->fromListing(req([],$member),$listing,new App\Services\DealEventService)),'Administrative announcement cannot create a deal');
check(User::count()===2,'Publishing does not create a fake user');
check(rejected(fn()=>$ops->createListing(req([...$data,'seller_id'=>$member->id],$admin))),'User listings still require verified KYC');
$ops->changeListingStatus(req(['status'=>'archived','reason'=>'Test archive'],$admin),$listing);
$ops->changeListingStatus(req(['status'=>'published','reason'=>'Test republish'],$admin),$listing->fresh());
check($listing->fresh()->status==='published','Admin announcement can be republished without user KYC');
$new=$categories->store(req(['name'=>'Máquinas'],$admin))->getData();
check(DB::table('listing_categories')->where('name','Máquinas')->exists(),'Admin creates category');
check(rejected(fn()=>$categories->store(req(['name'=>'Máquinas'],$admin))),'Duplicate categories rejected');
$categories->update(req(['name'=>'Equipamentos','active'=>true],$admin),$new->id);
$ops->updateListing(req([...$data,'category'=>'Equipamentos'],$admin),$listing->fresh());
$categories->update(req(['name'=>'Ferramentas','active'=>false],$admin),$new->id);
check($listing->fresh()->category==='Ferramentas','Rename preserves and updates existing announcements');
check(rejected(fn()=>$ops->createListing(req([...$data,'category'=>'Ferramentas'],$admin))),'Disabled categories reject new announcements');
check(!$categories->index()->contains('name','Ferramentas'),'Disabled category is absent from public options');
$middleware=new App\Http\Middleware\EnsureUserIsAdmin;
try{$middleware->handle(req([],$member),fn()=>response('ok'));throw new RuntimeException('Member passed admin guard');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Ordinary member cannot access admin mutations');}
check(DB::table('admin_audit_logs')->where('entity_type','listing_category')->count()===3,'Category writes are audited');
