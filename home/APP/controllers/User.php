<?php
use Illuminate\Database\Capsule\Manager as DB;

class UserController extends CoreController {
    private static $datatype;
    private static $callback;
    private static $client;
	private static $user_id;
	private static $userinfo;
	/**
	 *
	 * 初始化验证
	 *
	 **/
	public function init(){
        parent::init();
        Yaf_Dispatcher::getInstance()->disableView();

        self::$datatype	= $this->get('datatype', 'json');
        self::$callback	= $this->get('callback', 'callback');
        $inputs		= array(
            ['name'=>'datatype',  	'value'=>self::$datatype,	'role'=>'in:json,jsonp',	'msg'=>'数据类型有误'],
            ['name'=>'callback',  	'value'=>self::$callback,	'fun'=>'isFileName',		'msg'=>'回调变量有误'],
        );
        $result		= Validate::check($inputs);
        if(	!empty($result) ){
            $result	= array(
                'code'	=>	'0',
                'msg'	=>	'输入参数有误.',
                'data'	=>	$result,
            );
            json($result, self::$datatype, self::$callback);
        }
		/***验证登陆***/
		$token	  = addslashes($this->get('token', ''));
		if( (self::$user_id = self::checklogin($token))==FALSE ){
			$result	= array(
				'code'	=>	'-1',
				'msg'	=>	'用户未登陆，请先登陆吧',
				'data'	=>	array(),
			);
			json($result, self::$datatype, self::$callback);
		}
		self::$userinfo	=DB::table('members')->find(self::$user_id);
		unset(self::$userinfo['password']);
		
		if(self::$userinfo['company_id']>0){
			self::$userinfo['company']	=	DB::table('company')->find(self::$userinfo['company_id']);
			if(self::$userinfo['company']['city_id']>0){
				$myCity	=	DB::table('city')->find(self::$userinfo['company']['city_id']);
				self::$userinfo['company']['city']		=	$myCity['name'];
				self::$userinfo['company']['province']  =	DB::table('city')->find($myCity['up'])['name'];
			}
		}

        self::$client	=new Yar_client('http://cp.uu235.com/rpc');
        self::$client->SetOpt(YAR_OPT_CONNECT_TIMEOUT, 5000);
	}
	
	/**
	 *接口名称	用户中心首页
	 *接口地址	http://api.com/user/index/
	 *接口说明	显示欢迎页图片
	 *参数 @param无
	 *返回 @return
	 *返回格式	Json
	 * @images   图片地址组
	 **/
	public function indexAction(){
		do{
			$result	=	array(
							'code'	=>	'1',
							'msg'	=>	'用户中心',
							'data'	=>	self::$userinfo,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 *接口名称	个人资料
	 *接口地址	http://api.com/user/info/
	 *接口说明	显示个人资料
	 *参数 @param
	 * @token		登陆令牌
	 *返回 @return
	 * @rows
	 *
	 **/
	public function infoAction(){
		do{	
			if(self::$userinfo['company_id']>0){
				self::$userinfo['company']	=	DB::table('company')->find(self::$userinfo['company_id']);
			}
			$result	=	array(
							'code'	=>	'1',
							'msg'	=>	'个人及公司资料',
							'data'	=>	self::$userinfo,
						);
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 * 关联个推账号CID
	 **/
	public function setGetuiCidAction(){
		do{	
			$cid       = $this->get('cid', '');
			if(empty($cid)){
				$result	=	array(
							'code'	=>	'0',
							'msg'	=>	'CID参数不能为空',
							'data'	=>	[],
						);
				break;
			}			
			if (DB::table('members')->where('id','=',self::$user_id)->update(['getui_cid'=>$cid])===FALSE) {
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'关联用户个推CID失败.',
					'data'	=>	array(),
				);				
			}else{
				$result	= array(
					'code'	=>	'1',
					'msg'	=>	'关联用户个推CID成功.',
					'data'	=>	array(
									'cid'	=>	$cid,
								),
				);
			}
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 *接口名称	实名认证
	 *接口地址	http://api.com/user/consummate/
	 *接口说明	新注册用户完善个人信息
	 *参数 @param
	 * @realname 	姓名
	 * @card_id 	身份证号
	 * @email		邮箱
	 * @token		登陆标记
	 *返回 @return	
	 * @status		更新状态
	 **/
	public function authAction(){
		do{
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以提交认证申请.',
						'data'	=>	array(),
					);
					break;
			}
			if( empty(self::$userinfo['name']) || empty(self::$userinfo['company_id']) || empty(self::$userinfo['company']['company']) ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'请在填写公司及个人名称信息后提交.',
						'data'	=>	array(),
					);
					break;
			}
			if( self::$userinfo['company']['authstatus']==2 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'认证已通过，无需重复认证.',
						'data'	=>	array(),
					);
					break;
			}			
			
			if (DB::table('company')->where('id','=',self::$userinfo['company_id'])->update(['authstatus'=>1])===FALSE) {
						$result	= array(
							'code'	=>	'0',
							'msg'	=>	'提交申请认证信息失败.',
							'data'	=>	array(),
						);
			}else{
						$result	= array(
							'code'	=>	'1',
							'msg'	=>	'提交申请认证成功,请等待审核.',
							'data'	=>	array(
											'status'=>	1,
										),
						);
			}
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 *接口名称	完善信息
	 *接口地址	http://api.com/user/consummate/
	 *接口说明	新注册用户完善个人信息
	 *参数 @param
	 * @realname 	姓名
	 * @card_id 	身份证号
	 * @email		邮箱
	 * @token		登陆标记
	 *返回 @return	
	 * @status		更新状态
	 **/
	public function consummateAction(){

		do{			
			$name       = $this->get('name', '');
            $email	    = $this->get('email', '');
			$company    = $this->get('company', '');
			$city_id	= $this->get('city_id', '');
			$address	= $this->get('address', '');
			$tel    	= $this->get('tel', '');
			$description= $this->get('description', '');
			$license_no	= $this->get('license_no', '');
            $inputs	= array(
                ['name'=>'name', 	'value'=>$name,	 'fun'=>'isChinese', 'msg'=>'姓名格式有误'],     
            );
			if(!empty($email))
				array_push($inputs, ['name'=>'email',	'value'=>$email, 'fun'=>'isEmail','msg'=>'邮箱格式有误']);
            $result		= Validate::check($inputs);
            if(	!empty($result) ){
                $result	= array(
                    'code'	=>	'0',
                    'msg'	=>	'输入参数有误.',
                    'data'	=>	$result,
                );
                break;
            }			
			$rows		=	array(				
								'name'		=>	$name,
								'email'		=>	$email,
							);
			if (DB::table('members')->where('id','=',self::$user_id)->update($rows)===FALSE) {
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'更新用户信息失败.',
								'data'	=>	array(),
						);
			}else{
						if(self::$userinfo['is_root']==1){
								$rows	=	array(
										'type'		=>	self::$userinfo['type'],
										'city_id'	=>	$city_id,
										'company'	=>	$company,
										'address'	=>	$address,
										'tel'		=>	$tel,
										'description'=>	$description,
										'license_no'=>	$license_no,
								);
								if(self::$userinfo['company_id']==0){							
									$rows['created_at']	=	date('Y-m-d H:i:s');
									$company_id	=	DB::table('company')->insertGetId($rows);
									DB::table('members')->where('id','=',self::$userinfo['id'])->update(['company_id'=>$company_id]);
								}else{
									$rows['updated_at']	=	date('Y-m-d H:i:s');
									DB::table('company')->where('id','=',self::$userinfo['company_id'])->update($rows);
								}
						}
						self::$userinfo	=DB::table('members')->select('id','is_root','name','phone','headlogo','type','email','status','company_id')
															 ->find(self::$user_id);
						if(self::$userinfo['company_id']>0){
							self::$userinfo['company']	=	DB::table('company')->find(self::$userinfo['company_id']);
							if(self::$userinfo['company_id']>0){
								self::$userinfo['company']	=	DB::table('company')->find(self::$userinfo['company_id']);
								if(self::$userinfo['company']['city_id']>0){
									$myCity	=	DB::table('city')->find(self::$userinfo['company']['city_id']);
									self::$userinfo['company']['city']		=	$myCity['name'];
									self::$userinfo['company']['province']  =	DB::table('city')->find($myCity['up'])['name'];
								}
							}
						}
						$result	= array(
							'code'	=>	'1',
							'msg'	=>	'用户信息更新成功.',
							'data'	=>	self::$userinfo,
						);
			}
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
		
	/**
	 *接口名称	上传头像
	 *接口地址	http://api.com/user/uploadphoto/
	 *接口说明	上传图片，更新用户头像
	 *参数 @param
	 * @logo 		图片文件
	 * @token		登陆标记
	 *返回 @return	
	 * @status		更新状态
	 **/
	public function uploadheadphotoAction(){

		do{			
			$type	= addslashes($this->get('type', ''));
			$files	= $this->get('logo', '');
			if( $files=='' || $type=='' ){
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'图片类型或者内容为空',
								'data'	=>	array(),
							);
						break;
			}
			
			$config	  = Yaf_Registry::get('config');
			$filename = 'logo-t' . time() . '.' . $type;		
			$path	  = '/logo/' . date('Ym') . '/';
			$descdir  = $config['application']['uploadpath'] . $path;
			if( !is_dir($descdir) ){ mkdir($descdir, 0777, TRUE); }
			$realpath = $descdir . $filename;				
			$webpath  = $config['application']['uploadwebpath'] . $path . $filename;
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $files, $base64result)){			 
			  if (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', str_replace($base64result[1], '', $files))))){				
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			  }
			}elseif (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', $files)))){				
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			}
			$photourl	=	$webpath;
			$rows		=	array(								
								'headlogo'	=>	$photourl,
							);
			if (DB::table('members')->where('id','=',self::$user_id)->update($rows)===FALSE) {
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'更新用户头像更新失败.',
					'data'	=>	array(),
				);		
			}else{
				$result	= array(
					'code'	=>	'1',
					'msg'	=>	'用户头像更新成功.',
					'data'	=>	array(
									'status'	=>	1,
									'photourl'	=>	$photourl,
								),
				);
			}
					
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	public function uploadcompanylogoAction(){
		do{			
			if(self::$userinfo['is_root']!=1){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'唯公司主账号，可更新公司logo.',
						'data'	=>	[],
				);
				break;
			}
			$type	= addslashes($this->get('type', 'jpg'));
			$files	= $this->get('logo', '');
			if( $files=='' || $type=='' ){
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'图片类型或者内容为空',
								'data'	=>	array(),
							);
						break;
			}
			$config   = Yaf_Registry::get('config');
                        $filename = 'logo-t' . time() . '.' . $type;
                        $path     = '/logo/' . date('Ym') . '/';
                        $descdir  = $config['application']['uploadpath'] . $path;
                        if( !is_dir($descdir) ){ mkdir($descdir, 0777, TRUE); }
                        $realpath = $descdir . $filename;
                        $webpath  = $config['application']['uploadwebpath'] . $path . $filename;
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $files, $base64result)){			 
			  if (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', str_replace($base64result[1], '', $files))))){
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			  }
			}elseif (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', $files)))){
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			}			
			$photourl	=	$webpath;
			$rows		=	array(								
								'logo'	=>	$photourl,
							);
			if (DB::table('company')->where('id','=',self::$userinfo['company_id'])->update($rows)===FALSE) {
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'更新公司logo更新失败.',
					'data'	=>	array(),
				);		
			}else{
				$result	= array(
					'code'	=>	'1',
					'msg'	=>	'公司logo更新成功.',
					'data'	=>	array(
									'status'	=>	1,
									'photourl'	=>	$photourl,
								),
				);
			}
					
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	public function uploadlicenseAction(){
		do{			
			if(self::$userinfo['is_root']!=1){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'唯公司主账号，可上传公司营业执照.',
						'data'	=>	[],
				);
				break;
			}
			$type	= addslashes($this->get('type', 'jpg'));
			$files	= $this->get('logo', '');
			if( $files=='' || $type=='' ){
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'图片类型或者内容为空',
								'data'	=>	array(),
							);
						break;
			}
			$config   = Yaf_Registry::get('config');
                        $filename = 'logo-t' . time() . '.' . $type;
                        $path     = '/logo/' . date('Ym') . '/';
                        $descdir  = $config['application']['uploadpath'] . $path;
                        if( !is_dir($descdir) ){ mkdir($descdir, 0777, TRUE); }
                        $realpath = $descdir . $filename;
                        $webpath  = $config['application']['uploadwebpath'] . $path . $filename;
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $files, $base64result)){			 
			  if (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', str_replace($base64result[1], '', $files))))){
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			  }
			}elseif (!file_put_contents($realpath, base64_decode(str_replace(' ', '+', $files)))){
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			}			
			$photourl	=	$webpath;
			$rows		=	array(								
								'license_img'	=>	$photourl,
							);
			if (DB::table('company')->where('id','=',self::$userinfo['company_id'])->update($rows)===FALSE) {
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'更新公司logo更新失败.',
					'data'	=>	array(),
				);		
			}else{
				$result	= array(
					'code'	=>	'1',
					'msg'	=>	'公司营业执照更新成功.',
					'data'	=>	array(
									'license_img'	=>	$photourl,
								),
				);
			}
					
		}while(FALSE);

		json($result, self::$datatype, self::$callback);
	}
	
	public function staffAction() {
		do{
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以查看员工账号.',
						'data'	=>	array(),
					);
					break;
			}
			$members_id	  = addslashes($this->get('members_id', NULL));
			if($members_id==NULL){
				$rows	= DB::table('members')->where('company_id','=',self::$userinfo['company_id'])->get();
				foreach($rows as $k=>$v) unset($rows[$k]['password']);
			}else{
				$rows	= DB::table('members')->find($members_id);
				if($rows['company_id']!=self::$userinfo['company_id']){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'非本公司员工账号.',
						'data'	=>	[],
					);
					break;
				}
				unset($rows['passowrd']);
			}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'公司员工账号.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	public function staffaddAction() {
		do{
			$this->authCheck();
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以创建员工账号.',
						'data'	=>	array(),
					);
					break;
			}
						
			$phone		= $this->get('phone', 		'');			
			$password	= $this->get('password', 	'');			
			$name       = $this->get('name', '');
            $email	    = $this->get('email', '');			
			$inputs	= array(
					['name'=>'phone',  'value'=>$phone,	 'fun'=>'isPhone', 'msg'=>'手机号码格式有误'],
			);
			if(!empty($name))	array_push($inputs, ['name'=>'name',	'value'=>$name,  'fun'=>'isName','msg'=>'姓名格式有误']);            
			if(!empty($email))	array_push($inputs, ['name'=>'email',	'value'=>$email, 'fun'=>'isEmail','msg'=>'邮箱格式有误']);
            $result		= Validate::check($inputs);
			if( $password=='' ){
					$result['password']		= '密码不能为空';				
			}
			if(	!empty($result) ){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'输入参数有误.',
							'data'	=>	$result,
					);
					break;
			}			
			if( DB::table('members')->where('phone','=',$phone)->count()>0 ){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此手机号已存在.',
							'data'	=>	[],
						);
					break;
			}			
			
			$rows	=	array(
							'type'			=>	self::$userinfo['type'],
							'phone'			=>	$phone,
							'password'		=>	md5($password),
							'company_id'	=>	self::$userinfo['company_id'],
							'is_root'		=>	0,
							'name'			=>	$name,
							'email'			=>	$email,
							'status'		=>	1,
							'created_at'	=>	date('Y-m-d H:i:s'),
			);
			$lastId = DB::table('members')->insertGetId($rows);
			if ($lastId) {												
					$result	= array(
							'code'	=>	'1',
							'msg'	=>	'添加员工账号成功.',
							'data'	=>	array(
											'user_id'		=>	$lastId,
											'type'			=>	self::$userinfo['type'],
											'phone'			=>	$phone,
											'name'			=>	$name,
											'email'			=>	$email,
											'status'		=>	1,
										)
					);
					break;
			}
			$result	= array(
					'code'	=>	'0',
					'msg'	=>	'用户注册失败',
					'data'	=>	array(),
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);	
	}

	public function staffeditAction() {
		do{
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以编辑员工账号.',
						'data'	=>	array(),
					);
					break;
			}
			$members_id	  = addslashes($this->get('members_id', NULL));
			if($members_id==NULL){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'账号ID参数为空.',
						'data'	=>	array(),
					);
					break;
			}else{
				$rows	= DB::table('members')->find($members_id);
				if($rows['company_id']!=self::$userinfo['company_id']){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'非本公司员工账号.',
						'data'	=>	[],
					);
					break;
				}				
			}

			$phone		= $this->get('phone', 		'');			
			$password	= $this->get('password', 	'');			
			$name       = $this->get('name', '');
		        $email	    = $this->get('email', '');		
			$status		= $this->get('status', 0);		
			$inputs	= array(
					['name'=>'phone',  'value'=>$phone,	 'fun'=>'isPhone', 'msg'=>'手机号码格式有误'],
			);
			if(!empty($name))	array_push($inputs, ['name'=>'name',	'value'=>$name,  'fun'=>'isName','msg'=>'姓名格式有误']);            
			if(!empty($email))	array_push($inputs, ['name'=>'email',	'value'=>$email, 'fun'=>'isEmail','msg'=>'邮箱格式有误']);
            $result		= Validate::check($inputs);			
			if(	!empty($result) ){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'输入参数有误.',
							'data'	=>	$result,
					);
					break;
			}			
			if( DB::table('members')->where('id','<>',$members_id)->where('phone','=',$phone)->count()>0 ){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此手机号已存在.',
							'data'	=>	[],
						);
					break;
			}			
			
			$rows	=	array(			
							'phone'			=>	$phone,			
							'name'			=>	$name,
							'email'			=>	$email,
							'status'		=>	$status,
							'updated_at'	=>	date('Y-m-d H:i:s'),
			);
			if( $password=='' )	$rows['password']=md5($password);			
			if (DB::table('members')->where('id','=',$members_id)->update($rows)!==FALSE) {												
					$result	= array(
							'code'	=>	'1',
							'msg'	=>	'编辑员工账号成功.',
							'data'	=>	array(
											'user_id'		=>	$members_id,
											'type'			=>	self::$userinfo['type'],
											'phone'			=>	$phone,
											'name'			=>	$name,
											'email'			=>	$email,
											'status'		=>	$status,
										)
					);
					break;
			}
			$result	= array(
					'code'	=>	'0',
					'msg'	=>	'用户注册失败',
					'data'	=>	array(),
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);	
	}
	
	public function staffstatusAction() {
		do{
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以修改员工账号.',
						'data'	=>	array(),
					);
					break;
			}
			$members_id	  = addslashes($this->get('members_id', NULL));
			if($members_id==NULL){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'账号ID参数为空.',
						'data'	=>	array(),
					);
					break;
			}
			$members	= DB::table('members')->find($members_id);
			if($members['company_id']!=self::$userinfo['company_id']){
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'非本公司员工账号.',
					'data'	=>	[],
				);
				break;
			}
			$status	=	$members['status']==1 ? 0 : 1;
			$rows	=	array(
							'status'		=>	$status,
							'updated_at'	=>	date('Y-m-d H:i:s'),
			);
			if (DB::table('members')->where('id','=',$members_id)->update($rows)!==FALSE) {												
					$result	= array(
							'code'	=>	'1',
							'msg'	=>	'修改员工账号状态成功.',
							'data'	=>	array(
											'members_id'		=>	$members_id,											
											'status'		=>	$status,
										)
					);
					break;
			}
			$result	= array(
					'code'	=>	'0',
					'msg'	=>	'用户注册失败',
					'data'	=>	array(),
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);	
	}
	
	public function walletAction() {
		do{
			$result	=	array(
							'code'	=>	'1',
							'msg'	=>	'我的钱包',
							'data'	=>	[
											"account"	=> self::$userinfo['company']['account'],
											"frozen"	=> self::$userinfo['company']['frozen'],
											"usemoney"	=> self::$userinfo['company']['usemoney'],
										]
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);		
	}
	
	public function inquiryAction() {
		do{
			$this->authCheck();			
			$order_type		= intval($this->get('order_type', 	0));
			$insurance_id	= intval($this->get('insurance_id', 0));
			$car_type		= intval($this->get('car_type', 	0));
			$report_number	= trim($this->get('report_number', 	''));
			$inputs	= array(
					['name'=>'order_type',  'value'=>$order_type,	 'fun'=>'isBool', 'msg'=>'订单类型有误'],
					['name'=>'insurance_id','value'=>$insurance_id,	 'fun'=>'isInt',  'msg'=>'保险公司ID有误'],
			);
            $result		= Validate::check($inputs);
			if( $order_type==0 && $insurance_id==0 ){
					$result['insurance_id']		= '保险发单需要选择保险公司';				
			}
			if(	!empty($result) ){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'输入参数有误.',
							'data'	=>	$result,
					);
					break;
			}			
			$order_no	= date('ymdHis').rand(100000,999999);
			$rows	= array(
					'order_no'		=>	$order_no,
					'members_id'	=>	self::$userinfo['id'],
					'company_id'	=>	self::$userinfo['company_id'],
					'order_type'	=>	$order_type,
					'car_type'		=>	$car_type,
					'insurance_id'	=>	$insurance_id,
					'report_number'	=>	$report_number,
					'status'		=>	0,
					'created_at'	=>	date('Y-m-d H:i:s'),
			);
			$neworder = DB::table('orders')->where('order_type','=',$order_type)
											->where('company_id','=',self::$userinfo['company_id'])
											->where('car_type','=',$car_type)
											->where('insurance_id','=',$insurance_id)
											->where('report_number','=',$report_number)
											->where('carmodel','=','')
											->where('autoparts','=','')
											->where('autoparts_img','=','')
											->first();	
			if( empty($neworder) ){
				if( $order_id=DB::table('orders')->insertGetId($rows) ){
						$result	= array(
								'code'	=>	'1',
								'msg'	=>	'开始发布新询价单.',
								'data'	=>	[
											"order_no"=> $order_no,
										],
							);
						break;
				}
			}else{
				$result	= array(
								'code'	=>	'1',
								'msg'	=>	'开始发布新询价单.',
								'data'	=>	[
											"order_no"=> $neworder['order_no'],
										],
							);
				break;
			}
			
						
			$result	= array(
					'code'	=>	'0',
					'msg'	=>	'发布新询价单失败',
					'data'	=>	array(),
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);	
	}

	public function myinquiryAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('orders')->where('members_id','=',self::$user_id)
										 ->orderBy('id', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize);
			$status	= $this->get('status', '');
			if($status!==''){
				$rows	= $rows->where('status','=',$status);			
			}/*else{
				$rows	= $rows->where('status','<',200);
			}*/
			$is_img	  = intval($this->get('is_img', 0));
			if($is_img==0){
				$rows	= $rows->where('autoparts','<>','');
			}elseif($is_img==1){
				$rows	= $rows->where('autoparts','=','');
			}			
			$rows	= $rows->get();
			
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){				
				$rows[$k]['quotenum'] = DB::table('quote')->where('orders_id','=',$v['id'])->count();
				$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);
				if($is_img!=0){
					$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
				}
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'公司订单列表.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function repairorderAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('orders')->where('members_id','=',self::$user_id)
										 ->orderBy('id', 'DESC');
			$status	= intval($this->get('status', 0));
			if($status>0){
				$rows	= $rows->where('status','=',$status);			
			}			
			$total	=  $rows->count();
			$rows	=  $rows->offset($startpagenum)
							->limit($pagesize)
							->get();
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){
				$quoteselect	= DB::table('quoteselect')->where('order_no','=',$v['order_no'])->get();
				if(!empty($quoteselect)&&is_array($quoteselect)){
				foreach($quoteselect as $k1=>&$v1){
					$v1['providercompany']= DB::table('company')->where('id','=',$v1['provider_id'])->select('company','logo','address','tel','description')->first();
					$v1['quoteselectresult']	= empty($v1['quoteselect']) ? '' : json_decode($v1['quoteselect'], TRUE);
					switch($v1['delivery_status']){
						case '0':
							$v1['deliverystatusname']	=	'未发货';
							break;
						case '1':
							$v1['deliverystatusname']	=	'已发货';
							break;
						case '2':
							$v1['deliverystatusname']	=	'已收货';
							break;
					}
				}}
				$rows[$k]['quoteselect'] = $quoteselect;
				$rows[$k]['quotenum'] = DB::table('quote')->where('orders_id','=',$v['id'])->count();
				$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);
				if(empty($rows[$k]['autoparts'])){
					$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
				}
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.',
						'total'	=>	$total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function pendingdeliveryAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.members_id','=',self::$user_id)
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',0)
										 ->orderBy('orders.id', 'DESC')										 
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon');
			$total	= $rows->count();
			$rows	= $rows->offset($startpagenum)
							->limit($pagesize)
							->get();	 
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}

				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.待发货',
						'total'	=>  $total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function pendingreceiveAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.members_id','=',self::$user_id)
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',1)
										 ->orderBy('orders.id', 'DESC')										 
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon');
			$total	= $rows->count();
			$rows	= $rows->offset($startpagenum)
							->limit($pagesize)
							->get();
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){				
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}
				
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.待收货',
						'total'	=>  $total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function orderfinishedAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.members_id','=',self::$user_id)
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',2)
										 ->orderBy('orders.id', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize)
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon')
										 ->get();
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){				
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}
				
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.已完成',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function providerorderAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
			->where('orders.status','=',400)
			->where('quoteselect.provider_id','=',self::$userinfo['company_id'])			
			->select('orders.id','orders.order_no','orders.company_id','orders.carmodel','orders.shippingaddr_id','orders.caricon','orders.autoparts','orders.created_at','quoteselect.provider_id','quoteselect.quote_id','quoteselect.quoteselect')
			->orderBy('orders.id', 'DESC');			
			$delivery_status	= intval($this->get('delivery_status', 0));
			$rows	= $rows->where('delivery_status','=',$delivery_status);
			
			$total	=  $rows->count();
			$rows	=  $rows->offset($startpagenum)
							->limit($pagesize)
							->get();		
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){						
				$rows[$k]['repaircompany']= DB::table('company')->where('id','=',$v['company_id'])->select('company','logo','address','tel','description')->first();
				$rows[$k]['quotenum'] = DB::table('quote')->where('orders_id','=',$v['id'])->count();
				$rows[$k]['autoparts'] 		= empty($v['autoparts']) ? '' : json_decode($v['autoparts'], TRUE);
				if(empty($rows[$k]['autoparts'])){
					$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
				}
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
				$rows[$k]['quote']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.',
						'total'	=>	$total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	public function insuranceorderAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('orders')->where('insurance_id','=',self::$userinfo['company_id'])
										 ->orderBy('id', 'DESC');
			$status	= intval($this->get('status', 0));
			if($status>0){
				$rows	= $rows->where('status','=',$status);			
			}
			$total	=  $rows->count();
			$rows	=  $rows->offset($startpagenum)
							->limit($pagesize)
							->get();		
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){				
				
				$quoteselect	= DB::table('quoteselect')->where('order_no','=',$v['order_no'])->get();
				if(!empty($quoteselect)&&is_array($quoteselect)){
				foreach($quoteselect as $k1=>&$v1){
					$v1['providercompany']= DB::table('company')->where('id','=',$v1['provider_id'])->select('company','logo','address','tel','description')->first();
					$v1['quoteselectresult']	= empty($v1['quoteselect']) ? '' : json_decode($v1['quoteselect'], TRUE);
					switch($v1['delivery_status']){
						case '0':
							$v1['deliverystatusname']	=	'未发货';
							break;
						case '1':
							$v1['deliverystatusname']	=	'已发货';
							break;
						case '2':
							$v1['deliverystatusname']	=	'已收货';
							break;
					}
				}}
				$rows[$k]['quoteselect'] = $quoteselect;
				$rows[$k]['quotenum'] = DB::table('quote')->where('orders_id','=',$v['id'])->count();
				$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);
				if(empty($rows[$k]['autoparts'])){
					$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
				}
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}			
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.保险公司',
						'total'	=>	$total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function insurancependingdeliveryAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.insurance_id','=',self::$userinfo['company_id'])
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',0)
										 ->orderBy('orders.id', 'DESC')			
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon');
			$total	= $rows->count();
			$rows	= $rows->offset($startpagenum)
							->limit($pagesize)
							->get();						 
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}
				
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.待发货',
						'total'	=>  $total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function insurancependingreceiveAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.insurance_id','=',self::$userinfo['company_id'])
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',1)
										 ->orderBy('orders.id', 'DESC')										 
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon');
			$total	= $rows->count();
			$rows	= $rows->offset($startpagenum)
							->limit($pagesize)
							->get();							 
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){				
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}
				
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.待收货',
						'total'	=>  $total,
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function insuranceorderfinishedAction() {
		do{
			$this->authCheck();
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('quoteselect')->join('orders','quoteselect.order_no','=','orders.order_no')
										 ->where('orders.insurance_id','=',self::$userinfo['company_id'])
										 ->where('orders.status','>=',400)
										 ->where('quoteselect.delivery_status','=',2)
										 ->orderBy('orders.id', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize)
										 ->select('quoteselect.*','orders.shippingaddr_id','orders.order_no','orders.order_type','orders.vin_code','orders.car_number','orders.car_type','orders.carmodel','orders.caricon')
										 ->get();
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>&$v){				
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect']	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);
				switch($v['delivery_status']){
					case '0':
						$v['deliverystatusname']	=	'未发货';
						break;
					case '1':
						$v['deliverystatusname']	=	'已发货';
						break;
					case '2':
						$v['deliverystatusname']	=	'已收货';
						break;
				}
				
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.已完成',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function orderdetailAction() {
		do{
			$this->authCheck();
			$order_no        =  trim($this->get('order_no', ''));
			
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->first();			
			if(empty($rows)||!is_array($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'没找着订单.',
						'data'	=>	[],
				);
				break;
			}
			
			$rows['autoparts'] 	= empty($rows['autoparts']) ? '' : json_decode($rows['autoparts'], TRUE);
			$rows['autoparts_img']= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$orders_id)->get();
			$rows['auto_img']= DB::table('ordersimage')->where('type','=',0)->where('orders_id','=',$orders_id)->get();

			$quotes = DB::table('quoteselect')->where('order_no','=',$order_no)->get();
			if(!empty($quotes)&&is_array($quotes)){
			foreach($quotes as $k=>&$v){
				$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
				$v['quoteselect'] 	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);				
			}}
			$rows['quote']		= $quotes;
			$rows['shippingaddr']=DB::table('shippingaddr')->find($rows['shippingaddr_id']);				
			switch($rows['status']){
				case '0':
					$rows['statusname']	=	'未发布';
					break;
				case '100':
					$rows['statusname']	=	'待询价';
					break;
				case '200':
					$rows['statusname']	=	'待支付';
					break;
				case '300':
					$rows['statusname']	=	'已取消';
					break;
				case '400':
					$rows['statusname']	=	'待发货';
					break;
				case '500':
					$rows['statusname']	=	'待收货';
					break;
				case '600':
					$rows['statusname']	=	'已完成';
					break;
			}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.',
						'data'	=>	['orderinfo'=>$rows],
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function providerOrderDetailAction() {
		do{
			$this->authCheck();
			$order_no        =  trim($this->get('order_no', ''));
			
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->first();			
			if(empty($rows)||!is_array($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'没找着订单.',
						'data'	=>	[],
				);
				break;
			}
			
			$rows['autoparts'] 	= empty($rows['autoparts']) ? '' : json_decode($rows['autoparts'], TRUE);
			$rows['autoparts_img']= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$orders_id)->get();

			$quotes = DB::table('quoteselect')->where('order_no','=',$order_no)->where('provider_id','=',self::$userinfo['company_id'])->get();
			if(!empty($quotes)&&is_array($quotes)){
			foreach($quotes as $k=>&$v){				
				$v['quoteselect'] 	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);				
			}}
			$rows['quote']		= $quotes;
			$rows['shippingaddr']=DB::table('shippingaddr')->find($rows['shippingaddr_id']);				
			$rows['repaircompany']= DB::table('company')->where('id','=',$rows['company_id'])->select('company','logo','address','tel','description')->first();
			switch($rows['status']){
				case '0':
					$rows['statusname']	=	'未发布';
					break;
				case '100':
					$rows['statusname']	=	'待询价';
					break;
				case '200':
					$rows['statusname']	=	'待支付';
					break;
				case '300':
					$rows['statusname']	=	'已取消';
					break;
				case '400':
					$rows['statusname']	=	'待发货';
					break;
				case '500':
					$rows['statusname']	=	'待收货';
					break;
				case '600':
					$rows['statusname']	=	'已完成';
					break;
			}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'我的订单.',
						'data'	=>	['orderinfo'=>$rows],
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function companyinquiryAction() {
		do{
			$this->authCheck();
			if( self::$userinfo['is_root']!=1 ){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司主账号才可以修改员工账号.',
						'data'	=>	array(),
					);
					break;
			}
			
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;			
			$rows	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->orderBy('id', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize);
			$status	= $this->get('status', '');
			if($status!==''){
				$rows	= $rows->where('status','=',$status);			
			}
			$is_img	  = intval($this->get('is_img', 0));
			if($is_img==0){
				$rows	= $rows->where('autoparts','<>','');
			}else{
				$rows	= $rows->where('autoparts','=','');
			}			
			$rows	= $rows->get();	
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){
				
				$rows[$k]['quotenum'] = DB::table('quote')->where('orders_id','=',$v['id'])->count();
				$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);
				if($is_img!=0){
					$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
				}
				if($v['shippingaddr_id']!=0){
					$rows[$k]['shippingaddr']	= DB::table('shippingaddr')->find($v['shippingaddr_id']);
				}
			}}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'公司订单列表.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryVinAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$vin_code	= trim($this->get('vin_code', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($vin_code)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'车架号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();		
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改车架号了.',
							'data'	=>	[],
					);
					break;
			}
			$rows	= ['vin_code'	=> $vin_code];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新车架号成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新车架号失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryCarmodelAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$carmodel	= trim($this->get('carmodel', ''));
			$caricon	= trim($this->get('caricon', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($carmodel)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'车型名称不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改车型名称了.',
							'data'	=>	[],
					);
					break;
			}
			$series_id = DB::table('carmodel')->where('model','like',"%{$carmodel}%")->first()['series_id'];			
			$factory_id= DB::table('carseries')->find($series_id)['factory_id'];
			$rows	= ['carmodel'=>$carmodel,'caricon'=>$caricon,'carfactory_id'=>$factory_id];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新车型名称成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新车型名称失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
		public function autophotoUploadAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$imgtype	= $this->get('imgtype', 'jpg');
			$img		= $this->get('img', '');			
			if( $img=='' || $imgtype=='' ){
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'图片类型或者内容为空',
								'data'	=>	array(),
							);
						break;
			}			
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}			
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改报价单图片了.',
							'data'	=>	[],
					);
					break;
			}
			$config	  = Yaf_Registry::get('config');
			$filename = 'Img-t' . time() . '.' . $imgtype;
			$path	  = '/Images/' . date('Ym') . '/';			
			$descdir  = $config['application']['uploadpath'] . $path;			
			if( !is_dir($descdir) ){ mkdir($descdir, 0777, TRUE); }
			$realpath = $descdir . $filename;
			$webpath  = $config['application']['uploadwebpath'] . $path . $filename;
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/is', $img, $base64result)){			 
			  if (file_put_contents($realpath, base64_decode(str_replace(' ', '+', str_replace($base64result[1], '', $img))))){
				$newfile = str_replace('./', '', $realpath);
			  }else{
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			  }
			}elseif (file_put_contents($realpath, base64_decode(str_replace(' ', '+', $img)))){
				$newfile = str_replace('./', '', $realpath);
			}else{
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			}
									
			$rows	= [
						'orders_id'	=> $data['id'],
						'type'		=> 0,
						'file'		=> $webpath,
						'created_at'=> date('Y-m-d H:i:s'),
			];
			if($id=DB::table('ordersimage')->insertGetId($rows)){
					$rows['id']	=	$id;
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'上传车辆图片成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'上传车辆图片失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function autophotoListAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}						
			$rows	= DB::table('ordersimage')->where('type','=',0)->where('orders_id','=',$data['id'])->get();
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'车辆照片列表.',
						'data'	=>	$rows,
			);			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function autophotoDeleteAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$oimg_id	= intval($this->get('oimg_id', 0));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($oimg_id)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'询价单图片ID不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能删除询价单图片了.',
							'data'	=>	[],
					);
					break;
			}
			
			if(DB::table('ordersimage')->where('orders_id', '=', $data['id'])->where('id', '=', $oimg_id)->delete()!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'删除车辆图片成功.',
								'data'	=>	[],
					);
			}else{
					$result	= array(
								'code'	=>	'0',
								'msg'	=>	'删除车辆图片失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
		
	public function inquiryImgUploadAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$imgtype	= $this->get('imgtype', 'jpg');
			$img		= $this->get('img', '');			
			if( $img=='' || $imgtype=='' ){
						$result	= array(
								'code'	=>	'0',
								'msg'	=>	'图片类型或者内容为空',
								'data'	=>	array(),
							);
						break;
			}			
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}			
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改报价单图片了.',
							'data'	=>	[],
					);
					break;
			}
			$config	  = Yaf_Registry::get('config');
			$filename = 'Img-t' . time() . '.' . $imgtype;
			$path	  = '/Images/' . date('Ym') . '/';			
			$descdir  = $config['application']['uploadpath'] . $path;			
			if( !is_dir($descdir) ){ mkdir($descdir, 0777, TRUE); }
			$realpath = $descdir . $filename;
			$webpath  = $config['application']['uploadwebpath'] . $path . $filename;
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/is', $img, $base64result)){			 
			  if (file_put_contents($realpath, base64_decode(str_replace(' ', '+', str_replace($base64result[1], '', $img))))){
				$newfile = str_replace('./', '', $realpath);
			  }else{
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			  }
			}elseif (file_put_contents($realpath, base64_decode(str_replace(' ', '+', $img)))){
				$newfile = str_replace('./', '', $realpath);
			}else{
				$result	= array(
								'code'	=>	'0',
								'msg'	=>	'储存图片出错.',
								'data'	=>	array(),
							);
				break;
			}
									
			$rows	= [
						'orders_id'	=> $data['id'],
						'type'		=> 1,
						'file'		=> $webpath,
						'created_at'=> date('Y-m-d H:i:s'),
			];
			if($id=DB::table('ordersimage')->insertGetId($rows)){
					$rows['id']	=	$id;	
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'上传报价单图片成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'上传报价单图片失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryImgListAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}						
			$rows	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$data['id'])->get();
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'订单配件列表.',
						'data'	=>	$rows,
			);			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryImgDeleteAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$oimg_id	= intval($this->get('oimg_id', 0));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($oimg_id)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'询价单图片ID不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能删除询价单图片了.',
							'data'	=>	[],
					);
					break;
			}
			
			if(DB::table('ordersimage')->where('orders_id', '=', $data['id'])->where('id', '=', $oimg_id)->delete()!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'删除询价单图片成功.',
								'data'	=>	[],
					);
			}else{
					$result	= array(
								'code'	=>	'0',
								'msg'	=>	'删除询价单图片失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryAutopartsAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$autoparts	= trim($this->get('autoparts', ''));
			$autoparts	= explode(',', $autoparts);
			
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($autoparts)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'配件名称不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改车型配件了.',
							'data'	=>	[],
					);
					break;
			}
			$dataset	=	[];
			$myparts	=	[];
			$dbparts	=	empty($data['autoparts']) ? [] : json_decode($data['autoparts'], TRUE);			
			if( !empty($dbparts) ){/***清除已取消的配件***/
			foreach($dbparts as $k=>$v){
					$myparts[]	= $v['name'];					
					if( !in_array($v['name'], $autoparts) ){
						unset($dbparts[$k]);
					}
			}}
			foreach($autoparts as $k=>$v){
			if( !in_array($v, $myparts) ){
					array_push($dbparts, ['name'=>$v]);
			}}
			foreach($dbparts as $k=>$v){			
					array_push($dataset, $v);
			}
			$rows	= ['autoparts'	=> json_encode($dataset, JSON_UNESCAPED_UNICODE)];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件成功.',
								'data'	=>	$dataset,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryAutopartsListAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}			
			$dbparts	=	empty($data['autoparts']) ? [] : json_decode($data['autoparts'], TRUE);
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'订单配件列表.',
						'data'	=>	$dbparts,
			);			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	public function inquiryAutopartsDeleteAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			$autoparts	= trim($this->get('autoparts', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($autoparts)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'配件名称不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能删除车型配件了.',
							'data'	=>	[],
					);
					break;
			}
			$myparts	=	[];
			$dbparts	=	empty($data['autoparts']) ? [] : json_decode($data['autoparts'], TRUE);
			if( !empty($dbparts) ){
			foreach($dbparts as $k=>$v){
				if($v['name']!=$autoparts)
					array_push($myparts,	$v);
			}}
			
			$rows	= ['autoparts'	=> json_encode($myparts, JSON_UNESCAPED_UNICODE)];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	public function inquiryAutopartsQualityAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no',  ''));
			$autoparts	= trim($this->get('autoparts', ''));
			$vin_code	= trim($this->get('vin_code', ''));
			$car_number	= trim($this->get('car_number', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($autoparts)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'配件品质列表不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改车型配件品质了.',
							'data'	=>	[],
					);
					break;
			}						
			$rows	= ['autoparts'	=> $autoparts];			
			if( empty($data['vin_code']) ){
				$rows['vin_code']	=	$vin_code;
			}
			$rows['car_number']	=	$car_number;
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件品质成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件品质失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	public function inquiryAutopartsQualityBakAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no',  ''));
			$autoparts	= trim($this->get('autoparts', ''));
			$quality	= trim($this->get('quality',   ''));
			$num		= trim($this->get('num',   	   '1'));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($autoparts)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'配件名称不能空.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($quality)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'配件品质不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布，不能修改车型配件品质了.',
							'data'	=>	[],
					);
					break;
			}
			$dbparts	=	empty($data['autoparts']) ? [] : json_decode($data['autoparts'], TRUE);
			if( !empty($dbparts) ){
			foreach($dbparts as $k=>$v){
				if($v['name']==$autoparts){
					$dbparts[$k]['quality']	= $quality;
					$dbparts[$k]['num']		= $num;
				}
			}}
			
			$rows	= ['autoparts'	=> json_encode($dbparts, JSON_UNESCAPED_UNICODE)];			
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件品质成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'更新订单配件品质失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	public function inquirySubmitAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();		
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if(empty($data['autoparts'])&&(DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$data['id'])->count()<1)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单尚未选择配件及上传配件清单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'此订单已发布过了.',
							'data'	=>	[],
					);
					break;
			}
						
			$rows	= ['status'	=> 100];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'发布询价单成功.',
								'data'	=>	$rows,
					);
										
					$recevier=DB::table('members')->join('company','members.company_id','=','company.id')
												  ->join('brand2insure','brand2insure.provider_id','=','company.id')
												  ->where('brand2insure.carfactory_id','=',$data['carfactory_id'])
												  ->where('company.type','=','2')
												  ->where('company.authstatus','=','2')
												  ->where('members.status','=','1')
												  ->where('members.getui_cid','<>','')
												  ->select('members.getui_cid as cid');
					if($data['insurance_id']>0){
						$recevier=$recevier->where('brand2insure.insure_id','=',$data['insurance_id']);
					}
					$recevier=$recevier->get();
					if(!empty($recevier)&&is_array($recevier)){
					Yaf_Loader::import(APP_PATH . '/library/Getui/Getui.php');
					foreach($recevier as $k=>$v){
						Getui::send($v['cid'], '收到新的询价单', '有新的询价单等待报价,请注意查收');
					}}
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'发布询价单失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	public function inquiryCancelAction() {
		do{
			$this->authCheck();
			$order_no	= trim($this->get('order_no', ''));
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单号不能空.',
							'data'	=>	[],
					);
					break;
			}
			$data	= DB::table('orders')->where('company_id','=',self::$userinfo['company_id'])
										 ->where('order_no','=',$order_no)
										 ->first();		
			if(empty($data)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'未过滤到对应的订单.',
							'data'	=>	[],
					);
					break;
			}
			if($data['status']>300){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单已支付过，无法取消.',
							'data'	=>	[],
					);
					break;
			}
						
			$rows	= ['status'	=> 300];
			if(DB::table('orders')->where('id', '=', $data['id'])->update($rows)!==FALSE){
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'取消询价单成功.',
								'data'	=>	$rows,
					);
			}else{
					$result	= array(
								'code'	=>	'1',
								'msg'	=>	'取消询价单失败,请重试.',
								'data'	=>	[],
					);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	/**
	  * 修理厂发布新收货地址
	  *
	  */
	public function newshippingaddrAction(){
		do{
			$this->authCheck();			
			$name  		=  trim($this->get('name', ''));
			$phone    	=  trim($this->get('phone', ''));			
			$city    	=  trim($this->get('city', ''));
			$address    =  trim($this->get('address', ''));
			$postcode   =  trim($this->get('postcode', ''));
			
			$inputs	= array(
                ['name'=>'name', 	'value'=>$name,	 'fun'=>'isUsername','msg'=>'姓名格式有误'],                
                ['name'=>'phone', 	'value'=>$phone, 'fun'=>'isPhone',   'msg'=>'电话号码格式有误'],				
            );
            $result		= Validate::check($inputs);
            if(	!empty($result) ){
                $result	= array(
                    'code'	=>	'0',
                    'msg'	=>	'输入参数有误.',
                    'data'	=>	$result,
                );
                break;
            }
			$rows	=	array(
				'company_id'	=>	self::$userinfo['company_id'],
				'name'			=>	$name,
				'phone'			=>	$phone,
				'city'			=>	$city,
				'address'		=>	$address,
				'postcode'		=>	$postcode,
				'created_at'	=>	date('Y-m-d H:i:s'),
			);
			
			if($addressId=DB::table('shippingaddr')->insertGetId($rows)){			
				$rows['id'] = $addressId;
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'提交新收货地址成功.',
						'data'	=>	$rows,
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'提交新收货地址失败，请重试.',
						'data'	=>	$rows,
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 修改收货地址
	  *
	  */
	public function editshippingaddrAction(){
		do{
			$this->authCheck();			
			$id  		=  trim($this->get('shippingaddr_id',   0));
			$name  		=  trim($this->get('name', ''));
			$phone    	=  trim($this->get('phone', ''));			
			$city    	=  trim($this->get('city', ''));
			$address    =  trim($this->get('address', ''));
			$postcode   =  trim($this->get('postcode', ''));
			
			$inputs	= array(
				['name'=>'id', 		'value'=>$id,	 'fun'=>'isInteger', 'conditions'=>'gt:0', 'msg'=>'ID格式有误'],
                ['name'=>'name', 	'value'=>$name,	 'fun'=>'isUsername','msg'=>'姓名格式有误'],                
                ['name'=>'phone', 	'value'=>$phone, 'fun'=>'isPhone',   'msg'=>'电话号码格式有误'],
            );
            $result		= Validate::check($inputs);
            if(	!empty($result) ){
                $result	= array(
                    'code'	=>	'0',
                    'msg'	=>	'输入参数有误.',
                    'data'	=>	$result,
                );
                break;
            }
			$rows	=	array(
				'name'			=>	$name,
				'phone'			=>	$phone,
				'city'			=>	$city,
				'address'		=>	$address,
				'postcode'		=>	$postcode,
				'created_at'	=>	date('Y-m-d H:i:s'),
			);
			
			if(DB::table('shippingaddr')->where('id','=',$id)->update($rows)){			
				$rows['id'] = $id;
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'更新收货地址成功.',
						'data'	=>	$rows,
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'更新收货地址失败，请重试.',
						'data'	=>	$rows,
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 设置默认收货地址
	  *
	  */
	public function setdefaultaddrAction(){
		do{
			$this->authCheck();			
			$id  		=  trim($this->get('shippingaddr_id',   0));			
			$inputs	= array(
				['name'=>'id', 		'value'=>$id,	 'fun'=>'isInteger', 'conditions'=>'gt:0', 'msg'=>'ID格式有误'],
            );
            $result		= Validate::check($inputs);
            if(	!empty($result) ){
                $result	= array(
                    'code'	=>	'0',
                    'msg'	=>	'输入参数有误.',
                    'data'	=>	$result,
                );
                break;
            }	
			DB::table('shippingaddr')->where('id','<>',$id)->where('company_id','=',self::$userinfo['company_id'])->update(['flag'=>0]);			
			if(DB::table('shippingaddr')->where('id','=',$id)->update(['flag'=>1])!==FALSE){
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'更新收货地址成功.',
						'data'	=>	DB::table('shippingaddr')->find($id),
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'更新收货地址失败，请重试.',
						'data'	=>	[],
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 删除收货地址
	  *
	  */
	public function deleteshippingaddrAction(){
		do{
			$this->authCheck();			
			$id  		=  trim($this->get('shippingaddr_id',   0));
			
			$inputs	= array(
				['name'=>'id', 		'value'=>$id,	 'fun'=>'isInteger', 'conditions'=>'gt:0', 'msg'=>'ID格式有误'],
            );
            $result		= Validate::check($inputs);
            if(	!empty($result) ){
                $result	= array(
                    'code'	=>	'0',
                    'msg'	=>	'输入参数有误.',
                    'data'	=>	$result,
                );
                break;
            }			
			if(DB::table('shippingaddr')->where('company_id','=',self::$userinfo['company_id'])->where('id','=',$id)->delete()){
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'删除收货地址成功.',
						'data'	=>	[],
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'更新收货地址失败，请重试.',
						'data'	=>	[],
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 收货地址列表
	  *
	  */
	public function shippingaddrAction(){
		do{
			$this->authCheck();
			$rows = DB::table('shippingaddr')->where('company_id','=',self::$userinfo['company_id'])->get();			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'收货地址列表.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 为订单选择收货地址
	  *
	  */
	public function setshippingaddrAction(){
		do{
			$this->authCheck();
			$order_no  	 	=  trim($this->get('order_no', ''));
			$shippingaddr_id=  intval($this->get('shippingaddr_id', 0));
			if($order_no==0){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单编号不能为空.',
						'data'	=>	[],
					);
					break;					
			}
			if($shippingaddr_id==0){
					$result	= array(
						'code'	=>	'0',
						'msg'	=>	'收货地址ID不能为空.',
						'data'	=>	[],
					);
					break;					
			}
			
			if( DB::table('orders')->where('order_no','=',$order_no)->update(['shippingaddr_id'=>$shippingaddr_id])!==FALSE ){
				$result	= array(
							'code'	=>	'1',
							'msg'	=>	'设置收货地址成功.',
							'data'	=>	[
											'order_no'			=>$order_no,
											'shippingaddr_id'	=>$shippingaddr_id,
										],
				);
			}else{
				$result	= array(
							'code'	=>	'0',
							'msg'	=>	'设置收货地址失败.',
							'data'	=>	'',
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	/**
	  * 提交订单，并关联收货地址
	  *
	  */
	public function submitOrderAction(){
		do{
			$this->authCheck();
			$order_no  	 	=  trim($this->get('order_no', ''));
			$shippingaddr_id=  intval($this->get('shippingaddr_id', 0));
			$inputs	= array(
				['name'=>'order_no', 'value'=>$order_no, 'role'=>'required', 'msg'=>'订单编号不能为空'],
				['name'=>'shippingaddr_id', 'value'=>$shippingaddr_id, 'role'=>'required|gt:0', 'func'=>'isInteger', 'msg'=>'收货地址不能为空'],
			);
			$result	= Validate::check($inputs);
			if(!empty($result)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'输入参数有误.',
						'data'	=>	$result,
				);
				break;
			}			
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->where('members_id','=',self::$user_id)->first();
			if(!$rows){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'未匹配到对应的订单.',
						'data'	=>	[],
				);
				break;
			}
			$inputs	= array(
				['name'=>'status',  'value'=>$rows['status'], 'role'=>'in:100,200', 'func'=>'isInteger','msg'=>'订单状态不符，无法提交订单'],
			);
			$result	= Validate::check($inputs);
			if(!empty($result)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'数据参数有误.',
						'data'	=>	$result,
				);
				break;
			}
			
			$data	= array(
				'status'			=> 200,
				'shippingaddr_id'	=> $shippingaddr_id,
				'fee'				=> DB::table('quoteselect')->where('order_no','=',$order_no)->sum('fee'),
			);
			if( DB::table('orders')->where('order_no','=',$order_no)->update($data)!==FALSE ){
				$rows['autoparts'] 	= empty($rows['autoparts']) ? '' : json_decode($rows['autoparts'], TRUE);
				$rows['autoparts_img']= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$orders_id)->get();

				$quotes = DB::table('quoteselect')->where('order_no','=',$order_no)->get();
				if(!empty($quotes)&&is_array($quotes)){
				Yaf_Loader::import(APP_PATH . '/library/Getui/Getui.php');
				foreach($quotes as $k=>&$v){
					$v['providercompany']= DB::table('company')->where('id','=',$v['provider_id'])->select('company','logo','address','tel','description')->first();
					$v['quoteselect'] 	= empty($v['quoteselect']) ? '' : json_decode($v['quoteselect'], TRUE);				
					
					$recevier=DB::table('quote')->join('members','quote.members_id','=','members.id')
											->where('quote.id','=',$v['quote_id'])
											->where('members.getui_cid','<>','')
											->select('members.getui_cid as cid')
											->first();
					Getui::send($recevier['cid'], '报价单已被选择', '您的报价单已被选择.');
				}}
				$rows['quote']		= $quotes;
				$rows['shippingaddr']=DB::table('shippingaddr')->find($shippingaddr_id);
				$rows['statusname']	=	'待支付';				
				$data['orderinfo']	= $rows;
				$result	= array(
							'code'	=>	'1',
							'msg'	=>	'提交订单，并设置收货地址成功.',
							'data'	=>	$data,
				);
									
				
				
			}else{
				$result	= array(
							'code'	=>	'0',
							'msg'	=>	'提交订单失败.',
							'data'	=>	'',
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 待报价(配件商使用)
	  *
	  * 返回所有询价单	  
	  */
	public function pendingquoteAction(){
		do{
			$this->authCheck();			
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;			
			#DB::enableQueryLog();
			#$rows	= DB::table('orders')->join('brand2insure', function($join) {
			#				$join->on('orders.insurance_id', '=', 'brand2insure.insure_id')->on('orders.carfactory_id', '=', 'brand2insure.carfactory_id');
			#		})
			$rows	= DB::table('orders')->join('brand2insure', 'orders.carfactory_id', '=', 'brand2insure.carfactory_id')
										 ->whereRaw('(go_brand2insure.insure_id=go_orders.insurance_id OR go_orders.insurance_id=0)')
										 ->where('orders.status','=',100)
										 ->where('brand2insure.provider_id','=',self::$userinfo['company_id'])
										 ->select('orders.id','orders.order_no','orders.company_id','orders.carmodel','orders.caricon','orders.autoparts','orders.created_at')
										 ->orderBy('orders.id', 'DESC')										 
										 ->offset($startpagenum)
										 ->limit($pagesize);
			$is_img	  = intval($this->get('is_img', 2));
			if($is_img<2){
				if($is_img==0){
					$rows	= $rows->where('autoparts','<>','');
				}else{
					$rows	= $rows->where('autoparts','=','');
				}	
			}
			$rows	= $rows->get();
			#$this->sqllog();
			
			
			$dataset= [];
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){
				if( DB::table('quote')->where('orders_id','=',$v['id'])->where('company_id','=',self::$userinfo['company_id'])->count()>0 ){
					unset($rows[$k]);					
				}else{
					$rows[$k]['companyname']	= DB::table('company')->where('id','=',$v['company_id'])->first()['company'];
					$rows[$k]['quotenum']		= DB::table('quote')->where('orders_id','=',$v['id'])->count();					
					$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);					
					if($is_img!=0){
						$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
					}
					array_push($dataset, $rows[$k]);
				}				
			}}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'待报价订单列表.',
						'data'	=>	$dataset,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 报价(配件商使用)
	  *
	  * 返回所有询价单	  
	  */
	public function quoteAction(){
		do{
			$this->authCheck();			
			$orders_id  =  intval($this->get('orders_id', 0));
			$quote    	=  trim($this->get('quote', ''));			
			$remark    	=  trim($this->get('remark', ''));
			if($orders_id==0){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'询价单ID不能为空.',
						'data'	=>	[],
				);
				break;
			}
			if(empty($quote)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'报价单内容不能为空.',
						'data'	=>	[],
				);
				break;
			}
			$rows	=	array(
				'members_id'	=>	self::$userinfo['id'],
				'company_id'	=>	self::$userinfo['company_id'],
				'orders_id'		=>	$orders_id,
				'quote'			=>	$quote,			
				'remark'		=>	$remark,
				'created_at'	=>	date('Y-m-d H:i:s'),
			);
			
			if( DB::table('quote')->where('company_id','=',self::$userinfo['company_id'])->where('orders_id','=',$orders_id)->count()>0 ){
				if(DB::table('quote')->where('company_id','=',self::$userinfo['company_id'])->where('orders_id','=',$orders_id)->update($rows)){			
					$result	= array(
							'code'	=>	'1',
							'msg'	=>	'提交报价成功.',
							'data'	=>	$rows,
					);
					
					$receiver=DB::table('members')->join('orders','members.id','=','orders.members_id')
												  ->where('orders.id','=',$orders_id)
												  ->where('members.getui_cid','<>','')
												  ->select('members.getui_cid as cid')
												  ->get();
					if(!empty($receiver)&&is_array($recevier)){
					Yaf_Loader::import(APP_PATH . '/library/Getui/Getui.php');
					foreach($receiver as $k=>$v){
						Getui::send($v['cid'], '报价单更新', '有报价单被更新,请注意查收');
					}}
					break;
				}				
			}else{
				if(DB::table('quote')->insert($rows)){			
					$result	= array(
							'code'	=>	'1',
							'msg'	=>	'提交报价成功.',
							'data'	=>	$rows,
					);					
					$receiver=DB::table('members')->join('orders','members.id','=','orders.members_id')
												  ->where('orders.id','=',$orders_id)
												  ->where('members.getui_cid','<>','')
												  ->select('members.getui_cid as cid')
												  ->get();
					if(!empty($receiver)&&is_array($recevier)){
					Yaf_Loader::import(APP_PATH . '/library/Getui/Getui.php');
					foreach($receiver as $k=>$v){
						Getui::send($v['cid'], '收到新的报价', '有新的报价，请等待查收');
					}}
					break;
				}
			}
			
			$result	= array(
					'code'	=>	'0',
					'msg'	=>	'提交报价失败，请重试.',
					'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}

	/**
	  * 已报价(配件商使用)
	  *
	  * 返回所有询价单	  
	  */
	public function myquoteAction(){
		do{
			$this->authCheck();		
			DB::enableQueryLog();			
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;									
			$rows	= DB::table('orders')->join('quote','orders.id','=','quote.orders_id')
										 ->where('quote.company_id','=',self::$userinfo['company_id'])
										 ->select('orders.id','orders.order_no','orders.company_id','orders.carmodel','orders.caricon','orders.autoparts','orders.created_at','quote.members_id','quote.quote','quote.remark')
										 ->orderBy('created_at', 'DESC')										 
										 ->offset($startpagenum)
										 ->limit($pagesize);
			
			$is_img	  = intval($this->get('is_img', 2));
			if($is_img<2){
				if($is_img==0){
					$rows	= $rows->where('autoparts','<>','');
				}else{
					$rows	= $rows->where('autoparts','=','');
				}	
			}
			$rows	=	$rows->get();
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){
					$rows[$k]['companyname']	= DB::table('company')->where('id','=',$v['company_id'])->first()['company'];
					$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);					
					if(empty($rows[$k]['autoparts'])){
						$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
					}
					$rows[$k]['quote']			= empty($rows[$k]['quote']) ? '' : json_decode($rows[$k]['quote'], TRUE);
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'已报价订单列表.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	/**
	  * 报价详情(配件商使用)
	  *
	  * 返回所有询价单	  
	  */
	public function quoteDetailAction(){
		do{
			$this->authCheck();						
			$order_no = trim($this->get('order_no', ''));			
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->first();			
			if(empty($rows)||!is_array($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'没找着订单.',
						'data'	=>	[],
				);
				break;
			}												
			$rows	= DB::table('quote')->join('orders','quote.orders_id','=','orders.id')
										 ->where('quote.company_id','=',self::$userinfo['company_id'])
										 ->where('orders.order_no','=',$order_no)
										 ->select('orders.id','orders.order_no','orders.company_id','orders.carmodel','orders.caricon','orders.autoparts','orders.created_at','quote.company_id as provider_id','quote.id as quote_id','quote.quote')
										 ->first();						
			
			$rows['repaircompany']	= DB::table('company')->where('id','=',$rows['company_id'])->select('company','logo','address','tel','description')->first();			
			$rows['quote']			= empty($rows['quote']) ? '' : json_decode($rows['quote'], TRUE);
			$rows['autoparts'] 		= empty($rows['autoparts']) ? '' : json_decode($rows['autoparts'], TRUE);
			if(empty($rows['autoparts'])){
				$rows['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$rows['id'])->get();
			}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'报价详情.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	
	/**
	  * 交易流水
	  *
	  * 返回所有询价单	  
	  */
	public function flowingAction(){
		do{
			$this->authCheck();									
			$rows	= DB::table('orderslog')->orderBy('created_at', 'desc')->get();			
			if(empty($rows)||!is_array($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'没找着交易流水.',
						'data'	=>	[],
				);
				break;
			}
			foreach($rows as $k=>&$v){
				$v['company'] = DB::table('quoteselect')->join('company','quoteselect.provider_id','=','company.id')
										->where('quoteselect.order_no','=',$v['order_no'])
										->select('quoteselect.fee','quoteselect.delivery_com','quoteselect.delivery_no','company.company','company.logo','company.address','company.tel')										
										->get();
			}			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'交易流水.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	
	
	
	
	
	/**
	  * 待选价订单列表(保险公司使用)
	  *
	  * 返回所有待选订单
	  */
	public function pendingbidAction(){
		do{
			$this->authCheck();			
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;									
			$rows	= DB::table('orders')->where('status','=',100)
										 ->where('quote_id','=',0)		
										 //->where('insurance_id','=',self::$userinfo['company_id'])
										 ->orderBy('created_at', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize);			
			$dataset= [];
			$is_img	  = intval($this->get('is_img', 0));
			if($is_img==0){
				$rows	= $rows->where('autoparts','<>','');
			}else{
				$rows	= $rows->where('autoparts','=','');
			}			
			$rows	= $rows->get();			
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){
				if( DB::table('quote')->where('orders_id','=',$v['id'])->count()<1 ){
					unset($rows[$k]);
				}else{	
					$rows[$k]['repaircompany']	= DB::table('company')->where('id','=',$v['company_id'])->select('company','logo','address','tel','description')->first();
					$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);
					if(empty($rows[$k]['autoparts'])){
						$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
					}
					array_push($dataset, $rows[$k]);
				}
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'待选价报价单列表.',
						'data'	=>	$dataset,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 查看订单的报价列表(保险公司使用)
	  *
	  * 返回所有待选报价单
	  */
	public function bidviewAction(){
		do{
			$this->authCheck();			
			$orders_id      =  intval($this->get('orders_id', 0));
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			if($orders_id==0){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单ID不能为空.',
						'data'	=>	[],
				);
				break;
			}			
			$rows	= DB::table('orders')->where('id','=',$orders_id)->first();
			if(empty($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'未找到对应订单.',
						'data'	=>	[],
				);
				break;
			}
			$rows['autoparts'] 		= empty($rows['autoparts']) ? '' : json_decode($rows['autoparts'], TRUE);			
			$rows['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$orders_id)->get();
			$quote	= DB::table('quote')->where('orders_id','=',$rows['id'])
										->orderBy('created_at', 'ASC')
										->offset($startpagenum)
										->limit($pagesize)
										->get();
			if(!empty($quote)&&is_array($quote)){
			foreach($quote as $k=>$v){
				$quote[$k]['providercompany']= DB::table('company')->where('id','=',$v['company_id'])->select('id as provider_id','company','logo','address','tel','description')->first();				
				$quote[$k]['quote'] 		= empty($quote[$k]['quote']) ? '' : json_decode($quote[$k]['quote'], TRUE);
				$quote[$k]['flag']			= ($quote[$k]['id']==$rows['quote_id']) ? 1 : 0;
			}}
			$rows['quote']	=	$quote;
			/***为每个配件列出所有报价***/
			if(!empty($rows['autoparts'])&&is_array($rows['autoparts'])){
			foreach($rows['autoparts'] as $k=>&$v){
					if(!empty($quote)&&is_array($quote)){
					foreach($quote as $k1=>&$v1){							
							if(!empty($v1['quote'])&&is_array($v1['quote'])){
							foreach($v1['quote'] as $k2=>&$v2){
									if($v['name']==$v2['name']){
											if(!empty($v2['quality'])&&is_array($v2['quality'])){
											foreach($v2['quality'] as $k3=>&$v3){
												$quoteselect = DB::table('quoteselect')->where('order_no','=',$rows['order_no'])
																		->where('quote_id','=',$v1['id'])
																		->first();
												if(!empty($quoteselect)){
													$quoteselect = json_decode($quoteselect['quoteselect'], TRUE);
													if(!empty($quoteselect)&&is_array($quoteselect)){
													foreach($quoteselect as $k4=>$v4){
														if($v4['name']==$v2['name']){
															if(!empty($v4['quality'])&&is_array($v4['quality'])){
															foreach($v4['quality'] as $k5=>$v5){
																if($v5['caption']==$v3['caption']){
																	$v3['flag'] = $v5['flag'];
																}
															}}else{
																$v3['flag']	= 0;
															}
														}
													}}else{
														$v3['flag']	= 0;
													}
												}else{
													$v3['flag']	= 0;	
												}
											}}
											$v['quote'][]	= array(
													'quote_id'			=> $v1['id'],
													'providercompany'   => $v1['providercompany'],													
													'quote'				=> $v2,
											);
									}
							}}
					}}
			}}
			
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'待选价订价列表.',
						'data'	=>	$rows,
			);
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 报价选择(保险公司使用)
	  * 
	  */
	public function bidselectAction(){
		do{
			$this->authCheck();			
			$order_no	=  $this->get('order_no', '');
			if(empty($order_no)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单编号不能为空.',
						'data'	=>	[],
				);
				break;
			}
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->first();
			if(empty($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'未找到对应订单.',
						'data'	=>	[],
				);
				break;
			}				   
			if($rows['status']>=200){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单状态已改变，无法更新选价.',
						'data'	=>	[],
				);
				break;
			}
			$quote	= trim($this->get('quote', ''));						
			if(!empty($quote)){
				$totalfee=0.00;
				DB::table('quoteselect')->where('order_no','=',$order_no)->delete();				
				$sequote= json_decode($quote, TRUE);												
				foreach($sequote as $k=>$v){
					$fee	= 0.00;
					if(!empty($v['quality'])&&is_array($v['quality'])){
					$num	= empty($v['num']) ? 1 : intval($v['num']);
					foreach($v['quality'] as $k1=>$v1){
						if($v1['flag']==1)	$fee+=(floatval($v1['price'])*$num);
					}}
					$totalfee += $fee;
					$qst = DB::table('quoteselect')->where('order_no','=',$order_no)->where('quote_id','=',$v['quote_id'])->first();
					if($qst){
						$fee  = $fee + $qst['fee'];						
						$extquotect	 = json_decode($qst['quoteselect'], TRUE);						
						array_push($extquotect, $v);
						$data = array(
							'fee'	 	 => round($fee, 2),
							'quoteselect'=> json_encode($extquotect),
						);
						DB::table('quoteselect')->where('order_no','=',$order_no)->where('quote_id','=',$v['quote_id'])->update($data);
					}else{
						$quotedb 	= DB::table('quote')->find($v['quote_id']);
						$extquotect = [];
						array_push($extquotect, $v);
						$data = array(
							'order_no'	=> $order_no,
							'quote_id'	=> $v['quote_id'],
							'provider_id'=>$quotedb['company_id'],
							'fee'	 	=> round($fee, 2),
							'quoteselect'=>json_encode($extquotect),
							'created_at'=> date('Y-m-d H:i:s'),
						);						
						DB::table('quoteselect')->insert($data);
					}
				}

				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'选择报价成功.',
						'data'	=>	['totalfee'=>round($totalfee,2)],
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'提交报价失败，请重试.',
						'data'	=>	[],
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}


	/**
	  * 报价选择(保险公司使用)
	  * 
	  */
	public function bidselectbakAction(){
		do{
			$this->authCheck();			
			$quote_id	=  intval($this->get('quote_id', 0));
			if($quote_id==0){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'报价单ID不能为空.',
						'data'	=>	[],
				);
				break;
			}
			$rows	= DB::table('orders')->join('quote','orders.id','=','quote.orders_id')
										 ->where('quote.id','=',$quote_id)
										 ->select('orders.id as orders_id','orders.status','quote.id as quote_id','quote.company_id')
										 ->first();
			if(empty($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'未找到对应订单.',
						'data'	=>	[],
				);
				break;
			}				   
			if($rows['status']>=200){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单状态已改变，无法选价.',
						'data'	=>	[],
				);
				break;
			}			
			$data	= array(
				'quote_id'		=>	$rows['quote_id'],
				'service_id'	=>	$rows['company_id'],
			);
			if(DB::table('orders')->where('id','=',$rows['orders_id'])->update($data)!==FALSE){
				$quote	= trim($this->get('quote', ''));
				$fee	= 0.00;
				if(!empty($quote)){
				$sequote= json_decode($quote, TRUE);				
				foreach($sequote as $k=>$v){
					if(!empty($v['quality'])&&is_array($v['quality'])){
					foreach($v['quality'] as $k1=>$v1){
						if($v1['flag']==1)	$fee+=floatval($v1['price']);
					}}
				}}
				$data['fee'] = $fee;
				DB::table('quote')->where('id','=',$quote_id)->update(['quote'=>$quote, 'fee'=>$fee]);
				
				$otherQuote = DB::table('quote')->where('orders_id','=',$rows['orders_id'])->where('id','<>',$quote_id)->get();								
				if(!empty($otherQuote)&&is_array($otherQuote)){
				foreach($otherQuote as $k=>$v){
					$quote = json_decode($v['quote'], TRUE);					
					foreach($quote as $k1=>$v1){
						if(!empty($v1['quality'])&&is_array($v1['quality'])){
						foreach($v1['quality'] as $k2=>$v2){
							$quote[$k1]['quality'][$k2]['flag']	= 0;
						}}
					}
					$quote = json_encode($quote);
					DB::table('quote')->where('id','=',$v['id'])->update(['quote'=>$quote, 'fee'=>0.00]);
				}}				
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'选择报价成功.',
						'data'	=>	$data,
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'提交报价失败，请重试.',
						'data'	=>	$data,
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 确认选价,提交订单(保险公司使用)
	  * 
	  */
	public function confirmbidAction(){
		do{
			$this->authCheck();	
			$orders_id      =  intval($this->get('orders_id', 0));			
			if($orders_id==0){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单ID不能为空.',
						'data'	=>	[],
				);
				break;
			}			
			$rows	= DB::table('orders')->where('id','=',$orders_id)->first();
			if(empty($rows)){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'未找到对应订单.',
						'data'	=>	[],
				);
				break;
			}
			if(empty($rows['quote_id'])){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'请先选择报价.',
						'data'	=>	[],
				);
				break;
			}				   
			if($rows['status']>200){
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'订单状态已改变，无法确认选价.',
						'data'	=>	[],
				);
				break;
			}
			$fee	= DB::table('quote')->find($rows['quote_id'])['fee'];
			if(DB::table('orders')->where('id','=',$orders_id)->update(['status'=>200, 'fee'=>$fee])!==FALSE){						
				$result	= array(
						'code'	=>	'1',
						'msg'	=>	'确认选价成功.',
						'data'	=>	[],
				);
			}else{
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'确认选价失败，请重试.',
						'data'	=>	[],
				);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}


	/**
	  * 订单列表(保险公司使用)
	  *
	  */
	public function bidlistAction(){
		do{
			$this->authCheck();			
			$pagenum        =  intval($this->get('pagenum', 1));
			$pagesize    	=  intval($this->get('pagesize', 10));
			$startpagenum	=  ($pagenum-1) * $pagesize;
			
			$rows	= DB::table('orders')->where('insurance_id','=',self::$userinfo['company_id'])
										 ->where('status','=', '100')
										 ->orderBy('orders.created_at', 'DESC')
										 ->offset($startpagenum)
										 ->limit($pagesize);
			$is_img	  = intval($this->get('is_img', 2));
			if($is_img<2){
				if($is_img==0){
					$rows	= $rows->where('autoparts','<>','');
				}else{
					$rows	= $rows->where('autoparts','=','');
				}			
			}
			$rows	= $rows->get();			
			
			if(!empty($rows)&&is_array($rows)){
			foreach($rows as $k=>$v){					
					$rows[$k]['repaircompany']	= DB::table('company')->where('id','=',$v['company_id'])->select('company','logo','address','tel','description')->first();
					$rows[$k]['bid']			= DB::table('quote')->find($v['quote_id']);
					$rows[$k]['bid']['quote']	= empty($rows[$k]['bid']['quote']) ? '' : json_decode($rows[$k]['bid']['quote'], TRUE);					
					$rows[$k]['autoparts'] 		= empty($rows[$k]['autoparts']) ? '' : json_decode($rows[$k]['autoparts'], TRUE);					
					if(empty($rows[$k]['autoparts'])){
						$rows[$k]['autoparts_img']	= DB::table('ordersimage')->where('type','=',1)->where('orders_id','=',$v['id'])->get();
					}					
			}}
			$result	= array(
						'code'	=>	'1',
						'msg'	=>	'已选价订单列表.',
						'data'	=>	$rows,
			);			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 配件商发货(配件商使用)
	  * 
	  */
	public function deliveryAction(){
		do{
			$this->authCheck();
			$order_no       = $this->get('order_no', '');
			$delivery_com	= $this->get('delivery_com', '快递公司');
			$delivery_no	= $this->get('delivery_no',  '');
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'参数有误，请传入订单号.',
							'data'	=>	[],
					);
					break;
			}
			$rows	= DB::table('orders')->where('order_no','=',$order_no)->first();
			/***1.验证订单状态及报价单与订单里的金额是否对等***/
			if($rows['status']!=400){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单状态有误，请确认已支付成功.',
							'data'	=>	[],
					);
					break;
			}			
			try{
				DB::beginTransaction();
				/***1.更新order表***/				
				DB::table('quoteselect')->where('order_no','=',$order_no)
										->where('provider_id','=',self::$userinfo['company_id'])	
										->update(['delivery_status'=>1,'delivery_com'=>$delivery_com,'delivery_no'=>$delivery_no,'updated_at'=>date('Y-m-d H:i:s')]);
				DB::commit();
				$result	= array(
							'code'	=>	'1',
							'msg'	=>	'确认发货状态更新成功.',
							'data'	=>	[],
				);
			}catch(Exception $e){
				DB::rollBack();
				$result	= array(
							'code'	=>	'0',
							'msg'	=>	'确认发货状态更新失败.',
							'data'	=>	[],
				);
			}			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	  * 确认收货(修理厂使用)
	  * 
	  */
	public function confirmreceiveAction(){
		do{
			$this->authCheck();
			$order_no       =	$this->get('order_no', 	  '');
			$provider_id	=	$this->get('provider_id', '');
			if(empty($order_no)){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'参数有误，请传入订单号.',
							'data'	=>	[],
					);
					break;
			}
			$rows	= DB::table('quoteselect')->where('order_no',	'=', $order_no)
											  ->where('provider_id','=', $provider_id)
											  ->first();
			/***1.验证订单状态及报价单与订单里的金额是否对等***/
			if($rows['delivery_status']==0){
					$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单发货状态有误，请确认已收到货物.',
							'data'	=>	[],
					);
					break;
			}			
			try{
				DB::beginTransaction();
				/***1.更新quoteselect表***/				
				DB::table('quoteselect')->where('order_no',	'=', $order_no)
										->where('provider_id','=', $provider_id)
										->update(['delivery_status'=>2]);				
				/***2.给报价者解冻资金***/
				$quotes = DB::table('quoteselect')->where('order_no','=',$order_no)->where('provider_id','=', $provider_id)->get();
				if(!empty($quote)&&is_array($quotes)){
				foreach($quotes as $k=>&$v){					
					DB::table('company')->where('id', '=', $v['provider_id'])->decrement('frozen',  $v['fee']);	
					DB::table('company')->where('id', '=', $v['provider_id'])->increment('account', $v['fee']);
				}}				
				DB::commit();
				$result	= array(
							'code'	=>	'1',
							'msg'	=>	'订单状态更新成功.',
							'data'	=>	[],
				);
			}catch(Exception $e){
				DB::rollBack();
				$result	= array(
							'code'	=>	'0',
							'msg'	=>	'订单状态更新失败.',
							'data'	=>	[],
				);
			}			
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}






	/**
	 *接口名称	公司认证检测
	 *接口地址	内部调用
	 *接口说明	检测公司是否已认证
	 **/
	private function authCheck(){
		if( self::$userinfo['company_id']<=0 || self::$userinfo['company']['authstatus']!=2 ){			
				$result	= array(
						'code'	=>	'0',
						'msg'	=>	'公司认证通过后，才可以执行此操作.',
						'data'	=>	array(),
				);
				json($result, self::$datatype, self::$callback);
		}
	}
	/**
	 *接口名称	我的消息
	 *接口地址	http://api.com/user/message/
	 *接口说明	列出我的消息
	 *参数 @param
	 * @status   	整数  0:未读  1：已读
	 * @pagenum		页码 
	 * @pagesize	每页数量
	 * @token		登陆标记
	 *返回 @return
	 * @list		消息列表
	 *
	 **/
	public function messageAction(){		
		$pagenum        =  intval($this->get('pagenum', 1));
        $pagesize    	=  intval($this->get('pagesize', 10));
		
		$rows 		= (new Table('message'))->findAll("receive_user='".self::$user_id."'", '', array($pagenum-1, $pagesize), 'id,name,status,type,content,addtime');
		$counter	= (new Table('message'))->findCount("receive_user='".self::$user_id."'");
		
		$result		= array(
						'code'	=>	'1',
						'msg'	=>	'数据读取成功',
						'data'	=>	array(
										
										'total'		=>	$counter,
										'pagenum'	=>	$pagenum,
										'pagesize'	=>	$pagesize,
										'totalpage'	=>	ceil($counter/$pagesize),
										'list'		=>	(array)$rows,
									),
					);
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 *接口名称	未读消息数
	 *接口地址	http://api.com/user/newmessagenum/
	 *接口说明	列出我的消息
	 *参数 @param
	 * @token		登陆标记
	 *返回 @return
	 * @num			未读消息条数
	 *
	 **/
	public function newmessagenumAction(){	
	
		$counter	= (new Table('message'))->findCount("receive_user='".self::$user_id."' AND status=0");
		
		$result		= array(
						'code'	=>	'1',
						'msg'	=>	'未读消息条数',
						'data'	=>	array(										
										'num'	=>	$counter,
									),
					);
		json($result, self::$datatype, self::$callback);
	}
	
	
	/**
	 *接口名称	消息删除
	 *接口地址	http://api.com/user/messagedelete/
	 *接口说明	删除我的消息
	 *参数 @param
	 * @id   		消息ID
	 * @token		登陆标记
	 *返回 @return
	 * @list		消息列表
	 *
	 **/
	public function messagedeleteAction(){		
		do{	
			$id         =  intval($this->get('id',  0));
			$all        =  intval($this->get('all', 0));
			
			if($id==0 && $all==0){			
				$result	= array(
					'code'	=>	'0',
					'msg'	=>	'参数异常.',
					'data'	=>	array(),
				);
				break;				
			}
			if($id>0){
				$rows 		= (new Table('message'))->delete("receive_user='".self::$user_id."' AND id='{$id}'");
			}elseif($all==1){
				$rows 		= (new Table('message'))->delete("receive_user='".self::$user_id."'");
			}
			
			if($rows){
				$result		= array(
							'code'	=>	'1',
							'msg'	=>	'消息删除成功',
							'data'	=>	array(										
											'status'	=> 1,
										),
						);
			}else{
				$result		= array(
							'code'	=>	'1',
							'msg'	=>	'消息删除失败',
							'data'	=>	array(),
						);
			}
		}while(FALSE);
		
		json($result, self::$datatype, self::$callback);
	}
	
	/**
	 *接口名称	我的积分
	 *接口地址	http://api.com/user/points/
	 *接口说明	列出我的积分记录
	 *参数 @param
	 * @status   	整数  0:未读  1：已读
	 * @pagenum		页码 
	 * @pagesize	每页数量
	 * @token		登陆标记
	 *返回 @return
	 * @list		消息列表
	 *
	 **/
	public function pointsAction(){
		$pagenum        =  intval($this->get('pagenum', 1));
        $pagesize    	=  intval($this->get('pagesize', 10));
		
		$creditNum	= (new Table('credit'))->find("user_id='".self::$user_id."'");		
		if( empty($creditNum) ){
			$rows	= array( 'user_id'=>self::$user_id, 'value'=>0, 'op_user'=>0, 'addtime'=>time(), 'addip'=>getIp() );
			$_DBcredit->add($rows);
			$creditNum['value']= 0;
		}
		
		$creditLog	= (new Table('credit_log'))->findAll("user_id='".self::$user_id."'", '', array($pagenum-1, $pagesize), 'id,type_id,value,remark,addtime');
		$counter	= (new Table('credit_log'))->findCount("user_id='".self::$user_id."'");
		
		if(is_array($creditLog) && !empty($creditLog)){
			$creditType	=	new Table('credit_type');
			foreach($creditLog as $k=>$v){
					$type	=	$creditType->find($v['type_id']);
					$creditLog[$k]['type']	=	$type['name'];
			}
		}
		
		$result		= array(
						'code'	=>	'1',
						'msg'	=>	'数据读取成功',
						'data'	=>	array(
										'credit'	=>	$creditNum['value'],
										'total'		=>	$counter,
										'pagenum'	=>	$pagenum,
										'pagesize'	=>	$pagesize,
										'totalpage'	=>	ceil($counter/$pagesize),
										'list'		=>	(array)$creditLog,
									),
					);
		json($result, self::$datatype, self::$callback);
	}
		
	/**
	 *私有方法	验证登陆
	 *方法说明	验证token，返回用户ID
	 *参数 @param
	 * @token 	标记
	 *返回 @return	
	 * @user_id   	成功返回用户ID
	 * FALSE		失败返回FALSE
	 **/
	private function checklogin($token){
		$myCache 		= Cache::getInstance();
		if( !$myCache->exists($token) ){
			return FALSE;
		}else{
			$myCache->expire($token, $this->config->cache->redis->expire);
			return $myCache->get($token);
		}
	}	
}
