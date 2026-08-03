<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\Meeting;
use App\Services\UpcomingReminderService;
use Illuminate\Console\Command;

class SendUpcomingReminders extends Command
{
    protected $signature = 'reminders:send-upcoming';

    protected $description = 'Send 24-hour and 30-minute reminder emails for upcoming meetings and follow-ups.';

    public function __construct(private UpcomingReminderService $reminderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $windowStart = now();
        $windowEnd = $windowStart->copy()->addDay();

        $meetingCount = $this->sendMeetingReminders($windowStart, $windowEnd);
        $followUpCount = $this->sendFollowUpReminders($windowStart, $windowEnd);

        $this->info("Upcoming reminder run completed. Meetings: {$meetingCount}, Follow-ups: {$followUpCount}");

        return self::SUCCESS;
    }

    private function sendMeetingReminders($windowStart, $windowEnd): int
    {
        $meetings = Meeting::with(['assignedUser', 'customer'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', $windowStart)
            ->where('scheduled_at', '<=', $windowEnd)
            ->get();

        $sent = 0;

        foreach ($meetings as $meeting) {
            $sent += $this->reminderService->sendDueMeetingReminders($meeting);
        }

        return $sent;
    }

    private function sendFollowUpReminders($windowStart, $windowEnd): int
    {
        $followUps = FollowUp::with(['assignedUser', 'lead', 'customer'])
            ->whereIn('status', ['pending', 'resheduled'])
            ->where('follow_up_at', '>', $windowStart)
            ->where('follow_up_at', '<=', $windowEnd)
            ->get();

        $sent = 0;

        foreach ($followUps as $followUp) {
            $sent += $this->reminderService->sendDueFollowUpReminders($followUp);
        }

        return $sent;
    }
}
