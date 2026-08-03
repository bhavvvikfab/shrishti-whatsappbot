<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookPage extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_account_id', 'page_id', 'name', 'access_token', 'is_synced', 'is_subscribed'];

    protected $casts = [
        'is_synced' => 'boolean',
        'is_subscribed' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }

    public function leadForms()
    {
        return $this->hasMany(FacebookLeadForm::class);
    }
}
