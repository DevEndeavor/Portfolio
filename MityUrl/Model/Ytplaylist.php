<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ytplaylist extends Model
{
    protected $table = "ytplaylists";

    protected $fillable = ['ytuser_id', 'playlist_id', 'playlist_type', 'playlist_title', 'thumbnail', 'updated_at'];   //Gotta do this to use firstOrCreate, etc

    public function ytlists()
    {
        return $this->hasMany('App\Ytlist');
    }

    public function ytlinks()
    {
        return $this->belongsToMany('App\Ytlink')->withTimestamps();;
    }

    public function ytuser()
    {
        return $this->belongsTo('App\Ytuser');
    }


}
