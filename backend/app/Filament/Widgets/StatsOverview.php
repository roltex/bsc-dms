<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Partner;
use App\Models\Task;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalTasks = Task::count();
        $pendingTasks = Task::whereIn('status', [
            TaskStatus::PendingManager,
            TaskStatus::PendingLawyer,
            TaskStatus::PendingInitiator,
            TaskStatus::PendingFinalLawyer,
            TaskStatus::PendingFinalManager,
        ])->count();
        $overdueTasks = Task::where('deadline', '<', now())
            ->whereIn('status', [
                TaskStatus::PendingManager,
                TaskStatus::PendingLawyer,
                TaskStatus::PendingInitiator,
                TaskStatus::PendingFinalLawyer,
                TaskStatus::PendingFinalManager,
            ])->count();
        $approvedTasks = Task::where('status', TaskStatus::Approved)->count();

        return [
            Stat::make('Total Tasks', $totalTasks)
                ->description('All tasks in the system')
                ->icon('heroicon-o-document-text')
                ->color('primary'),
            Stat::make('Pending Review', $pendingTasks)
                ->description('Awaiting action')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Overdue', $overdueTasks)
                ->description('Past deadline')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($overdueTasks > 0 ? 'danger' : 'success'),
            Stat::make('Approved', $approvedTasks)
                ->description('Completed successfully')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Partners', Partner::count())
                ->description(Partner::whereNotNull('blacklisted_at')->count().' blacklisted')
                ->icon('heroicon-o-building-office-2')
                ->color('info'),
            Stat::make('Users', User::count())
                ->description('Active accounts')
                ->icon('heroicon-o-users')
                ->color('gray'),
        ];
    }
}
