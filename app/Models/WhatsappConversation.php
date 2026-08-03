<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConversation extends Model
{
    use SoftDeletes, Blameable;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const FILTER_ALL = 'all';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public const TAG_PENDING_PAYMENT = 'pending_payment';

    public const TAG_PAID = 'paid';

    public const TAG_IMPORTANT = 'important';

    public const TAGS = [
        self::TAG_PENDING_PAYMENT,
        self::TAG_PAID,
        self::TAG_IMPORTANT,
    ];

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'lead_id',
        'phone_number',
        'whatsapp_phone_id',
        'contact_name',
        'profile_picture',
        'status',
        'assigned_user_id',
        'unread_count',
        'last_message_at',
        'last_read_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(WhatsappMessage::class, 'conversation_id')
            ->where('message_type', '!=', 'reaction')
            ->latestOfMany();
    }

    public function followups()
    {
        return $this->hasMany(WhatsappFollowup::class, 'conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeHasTag($query, string $tag)
    {
        return $query->where('metadata->tags->'.$tag, true);
    }

    public function scopeInboxFilter($query, ?string $filter)
    {
        if ($filter === 'unread') {
            return $query->where('unread_count', '>', 0);
        }

        if ($filter === '' || $filter === 'all') {
            return $query;
        }

        if (in_array($filter, self::TAGS, true)) {
            return $query->hasTag($filter);
        }

        if (in_array($filter, self::STATUSES, true)) {
            return $query->where('status', $filter);
        }

        return $query->where('status', self::STATUS_OPEN);
    }

    public function hasTag(string $tag): bool
    {
        return (bool) (($this->metadata['tags'][$tag] ?? false));
    }

    public function toggleTag(string $tag): bool
    {
        if (! in_array($tag, self::TAGS, true)) {
            return false;
        }

        $meta = $this->metadata ?? [];
        $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        $enabled = ! (bool) ($tags[$tag] ?? false);

        if ($enabled) {
            $tags[$tag] = true;

            // Payment tags are mutually exclusive.
            if ($tag === self::TAG_PAID) {
                unset($tags[self::TAG_PENDING_PAYMENT]);
            } elseif ($tag === self::TAG_PENDING_PAYMENT) {
                unset($tags[self::TAG_PAID]);
            }
        } else {
            unset($tags[$tag]);
        }

        $meta['tags'] = $tags;
        $this->update(['metadata' => $meta]);

        return $enabled;
    }

    public function activeTags(): array
    {
        $tags = [];
        foreach (self::TAGS as $tag) {
            if ($this->hasTag($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    public function tagLabel(string $tag): string
    {
        return match ($tag) {
            self::TAG_PENDING_PAYMENT => 'Pending (Payment)',
            self::TAG_PAID => 'Paid',
            self::TAG_IMPORTANT => 'Important',
            default => ucfirst(str_replace('_', ' ', $tag)),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_ARCHIVED => 'Archived',
            default => 'Open',
        };
    }

    public function markAsRead()
    {
        $this->update([
            'unread_count' => 0,
            'last_read_at' => now(),
        ]);
    }

    /**
     * Whether delayed AI auto-reply is allowed for this chat (per-contact override).
     */
    public function aiReplyEnabled(): bool
    {
        return ! (bool) ($this->metadata['ai_reply_disabled'] ?? false);
    }

    public function incrementUnread()
    {
        $this->increment('unread_count');
        $this->touch('last_message_at');
    }

    public function pinnedMessageId(): ?int
    {
        $id = $this->metadata['pinned_message']['message_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function pinnedMessage(): ?WhatsappMessage
    {
        $messageId = $this->pinnedMessageId();
        if (! $messageId) {
            return null;
        }

        return WhatsappMessage::withTrashed()
            ->where('conversation_id', $this->id)
            ->where('id', $messageId)
            ->first();
    }

    public function pinMessage(WhatsappMessage $message, ?int $userId = null): bool
    {
        if ($message->conversation_id !== $this->id || $message->message_type === 'reaction') {
            return false;
        }

        $meta = $this->metadata ?? [];
        $meta['pinned_message'] = [
            'message_id' => $message->id,
            'pinned_at' => now()->toIso8601String(),
            'pinned_by' => $userId,
        ];
        $this->update(['metadata' => $meta]);

        return true;
    }

    public function unpinMessage(): void
    {
        $meta = $this->metadata ?? [];
        unset($meta['pinned_message']);
        $this->update(['metadata' => $meta]);
    }

    /**
     * @return array{message_id: int, text: string, message_type: string, direction: string, author: string, media_url: ?string, revoked: bool}|null
     */
    public function pinnedMessagePreview(): ?array
    {
        $message = $this->pinnedMessage();
        if (! $message || $message->trashed() || $message->isRevoked()) {
            if ($this->pinnedMessageId()) {
                $this->unpinMessage();
            }

            return null;
        }

        return [
            'message_id' => $message->id,
            'text' => $message->replyPreviewText(),
            'message_type' => (string) $message->message_type,
            'direction' => (string) $message->direction,
            'author' => $message->direction === 'outgoing'
                ? 'You'
                : ($this->contact_name ?? $this->phone_number ?? 'Contact'),
            'media_url' => $message->displayMediaUrl(),
            'revoked' => $message->isRevoked(),
        ];
    }
}
