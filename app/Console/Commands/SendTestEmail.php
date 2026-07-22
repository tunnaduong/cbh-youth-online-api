<?php
namespace App\Console\Commands;

use App\Mail\StudyMaterialRatedMail;
use App\Models\AuthAccount;
use App\Models\StudyMaterial;
use App\Models\StudyMaterialRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'email:send-test
        {email=test@example.com : Email address to receive the test email}
        {--rating-id= : Study material rating ID to use}
        {--material-id= : Study material ID to use}
        {--rater-id= : Rater account ID to use}';

    protected $description = 'Send a test email to a specified address';

    public function handle(): int
    {
        $recipient = $this->argument('email');

        $this->info("Sending test email to {$recipient}...");

        try {
            $rating = $this->resolveRating();
            $material = StudyMaterial::find($this->option('material-id') ?: $rating->study_material_id);
            $rater = AuthAccount::find($this->option('rater-id') ?: $rating->user_id);

            if (!$material || !$rater) {
                throw new \RuntimeException('Could not find the material or rater for the selected rating.');
            }

            $recipientAccount = AuthAccount::where('email', $recipient)->first();
            if (!$recipientAccount) {
                throw new \RuntimeException("No account found for {$recipient}. Use an email belonging to a CBH Youth Online account so the unsubscribe link can be generated.");
            }

            Mail::to($recipient)->send(new StudyMaterialRatedMail($material, $rater, $rating, $recipientAccount));
            $this->info('Email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function resolveRating(): StudyMaterialRating
    {
        if ($ratingId = $this->option('rating-id')) {
            $rating = StudyMaterialRating::find($ratingId);
        } else {
            $rating = StudyMaterialRating::latest('id')->first();
        }

        if (!$rating) {
            throw new \RuntimeException('No study material ratings found. Create a rating or pass a valid --rating-id.');
        }

        return $rating;
    }
}