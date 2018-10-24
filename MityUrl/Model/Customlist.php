<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customlist extends Model
{
    use SoftDeletes;

    protected $table = "customlists";

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function listpref()
    {
        return $this->hasOne('App\Listpref');
    }

    /*public function customlinks()
    {
        return $this->belongsToMany('App\Customlink')->withTimestamps();
    }*/

    public function urls()
    {
        return $this->belongsToMany('App\Url')->withTimestamps();
    }

    public function customclicks()
    {
        return $this->hasMany('App\Customclick');
    }

}
