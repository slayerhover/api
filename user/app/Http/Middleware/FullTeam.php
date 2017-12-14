<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use App\Http\Controllers\PostController;

class FullTeam
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
        if(! (User::where('token',$token)->count() ) )
        {
            return ret(401,"Unauthorized.",'请登陆后重试');
        }
        $day = date('d');
        $withDraw_days =['15','16','17','18','19','20','06'];
        if(!in_array($day,$withDraw_days)){
            return ret(1,"Failed","请在每个月的15号-20号进行提现操作.");
        }

        $user = User::where('token',$token)->first();
        if(!( $user->saleuser_state == 2 || $user->saleuser_state == "已认证"))
        {
            return ret(1,"Failed","您的账号未通过实名审核.");
        }
        if(!($user->baozhengjin_status==2))
        {
            return ret(1,"Failed","您尚未缴纳保证金");
        }

        if(!( $user->team_finished==1 && $user->team_count>=5  ) )
        {
            return ret(1,"Failed","您的联盟会员未达到提现的团队构建要求的数量,或联盟会员未完成实名认证/缴费.");
        }
        $postController = new PostController($request);
        $bene = $postController->bene($user->id,$request);
        $useMoney = $bene['useMoney'];
        $amount = abs($request->json('amount',0));
        if( ( $amount < 1000 || $amount > $useMoney ))
        {
            return ret(1,"Failed","您的可结算金额未达到提现的最低限额1000元,但是不能超过可提现金额".$useMoney.".");
        }

        return $next($request);
    }
}
