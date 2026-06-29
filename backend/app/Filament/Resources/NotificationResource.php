<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;

class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'Notification';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                DatabaseNotification::query()
                    ->with('notifiable')
                    ->latest()
            )
            ->columns([
                TextColumn::make('notifiable.name')->label('User')->searchable(),
                TextColumn::make('data.message')
                    ->label('Message')
                    ->limit(80)
                    ->searchable(query: fn ($query, string $search) => $query->where('data', 'like', "%{$search}%")),
                TextColumn::make('data.event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'deadline_warning', 'overdue' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('read_at')
                    ->label('Read')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->read_at !== null)
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('read')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('read_at'),
                        false: fn ($query) => $query->whereNull('read_at'),
                    ),
            ])
            ->actions([
                \Filament\Actions\Action::make('markRead')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->action(fn ($record) => $record->update(['read_at' => now()]))
                    ->visible(fn ($record) => $record->read_at === null)
                    ->requiresConfirmation(false),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('markAllRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->action(fn ($records) => $records->each->update(['read_at' => now()]))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
