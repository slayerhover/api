<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/u', function () use ($router) {
    return "ap";
    return $router->app->version();
});

$router->get('/userdb',function()use($router){
        $router = $router->app->version();
        $data = app('db')->select("SELECT * FROM t_user limit 10");
        $retData =[
            'data'=>$data,
            'router'=>$router,
        ];
        return $retData;
});
$router->post('/u/db', ['middleware'=>'Login','uses'=>"PostController@db2"]);
$router->post('/u/sync', ['uses'=>"DataController@sync"]);
$router->post('/u/myPage', ['uses'=>"PostController@myPage"]);

$router->post('/user/dbt',['uses'=>"PostController@dbt"]);
$router->post('/user/bene',['uses'=>"PostController@bene"]);
$router->post('/u/bene',['uses'=>"PostController@bene"]);



$router->get('/syncUserData',['uses'=>"DataController@syncUserData"]);
$router->any('/bks',['uses'=>"PostController@bks"]);
$router->any('/u/ip',['uses'=>"PostController@ip"]);


$router->group(['prefix'=>'u','middleware'=>'Login'], function () use ($router) {
    $router->any('/center',['uses'=>"PostController@center"]);
    $router->any('/userInfo',['middleware'=>'Login','uses'=>"PostController@user_info"]);
    $router->any('/userinfo',['middleware'=>'Login','uses'=>"PostController@user_info"]);
    $router->any('/mobileBind',['middleware'=>'Login','uses'=>"PostController@mobileBind"]);
    $router->any('/certify',['middleware'=>'Login','uses'=>"PostController@certify"]);
    $router->any('/useMoney',['middleware'=>'Login','uses'=>"PostController@useMoney"]);

    $router->any('/banks',['middleware'=>'Login','uses'=>"PostController@banks"]);
    $router->any('/test',['middleware'=>'Login','uses'=>"PostController@test"]);
    $router->any('/{banks:bank}',['middleware'=>'Login','uses'=>"PostController@banks"]);
    $router->any('/addBank',['middleware'=>'Login','uses'=>"PostController@addBank"]);
    $router->any('/bankInfo',['middleware'=>'Login','uses'=>"PostController@bankInfo"]);
    $router->any('/myBank',['middleware'=>'Login','uses'=>"PostController@myBank"]);
    $router->any('/withDrawData',['middleware'=>'Login','uses'=>"PostController@withDrawData"]);
    $router->any('/withDrawList',['middleware'=>'Login','uses'=>"PostController@withDrawList"]);
    $router->any('/benefits',['middleware'=>'Login','uses'=>"PostController@benefits"]);
    $router->any('/income',['middleware'=>'Login','uses'=>"PostController@income"]);
    $router->any('/incomeSearch',['middleware'=>'Login','uses'=>"PostController@incomeSearch"]);
    $router->any('/withDraw',['middleware'=>['Login','FullTeam'],'uses'=>"PostController@withDraw"]);##----##
    $router->any('/withDrawCancel',['middleware'=>['Login'],'uses'=>"PostController@withDrawCancel"]);
    $router->any('/bankCancel',['middleware'=>['Login'],'uses'=>"PostController@bankCancel"]);

    $router->any('/uploadidcard',['middleware'=>'Login','uses'=>"PostController@uploadidcard"]);
    $router->any('/authentication',['middleware'=>'Login','uses'=>"PostController@authentication"]);
});





