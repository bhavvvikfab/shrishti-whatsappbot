<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_account_id', 'campaign_id', 'name', 'status', 'objective', 'raw_data'];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(FacebookAccount::class, 'facebook_account_id');
    }

    public function adsets()
    {
        return $this->hasMany(FacebookAdset::class);
    }
}
