<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    protected $table = "clicks";

    public function link()
    {
        return $this->belongsTo('App\Link');
    }
}
