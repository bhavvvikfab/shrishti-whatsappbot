<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\RoleBasedFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\OwnedByUser;

class FollowUp extends Model
{
    use HasFactory, SoftDeletes, OwnedByUser, Blameable, RoleBasedFilter;

    protected static function booted(): void
    {
        static::saving(function ($followUp) {
            static::syncOwnedUserFromAssignee($followUp);

            if ($followUp->isDirty('follow_up_at') || $followUp->isDirty('status')) {
                $followUp->reminder_sent_at = null;
                $followUp->first_reminder_sent_at = null;
                $followUp->final_reminder_sent_at = null;
            }
        });
    }

    protected $casts = [
        'follow_up_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'first_reminder_sent_at' => 'datetime',
        'final_reminder_sent_at' => 'datetime',
    ];

    protected $fillable = [
        'lead_id',
        'customer_id',
        'related_type',
        'related_id',
        'assigned_user_id',
        'purpose',
        'comment',
        'priority',
        'status',
        'follow_up_at',
        'reminder_sent_at',
        'first_reminder_sent_at',
        'final_reminder_sent_at',
        'user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistories()
    {
        return $this->hasMany(FollowUpStatusHistory::class)->latest();
    }
}
