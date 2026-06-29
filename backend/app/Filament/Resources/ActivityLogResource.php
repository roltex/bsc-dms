<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\TaskActivity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = TaskActivity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activities';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(TaskActivity::query()->with(['task.category', 'task.partner', 'user']))
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->label('When'),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'created', 'submitted' => 'info',
                        'signed_document_uploaded' => 'warning',
                        'delegated', 'fast_tracked' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('task.id')
                    ->label('Task #')
                    ->url(fn ($record) => $record->task ? route('filament.admin.resources.tasks.view', $record->task) : null),
                TextColumn::make('task.category.name')
                    ->label('Category')
                    ->placeholder('—'),
                TextColumn::make('task.partner.name')
                    ->label('Partner')
                    ->placeholder('—'),
                TextColumn::make('comment')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(
                        TaskActivity::query()
                            ->distinct()
                            ->pluck('action', 'action')
                            ->mapWithKeys(fn ($v, $k) => [$k => str_replace('_', ' ', ucfirst($k))])
                            ->toArray()
                    )
                    ->multiple(),
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->label('User')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\Action::make('viewTask')
                    ->label('View Task')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->task ? route('filament.admin.resources.tasks.view', $record->task) : null)
                    ->visible(fn ($record) => $record->task !== null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
