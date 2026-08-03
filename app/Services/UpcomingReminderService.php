<?php

namespace App\Services;

use App\Mail\UpcomingReminderMail;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UpcomingReminderService
{
    private const DISPLAY_TIMEZONE = 'Asia/Kolkata';
    private const FIRST_REMINDER_MINUTES = 1440;
    private const FINAL_REMINDER_MINUTES = 120;
    private const FIRST_REMINDER_GRACE_MINUTES = 60;
    private const FIRST_REMINDER_EARLY_CREATE_GRACE_MINUTES = 60;

    public function sendDueMeetingReminders(Meeting $meeting, bool $allowEarlyFirstReminder = false): int
    {
        $sent = 0;
        if ($this->sendMeeting24HourReminderIfDue($meeting, $allowEarlyFirstReminder)) {
            $sent++;
        }
        if ($this->sendMeeting30MinuteReminderIfDue($meeting)) {
            $sent++;
        }
        return $sent;
    }

    public function sendDueFollowUpReminders(FollowUp $followUp, bool $allowEarlyFirstReminder = false): int
    {
        $sent = 0;
        if ($this->sendFollowUp24HourReminderIfDue($followUp, $allowEarlyFirstReminder)) {
            $sent++;
        }
        if ($this->sendFollowUp30MinuteReminderIfDue($followUp)) {
            $sent++;
        }
        return $sent;
    }

    public function sendMeeting24HourReminderIfDue(Meeting $meeting, bool $allowEarlyFirstReminder = false): bool
    {
        $meeting->loadMissing(['assignedUser', 'customer']);

        if (!$this->meetingNeedsFirstReminder($meeting, $allowEarlyFirstReminder)) {
            return false;
        }

        $recipients = $this->meetingRecipients($meeting);

        if ($recipients->isEmpty()) {
            Log::warning('Meeting 24-hour reminder skipped because no email recipients were found.', [
                'meeting_id' => $meeting->id,
            ]);

            return false;
        }

        if (!$this->sendReminderEmails($recipients, [
            'subjectLine' => 'Reminder: Meeting in 24 hours - ' . ($meeting->title ?: 'Upcoming Meeting'),
            'moduleLabel' => 'Meeting Reminder',
            'title' => $meeting->title ?: 'Upcoming Meeting',
            'reminderLeadText' => '24 hours',
            'scheduledFor' => $meeting->scheduled_at?->timezone(self::DISPLAY_TIMEZONE)->format('d M Y h:i A') ?? '-',
            'assignedStaffName' => $meeting->assignedUser?->name,
            'customerName' => $meeting->customer?->name,
            'meetingType' => $meeting->meeting_type ? ucfirst($meeting->meeting_type) : null,
            'location' => $meeting->address,
            'details' => $meeting->agenda,
        ])) {
            return false;
        }

        $meeting->forceFill([
            'reminder_sent_at' => now(),
            'first_reminder_sent_at' => now(),
        ])->saveQuietly();
        Log::info('Meeting 24-hour reminder email sent successfully.', [
            'meeting_id' => $meeting->id,
            'recipients' => $recipients->pluck('email')->values()->all(),
        ]);

        return true;
    }

    public function sendMeeting30MinuteReminderIfDue(Meeting $meeting): bool
    {
        $meeting->loadMissing(['assignedUser', 'customer']);

        if (!$this->meetingNeedsFinalReminder($meeting)) {
            return false;
        }

        $recipients = $this->meetingRecipients($meeting);

        if ($recipients->isEmpty()) {
            Log::warning('Meeting 2-hour reminder skipped because no email recipients were found.', [
                'meeting_id' => $meeting->id,
            ]);

            return false;
        }

        if (!$this->sendReminderEmails($recipients, [
            'subjectLine' => 'Reminder: Meeting in 2 hours - ' . ($meeting->title ?: 'Upcoming Meeting'),
            'moduleLabel' => 'Meeting Reminder',
            'title' => $meeting->title ?: 'Upcoming Meeting',
            'reminderLeadText' => '2 hours',
            'scheduledFor' => $meeting->scheduled_at?->timezone(self::DISPLAY_TIMEZONE)->format('d M Y h:i A') ?? '-',
            'assignedStaffName' => $meeting->assignedUser?->name,
            'customerName' => $meeting->customer?->name,
            'meetingType' => $meeting->meeting_type ? ucfirst($meeting->meeting_type) : null,
            'location' => $meeting->address,
            'details' => $meeting->agenda,
        ])) {
            return false;
        }

        $meeting->forceFill([
            'reminder_sent_at' => now(),
            'final_reminder_sent_at' => now(),
        ])->saveQuietly();
        Log::info('Meeting 2-hour reminder email sent successfully.', [
            'meeting_id' => $meeting->id,
            'recipients' => $recipients->pluck('email')->values()->all(),
        ]);

        return true;
    }

    public function sendFollowUp24HourReminderIfDue(FollowUp $followUp, bool $allowEarlyFirstReminder = false): bool
    {
        $followUp->loadMissing(['assignedUser', 'lead', 'customer']);

        if (!$this->followUpNeedsFirstReminder($followUp, $allowEarlyFirstReminder)) {
            return false;
        }

        $relatedName = $followUp->lead?->name ?: $followUp->customer?->name;
        $recipients = $this->followUpRecipients($followUp);

        if ($recipients->isEmpty()) {
            Log::warning('Follow-up 24-hour reminder skipped because no email recipients were found.', [
                'follow_up_id' => $followUp->id,
            ]);

            return false;
        }

        if (!$this->sendReminderEmails($recipients, [
            'subjectLine' => 'Reminder: Follow-up in 24 hours - ' . ($followUp->purpose ?: 'Upcoming Follow-up'),
            'moduleLabel' => 'Follow-up Reminder',
            'title' => $followUp->purpose ?: 'Upcoming Follow-up',
            'reminderLeadText' => '24 hours',
            'scheduledFor' => $followUp->follow_up_at?->timezone(self::DISPLAY_TIMEZONE)->format('d M Y h:i A') ?? '-',
            'assignedStaffName' => $followUp->assignedUser?->name,
            'customerName' => $relatedName,
            'details' => $followUp->comment,
        ])) {
            return false;
        }

        $followUp->forceFill([
            'reminder_sent_at' => now(),
            'first_reminder_sent_at' => now(),
        ])->saveQuietly();
        Log::info('Follow-up 24-hour reminder email sent successfully.', [
            'follow_up_id' => $followUp->id,
            'recipients' => $recipients->pluck('email')->values()->all(),
        ]);

        return true;
    }

    public function sendFollowUp30MinuteReminderIfDue(FollowUp $followUp): bool
    {
        $followUp->loadMissing(['assignedUser', 'lead', 'customer']);

        if (!$this->followUpNeedsFinalReminder($followUp)) {
            return false;
        }

        $relatedName = $followUp->lead?->name ?: $followUp->customer?->name;
        $recipients = $this->followUpRecipients($followUp);

        if ($recipients->isEmpty()) {
            Log::warning('Follow-up 2-hour reminder skipped because no email recipients were found.', [
                'follow_up_id' => $followUp->id,
            ]);

            return false;
        }

        if (!$this->sendReminderEmails($recipients, [
            'subjectLine' => 'Reminder: Follow-up in 2 hours - ' . ($followUp->purpose ?: 'Upcoming Follow-up'),
            'moduleLabel' => 'Follow-up Reminder',
            'title' => $followUp->purpose ?: 'Upcoming Follow-up',
            'reminderLeadText' => '2 hours',
            'scheduledFor' => $followUp->follow_up_at?->timezone(self::DISPLAY_TIMEZONE)->format('d M Y h:i A') ?? '-',
            'assignedStaffName' => $followUp->assignedUser?->name,
            'customerName' => $relatedName,
            'details' => $followUp->comment,
        ])) {
            return false;
        }

        $followUp->forceFill([
            'reminder_sent_at' => now(),
            'final_reminder_sent_at' => now(),
        ])->saveQuietly();
        Log::info('Follow-up 2-hour reminder email sent successfully.', [
            'follow_up_id' => $followUp->id,
            'recipients' => $recipients->pluck('email')->values()->all(),
        ]);

        return true;
    }

    public function sendReminderEmails(Collection $recipients, array $mailData): bool
    {
        if (!function_exists('setMailConfig')) {
            require_once app_path('Helpers/emailSendHelper.php');
        }

        if (function_exists('setMailConfig')) {
            setMailConfig();
        }

        try {
            foreach ($recipients as $recipient) {
                Mail::to($recipient['email'])->send(new UpcomingReminderMail(
                    subjectLine: $mailData['subjectLine'],
                    moduleLabel: $mailData['moduleLabel'],
                    title: $mailData['title'],
                    reminderLeadText: $mailData['reminderLeadText'],
                    scheduledFor: $mailData['scheduledFor'],
                    recipientName: $recipient['name'] ?? null,
                    assignedStaffName: $mailData['assignedStaffName'] ?? null,
                    customerName: $mailData['customerName'] ?? null,
                    meetingType: $mailData['meetingType'] ?? null,
                    location: $mailData['location'] ?? null,
                    details: $mailData['details'] ?? null,
                ));
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Upcoming reminder email send failed.', [
                'subject' => $mailData['subjectLine'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function collectRecipients(array $recipients): Collection
    {
        return collect($recipients)
            ->filter(function (array $recipient) {
                return !empty($recipient['email']);
            })
            ->unique(function (array $recipient) {
                return strtolower((string) $recipient['email']);
            })
            ->values();
    }

    public function meetingNeedsFirstReminder(Meeting $meeting, bool $allowEarlyFirstReminder = false): bool
    {
        if ($meeting->status !== 'scheduled' || $meeting->first_reminder_sent_at || !$meeting->scheduled_at) {
            return false;
        }

        $minutesUntilStart = now()->diffInMinutes($meeting->scheduled_at, false);

        return $this->isWithinFirstReminderWindow($minutesUntilStart, $allowEarlyFirstReminder);
    }

    public function meetingNeedsFinalReminder(Meeting $meeting): bool
    {
        if ($meeting->status !== 'scheduled' || $meeting->final_reminder_sent_at || !$meeting->scheduled_at) {
            return false;
        }

        $minutesUntilStart = now()->diffInMinutes($meeting->scheduled_at, false);

        return $minutesUntilStart > 0
            && $minutesUntilStart <= self::FINAL_REMINDER_MINUTES;
    }

    public function followUpNeedsFirstReminder(FollowUp $followUp, bool $allowEarlyFirstReminder = false): bool
    {
        if (!in_array($followUp->status, ['pending', 'resheduled'], true) || $followUp->first_reminder_sent_at || !$followUp->follow_up_at) {
            return false;
        }

        $minutesUntilStart = now()->diffInMinutes($followUp->follow_up_at, false);

        return $this->isWithinFirstReminderWindow($minutesUntilStart, $allowEarlyFirstReminder);
    }

    public function followUpNeedsFinalReminder(FollowUp $followUp): bool
    {
        if (!in_array($followUp->status, ['pending', 'resheduled'], true) || $followUp->final_reminder_sent_at || !$followUp->follow_up_at) {
            return false;
        }

        $minutesUntilStart = now()->diffInMinutes($followUp->follow_up_at, false);

        return $minutesUntilStart > 0
            && $minutesUntilStart <= self::FINAL_REMINDER_MINUTES;
    }

    private function isWithinFirstReminderWindow(int $minutesUntilStart, bool $allowEarlyFirstReminder = false): bool
    {
        $maxMinutes = self::FIRST_REMINDER_MINUTES + ($allowEarlyFirstReminder ? self::FIRST_REMINDER_EARLY_CREATE_GRACE_MINUTES : 0);

        return $minutesUntilStart <= $maxMinutes
            && $minutesUntilStart > (self::FIRST_REMINDER_MINUTES - self::FIRST_REMINDER_GRACE_MINUTES);
    }

    private function meetingRecipients(Meeting $meeting): Collection
    {
        $recipients = [];

        if ($this->isRecipientGroupEnabled('admin')) {
            $recipients = array_merge($recipients, $this->adminRecipients()->all());
        }

        if ($meeting->assignedUser) {
            $recipientGroup = $meeting->assignedUser->isAdmin() ? 'admin' : 'staff';

            if ($this->isRecipientGroupEnabled($recipientGroup)) {
                $recipients[] = [
                    'email' => $meeting->assignedUser->email,
                    'name' => $meeting->assignedUser->name,
                ];
            }
        }

        if ($this->isRecipientGroupEnabled('customer')) {
            $recipients[] = [
                'email' => $meeting->customer?->email,
                'name' => $meeting->customer?->name,
            ];
        }

        return $this->collectRecipients($recipients);
    }

    private function followUpRecipients(FollowUp $followUp): Collection
    {
        $recipients = [];

        if ($this->isRecipientGroupEnabled('admin')) {
            $recipients = array_merge($recipients, $this->adminRecipients()->all());
        }

        if ($followUp->assignedUser) {
            $recipientGroup = $followUp->assignedUser->isAdmin() ? 'admin' : 'staff';

            if ($this->isRecipientGroupEnabled($recipientGroup)) {
                $recipients[] = [
                    'email' => $followUp->assignedUser->email,
                    'name' => $followUp->assignedUser->name,
                ];
            }
        }

        if ($this->isRecipientGroupEnabled('customer')) {
            $recipients[] = [
                'email' => $followUp->lead?->email,
                'name' => $followUp->lead?->name,
            ];
            $recipients[] = [
                'email' => $followUp->customer?->email,
                'name' => $followUp->customer?->name,
            ];
        }

        return $this->collectRecipients($recipients);
    }

    private function adminRecipients(): Collection
    {
        return User::query()
            ->role(['admin', 'super-admin'])
            ->whereNotNull('email')
            ->get(['id', 'name', 'email'])
            ->map(function (User $user) {
                return [
                    'email' => $user->email,
                    'name' => $user->name,
                ];
            })
            ->values();
    }

    private function isRecipientGroupEnabled(string $group): bool
    {
        $settingKey = match ($group) {
            'admin' => 'email_notifications_admin',
            'staff' => 'email_notifications_staff',
            default => 'email_notifications_customer',
        };

        $value = Setting::query()->where('key', $settingKey)->value('value');

        if ($value === null) {
            return true;
        }

        return (string) $value === '1';
    }
}
