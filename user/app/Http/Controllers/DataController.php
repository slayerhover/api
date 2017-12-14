<?php

namespace App\Http\Controllers;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
/*    public function __construct()
    {
        //
/*        DB::enableQueryLog();
        $query = DB::getQueryLog();
        dumpa($query);
    }

    */
     public function syncUserData(Request $request)
     {
        $uid = $request->input('uid',1095);
        $sql='
             insert into r_index (id,uid,Lid)
select "'.$uid.'" as id,b.id as uid,"1" as Lid from r_user_relation a,r_user_relation b  where b.inviter_id=a.id  and a.id='.$uid.' and b.id not in (select uid from r_index where id='.$uid.')  union 
select "'.$uid.'" as id,c.id  as uid,"2" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d where  b.inviter_id=a.id  and a.id='.$uid.'   and c.inviter_id =b.id  and c.id not in (select uid from r_index where id='.$uid.')  union 
select "'.$uid.'" as id,d.id as uid,"3" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d where b.inviter_id=a.id  and c.inviter_id =b.id and d.inviter_id=c.id and a.id='.$uid.'  and d.id not in (select uid from r_index where id='.$uid.')   union 
select "'.$uid.'" as id,e.id as uid,"4" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d,r_user_relation e where b.inviter_id=a.id  and c.inviter_id =b.id and d.inviter_id=c.id and e.inviter_id=d.id and a.id='.$uid.'  and e.id not in (select uid from r_index where id='.$uid.')
        ';
         echo $sql;
         $ok = DB::select($sql);
         dumpa($ok);
     }

     public function flushData2Relation($relation_id=0)
     {
        $fs = "id,inviter_id,baozhengjin_status,saleuser_state,team_finished,team_count";
        $sql="insert into r_user_relation (".$fs.")  select ".$fs." from t_user where id not in (select id from r_user_relation)";
         $ok = DB::select($sql);
         if($ok)
         {
             return ret(0,"Success","刷新数据成功");
         }else{
             return ret(0,"Failed","刷新数据失败");
         }
     }

    public function sync(Request $request)
    {
        if(!$request->json()->has("phone"))
        {
            $arr = [
                'ret'=>1,
                'data'=>'Failed to find phone',
                'msg'=>'Failed to find phone',
            ];
            return $arr;
        }
        $phone = $request->json("phone");
        $model = new User();
        if(!$model->where('phone',$phone)->count())
        {
            $arr = [
                'ret'=>1,
                'data'=>'Failed to find phone user',
                'msg'=>'Failed to find phone user',
            ];
            return $arr;
        }
        $user = $model->where('phone',$phone)->first();
        dumpa($user);
        $r = $model->syncUserRelation($user['id']);
        $level = $model->syncUserLevelData($user['id']);
        $retData=[
            'user_data'=>$user,
            'r'=>$r,
            'level'=>$level,
        ];

        return ret(0,$retData,"ok");
    }



}
