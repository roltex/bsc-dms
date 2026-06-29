<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message,
        public ?int $taskId = null,
        public string $eventType = 'status_change'
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (Setting::get('email_notifications_enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->mailSubject())
            ->line($this->message);

        if ($this->taskId) {
            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
            $mail->action('View Task', $frontendUrl . '/tasks/' . $this->taskId);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'task_id' => $this->taskId,
            'event_type' => $this->eventType,
        ];
    }

    private function mailSubject(): string
    {
        $prefix = 'EFES DMS';
        $taskRef = $this->taskId ? " — Task #{$this->taskId}" : '';

        return match ($this->eventType) {
            'approved' => "{$prefix}: Approved{$taskRef}",
            'rejected' => "{$prefix}: Rejected{$taskRef}",
            'needs_revision' => "{$prefix}: Revision Needed{$taskRef}",
            'overdue' => "{$prefix}: Overdue{$taskRef}",
            'deadline_approaching' => "{$prefix}: Deadline Approaching{$taskRef}",
            'delegated' => "{$prefix}: Task Delegated{$taskRef}",
            'reviewer_added' => "{$prefix}: Review Requested{$taskRef}",
            default => "{$prefix}: Task Update{$taskRef}",
        };
    }
}
