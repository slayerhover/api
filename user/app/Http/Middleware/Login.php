<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class Login
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
/*    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }*/

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        //if ($this->auth->guard($guard)->guest()) {
        $token = $request->json('token');
    //    dumpa($token);
        if (!User::where('token',$token)->count()) {
            return ret(401,'Unauthorized.','未授权用户');
        //    return response('Unauthorized.', 401);
        }

        return $next($request);
    }
}
