<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmActivityNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'created_by',
        'note',
        'note_type',
        'is_private',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    public function noteable()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
