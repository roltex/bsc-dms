<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Exports\ArchiveExport;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchiveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()
            ->with(['category', 'partner', 'initiator'])
            ->whereIn('status', [TaskStatus::Approved, TaskStatus::Archived]);

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                  ->orWhere('registration_number', 'like', "%{$s}%")
                  ->orWhereHas('partner', fn ($pq) => $pq->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('initiator', fn ($iq) => $iq->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('year')) {
            $query->whereYear('updated_at', $request->input('year'));
        }
        if ($request->filled('document_category_id')) {
            $query->where('document_category_id', $request->input('document_category_id'));
        }
        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->input('partner_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortField = $request->input('sort', 'updated_at');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['id', 'updated_at', 'created_at', 'registration_number'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('updated_at');
        }

        $tasks = $query->paginate($request->integer('per_page', 20));

        return response()->json($tasks);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Task::query()
            ->with(['category', 'partner'])
            ->whereIn('status', [TaskStatus::Approved, TaskStatus::Archived]);

        if ($request->filled('year')) {
            $query->whereYear('updated_at', $request->input('year'));
        }
        if ($request->filled('document_category_id')) {
            $query->where('document_category_id', $request->input('document_category_id'));
        }

        $filename = 'archive-' . ($request->input('year', date('Y'))) . '.xlsx';

        return Excel::download(new ArchiveExport($query), $filename);
    }
}
