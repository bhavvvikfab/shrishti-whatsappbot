<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappMessage extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'lead_id',
        'direction',
        'message',
        'message_type',
        'media_url',
        'media_type',
        'meta_message_id',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeUnread($query)
    {
        return $query->incoming()->where('status', '!=', 'read');
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function isRevoked(): bool
    {
        return $this->message_type === 'revoked';
    }

    /**
     * Escape message text and turn URLs into clickable links (WhatsApp-style).
     */
    public function messageHtml(): string
    {
        return static::linkifyHtml((string) ($this->message ?? ''));
    }

    public static function linkifyHtml(?string $text): string
    {
        $text = (string) ($text ?? '');
        if ($text === '') {
            return '';
        }

        // Match on raw text so query strings with & ? = % stay intact
        // (Instagram / tracking links often include &utm_... / &igsh=...).
        $pattern = '/(?:https?:\/\/|www\.)[^\s<>"\']+/iu';
        $out = '';
        $offset = 0;

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $url = $match[0];
                $pos = (int) $match[1];
                $out .= e(substr($text, $offset, $pos - $offset));

                $trimmed = rtrim($url, '.,;:!?)]}');
                $trailing = substr($url, strlen($trimmed));
                $href = preg_match('#^https?://#i', $trimmed) ? $trimmed : 'https://'.$trimmed;
                $out .= '<a class="wa-msg-link" href="'.e($href).'" target="_blank" rel="noopener noreferrer">'.e($trimmed).'</a>'.e($trailing);
                $offset = $pos + strlen($url);
            }
        }

        $out .= e(substr($text, $offset));

        return nl2br($out);
    }

    public function replyPreviewText(): string
    {
        if ($this->isRevoked()) {
            return 'This message was deleted';
        }

        return match ($this->message_type) {
            'image' => 'Photo',
            'video' => 'Video',
            'audio' => 'Audio',
            'document' => \Illuminate\Support\Str::limit($this->message ?: 'Document', 80),
            'location' => 'Location',
            'contacts' => 'Contact',
            default => \Illuminate\Support\Str::limit((string) ($this->message ?? ''), 120) ?: 'Message',
        };
    }

    /**
     * @return array{message_id: ?int, author: string, text: string, message_type: string, direction: string}
     */
    public function toReplyPreview(WhatsappConversation $conversation): array
    {
        return [
            'message_id' => $this->id,
            'author' => $this->direction === 'outgoing'
                ? 'You'
                : ($conversation->contact_name ?? $conversation->phone_number ?? 'Contact'),
            'text' => $this->replyPreviewText(),
            'message_type' => (string) $this->message_type,
            'direction' => (string) $this->direction,
        ];
    }

    /**
     * Per-request cache: conversation_id => [wamid_key => WhatsappMessage]
     *
     * @var array<int, array<string, self>>
     */
    protected static array $wamidKeyIndexCache = [];

    /**
     * WhatsApp may send the same message as phone-based or LID-based wamids.
     * Both share an inner hex key — use that to match replies/reactions.
     */
    public static function extractWamidMessageKey(?string $wamid): ?string
    {
        if (! is_string($wamid) || $wamid === '') {
            return null;
        }

        $b64 = preg_replace('/^wamid\./i', '', $wamid);
        $raw = base64_decode(strtr((string) $b64, '-_', '+/'), true);
        if ($raw === false || $raw === '') {
            return null;
        }

        if (! preg_match_all('/[0-9A-Fa-f]{16,64}/', $raw, $matches) || empty($matches[0])) {
            return null;
        }

        // Prefer the last hex run (message key); earlier runs are often LID digits.
        return strtoupper((string) end($matches[0]));
    }

    public static function findByMetaMessageId(string $wamid, ?int $conversationId = null): ?self
    {
        $exact = static::withTrashed()->where('meta_message_id', $wamid)->first();
        if ($exact) {
            return $exact;
        }

        $key = static::extractWamidMessageKey($wamid);
        if (! $key || ! $conversationId) {
            return null;
        }

        if (! isset(static::$wamidKeyIndexCache[$conversationId])) {
            static::$wamidKeyIndexCache[$conversationId] = [];
            $candidates = static::withTrashed()
                ->where('conversation_id', $conversationId)
                ->whereNotNull('meta_message_id')
                ->orderByDesc('id')
                ->limit(800)
                ->get();

            foreach ($candidates as $candidate) {
                $candidateKey = static::extractWamidMessageKey($candidate->meta_message_id);
                if ($candidateKey && ! isset(static::$wamidKeyIndexCache[$conversationId][$candidateKey])) {
                    static::$wamidKeyIndexCache[$conversationId][$candidateKey] = $candidate;
                }
            }
        }

        return static::$wamidKeyIndexCache[$conversationId][$key] ?? null;
    }

    /**
     * @return array{message_id: ?int, meta_message_id: ?string, author: string, text: string, message_type: string, direction: string}|null
     */
    public function replyContext(): ?array
    {
        $meta = $this->metadata ?? [];

        $quotedMetaId = $meta['reply_to_meta_message_id'] ?? null;
        if (! $quotedMetaId) {
            return null;
        }

        $quoted = static::findByMetaMessageId($quotedMetaId, $this->conversation_id);
        if ($quoted) {
            $conversation = $this->relationLoaded('conversation')
                ? $this->conversation
                : $this->conversation()->first();

            $preview = $quoted->toReplyPreview($conversation ?? new WhatsappConversation());
            // Prefer the stored meta id on the quoted row so the UI can jump to it.
            $preview['meta_message_id'] = $quoted->meta_message_id ?: $quotedMetaId;

            return $preview;
        }

        $cached = is_array($meta['reply_preview'] ?? null) ? $meta['reply_preview'] : [];

        return [
            'message_id' => $cached['message_id'] ?? null,
            'meta_message_id' => $quotedMetaId,
            'author' => $cached['author'] ?? 'Message',
            'text' => $cached['text'] ?? 'Original message',
            'message_type' => $cached['message_type'] ?? 'text',
            'direction' => $cached['direction'] ?? 'incoming',
        ];
    }

    public function displayMediaUrl(): ?string
    {
        if (! $this->media_url) {
            return null;
        }

        $path = parse_url($this->media_url, PHP_URL_PATH) ?: $this->media_url;

        if (preg_match('#(?:^|/)whatsapp/inbox/media/([a-f0-9-]+\.[a-z0-9]+)$#i', $path, $matches)) {
            return route('whatsapp.media', ['filename' => $matches[1]], false);
        }

        if (preg_match('#(?:^|/)uploads/whatsapp/([a-f0-9-]+\.[a-z0-9]+)$#i', $path, $matches)) {
            return route('whatsapp.media', ['filename' => $matches[1]], false);
        }

        // Legacy rows stored Meta media ids directly in media_url.
        if (preg_match('/^\d{5,}$/', (string) $this->media_url)) {
            return route('whatsapp.meta_media', ['message' => $this->id], false);
        }

        return $this->media_url;
    }

    /**
     * WhatsApp-style reaction emojis attached to this message.
     *
     * @return list<string>
     */
    public function reactionEmojis(): array
    {
        $reactions = collect($this->metadata['reactions'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['emoji'] ?? null))
            ->pluck('emoji')
            ->map(fn ($emoji) => (string) $emoji)
            ->unique()
            ->values()
            ->all();

        return $reactions;
    }

    /**
     * Normalized location payload for map cards.
     *
     * @return array{latitude: ?float, longitude: ?float, name: ?string, address: ?string, maps_url: ?string, preview_url: ?string}|null
     */
    public function locationData(): ?array
    {
        $meta = $this->metadata ?? [];
        $lat = $meta['latitude'] ?? null;
        $lng = $meta['longitude'] ?? null;

        // Legacy rows: "Location: 21.20,72.78"
        if (($lat === null || $lng === null) && is_string($this->message) && preg_match('/Location:\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/i', $this->message, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $name = filled($meta['name'] ?? null) ? (string) $meta['name'] : null;
        $address = filled($meta['address'] ?? null) ? (string) $meta['address'] : null;

        // Yandex static maps (lng,lat) — openstreetmap.de static endpoint is often unreachable.
        $previewUrl = sprintf(
            'https://static-maps.yandex.ru/1.x/?lang=en_US&ll=%1$s,%2$s&z=15&l=map&size=450,200&pt=%1$s,%2$s,pm2rdm',
            $lng,
            $lat
        );

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'name' => $name,
            'address' => $address,
            'maps_url' => 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng),
            'preview_url' => $previewUrl,
        ];
    }

    /**
     * Shared contact cards for WhatsApp-style display.
     *
     * @return list<array{name: string, initial: string, phones: list<array{phone: ?string, wa_id: ?string, type: ?string, display: string, tel_url: ?string, wa_url: ?string}>}>
     */
    public function contactsData(): array
    {
        $contacts = collect($this->metadata['contacts'] ?? [])
            ->filter(fn ($c) => is_array($c))
            ->values();

        // Legacy: "Shared contact: Name"
        if ($contacts->isEmpty() && is_string($this->message) && preg_match('/^Shared contact:\s*(.+)$/i', $this->message, $m)) {
            $contacts = collect([[
                'name' => trim($m[1]),
                'phones' => [],
            ]]);
        }

        return $contacts->map(function ($c) {
            $name = trim((string) ($c['name'] ?? 'Contact')) ?: 'Contact';
            $phones = collect($c['phones'] ?? [])
                ->filter(fn ($p) => is_array($p))
                ->map(function ($p) {
                    $phone = trim((string) ($p['phone'] ?? ''));
                    $waId = preg_replace('/\D+/', '', (string) ($p['wa_id'] ?? ''));
                    $display = $phone !== '' ? $phone : ($waId !== '' ? '+' . $waId : '');
                    $digits = preg_replace('/\D+/', '', $phone !== '' ? $phone : $waId);

                    return [
                        'phone' => $phone !== '' ? $phone : null,
                        'wa_id' => $waId !== '' ? $waId : null,
                        'type' => $p['type'] ?? null,
                        'display' => $display,
                        'tel_url' => $digits !== '' ? 'tel:+' . ltrim($digits, '+') : null,
                        'wa_url' => $waId !== '' ? 'https://wa.me/' . $waId : null,
                    ];
                })
                ->filter(fn ($p) => $p['display'] !== '')
                ->values()
                ->all();

            return [
                'name' => $name,
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                'phones' => $phones,
            ];
        })->all();
    }
}
