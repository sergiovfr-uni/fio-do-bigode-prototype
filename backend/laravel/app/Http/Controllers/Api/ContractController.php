<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use Illuminate\Http\Request;
class ContractController extends Controller {
 public function generate(Request $r,Deal $deal,ContractService $service){abort_unless(in_array($r->user()->id,[$deal->seller_id,$deal->buyer_id]),403);$generated=$service->generate($deal);$deal->update(['status'=>'signature_pending']);return response()->json(['deal_id'=>$deal->public_id,'status'=>'signature_pending','document'=>['type'=>'contract_draft','path'=>$generated['path'],'sha256'=>$generated['sha256']]]);}
}