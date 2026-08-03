<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'facebook_lead_form_id',
        'lead_id',
        'page_id',
        'form_id',
        'ad_id',
        'ad_name',
        'campaign_id',
        'campaign_name',
        'platform',
        'field_data',
        'full_name',
        'email',
        'phone',
        'is_imported',
        'imported_lead_id',
        'created_time'
    ];

    protected $casts = [
        'field_data' => 'array',
        'is_imported' => 'boolean',
        'created_time' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(FacebookLeadForm::class, 'facebook_lead_form_id');
    }

    public function importedLead()
    {
        return $this->belongsTo(Lead::class, 'imported_lead_id');
    }
}
