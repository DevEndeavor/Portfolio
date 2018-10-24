<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customclick extends Model
{
    protected $table = "customclicks";

    public function customlist()
    {
        return $this->belongsTo('App\Customlist');
    }
}
