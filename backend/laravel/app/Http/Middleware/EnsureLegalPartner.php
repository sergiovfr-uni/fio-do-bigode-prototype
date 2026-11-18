<?php
namespace App\Http\Middleware;
use App\Models\LegalPartner;
use Closure;
use Illuminate\Http\Request;
class EnsureLegalPartner {public function handle(Request $r,Closure $next){$p=$r->user();abort_unless($p instanceof LegalPartner && $p->tokenCan('legal:portal') && $p->hasAccess(),403,'Acesso jurídico não liberado, suspenso ou fora da vigência.');$response=$next($r);$response->headers->set('Cache-Control','private, no-store');return $response;}}
