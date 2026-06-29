<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::with('category', 'partner')->get();
        $users = User::all()->groupBy(fn ($u) => $u->role->value);

        $notifications = [];
        $now = now();

        foreach ($users[UserRole::Initiator->value] ?? [] as $initiator) {
            $notifications[] = $this->make($initiator->id, 'Your task for partner review has been approved by the manager.', $tasks->random()->id, 'status_change', $now->copy()->subHours(2));
            $notifications[] = $this->make($initiator->id, 'Legal review complete — please upload the signed document.', $tasks->random()->id, 'status_change', $now->copy()->subHours(6));
            $notifications[] = $this->make($initiator->id, 'Your supply agreement has been fully approved. Registration number assigned.', $tasks->random()->id, 'status_change', $now->copy()->subDay());
            $notifications[] = $this->make($initiator->id, 'Reminder: Task deadline approaching in 3 days.', $tasks->random()->id, 'deadline_warning', $now->copy()->subHours(8));
        }

        foreach ($users[UserRole::Manager->value] ?? [] as $manager) {
            $notifications[] = $this->make($manager->id, 'New task submitted and awaiting your approval.', $tasks->random()->id, 'status_change', $now->copy()->subHours(1));
            $notifications[] = $this->make($manager->id, 'Task requires final manager sign-off before registration.', $tasks->random()->id, 'status_change', $now->copy()->subHours(4));
            $notifications[] = $this->make($manager->id, 'A task has been rejected by legal. Please review the comments.', $tasks->random()->id, 'status_change', $now->copy()->subHours(12));
            $notifications[] = $this->make($manager->id, 'Weekly summary: 3 tasks pending your approval.', null, 'system', $now->copy()->subDay(), true);
        }

        foreach ($users[UserRole::Lawyer->value] ?? [] as $lawyer) {
            $notifications[] = $this->make($lawyer->id, 'New task forwarded to legal for review.', $tasks->random()->id, 'status_change', $now->copy()->subHours(3));
            $notifications[] = $this->make($lawyer->id, 'Initiator uploaded signed document — final legal review required.', $tasks->random()->id, 'status_change', $now->copy()->subHours(5));
            $notifications[] = $this->make($lawyer->id, 'You have been added as a reviewer on a bank guarantee task.', $tasks->random()->id, 'reviewer_added', $now->copy()->subHours(7));
            $notifications[] = $this->make($lawyer->id, 'A task you delegated has been completed.', $tasks->random()->id, 'delegation', $now->copy()->subDay());
            $notifications[] = $this->make($lawyer->id, 'Overdue alert: 2 tasks past their deadline require immediate attention.', null, 'deadline_overdue', $now->copy()->subHours(10));
        }

        foreach ($users[UserRole::Administrator->value] ?? [] as $admin) {
            $notifications[] = $this->make($admin->id, 'System: New user registered and requires role assignment.', null, 'system', $now->copy()->subHours(2));
            $notifications[] = $this->make($admin->id, 'System: Database backup completed successfully.', null, 'system', $now->copy()->subDay(), true);
        }

        \DB::table('notifications')->insert($notifications);
    }

    private function make(int $userId, string $message, ?int $taskId, string $eventType, $createdAt, bool $read = false): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TaskStatusNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode([
                'message' => $message,
                'task_id' => $taskId,
                'event_type' => $eventType,
            ]),
            'read_at' => $read ? $createdAt->copy()->addHour() : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
