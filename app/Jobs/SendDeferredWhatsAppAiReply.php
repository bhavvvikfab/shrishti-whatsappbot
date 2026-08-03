<?php

namespace App\Jobs;

use App\Services\WhatsAppInboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeferredWhatsAppAiReply implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $conversationId,
        public int $incomingMessageId,
    ) {}

    public function handle(WhatsAppInboxService $inbox): void
    {
        $inbox->processDeferredAiReply($this->conversationId, $this->incomingMessageId);
    }
}
