<?php
namespace App\Services;
use App\Models\Deal;
use Carbon\Carbon;
class InstallmentService {
 public function generate(Deal $deal, ?Carbon $firstDueDate=null): void {
  if ($deal->installments()->exists()) return;
  $firstDueDate ??= now()->addMonthNoOverflow()->startOfDay();
  $principal=max(0,(float)$deal->total_amount-(float)$deal->down_payment);
  $count=max(1,(int)$deal->installments);
  $base=round($principal/$count,2);
  for($i=1;$i<=$count;$i++){
   $amount=$i===$count?round($principal-$base*($count-1),2):$base;
   $deal->installments()->create(['number'=>$i,'due_date'=>$firstDueDate->copy()->addMonthsNoOverflow($i-1),'amount'=>$amount,'status'=>'pending']);
  }
 }
}
