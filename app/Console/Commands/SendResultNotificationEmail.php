<?php

namespace App\Console\Commands;

use App\Mail\ResultNotificationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendResultNotificationEmail extends Command
{
    protected $signature = 'email:send-result-notification
        {email : Recipient email address}
        {name : Recipient display name}
        {status : Result status (accepted|rejected)}';

    protected $description = 'Send an acceptance or rejection email for member recruitment results.';

    public function handle(): int
    {
        $recipientEmail = $this->argument('email');
        $recipientName = $this->argument('name');
        $status = $this->argument('status');

        if (!in_array($status, ['accepted', 'rejected'], true)) {
            $this->error('Invalid status. Use accepted or rejected.');
            return Command::FAILURE;
        }

        try {
            Mail::to($recipientEmail)->send(new ResultNotificationMail(
                $recipientName,
                $status,
                $recipientEmail,
            ));

            $this->info('Result notification email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send result notification email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
