<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
        protected $table = "urls";

        protected $fillable = ['url', 'is_threat', 'threat_type', 'updated_at'];   //Gotta do this to use firstOrCreate, etc


        public function links()
        {
            return $this->hasMany('App\Link');
        }


        public function customlists()
        {
            return $this->belongsToMany('App\Customlist')->withTimestamps();
        }
}
