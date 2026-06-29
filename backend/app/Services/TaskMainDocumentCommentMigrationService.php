<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskDocument;
use Illuminate\Support\Facades\DB;

class TaskMainDocumentCommentMigrationService
{
    /**
     * When a new main (non-attachment) document version is saved: delete resolved PDF comment
     * threads that pointed at older main versions, and reassign unresolved threads to the new
     * document so open feedback stays visible on the latest file.
     */
    public function apply(Task $task, TaskDocument $newMainDocument): void
    {
        if ($newMainDocument->is_attachment) {
            return;
        }

        if ($newMainDocument->task_id !== $task->id) {
            return;
        }

        $oldMainDocIds = $task->documents()
            ->where('is_attachment', false)
            ->where('version', '<', $newMainDocument->version)
            ->pluck('id');

        if ($oldMainDocIds->isEmpty()) {
            return;
        }

        $roots = TaskComment::query()
            ->where('task_id', $task->id)
            ->whereIn('document_id', $oldMainDocIds)
            ->whereNull('parent_id')
            ->get();

        if ($roots->isEmpty()) {
            return;
        }

        $newId = (int) $newMainDocument->id;

        DB::transaction(function () use ($roots, $newId) {
            foreach ($roots as $root) {
                if ($root->resolved) {
                    $this->deleteCommentTree($root);
                } else {
                    $this->reassignDocumentTree($root, $newId);
                }
            }
        });
    }

    private function deleteCommentTree(TaskComment $comment): void
    {
        foreach ($comment->replies as $reply) {
            $this->deleteCommentTree($reply);
        }
        $comment->delete();
    }

    private function reassignDocumentTree(TaskComment $comment, int $newDocumentId): void
    {
        $comment->update(['document_id' => $newDocumentId]);
        foreach ($comment->replies as $reply) {
            $this->reassignDocumentTree($reply, $newDocumentId);
        }
    }
}
