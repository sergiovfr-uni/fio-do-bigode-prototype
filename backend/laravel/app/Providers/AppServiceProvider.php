<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {
        RateLimiter::for('public-login', fn(Request $r) => [
            Limit::perMinute(15)->by('login-ip:'.$r->ip()),
            Limit::perMinute(5)->by('login-email:'.hash('sha256',mb_strtolower(trim((string)$r->input('email'))))),
        ]);
        RateLimiter::for('public-otp', fn(Request $r) => [
            Limit::perMinute(30)->by('otp-ip:'.$r->ip()),
            Limit::perMinute(6)->by('otp-challenge:'.hash('sha256',(string)$r->input('challenge_id'))),
        ]);
        RateLimiter::for('public-register', fn(Request $r) => Limit::perHour(10)->by('register:'.$r->ip()));
        RateLimiter::for('public-recovery', fn(Request $r) => [
            Limit::perHour(15)->by('recovery-ip:'.$r->ip()),
            Limit::perHour(5)->by('recovery-email:'.hash('sha256',mb_strtolower(trim((string)$r->input('email'))))),
        ]);
    }
}
