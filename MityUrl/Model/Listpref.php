<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Listpref extends Model
{
    protected $table = "listpref";

    protected $fillable = ['title', 'ytlist_id', 'customlist_id', 'private_list', 'private_clickstats'];

    public function ytlists()
    {
        return $this->belongsTo('App\Ytlist');
    }

    public function customlist()
    {
        return $this->belongsTo('App\Customlist');
    }
}
