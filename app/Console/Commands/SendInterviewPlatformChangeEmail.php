<?php

namespace App\Console\Commands;

use App\Mail\InterviewPlatformChangeMail;
use App\Models\AuthAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInterviewPlatformChangeEmail extends Command
{
    protected $signature = 'email:send-interview-platform-change
        {email : Recipient email address}
        {recipientName? : Recipient name to display in the greeting}
        {--date=22h00-23h35 (05/08/2026) : Interview time and date}
        {--schedule-link= : Link to the schedule sheet containing the Google Meet link}';

    protected $description = 'Send a platform change notification email (Discord → Google Meet) to an interview candidate.';

    public function handle(): int
    {
        $recipientEmail = $this->argument('email');
        $recipientName = $this->argument('recipientName');
        $date = $this->option('date');
        $scheduleLink = $this->option('schedule-link');

        if (!$scheduleLink) {
            $this->error('--schedule-link is required.');
            return Command::FAILURE;
        }

        if (!$recipientName) {
            $recipientName = AuthAccount::where('email', $recipientEmail)->first()?->profile?->profile_name
                ?? AuthAccount::where('email', $recipientEmail)->first()?->username
                ?? explode('@', $recipientEmail)[0];
        }

        $this->info("Sending platform change notification to {$recipientEmail}...");

        try {
            Mail::to($recipientEmail)->send(new InterviewPlatformChangeMail(
                $recipientName,
                $date,
                $scheduleLink,
                $recipientEmail,
            ));

            $this->info('Platform change email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send platform change email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
