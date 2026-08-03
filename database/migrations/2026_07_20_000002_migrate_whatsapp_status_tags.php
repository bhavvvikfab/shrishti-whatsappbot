<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        $rows = DB::table('whatsapp_conversations')
            ->whereIn('status', ['pending_payment', 'important'])
            ->get(['id', 'status', 'metadata']);

        foreach ($rows as $row) {
            $meta = [];
            if (! empty($row->metadata)) {
                $decoded = json_decode($row->metadata, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
            $tags[$row->status] = true;
            $meta['tags'] = $tags;

            DB::table('whatsapp_conversations')
                ->where('id', $row->id)
                ->update([
                    'status' => 'open',
                    'metadata' => json_encode($meta),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — tag data remains in metadata.
    }
};
