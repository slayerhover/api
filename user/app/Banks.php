<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;
use Illuminate\Database\Eloquent\Builder;


class Banks extends Model
{
    use Rememberable;
    protected $primaryKey ="id";
    public $incrementing =true;
    protected $table="scsj_banks";

    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
  /*  protected $fillable = [
        'bid', 'bankNo','phone','userId'
    ];*/
 //  protected $fillable = ['bank_id', 'bankNo','phone','userId'];

    protected  $guarded  =[];

    public function bankInfo(){

        return $this->hasOne('App\Bank','bid','bank_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('if_valid', function (Builder $builder) {
            $builder->where('if_valid', '=', 1);
        });
    }
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
