<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    
    public function llist()
    {
        return $this->hasMany('App\Llist');
    }

    public function ytlists()
    {
        return $this->hasMany('App\Ytlist');
    }

    public function customlists()
    {
        return $this->hasMany('App\Customlist');
    }

    public function links()
    {
        return $this->hasMany('App\Link');
    }
    
}
