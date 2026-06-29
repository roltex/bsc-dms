<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $pendingQuery = Task::query();
        if ($user->isInitiator()) {
            $pendingQuery->where('initiator_id', $user->id)
                ->whereIn('status', [TaskStatus::Draft, TaskStatus::PendingInitiator]);
        } elseif ($user->isManager()) {
            $pendingQuery->whereIn('status', [TaskStatus::PendingManager, TaskStatus::PendingFinalManager]);
        } elseif ($user->isLawyer()) {
            $pendingQuery->whereIn('status', [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer]);
        }

        $pendingCount = $pendingQuery->count();

        $overdueCount = Task::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', [
                TaskStatus::Approved->value,
                TaskStatus::Archived->value,
                TaskStatus::Rejected->value,
                TaskStatus::Draft->value,
            ])
            ->when($user->isInitiator(), fn ($q) => $q->where('initiator_id', $user->id))
            ->count();

        $recentActivities = TaskActivity::query()
            ->with(['user:id,name', 'task:id,status'])
            ->when($user->isInitiator(), function ($q) use ($user) {
                $q->whereHas('task', fn ($tq) => $tq->where('initiator_id', $user->id));
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $totalPartners = Partner::count();
        $totalArchived = Task::whereIn('status', [TaskStatus::Approved, TaskStatus::Archived])->count();

        $myPendingTasks = Task::query()
            ->with(['category:id,name', 'partner:id,name'])
            ->when($user->isInitiator(), fn ($q) => $q->where('initiator_id', $user->id)->whereIn('status', [TaskStatus::Draft, TaskStatus::PendingInitiator]))
            ->when($user->isManager(), fn ($q) => $q->whereIn('status', [TaskStatus::PendingManager, TaskStatus::PendingFinalManager]))
            ->when($user->isLawyer(), fn ($q) => $q->whereIn('status', [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer]))
            ->when($user->isAdmin(), fn ($q) => $q->whereIn('status', [
                TaskStatus::PendingManager, TaskStatus::PendingLawyer,
                TaskStatus::PendingFinalLawyer, TaskStatus::PendingFinalManager,
            ]))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $statusBreakdown = Task::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $todayActivityCount = TaskActivity::whereDate('created_at', today())->count();
        $totalTasks = Task::count();

        return response()->json([
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
            'total_partners' => $totalPartners,
            'total_archived' => $totalArchived,
            'total_tasks' => $totalTasks,
            'today_activity_count' => $todayActivityCount,
            'status_breakdown' => $statusBreakdown,
            'recent_activities' => $recentActivities,
            'pending_tasks' => $myPendingTasks,
        ]);
    }
}
