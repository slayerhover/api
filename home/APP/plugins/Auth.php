<?phpuse Illuminate\Database\Capsule\Manager as DB;
class AuthPlugin extends Yaf_Plugin_Abstract {
	public function routerShutdown(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {		
		/***检查控制器是否存在***/
		$config	=	Yaf_Registry::get('config');
		if( !file_exists( $config['application']['directory'].'/controllers/' . ucfirst($request->controller) . '.' . $config['application']['ext']) )
			$request->controller = 'Index';
		
	}
}
