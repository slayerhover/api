<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;


class Bank extends Model
{
    use Rememberable;
    protected $primaryKey ="bid";
    public $incrementing =true;
    protected $table="scsj_bank_icon";

    public $timestamps = false;
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
