<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class ContractController extends Controller {
 public function generate(Request $r,Deal $deal,ContractService $service){abort_unless(in_array($r->user()->id,[$deal->seller_id,$deal->buyer_id]),403);$generated=$service->generate($deal,$r->user()->id);$deal->update(['status'=>'signature_pending']);return response()->json(['deal_id'=>$deal->public_id,'status'=>'signature_pending','document'=>['type'=>'unsigned_contract','sha256'=>$generated['sha256']]]);}
 public function download(Request $r,Deal $deal){abort_unless(in_array($r->user()->id,[$deal->seller_id,$deal->buyer_id]),403);$document=DB::table('deal_documents')->where('deal_id',$deal->id)->where('type','unsigned_contract')->latest()->first();abort_unless($document&&Storage::disk('local')->exists($document->storage_path),404,'Dossiê ainda não disponível.');return Storage::disk('local')->download($document->storage_path,$document->original_name,['Content-Type'=>'application/pdf']);}
}
