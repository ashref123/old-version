<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

class CheckWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $invalidatedAt = Cache::get('web_sessions_invalidated_at');

        if ($invalidatedAt && Auth::check()) {
            

                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Delete browser session cookie
                $cookie = Cookie::forget(config('session.cookie'));

                // Redirect to admin login
                $adminLogin = Setting::where('name', 'admin_login')
                    ->value('value');

               
                  // Forget browser session cookie
                $cookie = Cookie::forget(config('session.cookie'));


                return redirect('login/' . $adminLogin)
                    ->withCookie($cookie);

            }

        return $next($request);
    }
}