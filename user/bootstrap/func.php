<?php


function dumpa($arr)
{
    if(is_object($arr)){
        return dumpa(data($arr));
    }else{
        print "<pre>";
        print_r($arr);
        print "</pre>";
    }
}

function dumpb($arr)
{
    print "<pre>";
    print_r($arr);
    print "</pre>";
}

function data($arr)
{
    return json_decode(json_encode($arr),true);
}

function rJson($data)
{
    return response($data,200)->header('Content-type','application/json');
}

function d2json($data)
{
    return response($data,200)->header('Content-type','application/json');
}

function ret($retCode=0,$data=[],$msg=" ")
{
    #   header("Access-Control-Allow-Origin:*");
    #   header("Content-Type:application/json;charset=utf-8");
    $ret=[];
    $ret['ret'] = $retCode;
    $ret['msg'] = $msg;
    if(is_object($data))
    {
        $data = data($data);
    }
    if(isset($data['pageInfo'])){
        $ret['pageInfo'] = $data['pageInfo'];
    }
    if(isset($data['page'])){
        $ret['page'] = $data['page'];
        unset($data['page']);
    }
    if(isset($data['pagesize'])){
        $ret['pagesize'] = $data['pagesize'];
        unset($data['pagesize']);
    }
    if(isset($data['pageTotal'])){
        $ret['pageTotal'] = $data['pageTotal'];
        unset($data['pageTotal']);
    }
    if(isset($data['sum']) && count($data['sum']) ){
        $ret['sum'] = $data['sum'];
     //   unset($data['sum']);
    }
    if(isset($data['data']))
    {
        $ret['data'] = $data['data'];
    }else {
        $ret['data'] = $data;
    }
    //echo json_encode($ret);
    return response()->json($ret);
}

function updateNull(& $onearr){
    if(!empty($onearr)&&is_array($onearr)){
        foreach($onearr as $k=>$v){
            if(is_array($v)){
                $onearr[$k]	=	updateNull($v);
            }else{
                if($v===NULL){
                    $onearr[$k] = '';
                }
            }
        }}
    return $onearr;
}

function json($vars, $format='json', $callback='callback')
{
    header("Access-Control-Allow-Origin:*");
    header("Access-Control-Allow-Methods", "PUT,POST,GET,OPTIONS,DELETE");
    header("Access-Control-Allow-Headders", "content-type");
    if($format=='json'){
        header("Content-type: application/json");
        $data = updateNull($vars);
        die(json_encode($data));
    }else{
        header("Content-type: text/javascript");
        $data = updateNull($vars);
        die("{$callback}(".json_encode($data).")");
    }
}

function get_url($url,$paramters)
{
    return $url.'/?'.http_build_query($paramters);
}


//参数1：访问的URL，参数2：post数据(不填则为GET)，参数3：提交的$cookies,参数4：是否返回$cookies
function curl_request($url,$post='',$cookie='', $returnCookie=0){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER , false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,false);
    curl_setopt($curl, CURLOPT_REFERER, "http://www.baidu.com/");
    if($post) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    if($cookie) {
        curl_setopt($curl, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);
    if($returnCookie){
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
        $info['cookie']  = substr($matches[1][0], 1);
        $info['content'] = $body;
        return $info;
    }else{
        return $data;
    }
}


function http_get($url="",$data=[],$decode_json=1)
{
    $true_url = get_url($url,$data);
    $ret = curl_request($true_url);
    if($decode_json){
        $ret = json_decode($ret,true);
    }
    return $ret;
}

function http_post($url, $data_string,$decode_json=1) {
    if(is_array($data_string)) {
        $data_string = json_encode($data_string);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
    );
    $result = curl_exec($ch);

    if($decode_json){
        return json_decode($result,true);
    }
    return $result;
}


/*
  16-19 位卡号校验位采用 Luhm 校验方法计算：
    1，将未带校验位的 15 位卡号从右依次编号 1 到 15，位于奇数位号上的数字乘以 2
    2，将奇位乘积的个十位全部相加，再加上所有偶数位上的数字
    3，将加法和加上校验位能被 10 整除。
    $r = luhm('6225881414207430');
    var_dump($r);

https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo=6225883711916282&cardBinCheck=true

{
  "stat": "ok",
  "messages": [],
  "key": "6225883711916282",
  "cardType": "DC",
  "validated": true,
  "bank": "CMB"
}

    DC: "储蓄卡",
    CC: "信用卡",
    SCC: "准贷记卡",
    PC: "预付费卡"




*/
function bankNoCheck($s)
{
    $data = file_get_contents("https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo=".$s."&cardBinCheck=true");
    $arr = json_decode($data,true);
    if(!$arr['validated'])
    {
        return false;
    }else
    {
        return true;
    }
}





function getIp(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        return $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_REAL_IP']))
    {
        return $_SERVER['HTTP_X_REAL_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORVARDED_FOR'])){
        return $_SERVER['HTTP_X_FORVARDED_FOR'];
    }elseif(!empty($_SERVER['REMOTE_ADDR'])){
        return $_SERVER['REMOTE_ADDR'];
    }else{
        return "unknow";
    }
}


function msg($code){
    return Illuminate\Database\Capsule\Manager::table('scsj_language')->where('code','=',$code)->first()['string'];
}