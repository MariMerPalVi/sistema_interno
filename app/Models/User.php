<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['role_id', 'name', 'email', 'password', 'active'];

    protected $hidden = ['password', 'remember_token'];
}
