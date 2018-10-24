<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ytuser extends Model
{
    protected $table = "ytusers";

    protected $fillable = ['channel_id', 'channel_title', 'custom_url', 'updated_at'];   //Gotta do this to use firstOrCreate, etc

    public function ytplaylists()
    {
        return $this->hasMany('App\Ytplaylist');
    }


}
