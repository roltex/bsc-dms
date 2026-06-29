<?php

namespace App\Exports;

use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TaskSummaryExport implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function __construct(private Task $task) {}

    public function title(): string
    {
        return 'Task #'.$this->task->id.' Summary';
    }

    public function headings(): array
    {
        return ['Field', 'Value'];
    }

    public function array(): array
    {
        $t = $this->task;
        $rows = [
            ['Registration Number', $t->registration_number ?? '—'],
            ['Status', $t->status->label()],
            ['Category', $t->category?->name ?? '—'],
            ['Partner / Vendor', $t->partner?->name ?? '—'],
            ['Partner BIN/IIN', $t->partner?->bin_iin ?? '—'],
            ['Initiator', $t->initiator?->name ?? '—'],
            ['Assigned Lawyer', $t->assignedLawyer?->name ?? '—'],
            ['Amount', $t->amount ? number_format((float)$t->amount, 2) : '—'],
            ['Commercial Terms', $t->commercial_terms ?? '—'],
            ['Validity From', $t->validity_from?->format('Y-m-d') ?? '—'],
            ['Validity To', $t->validity_to?->format('Y-m-d') ?? '—'],
            ['Deadline', $t->deadline?->format('Y-m-d') ?? '—'],
            ['Created', $t->created_at?->format('Y-m-d H:i') ?? '—'],
            ['', ''],
            ['--- Approvers ---', ''],
        ];

        foreach ($t->stepCompletions as $sc) {
            if ($sc->status === 'completed' && $sc->step) {
                $actorName = $sc->actor_type === 'user'
                    ? (\App\Models\User::find($sc->actor_id)?->name ?? 'User #'.$sc->actor_id)
                    : 'Partner';
                $rows[] = [
                    $sc->step->name.' ('.$sc->outcome.')',
                    $actorName.' — '.$sc->completed_at?->format('Y-m-d H:i'),
                ];
            }
        }

        $rows[] = ['', ''];
        $rows[] = ['--- Reviewers ---', ''];
        foreach ($t->reviewers as $r) {
            $rows[] = ['Reviewer', $r->name.' ('.$r->email.')'];
        }

        $rows[] = ['', ''];
        $rows[] = ['--- Activity Log ---', ''];
        foreach ($t->activities->reverse() as $a) {
            $rows[] = [
                $a->created_at->format('Y-m-d H:i').' — '.$a->action,
                ($a->user?->name ?? 'System').($a->comment ? ': '.$a->comment : ''),
            ];
        }

        return $rows;
    }
}
