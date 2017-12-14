<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\DB;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $primaryKey ="id";
    public $incrementing =true;
    protected $table="t_user";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
/*    protected $fillable = [
        'name', 'email',
    ];*/

    protected  $guarded  =[];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public  $timestamps  = false;

    public function getUserTimeAttribute($value)
    {
        $value = is_string($value)?strtotime($value):$value;
        return empty($value)?"":date('Y-m-d H:i:s',$value);
    }
    public function getUserTruenameAttribute($value)
    {
        return is_null($value)?"":strval($value);
    }
    public function getidcardImgAttribute($value)
    {
        return is_null($value)?"":strval($value);
    }

    public function getSaleuserUpdatedAttribute($value)
    {
        return $value==0?"":date('Y-m-d H:i',$value);
    }
    public function getUserSexAttribute($value)
    {
        switch ($value)
        {
            case 1:
                return "男";
                break;
            case 2:
                return "女";
                break;
            default:
                return "未知";
        }
    }
    public function setUserSexAttribute($value)
    {
        switch ($value)
        {
            case "男":
                $this->attributes['user_sex'] = "1";
           //     return "男";
                break;
            case "女":
                $this->attributes['user_sex'] = "2";
                break;
            default:
                $this->attributes['user_sex']="0";
           //     return "1";
        }
    }

    public function getIsCertificationAttribute($value)
    {
        $status=[
            "0"=>"未认证",
            "1"=>"已认证",
        ];
        switch($value)
        {
            case "0":
                return "未认证";
                break;
            case "1":
                return "已认证";
                break;
            default:
                return "未知";
        }
       /*
       if(is_null($value)|| !in_array($value,$status) )
        {
            return "未认证";
        }
        return $status[$value];
       */
    }
    public function getSaleuserStateAttribute($value)
    {
        switch ($value)
        {
            case 0:
                return "未认证";
                break;
            case 1:
                return "待审核";
                break;
            case 2:
                return "已认证";
                break;
            case 3:
                return "未通过审核";
                break;
            default:
                return "未知";
        }
   //     return $value;
    }
    public function getUserWxopenidAttribute($value)
    {
        return is_null($value)?"未绑定":"已绑定";
    }

    public function getUserQqopenidAttribute($value)
    {
        return is_null($value)?"未绑定":"已绑定";
    }
    public function getBaozhengjinUpdatedAttribute($value)
    {
        $value = is_string($value)?strtotime($value):$value;
        return is_null($value)?0:date('Y-m-d H:i:s',$value);
    }

    public function setUserTruenameAttribute($value)
    {
        $this->attributes['user_truename'] = $value;
    }

    public function setUserAvatarAttribute($value)
    {
        if($value=="") {
            $this->attributes['user_avatar'] = "http://fast.scsj.net.cn/noimg.jpg";
        }else{
            $this->attributes['user_avatar'] = $value;
        }
    }

    public function getSons()
    {
        return $this->hasMany('App\User','inviter_id');
    }



    public function syncUserLevelData($uid)
    {
        $uid = $uid>0?$uid:"0";
        if($uid==0)
        {
            return false;
        }
        $sql='insert into r_index (id,uid,Lid)
select "'.$uid.'" as id,b.id as uid,"1" as Lid from r_user_relation a,r_user_relation b  where b.inviter_id=a.id  and a.id='.$uid.' and b.id not in (select uid from r_index where id='.$uid.')  union 
select "'.$uid.'" as id,c.id  as uid,"2" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d where  b.inviter_id=a.id  and a.id='.$uid.'   and c.inviter_id =b.id  and c.id not in (select uid from r_index where id='.$uid.')  union 
select "'.$uid.'" as id,d.id as uid,"3" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d where b.inviter_id=a.id  and c.inviter_id =b.id and d.inviter_id=c.id and a.id='.$uid.'  and d.id not in (select uid from r_index where id='.$uid.')   union 
select "'.$uid.'" as id,e.id as uid,"4" as Lid from r_user_relation a,r_user_relation b,r_user_relation c ,r_user_relation d,r_user_relation e where b.inviter_id=a.id  and c.inviter_id =b.id and d.inviter_id=c.id and e.inviter_id=d.id and a.id='.$uid.'  and e.id not in (select uid from r_index where id='.$uid.')
        ';
      //  echo $sql;
        $ok = DB::select($sql);
        return $ok;
    }

    public function syncUserRelation($uid)
    {
        $uid = $uid>0?$uid:"0";
        if($uid==0)
        {
            return false;
        }
        $sql_relation ="insert into r_user_relation(id,inviter_id) select id,inviter_id from t_user where id not in (select id from r_user_relation)";
        $ok = DB::select($sql_relation);
        return $ok;
    }


















}
