<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Organizer extends Model
{
    protected $fillable = ['user_id', 'approval_status'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
