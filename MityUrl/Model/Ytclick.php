<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ytclick extends Model
{
    protected $table = "ytclicks";

    public function ytlist()
    {
        return $this->belongsTo('App\Ytlist');
    }
}
