<?php

namespace App\Console\Commands;

use App\Mail\InterviewInviteMail;
use App\Models\AuthAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInterviewInviteEmail extends Command
{
    protected $signature = 'email:send-interview-invite
        {email : Recipient email address}
        {recipientName? : Recipient name to display in the greeting}
        {--team=Ban Truyền Thông : Team name (e.g. Ban Truyền Thông, Ban Design, Ban Developer)}
        {--date=21h45-23h00 : Interview time}
        {--format=Online (Google Meet) : Interview format}
        {--deadline=DD/MM/YYYY : Deadline to schedule the interview}
        {--meeting-link= : Optional Google Meet or meeting link}';

    protected $description = 'Send an interview invitation email to a recipient.';

    public function handle(): int
    {
        $recipientEmail = $this->argument('email');
        $recipientName = $this->argument('recipientName');
        $teamName = $this->option('team');
        $date = $this->option('date');
        $format = $this->option('format');
        $deadline = $this->option('deadline');
        $meetingLink = $this->option('meeting-link');

        if (!$recipientName) {
            $recipientName = AuthAccount::where('email', $recipientEmail)->first()?->profile?->profile_name
                ?? AuthAccount::where('email', $recipientEmail)->first()?->username
                ?? explode('@', $recipientEmail)[0];
        }

        $this->info("Sending interview invite to {$recipientEmail}...");

        try {
            Mail::to($recipientEmail)->send(new InterviewInviteMail(
                $recipientName,
                $teamName,
                $date,
                $format,
                $deadline,
                $meetingLink,
                $recipientEmail,
            ));

            $this->info('Interview invite email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send interview invite email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
