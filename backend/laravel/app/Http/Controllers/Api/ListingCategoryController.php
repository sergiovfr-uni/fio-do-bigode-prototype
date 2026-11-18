<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\AdminAuditLog;
class ListingCategoryController extends Controller {
    public function index() { return DB::table('listing_categories')->where('active',true)->orderBy('name')->get(); }
    public function adminIndex() { return DB::table('listing_categories')->orderBy('name')->get(); }
    public function store(Request $request) {
        $data=$request->validate(['name'=>['required','string','max:60','unique:listing_categories,name']]);
        $id=DB::transaction(function() use($request,$data) {
            $id=DB::table('listing_categories')->insertGetId([...$data,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
            $this->audit($request,$id,null,$data); return $id;
        });
        return response()->json(DB::table('listing_categories')->find($id),201);
    }
    public function update(Request $request, int $category) {
        $data=$request->validate(['name'=>['required','string','max:60',Rule::unique('listing_categories','name')->ignore($category)],'active'=>['required','boolean']]);
        DB::transaction(function() use($request,$category,$data) {
            $before=DB::table('listing_categories')->where('id',$category)->lockForUpdate()->first(); abort_unless($before,404);
            DB::table('listing_categories')->where('id',$category)->update([...$data,'updated_at'=>now()]);
            DB::table('listings')->where('category',$before->name)->update(['category'=>$data['name']]);
            $this->audit($request,$category,(array)$before,$data);
        });
        return DB::table('listing_categories')->find($category);
    }
    private function audit(Request $request,int $id,?array $before,array $after): void {
        AdminAuditLog::create(['admin_user_id'=>$request->user()->id,'action'=>'listing_category.saved','entity_type'=>'listing_category','entity_id'=>$id,'before_data'=>$before,'after_data'=>$after,'ip_hash'=>hash('sha256',(string)$request->ip()),'user_agent_hash'=>hash('sha256',(string)$request->userAgent())]);
    }
}
