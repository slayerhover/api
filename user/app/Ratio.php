<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;


class Ratio extends Model
{
    protected $primaryKey ="level_id";
    public $incrementing =true;
    protected $table="scsj_ratio";

    public $timestamps = false;
    protected  $guarded  =[];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

/*
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
