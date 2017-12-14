<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;


class TX extends Model
{
    use Rememberable;
    protected $primaryKey ="id";
    public $incrementing =true;
    protected $table="scsj_tixian";

    public $timestamps = false;
    public $guarded=[];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function getStatusAttribute($value)
    {
        switch ($value)
        {
            case 0:
                return "待处理";
                break;
            case 1:
                return "已处理等待到账";
                break;
            case 2:
                return "已结算";
                break;
            default:
                return "未知";
        }
      return $value;
    }


    public function getCreatedAtAttribute($value)
    {
        if(is_numeric($value))
            return date('Y-m-d H:i',$value);
        else
            return date('Y-m-d H:i',strtotime($value));
    }
/*
 *
 *     public function getUserTimeAttribute($value)
    {
        $status = [
            '0'=>'待处理',
            '1'=>'已处理等待到账',
            '2'=>'已结算'
        ];
        if(in_array($value,$status)) {
            return $status[$value];
        }else{
            return "未知";
        }
    }
    public function getUserTimeAttribute($value)
    {
        return date('Y-m-d H:i:s',$value);
    }

    public function setUserTruenameAttribute($value)
    {
        $this->attributes['user_truename'] = $value;
    }


*/











}
