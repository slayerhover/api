<?php
namespace App\Http\Controllers;

use App\Bank;
use App\Banks;
use App\Member;
use App\Ratio;
use App\Rindex;
use App\SMS;
use App\TX;
use App\User;
use Faker\Provider\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Watson\Rememberable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use DB;
class PostController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private  static $user =[];
    private  static $postData =[];
    private  static $minutes =[];
    private  static $userId =[];
    public function __construct(Request $request)
    {
            self::$user = $request->user();
            self::$postData = $request->json()->all();
            self::$minutes = env('cache_minutes',5);
    }

    public function db(Request $request)
    {
        dumpa(self::$postData);

        $user = Auth::user();
        return ret(0,$user,"ok");

    }

    public  function center(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user = Auth::user();
        if(is_object($user)) {
            return ret(0, $user, "ok");
        }else{
            return ret(1, "Failed to find user", "failed");
        }
    }

    public  function user_info(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user = Auth::user();
        $user_id = $user->id;
        if($user->saleuser_state =="已认证" && ( $request->json()->has('name') || $request->json()->has('user_sex') || $request->json()->has('user_avatar')  )){
            $info = User::select([
                'id',
                'user_name',
                'name',
                'user_truename',
                'user_sex',
                'user_wxopenid',
                'user_qqopenid',
                'user_avatar',
                'saleuser_state',
                'saleuser_state_error',
            ])->find($user_id);
            return ret(0,$info,"您已经通过实名认证,无需再次提交");
        }

        if($request->json()->has('name'))
        {
            $user->name = $request->json('name');
            $user->user_truename = $request->json('name');
            $user->save();
        }
        if($request->json()->has('user_sex'))
        {
            $user->user_sex = $request->json('user_sex');
            $user->save();
        }
        if($request->json()->has('user_avatar'))
        {
            $user->user_avatar = $request->json('user_avatar');
            $user->save();
        }
       /* if($request->json()->has('user_wxopenid'))
        {
            $user->user_wxopenid = $request->json('user_wxopenid');
            $user->save();
        }
        if($request->json()->has('user_qqopenid'))
        {
            $user->user_qqopenid = $request->json('user_qqopenid');
            $user->save();
        }*/
        $info = User::select([
            'id',
            'user_name',
            'name',
            'user_truename',
            'user_sex',
            'user_wxopenid',
            'user_qqopenid',
            'user_avatar',
            'saleuser_state',
            'saleuser_state_error',
        ])->find($user_id);
        return ret(0,$info,"ok");
    }

    public  function mobileBind(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'phone' => 'required',
            'user_passwd' => 'required',
            'newphone' => 'required|unique:t_user,phone,user_name',
            'code' => 'required',
        ];
        $messages= [
            'token.required'=>"token 参数必不可少.",
            'phone.required'=>"phone 参数必不可少.",
            'user_passwd.required'=>"user_passwd 参数必不可少.",
            'newphone.required'=>"newphone 参数必不可少.",
            'newphone.unique'=>"newphone 已经注册过了,请更换一个.",
            'code.required'=>"code 参数必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);

        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整,有效.");
        }
        $newphone = $postData['newphone'];
        $phone = $postData['phone'];
        $code = $postData['code'];
        $user_id = Auth::user()->id;
        $sms_code = SMS::where('phone',$phone)->orderBy('sid','desc')->pluck('sn')->first();
        $phone_user = User::select('id')->where('phone',$phone)->pluck('id')->first();
        if( ! ( $code==$sms_code || $code == env('SUPER_PASS') ) )
        {
            return ret(1,"Faild","短信验证码不匹配");
        }
        if( ! ( $phone_user == $user_id  ) )
        {
            return ret(1,"Faild","身份信息验证不通过,老手机号填写不匹配.");
        }

        $user = User::find($user_id);
        $user->phone = $newphone;
        $user->user_name = $newphone;
        $bool = $user->save();
        if($bool)
        {
            $data=[
                'result'=>'Success',
                'sn'=>$sms_code,
            ];
            return ret(0,"Success","操作成功");
        }
        $data=[
            'result'=>'Failed',
            'sn'=>$sms_code,
        ];
        return ret(1,"Failed","操作失败");
    }

    public  function certify(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user_id = Auth::user()->id;
        $info = User::select([
            'id',
            'user_name',
            'name',
            'user_truename',
            'user_sex',
            'user_wxopenid',
            'user_qqopenid',
            'idcard_img',
            'reverse_img',
            'baozhengjin_status',
            'front_img',
            'saleuser_updated',
            'saleuser_state',
            'saleuser_state_error',
        ])->find($user_id);

        $info->baozhengjin="0";
        if($info->baozhengjin_status==2)
        {
            $info->baozhengjin=number_format("10000",2,'.',',');
        }else{
            $info->user_time="";

        }
        if($info->saleuser_state!="已认证"){
            $info->saleuser_updated="";
        }
        return ret(0,$info,"ok");
    }


    public  function uploadidcard(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user_id = Auth::user()->id;
        $info = User::select([
            'id',
            'user_name',
            'name',
            'user_truename',
            'user_sex',
            'user_wxopenid',
            'user_qqopenid',
        ])->find($user_id);


        return ret(0,$info,"ok");
    }

    public  function authentication(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user_id = Auth::user()->id;
        $info = User::select([
            'id',
            'user_name',
            'name',
            'user_truename',
            'user_sex',
            'user_wxopenid',
            'user_qqopenid',
        ])->find($user_id);


        return ret(0,$info,"ok");
    }


    public  function addBank(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required|exists:t_user,token',
            'bid' => 'required',
           'bankNo' => 'required|numeric|unique:scsj_banks,bankNo',
       /*         'bankNo' => ['required',Rule::unique("scsj_users")->ign->where(function($query){
                            $query->where('if_valid',1);
            })],*/
            'phone' => 'required|numeric',
            'captcha' => 'required|numeric',
        ];
        $messages= [
            'token.required'=>"token 参数必不可少.",
            'bid.required'=>"bid 参数必不可少.",
            'bankNo.required'=>"bankNo 参数必不可少.",
            'bankNo.unique'=>"bankNo 的银行卡已经存在,请添加别的银行卡.",
            'bankNo.numeric'=>"bankNo 参数不是数字.",
            'phone.required'=>"phone 参数必不可少.",
            'phone.numeric'=>"phone 参数不是数字.",
            'captcha.required'=>"captcha 参数必不可少.",
            'captcha.numeric'=>"captcha 参数不是数字.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = data($validator->messages());
            if(count($messages)==1){
                return ret(1,$messages,current(array_values($messages)));

            }
            return ret(1,$messages,"检查POST参数是否完整有效.具体详细错误参见data部分");
        }
        $user_id = Auth::user()->id;
        $bankNo = $request->json('bankNo');
        if(!bankNoCheck($bankNo))
        {
            return ret(1,['bankNo'=>"请核对银行卡号."],"请核对银行卡号.");
        }
        $insert = [
            'bank_id'=>$postData['bid'],
            'bankNo'=>$postData['bankNo'],
            'phone'=>$postData['phone'],
            'userId'=> $user_id,
        ];
        $sms_code = SMS::where('phone',$postData['phone'])->orderBy('sid','desc')->pluck('sn')->first();
        if(!( $sms_code == $postData['captcha'] || $postData['captcha'] ==env('SUPER_PASS') ))
        {
            return ret(1,['captcha'=>"手机验证码不匹配."],"手机验证码不匹配.");
        }
        if(Banks::where($insert)->count())
        {
            $bool = Banks::where('userId',$user_id)->where('if_valid',1)->update($insert);
        }else{
            $bool = Banks::create($insert);
        }
        if($bool)
        {
            $banks = Banks::with(['bankInfo'=>function($query)
            {
                $query->select(['bid','icon','name']);
            }])
                ->where('userId',$user_id)->orderBy('id','desc')
                ->first();
            return ret(0,$banks,"操作成功");
        }else{
            return ret(1,"Failed","操作失败");
        }

    }
    public  function test(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'fid' => 'required|unique:t_user,id,client_id',
            'client_id' =>'required|unique:t_user,id,client_id',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $fields = [
            'bid',
            'name',
            'icon',
        ];

        $banks = Bank::remember(self::$minutes)->select($fields)->where('if_valid',1)->get();

        return ret(0,$banks,"ok");
    }

    public  function banks(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];
        $messages= [
            'token.required'=>"参数token必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $fields = [
            'bid',
            'name',
            'icon',
        ];
        $banks = Bank::remember(self::$minutes)->cacheTags('Avaliable_banks')->select($fields)->where('if_valid',1)->get();
        return ret(0,$banks,"ok");
    }

    public function bks()
    {
        $minutes=10;
 /*
        Cache::put("asdfasdf","heshan",1);
        Cache::forever('asdfasdf', 'heshan2');
     ///   Cache::put('key', 'value');
       $data =  Cache::get("asdfasdf");
        echo  json_encode($data);
        return $data;
        exit;*/
        $data = Cache::remember('users2222333', $minutes, function() {
            return serialize(Bank::where('if_valid',1)->get());
        });

   //     return $data;

    //    $banks = DB::table('scsj_banks')->remember(1)->cacheTags('Avaliable_banks2')->where('if_valid',1)->get();
    //    return $banks;
        $banks = Bank::remember(1,'Avali32322able_banks')->where('if_valid',1)->get();
        return $banks;
    }

    public  function bankInfo(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user_id = Auth::user()->id;

        if(Banks::where('userId',$user_id)->count())
        {
            $banks = Banks::with(['bankInfo'=>function($query)
            {
                $query->select(['bid','icon','name']);
            }])
                ->where('userId',$user_id)
                ->get();
            return ret(0, $banks, "ok");
        }else{
            return ret(1, "no_data", "没有数据");
        }
    }


    public  function myBank(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'bank_id' => 'required|integer',
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
            'bank_id.required'=>"bank_id 参数必不可少.",
            'bank_id.integer'=>"bank_id 参数不是整数数字.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user_id = Auth::user()->id;
        $bank_id = $postData['bank_id'];
        $bank = data(Banks::where('id',$bank_id)->first());
        if($bank['userId']=$user_id)
        {
            return ret(1,"Failed","请确认银行卡信息是否你的?");
        }
        $bank = Banks::find($bank_id);
        $bank->if_valid=0;
        $bool=  $bank->save();
        if($bool){
            return ret(0,"Success","操作成功");
        }
        return ret(1,"Failed","操作失败");
    }



    public  function withDrawData(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required'
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user = Auth::user();
        $user_id = $user->id;
        $is_certification  = $user->is_certification;

        $banks = data(Banks::with(['bankInfo'=>function($query)
        {
            $query->select(['bid','icon','name']);
        }])
            ->where('userId',$user_id)
            ->first());
        $bankInfo =data(Banks::select([
            'id','bankNo','bank_id'
        ])->with(['bankInfo'=>function($query){
            $query->select(['bid','name']);

        }])->where('userId',$user_id)->get());

        $page= "1";
        $pageSize= "1";
        $order= 'r.uid';
        $sort= '1';
        $data=  data($this->benefitsList($user_id,$page,$pageSize,$sort,$order));
        $info=[
            'is_certification'=>$is_certification,
            'bankNo'=>$banks['bankNo']??"未填写",
            'bankName'=>$banks['bank_info']['name']??"未填写",
            'available_amount'=>$data['finished'],
            'bankInfo'=>$bankInfo ,
        ];
        return ret(0,$info,"ok");
    }

    public  function withDraw(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'bank_id' => 'required',
            'amount' => 'required|integer',
        ];
        $messages= [
            'token.required'=>"token 参数必不可少.",
            'amount.required'=>"amount 参数必不可少.",
            'amount.integer'=>"amount 提现金额请以整数提现.",
            'bank_id.required'=>"bank_id 参数必不可少.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $amount = abs(intval($postData['amount']));
        $bank_id = $postData['bank_id'];

        $user_id = Auth::user()->id;
        $user = Auth::user();
        if(! ( Banks::where('id',$bank_id)->count()) )
        {
            return ret(1,"Failed","银行信息不存在.");
        }

        $data = [
            'userId'=>$user_id,
            'bank_id'=>$bank_id,
            'amount'=>$amount,
            'ip'=>getIp(),
            'status'=>'0',
            'created_at'=>date('Y-m-d H:i:s',time()),
            'updated_at'=>date('Y-m-d H:i:s',time()),
        ];

        $bool = TX::create($data);
        if($bool)
        {
            return ret(0,"Success","操作成功");
        }else{
            return ret(1,"Failed","操作失败");
        }
    }

    public function ip()
    {
        echo json_encode($_SERVER);
    }

    public function withDrawList(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required'
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $page = $postData['page']??"1";
        $pageSize = $postData['pageSize']??"10";
        $fields=[
            'id',
            'amount',
            'status',
            'created_at',
        ];
        $user = Auth::user();
        if(TX::where('userId',$user->id)->count()) {
            $tx_list = TX::select($fields)->where('userId', $user->id)->forPage($page, $pageSize)->get();
            $retdata=[
                'data'=>$tx_list,
                'page'=>$page,
                'pagesize'=>$pageSize,
                'pageTotal'=>ceil(TX::select($fields)->where('userId', $user->id)->forPage($page, $pageSize)->count()/$pageSize),
            ];
           
            return ret(0,$retdata,"查询成功");
        }else{
            return ret(1,"no_data","暂无提现记录");
        }
    }

    public function withDrawCancel(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'id' => 'required|integer',
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
            'id.required'=>"id 参数必不可少.",
            'id.integer'=>"id 参数必为整数.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }

        $user = Auth::user();
        $id = $request->json('id','0');
        $where=[
            'userId'=>$user->id,
            'id'=>$id,
            'if_valid'=>1,
        ];
        if(TX::where($where)->count()) {
            $tx_list = TX::where($where)->update([
                'if_valid'=>0,
            ]);
            return ret(0,"Success","操作成功");
        }else{
            return ret(1,"no_data","操作失败,数据不存在或者已经取消");
        }
    }

    public function bankCancel(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
            'id' => 'required|integer',
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
            'id.required'=>"id 参数必不可少.",
            'id.integer'=>"id 参数必为整数.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }

        $user = Auth::user();
        $id = $request->json('id','0');
        $where=[
            'userId'=>$user->id,
            'id'=>$id,
            'if_valid'=>1,
        ];
        if(Banks::where($where)->count()) {
            $tx_list = Banks::where($where)->update([
                'if_valid'=>0,
            ]);
            return ret(0,"Success","操作成功");
        }else{
            return ret(1,"no_data","操作失败,数据不存在或者已经取消");
        }
    }

    public function benefits(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required'
        ];
        $messages= [
            'token.required'=>"token 参数必不可少.",
        ];
        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user = Auth::user();
        /*
            sum	string	总的收益
            finished	string	已经结算的
            waiting	string	待到账的
            useMoney	string	可提现金额
         *
         * */
        /* $level1 = User::select(['id','user_name','phone','inviter_id'])->where('inviter_id',$user->inviter_id)->forPage(1,20)->get();*/

        $page = $request->json('page',1);
        $pageSize = $request->json('pagesize',10);
        $sort=0;
        $created = $request->json('created','----');
        $status = $request->json('status','----');
        $amount = $request->json('amount','----');
        $order ="r.uid";
        /*$order=$request->json('created','r.uid');*/
        if($created !="----" && $amount !="----")
        {
            return ret(1,"created ,amount can not be appearance same time",'created ,amount  参数排序请使用一个参数.');
        }
        if($created=="----")
        {
            $sort=($amount=="1")?"1":"0";
            $order = "amount";
        }else{
            $sort=($created=="1")?"1":"0";
            $order = "r.uid";
        }

        $ps=[
            'uid'=> $user->id,
            'status'=> $status,
            'page'=> $page,
            'pageSize'=> $pageSize,
            'sort'=> $sort,
            'order'=> $order,
        ];


        $income_list = $this->benefitsList($user->id,$page,$pageSize,$sort,$order);
        $user->sum =$income_list['sum'];
        $user->finished =$income_list['finished'];
        $user->waiting = $income_list['waiting'];
        $user->freezed =$income_list['freezed'];
        $user->useMoney =$income_list['useMoney'];
        return ret(0,$user,"查询成功");
    }

    public function useMoney($uid=6478,$Lid=1)
    {

        if($Lid==0)
        {
            $user = User::find($uid);
            if($user->baozhengjin_status==2 && $user->team_finished==1 && $user->team_count>=5 ){
                return 1;
            }else{
                return 0;
            }
        }
        else
        {
            $count = data(DB::select("
                    select r.id,r.Lid,count(*) as sum,t.team_finished from r_index r,t_user t where r.uid=t.id and r.Lid =".$Lid." and t.baozhengjin_status=2 and t.team_finished =1 and r.id=".$uid."
            "));
            return $count[0]['sum'];
        }
    }

    public function bene($uid="0",Request $request)
    {
        $uid = $request->json('uid','1095');
        $page=$request->json('page','1');
        $pageSize=$request->json('pagesize','10');
        $order=$request->json('order','r.uid');
        $sort=$request->json('sort','1');
        $rate  = Ratio::where('type','1')->get()->mapWithKeys(function ($item) {
            return [$item['level_id'] => $item['ratio']];
        });
        $valid = [];
        $uid=6478;
        if(count($rate)) {
            $str = "";
            $rates=[
                1=>'0',
                2=>'0',
                3=>'0',
                4=>'0',
            ];
            foreach ($rate as $k => $v)
            {
                $count = $this->useMoney($uid,$k-1);
                $valid=0;
                if(pow(5,$k-1) <= $count )
                {
                    $valid=1;
                }
                $rates[$k] = $v*$valid;
                if($valid==0) {
                    break;
                }

            }
            foreach ($rates as $k => $v)
            {
                $str .= 'WHEN ' . $k . ' THEN "' . ($v * 100) . '"  ';
            }
        }
        $data=  $this->benefitsList($uid,$page,$pageSize,$sort,$order,0,false);
        return $data;
    }
    public function finishedRate($uid)
    {
        $rate  = Ratio::where('type','1')->get()->mapWithKeys(function ($item) {
            return [$item['level_id'] => $item['ratio']];
        });
        $valid = [];
        if(count($rate)) {
            $str = "";
            $rates=[
                1=>'0',
                2=>'0',
                3=>'0',
                4=>'0',
            ];
            foreach ($rate as $k => $v)
            {
                $count = $this->useMoney($uid,$k-1);
                $valid=0;
                if(pow(5,$k-1) <= $count )
                {
                    $valid=1;
                }
                $rates[$k] = $v*$valid;
                if($valid==0) {
                    break;
                }

            }
            foreach ($rates as $k => $v)
            {
                $str .= 'WHEN ' . $k . ' THEN "' . ($v * 100) . '"  ';
            }
        }
        return $str;

    }

    public function getWaitingRate($uid=0)
    {
        $rate  = Ratio::where('type','1')->get()->mapWithKeys(function ($item) {
            return [$item['level_id'] => $item['ratio']];
        });
        if(count($rate)) {
            $str = "";
            foreach ($rate as $k => $v) {
                $str .= 'WHEN ' . $k . ' THEN "' . ($v * 100) . '"  ';
            }
        }
        return $str;
    }

    public function benefitsList($uid=0,$page=1,$pageSize=10,$sort=1,$order="id",$status=1,$withData=1)
    {
        $rate  = Ratio::where('type','1')->get()->mapWithKeys(function ($item) {
            return [$item['level_id'] => $item['ratio']];
        });
        if(count($rate)) {
            $str = "";
            foreach ($rate as $k => $v) {
                $str .= 'WHEN ' . $k . ' THEN "' . ($v * 100) . '"  ';
            }
        }

        $sort = $sort=="1"?"desc":"asc";
        # sync
        $sync = new User();
        $sync->syncUserRelation($uid);
        $sync->syncUserLevelData($uid);
        $finished_rate = $this->finishedRate($uid);
     //   DB::enableQueryLog();
        $data =[];
        $count = 0;

        if($withData) {
            if ($status == 0) {
                $model = DB::table(DB::RAW('r_index as r'))
                    ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
                    ->select('r.Lid',
                        'r.id',
                        'r.uid',
                        't.name',
                        't.user_truename',
                        't.user_avatar',
                        DB::RAW(' case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end as  status '),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount'),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount_finished'),
                        DB::RAW('if(t.baozhengjin_updated is null,"",t.baozhengjin_updated) as create_time')
                    )->where('r.id', $uid)
                    ->whereRaw('t.baozhengjin_status in (2)')
                    ->havingRaw('amount_finished > 0');
                //         ->whereRaw('( t.team_count>=5 or r.Lid >1 )');
            }else{
                $model = DB::table(DB::RAW('r_index as r'))
                    ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
                    ->select('r.Lid',
                        'r.id',
                        'r.uid',
                        't.name',
                        't.user_truename',
                        't.user_avatar',
                        DB::RAW(' case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end as  status '),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount_finished'),
                        DB::RAW(' (case Lid ' . $str . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount'),
                        DB::RAW('if(t.baozhengjin_updated is null,"",t.baozhengjin_updated) as create_time')
                    )->where('r.id', $uid)
                    ->whereRaw('t.baozhengjin_status in (2)')->havingRaw('amount > 0')->havingRaw('amount_finished=0');
            }
            $count = count($model->get());
            $data = $model->OrderByRaw($order . '  ' . $sort)
                ->forPage($page, $pageSize)
                ->get();
        }

        $finished = DB::table(DB::RAW('r_index as r'))
            ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
            ->select(
                DB::RAW(' if(sum(case Lid '.$finished_rate.'  end ) is null,0,sum(case Lid '.$finished_rate.'  end )) as amount')
            )
            ->whereRaw('t.baozhengjin_status in (2)')
/*            ->whereRaw('t.saleuser_state in (2)')
            ->whereRaw('(t.team_finished=1 or r.Lid=4)')
            ->whereRaw('( t.team_count>=5 or  r.Lid=4 )')*/
            ->where('r.id',$uid)->first();

        $sum = DB::table(DB::RAW('r_index as r'))
            ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
            ->select(
                DB::RAW(' if(sum(case Lid '.$str.'  end ) is null,0,sum(case Lid '.$str.'  end )) as amount')
            )
            ->whereRaw('t.baozhengjin_status in (2)')
            ->where('r.id',$uid)->first();
   //     $sql = DB::getQueryLog();

        $freezed = TX::select([
            DB::RAW('sum(amount) as amount'),

        ])->where('userId',$uid)
            ->where('if_valid',1)
            ->first();

        $useMoney =($finished->amount-$freezed->amount)>0?($finished->amount-$freezed->amount):0;
        $retData=[
            'data'=>$data,
            'page'=>(int)$page,
            'pagesize'=>(int)$pageSize,
            'pageTotal'=>(int)ceil($count/$pageSize),
            'sum'=>number_format($sum->amount,2,'.',''),
            'finished'=>number_format($finished->amount,2,'.',''),
            'waiting'=>number_format(($sum->amount-$finished->amount),2,'.',''),
            'freezed'=>number_format($freezed->amount,2,'.',''),
            'useMoney'=>number_format($useMoney,2,'.',''),
        ];
        return $retData;
    }


    #收益个人计算页面接口
    /*
    sum	string	总的收益
    finished	string	已经结算的
    waiting	string	待到账的
    useMoney	string	可提现金额
 *
 * */
    public function income(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1,"POST only.","只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules=[
            'token' => 'required',
        ];

        $messages= [
            'token.required'=>"token 参数必不可少.",
        ];

        $validator = Validator::make($postData,$rules,$messages);
        if($validator->fails())
        {
            $messages = $validator->messages();
            return ret(1,data($messages),"检查POST参数是否完整.");
        }
        $user = Auth::user();

        $page = $request->json('page',1);
        $pageSize = $request->json('pagesize',10);
        $sort=0;
        $created = $request->json('created','----');
        $status = $request->json('status','----');
        $amount = $request->json('amount','----');
        $order ="r.uid";
        /*$order=$request->json('created','r.uid');*/
        if($created !="----" && $amount !="----")
        {
            return ret(1,"created ,amount can not be appearance same time",'created ,amount  参数排序请使用一个参数.');
        }
        if($created=="----")
        {
            $sort=($amount=="1")?"1":"0";
            $order = "amount";
        }else{
            $sort=($created=="1")?"1":"0";
            $order = "r.uid";
        }

        switch($status)
        {
            case 1:
                $status = 1;
                break;
            case 0:
                $status = 0;
                break;
            default:
                $status = 0;
        }

        $ps=[
            'uid'=> $user->id,
            'status'=> $status,
            'page'=> $page,
            'pageSize'=> $pageSize,
            'sort'=> $sort,
            'order'=> $order,
            'staus'=>$status,
        ];

        $income_list = $this->benefitsList($user->id,$page,$pageSize,$sort,$order,$status);
    //    $user->ps =$ps;
        $user->finished =$income_list['finished'];
        $user->sum =$income_list['sum'];
        $user->waiting = $income_list['waiting'];
        $user->freezed =$income_list['freezed'];
        $user->useMoney =$income_list['useMoney'];
        $user->userdata =$income_list['data'];
        $user->page =$income_list['page'];
        $user->pagesize =$income_list['pagesize'];
        $user->pageTotal =$income_list['pageTotal'];
        return ret(0,$user,"查询成功");
    }

    #收益来源搜索
    public function incomeSearch(Request $request)
    {
        if ($request->isMethod('get')) {
            return ret(1, "POST only.", "只允许使用POST请求.");
        }
        $postData = $request->json()->all();
        $rules = [
            'token' => 'required'
        ];

        $messages = [
            'token.required' => "token 参数必不可少.",
        ];

        $validator = Validator::make($postData, $rules, $messages);
        if ($validator->fails()) {
            $messages = $validator->messages();
            return ret(1, data($messages), "检查POST参数是否完整.");
        }
        $page = $request->json('page',1);
        $pageSize = $request->json('pagesize',10);
        $name = $request->json('name',"----");
        $sort=0;
        $created = $request->json('created','----');
        $status = $request->json('status','----');
        $amount = $request->json('amount','----');
        $order ="r.uid";
        /*$order=$request->json('created','r.uid');*/
        if($created !="----" && $amount !="----")
        {
            return ret(1,"created ,amount can not be appearance same time",'created ,amount  参数排序请使用一个参数.');
        }
        if($created=="----")
        {
            $sort=($amount=="1")?"1":"0";
            $order = "amount";
        }else{
            $sort=($created=="1")?"1":"0";
            $order = "r.uid";
        }

        switch($status)
        {
            case 1:
                $status = 1;
                break;
            case 0:
                $status = 0;
                break;
            default:
                $status = 0;
        }

        $sort = $sort=="1"?"desc":"asc";
        $user = Auth::user();
        $uid = $user->id;
        $mod = new User();
        $mod->syncUserRelation($uid);
        $mod->syncUserLevelData($uid);


        $ps=[
            'sort'=>$sort,
            'order'=>$order,
            'uid'=>$uid,
            'name'=>$name,

        ];

        $sort = $sort=="1"?"desc":"asc";
        $str= $this->getWaitingRate();

        $model = DB::table(DB::RAW('r_index as r'))
            ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
            ->select('r.Lid',
                'r.id',
                'r.uid',
                't.name',
                't.user_truename',
                't.user_avatar',
                DB::RAW(' case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end as  status '),
                DB::RAW(' (case Lid '.$str.'  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount'),
                DB::RAW('if(t.baozhengjin_updated is null,"",t.baozhengjin_updated) as create_time')
            )->where('r.id',$uid)
            ->whereRaw('t.baozhengjin_status in (2)')
            ->whereRaw('( t.name like  "%'.$name.'%" or t.user_truename like "%'.$name.'%" )');

        $count =  $model->count();
        $data = $model->OrderByRaw($order.'  '.$sort)
            ->forPage($page,$pageSize)
            ->get();
       //$sql =  DB::getQueryLog();

        $ret = [
            'page'=>$page,
            'pagesize'=>$pageSize,
            'pageTotal'=>ceil($count/$pageSize),
            'data'=>$data,
        ];

        return ret(0,$ret,"查询成功");
    }


    public function myPage(Request $request)
    {
        $withData= 1;
        $uid=1095;
        $sort=1;
        $status=1;
        $page = $request->json('page',1);
        $pageSize = $request->json('pagesize',10);
        $order =1;

        $rate  = Ratio::where('type','1')->get()->mapWithKeys(function ($item) {
            return [$item['level_id'] => $item['ratio']];
        });
        if(count($rate)) {
            $str = "";
            foreach ($rate as $k => $v) {
                $str .= 'WHEN ' . $k . ' THEN "' . ($v * 100) . '"  ';
            }
        }

        $sort = $sort=="1"?"desc":"asc";
        # sync
        $sync = new User();
        $sync->syncUserRelation($uid);
        $sync->syncUserLevelData($uid);
        $finished_rate = $this->finishedRate($uid);
        //   DB::enableQueryLog();
        $data =[];
        $count = 0;

        if($withData) {
            if ($status == 0) {
                $model = DB::table(DB::RAW('r_index as r'))
                    ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
                    ->select('r.Lid',
                        'r.id',
                        'r.uid',
                        't.name',
                        't.user_truename',
                        't.user_avatar',
                        DB::RAW(' case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end as  status '),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount'),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount_finished'),
                        DB::RAW('if(t.baozhengjin_updated is null,"",t.baozhengjin_updated) as create_time')
                    )->where('r.id', $uid)
                    ->whereRaw('t.baozhengjin_status in (2)')
                    ->havingRaw('amount_finished > 0');
                //         ->whereRaw('( t.team_count>=5 or r.Lid >1 )');
            }else{
                $model = DB::table(DB::RAW('r_index as r'))
                    ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
                    ->select('r.Lid',
                        'r.id',
                        'r.uid',
                        't.name',
                        't.user_truename',
                        't.user_avatar',
                        DB::RAW(' case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end as  status '),
                        DB::RAW(' (case Lid ' . $finished_rate . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount_finished'),
                        DB::RAW(' (case Lid ' . $str . '  end)*(case t.baozhengjin_status WHEN 2 THEN "1" WHEN 1 THEN "0" WHEN 3 THEN "0"  end)  as amount'),
                        DB::RAW('if(t.baozhengjin_updated is null,"",t.baozhengjin_updated) as create_time')
                    )->where('r.id', $uid)
                    ->whereRaw('t.baozhengjin_status in (2)')->havingRaw('amount > 0')->havingRaw('amount_finished=0');
            }
            $count = count($model->count());
            $data = $model->OrderByRaw($order . '  ' . $sort)
                ->forPage($page, $pageSize)
                ->get();
        }

        $finished = DB::table(DB::RAW('r_index as r'))
            ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
            ->select(
                DB::RAW(' if(sum(case Lid '.$finished_rate.'  end ) is null,0,sum(case Lid '.$finished_rate.'  end )) as amount')
            )
            ->whereRaw('t.baozhengjin_status in (2)')
            /*            ->whereRaw('t.saleuser_state in (2)')
                        ->whereRaw('(t.team_finished=1 or r.Lid=4)')
                        ->whereRaw('( t.team_count>=5 or  r.Lid=4 )')*/
            ->where('r.id',$uid)->first();

        $sum = DB::table(DB::RAW('r_index as r'))
            ->join(DB::RAW('t_user as t'), 'r.uid', '=', 't.id')
            ->select(
                DB::RAW(' if(sum(case Lid '.$str.'  end ) is null,0,sum(case Lid '.$str.'  end )) as amount')
            )
            ->whereRaw('t.baozhengjin_status in (2)')
            ->where('r.id',$uid)->first();
        //     $sql = DB::getQueryLog();

        $freezed = TX::select([
            DB::RAW('sum(amount) as amount'),

        ])->where('userId',$uid)
            ->where('if_valid',1)
            ->first();

        $useMoney =($finished->amount-$freezed->amount)>0?($finished->amount-$freezed->amount):0;
        $retData=[
            'data'=>$data,
            'page'=>(int)$page,
            'pagesize'=>(int)$pageSize,
            'pageTotal'=>(int)ceil($count/$pageSize),
            'sum'=>number_format($sum->amount,2,'.',''),
            'finished'=>number_format($finished->amount,2,'.',''),
            'waiting'=>number_format(($sum->amount-$finished->amount),2,'.',''),
            'freezed'=>number_format($freezed->amount,2,'.',''),
            'useMoney'=>number_format($useMoney,2,'.',''),
        ];
        
        return response()->json($retData);
    }





















































}
