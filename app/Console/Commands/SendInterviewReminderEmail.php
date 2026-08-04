<?php

namespace App\Console\Commands;

use App\Mail\InterviewReminderMail;
use App\Models\AuthAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInterviewReminderEmail extends Command
{
    protected $signature = 'email:send-interview-reminder
        {email : Recipient email address}
        {recipientName? : Recipient name to display in the greeting}
        {--date=22h00-23h35 (05/08/2026) : Interview time and date}
        {--format=Online (Discord) : Interview format}
        {--schedule-link= : Link to fill in the interview schedule}
        {--discord-link= : Discord server invite link}
        {--deadline=23h59 ngày 04/08/2026 : Deadline to fill schedule and join Discord}
        {--contact-deadline=17:00 5/8/2026 : Deadline to contact via Messenger if unable to schedule}';

    protected $description = 'Send an interview reminder email to a recipient.';

    public function handle(): int
    {
        $recipientEmail = $this->argument('email');
        $recipientName = $this->argument('recipientName');
        $date = $this->option('date');
        $format = $this->option('format');
        $scheduleLink = $this->option('schedule-link');
        $discordLink = $this->option('discord-link');
        $deadline = $this->option('deadline');
        $contactDeadline = $this->option('contact-deadline');

        if (!$scheduleLink) {
            $this->error('--schedule-link is required.');
            return Command::FAILURE;
        }

        if (!$discordLink) {
            $this->error('--discord-link is required.');
            return Command::FAILURE;
        }

        if (!$recipientName) {
            $recipientName = AuthAccount::where('email', $recipientEmail)->first()?->profile?->profile_name
                ?? AuthAccount::where('email', $recipientEmail)->first()?->username
                ?? explode('@', $recipientEmail)[0];
        }

        $this->info("Sending interview reminder to {$recipientEmail}...");

        try {
            Mail::to($recipientEmail)->send(new InterviewReminderMail(
                $recipientName,
                $date,
                $format,
                $scheduleLink,
                $discordLink,
                $deadline,
                $contactDeadline,
                $recipientEmail,
            ));

            $this->info('Interview reminder email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send interview reminder email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
