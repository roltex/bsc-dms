<?php

namespace App\Notifications;

use App\Models\PartnerAccessToken;
use App\Models\Task;
use App\Models\WorkflowStep;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerAccessNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Task $task,
        private WorkflowStep $step,
        private string $accessUrl,
        private PartnerAccessToken $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partnerName = $this->task->partner?->name ?? 'Partner';
        $categoryName = $this->task->category?->name ?? 'Document';
        $actionLabel = match ($this->step->action_type) {
            'sign' => 'sign a document',
            'approve' => 'approve a document',
            'upload_document' => 'upload a document',
            'confirm' => 'confirm receipt',
            default => 'review a document',
        };

        return (new MailMessage)
            ->subject("Action Required: {$categoryName} — Task #{$this->task->id}")
            ->greeting("Hello {$partnerName},")
            ->line("You have been requested to {$actionLabel} for Task #{$this->task->id} ({$categoryName}).")
            ->line("**Step:** {$this->step->name}")
            ->when($this->task->deadline, fn (MailMessage $m) => $m->line('**Deadline:** '.$this->task->deadline))
            ->when($this->task->commercial_terms, fn (MailMessage $m) => $m->line('**Terms:** '.$this->task->commercial_terms))
            ->action('Open Task', $this->accessUrl)
            ->line('This link is valid until '.$this->token->expires_at->format('M d, Y H:i').' and can only be used once.')
            ->line('If you have any questions, please contact the initiator.')
            ->salutation('EFES Document Management System');
    }
}
