<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Task;
use App\Notifications\TaskStatusNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class CheckDeadlines extends Command
{
    protected $signature = 'app:check-deadlines';

    protected $description = 'Notify users about approaching and overdue task deadlines';

    public function handle(): int
    {
        if (! Setting::get('overdue_notification_enabled', true)) {
            $this->info('Overdue notifications are disabled in settings.');
            return self::SUCCESS;
        }

        $reminderDays = Setting::get('deadline_reminder_days', 2);
        $overdueNotified = 0;
        $approachingNotified = 0;

        $overdue = Task::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', ['approved', 'archived', 'rejected', 'draft'])
            ->with('initiator')
            ->get();

        foreach ($overdue as $task) {
            if (! $task->initiator) continue;

            if ($this->alreadyNotifiedToday($task->initiator_id, $task->id, 'overdue')) {
                continue;
            }

            $task->initiator->notify(new TaskStatusNotification(
                'Task #' . $task->id . ' is overdue! Deadline was ' . $task->deadline->format('M d, Y') . '.',
                $task->id,
                'overdue'
            ));
            $overdueNotified++;
        }

        $approaching = Task::query()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addDays($reminderDays)])
            ->whereNotIn('status', ['approved', 'archived', 'rejected', 'draft'])
            ->with('initiator')
            ->get();

        foreach ($approaching as $task) {
            if (! $task->initiator) continue;

            if ($this->alreadyNotifiedToday($task->initiator_id, $task->id, 'deadline_approaching')) {
                continue;
            }

            $task->initiator->notify(new TaskStatusNotification(
                'Task #' . $task->id . ' deadline is approaching: ' . $task->deadline->format('M d, Y') . '.',
                $task->id,
                'deadline_approaching'
            ));
            $approachingNotified++;
        }

        $this->info("Notified {$overdueNotified} overdue, {$approachingNotified} approaching.");

        return self::SUCCESS;
    }

    private function alreadyNotifiedToday(int $userId, int $taskId, string $eventType): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $userId)
            ->whereDate('created_at', today())
            ->where('data->task_id', $taskId)
            ->where('data->event_type', $eventType)
            ->exists();
    }
}
