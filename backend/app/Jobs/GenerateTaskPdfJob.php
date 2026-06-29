<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTaskPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->onConnection('sync');
    }

    public function handle(): void
    {
        if (!$this->task->registration_number) {
            $prefix = Setting::get('registration_number_prefix', 'EFES');
            $year = date('Y');
            $seq = str_pad($this->task->id, 4, '0', STR_PAD_LEFT);
            $categoryCode = strtoupper(substr($this->task->category?->name ?? 'DOC', 0, 3));

            $regNo = "{$prefix}-{$categoryCode}-{$year}-{$seq}";
            $this->task->update(['registration_number' => $regNo]);
        }
    }
}
