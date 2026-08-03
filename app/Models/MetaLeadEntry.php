<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaLeadEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'form_id',
        'leadgen_id',
        'ad_id',
        'adgroup_id',
        'campaign_id',
        'platform',
        'created_time',
        'status',
        'webhook_payload',
        'lead_data',
        'fetched_at',
        'fetch_error',
    ];

    protected $casts = [
        'created_time' => 'datetime',
        'fetched_at' => 'datetime',
        'webhook_payload' => 'array',
        'lead_data' => 'array',
    ];
}
