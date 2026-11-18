<?php
namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
class EnsureAppMember {public function handle(Request $r,Closure $next){abort_unless($r->user() instanceof User,403,'Acesso exclusivo de usuários do aplicativo.');return $next($r);}}
