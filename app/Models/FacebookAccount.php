<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookAccount extends Model
{
    use HasFactory;

    protected $fillable = ['fb_user_id', 'name', 'email', 'access_token', 'token_expires_at', 'user_id'];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pages()
    {
        return $this->hasMany(FacebookPage::class);
    }

    public function campaigns()
    {
        return $this->hasMany(FacebookCampaign::class);
    }
}
