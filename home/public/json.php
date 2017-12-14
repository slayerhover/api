<?php

$db = new mysqli('localhost', 'root', 'asdfasdf', 'scsj');
#$db->select_db('scsj');

$query = "SELECT id,username,password FROM bak_t_user limit 20";

$result = $db->query($query);

$result_num = $result->num_rows;
while($row=$result->fetch_assoc())
{ 
	$rows[] = $row;  //返回一个关联数组，可以通过$row['uid']的方式取得值
}
#$row = $result->fetch_row();  //返回一个列举数组，可以通过$row[0]的方式取得值

#$row = $result->fetch_array();  //返回一个混合数组，可以通过$row['uid']和$row[0]两种方式取得值

#$row = $result->fetch_object();  //返回一个对象，可以通过$row->uid的方式取得值

echo rJson($rows);


$result->free();  //释放结果集 
$db->close();  //关闭一个数据库连接，这不是必要的


function rJson($arr){
	$data = [
		'code'=>1,
		'msg'=>'ok',
		'data'=>$arr,
#'sing'=>md5($_SERVER['REQUEST_URI']);
#'json'=>file_get_contents("php://input");
	];
	return  json_encode($data);
}
