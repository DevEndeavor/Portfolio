<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ytlist extends Model
{

    use SoftDeletes;

    protected $table = "ytlists";


    public function ytplaylist()
    {
        return $this->belongsTo('App\Ytplaylist');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function ytclicks()
    {
        return $this->hasMany('App\Ytclick');
    }

    public function listpref()
    {
        return $this->hasOne('App\Listpref');
    }

}
