<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookAd extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_adset_id', 'ad_id', 'adset_id', 'name', 'status', 'raw_data'];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function adset()
    {
        return $this->belongsTo(FacebookAdset::class, 'facebook_adset_id');
    }
}
