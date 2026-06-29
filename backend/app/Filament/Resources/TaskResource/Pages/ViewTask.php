<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Filament\Resources\TaskResource;
use App\Models\TaskActivity;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->url(fn () => TaskResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil'),

            Action::make('changeStatus')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Select::make('status')
                        ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                        ->required()
                        ->default(fn () => $this->record->status->value),
                    Textarea::make('reason')
                        ->label('Reason for change')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $old = $this->record->status->label();
                    $this->record->update([
                        'status' => $data['status'],
                        'current_step' => TaskStatus::from($data['status'])->stepNumber(),
                    ]);

                    TaskActivity::create([
                        'task_id' => $this->record->id,
                        'user_id' => auth()->id(),
                        'action' => 'admin_status_change',
                        'comment' => "Status changed from {$old} to ".TaskStatus::from($data['status'])->label().". Reason: {$data['reason']}",
                    ]);

                    Notification::make()->title('Status updated')->success()->send();
                }),

            Action::make('assignLawyer')
                ->label('Assign Lawyer')
                ->icon('heroicon-o-user')
                ->color('info')
                ->form([
                    Select::make('assigned_lawyer_id')
                        ->label('Lawyer')
                        ->options(User::where('role', UserRole::Lawyer)->pluck('name', 'id'))
                        ->required()
                        ->default(fn () => $this->record->assigned_lawyer_id),
                ])
                ->action(function (array $data) {
                    $this->record->update(['assigned_lawyer_id' => $data['assigned_lawyer_id']]);
                    $lawyer = User::find($data['assigned_lawyer_id']);

                    TaskActivity::create([
                        'task_id' => $this->record->id,
                        'user_id' => auth()->id(),
                        'action' => 'admin_lawyer_assigned',
                        'comment' => "Lawyer assigned: {$lawyer->name}",
                    ]);

                    Notification::make()->title("Assigned to {$lawyer->name}")->success()->send();
                }),

            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn () => $this->record->status === TaskStatus::Approved)
                ->requiresConfirmation()
                ->modalHeading('Archive this task?')
                ->modalDescription('This will move the task to the archive. It can still be viewed but not modified.')
                ->action(function () {
                    $this->record->update(['status' => TaskStatus::Archived]);

                    TaskActivity::create([
                        'task_id' => $this->record->id,
                        'user_id' => auth()->id(),
                        'action' => 'archived',
                        'comment' => 'Archived by administrator',
                    ]);

                    Notification::make()->title('Task archived')->success()->send();
                }),
        ];
    }
}
