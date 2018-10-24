<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ytlink extends Model
{
    protected $table = "ytlinks";

    public function ytplaylists()
    {
        return $this->belongsToMany('App\Ytplaylist')->withTimestamps();
    }
}
