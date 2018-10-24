<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Link extends Model
{
    use SoftDeletes;

    protected $table = "links";

/*    public function llist()
    {
        return $this->belongsToMany('App\Llist');
    }*/

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function url()
    {
        return $this->belongsTo('App\Url');
    }

    public function clicks()
    {
        return $this->hasMany('App\Click');
    }
}
