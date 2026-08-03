<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookAdset extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_campaign_id', 'adset_id', 'campaign_id', 'name', 'status', 'raw_data'];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(FacebookCampaign::class, 'facebook_campaign_id');
    }

    public function ads()
    {
        return $this->hasMany(FacebookAd::class);
    }
}
