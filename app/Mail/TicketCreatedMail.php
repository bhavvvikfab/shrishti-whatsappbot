<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public ?string $companyName = null;
    public ?string $companyLogoUrl = null;

    /**
     * Create a new message instance.
     */
    public function __construct($ticket)
    {
        $this->ticket = $ticket;
        $this->hydrateBranding();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Support Ticket ' . ($this->ticket->name ?? 'Created'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-created',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function hydrateBranding(): void
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'company_name',
                'company_logo_path',
                'mail_from_name',
                'app_name',
                'app_logo',
            ])
            ->pluck('value', 'key');

        $this->companyName = $settings->get('company_name')
            ?: $settings->get('mail_from_name')
            ?: $settings->get('app_name')
            ?: 'Fablead Developers Technolab';

        $this->companyLogoUrl = $this->resolveLogoUrl(
            $settings->get('company_logo_path') ?: $settings->get('app_logo')
        );
    }

    private function resolveLogoUrl(?string $logoPath): ?string
    {
        if (!$logoPath) {
            return 'https://crm.fableadtech.com/public/assets/img/logos/fabcrmlogo.png';
        }

        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $this->normalizePublicUrl($logoPath);
        }

        try {
            if (Storage::disk('public')->exists($logoPath)) {
                if (Route::has('profile.company_logo.image')) {
                    return $this->normalizePublicUrl(
                        route('profile.company_logo.image') . '?v=' . Storage::disk('public')->lastModified($logoPath)
                    );
                }

                return $this->normalizePublicUrl(asset('storage/' . ltrim($logoPath, '/')));
            }
        } catch (\Throwable) {
            return 'https://crm.fableadtech.com/public/assets/img/logos/fabcrmlogo.png';
        }

        return 'https://crm.fableadtech.com/public/assets/img/logos/fabcrmlogo.png';
    }

    private function normalizePublicUrl(string $url): string
    {
        $url = preg_replace('#/public/storage/#', '/storage/', $url) ?: $url;

        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }
}
