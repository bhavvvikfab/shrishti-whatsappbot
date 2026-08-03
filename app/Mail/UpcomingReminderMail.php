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

class UpcomingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $moduleLabel,
        public string $title,
        public string $reminderLeadText,
        public string $scheduledFor,
        public ?string $recipientName = null,
        public ?string $assignedStaffName = null,
        public ?string $customerName = null,
        public ?string $meetingType = null,
        public ?string $location = null,
        public ?string $details = null,
        public ?string $companyName = null,
        public ?string $companyAddress = null,
        public ?string $companyLogoUrl = null,
        public ?string $companyEmail = null,
    ) {
        $this->hydrateBranding();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.upcoming-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function hydrateBranding(): void
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'company_name',
                'company_address',
                'company_logo_path',
                'mail_from_name',
                'mail_from_address',
                'app_name',
                'app_logo',
            ])
            ->pluck('value', 'key');

        $this->companyName = $settings->get('company_name')
            ?: $settings->get('mail_from_name')
            ?: $settings->get('app_name')
            ?: config('app.name', 'CRM');

        $this->companyAddress = $settings->get('company_address') ?: null;
        $this->companyEmail = $settings->get('mail_from_address') ?: null;
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
