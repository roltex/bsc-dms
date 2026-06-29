<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestTasks extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Tasks';

    public function table(Table $table): Table
    {
        return $table
            ->query(Task::query()->with(['category', 'partner', 'initiator'])->latest('updated_at')->limit(10))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('partner.name')->label('Partner'),
                TextColumn::make('initiator.name')->label('Initiator'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus $state) => $state->label())
                    ->color(fn (TaskStatus $state) => match ($state) {
                        TaskStatus::Draft => 'gray',
                        TaskStatus::Approved => 'success',
                        TaskStatus::Rejected => 'danger',
                        TaskStatus::Archived => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('deadline')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->url(fn (Task $record) => route('filament.admin.resources.tasks.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}
