<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function index(Task $task, Request $request): JsonResponse
    {
        $query = $task->comments()
            ->whereNull('parent_id')
            ->with(['user:id,name', 'replies.user:id,name']);

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->input('document_id'));
        }

        if ($request->input('type') === 'general') {
            $query->whereNull('page')->orderByDesc('created_at');
        } else {
            $query->orderBy('page')->orderBy('y_percent')->orderBy('created_at');
        }

        return response()->json($query->get());
    }

    public function store(Task $task, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_id' => 'nullable|exists:task_documents,id',
            'page' => 'nullable|integer|min:1',
            'x_percent' => 'nullable|numeric|min:0|max:100',
            'y_percent' => 'nullable|numeric|min:0|max:100',
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:task_comments,id',
        ]);

        $comment = $task->comments()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'comment',
            'comment' => mb_substr($validated['body'], 0, 200),
            'meta' => [
                'comment_id' => $comment->id,
                'page' => $validated['page'] ?? null,
                'document_id' => $validated['document_id'] ?? null,
            ],
        ]);

        $comment->load('user:id,name', 'replies.user:id,name');

        return response()->json($comment, 201);
    }

    public function update(Task $task, TaskComment $comment, Request $request): JsonResponse
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $user = $request->user();
        $isOwner = $comment->user_id === $user->id;
        $isAdmin = in_array($user->role->value ?? $user->role, ['administrator', 'lawyer']);

        if (!$isOwner && !$isAdmin) {
            abort(403, 'You can only edit your own comments.');
        }

        $validated = $request->validate([
            'body' => 'sometimes|string|max:2000',
            'resolved' => 'sometimes|boolean',
        ]);

        $comment->update($validated);
        $comment->load('user:id,name', 'replies.user:id,name');

        return response()->json($comment);
    }

    public function destroy(Task $task, TaskComment $comment, Request $request): JsonResponse
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $user = $request->user();
        $isOwner = $comment->user_id === $user->id;
        $isAdmin = in_array($user->role->value ?? $user->role, ['administrator', 'lawyer']);

        if (!$isOwner && !$isAdmin) {
            abort(403, 'You can only delete your own comments.');
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted']);
    }
}
