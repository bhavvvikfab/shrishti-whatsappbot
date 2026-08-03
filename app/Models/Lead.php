<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\RoleBasedFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\HasCustomFields;
use App\Traits\OwnedByUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Lead extends Model
{
    use HasFactory, HasCustomFields, OwnedByUser, SoftDeletes, Blameable, RoleBasedFilter;

    protected static function booted(): void
    {
        static::saving(function ($lead) {
            static::syncOwnedUserFromAssignee($lead);
        });
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'source',
        'whatsapp',
        'address',
        'image',
        'company_name',
        'sic_code',
        'status',
        'lead_source_id',
        'lead_stage_id',
        'assigned_user_id',
        'user_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_converted',
        'converted_customer_id',
        'notes',
    ];

    protected $casts = [
        'is_converted' => 'boolean',
        'travel_start_date' => 'date',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'lead_stage_id');
    }

    public function leadStage()
    {
        return $this->stage();
    }

    public function convertedCustomer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function customer()
    {
        return $this->convertedCustomer();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'related_id')->where('related_type', 'lead');
    }

    public function statusHistories()
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public function whatsappConversation()
    {
        return $this->hasOne(WhatsappConversation::class);
    }

    public function whatsappMessages()
    {
        return $this->hasManyThrough(WhatsappMessage::class, WhatsappConversation::class);
    }

    public function whatsappFollowups()
    {
        return $this->hasMany(WhatsappFollowup::class);
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function tags()
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag_pivot', 'lead_id', 'lead_tag_id')
            ->withTimestamps()
            ->withPivot('created_by');
    }

    /**
     * Lead is WhatsApp-related (auto-import or explicit WhatsApp number).
     */
    public function isWhatsAppLead(): bool
    {
        if (strcasecmp(trim((string) $this->source), 'WhatsApp') === 0) {
            return true;
        }

        return filled($this->whatsapp);
    }

    /**
     * CRM conversation row for this lead (FK or same normalized phone as whatsapp/phone).
     */
    public function resolveWhatsappConversation(): ?WhatsappConversation
    {
        if ($this->relationLoaded('whatsappConversation')) {
            $c = $this->getRelation('whatsappConversation');
            if ($c instanceof WhatsappConversation) {
                return $c;
            }

            return $this->findWhatsappConversationByNormalizedPhone();
        }

        $byFk = $this->whatsappConversation()->first();
        if ($byFk) {
            return $byFk;
        }

        return $this->findWhatsappConversationByNormalizedPhone();
    }

    protected function findWhatsappConversationByNormalizedPhone(): ?WhatsappConversation
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return null;
        }

        $raw = $this->whatsapp ?: $this->phone;
        if (! filled($raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            $digits = '91' . $digits;
        }

        return WhatsappConversation::query()
            ->where('phone_number', $digits)
            ->first();
    }

    /**
     * Open WhatsApp inbox thread for this lead when a conversation exists for this number or lead link.
     */
    public function resolveWhatsappChatUrl(): ?string
    {
        try {
            $conv = $this->resolveWhatsappConversation();
            if (! $conv) {
                return null;
            }

            return route('whatsapp.conversation', $conv);
        } catch (\Throwable $e) {
            Log::warning('Lead WhatsApp chat URL lookup failed.', [
                'lead_id' => $this->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function scopeWithWhatsApp($query)
    {
        return $query->whereNotNull('whatsapp');
    }

    public function scopeWithTag($query, $tagName)
    {
        return $query->whereHas('tags', function ($q) use ($tagName) {
            $q->where('name', $tagName);
        });
    }
}
