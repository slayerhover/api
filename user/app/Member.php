<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;


class Member extends Model
{
    protected $primaryKey ="id";
    public $incrementing =true;
    protected $table="t_user";

    public $timestamps = false;
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


    public function getUserTimeAttribute($value)
    {
        return date('Y-m-d H:i:s',$value);
    }

    public function setUserTruenameAttribute($value)
    {
        $this->attributes['user_truename'] = $value;
    }














}
