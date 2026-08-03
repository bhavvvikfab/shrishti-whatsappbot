<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookLeadForm extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_page_id', 'form_id', 'name', 'status', 'raw_data'];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(FacebookPage::class, 'facebook_page_id');
    }

    public function leads()
    {
        return $this->hasMany(FacebookLead::class);
    }
}
