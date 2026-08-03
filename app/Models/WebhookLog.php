<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = ['event_type', 'payload', 'processed', 'error_message'];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];
}
