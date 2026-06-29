<?php

namespace App\Filament\Resources;

use App\Models\PartnerAccessToken;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerAccessTokenResource extends Resource
{
    protected static ?string $model = PartnerAccessToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?string $navigationLabel = 'Partner Links';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('task.id')
                    ->label('Task')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner_email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('workflowStep.name')
                    ->label('Step'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->state(function (PartnerAccessToken $record): string {
                        if ($record->used_at) {
                            return $record->action_taken ?? 'used';
                        }

                        return $record->expires_at->isPast() ? 'expired' : 'active';
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-clock',
                        'approved' => 'heroicon-o-check-circle',
                        'rejected' => 'heroicon-o-x-circle',
                        'expired' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-check',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('action_taken')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\Action::make('copy_link')
                    ->label('Copy Link')
                    ->icon('heroicon-o-clipboard')
                    ->visible(fn (PartnerAccessToken $record) => $record->isValid())
                    ->action(function (PartnerAccessToken $record) {
                        $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/partner/'.$record->token;
                        \Filament\Notifications\Notification::make()
                            ->title('Partner Access Link')
                            ->body($url)
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('resend')
                    ->label('Resend Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (PartnerAccessToken $record) => $record->isValid())
                    ->requiresConfirmation()
                    ->action(function (PartnerAccessToken $record) {
                        $task = $record->task;
                        $step = $record->workflowStep;
                        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
                        $accessUrl = $frontendUrl.'/partner/'.$record->token;

                        \Illuminate\Support\Facades\Notification::route('mail', $record->partner_email)
                            ->notify(new \App\Notifications\PartnerAccessNotification($task, $step, $accessUrl, $record));

                        \Filament\Notifications\Notification::make()
                            ->title('Email resent to '.$record->partner_email)
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'used' => 'Used',
                        'expired' => 'Expired',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'active' => $query->whereNull('used_at')->where('expires_at', '>', now()),
                            'used' => $query->whereNotNull('used_at'),
                            'expired' => $query->whereNull('used_at')->where('expires_at', '<=', now()),
                            default => $query,
                        };
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\PartnerAccessTokenResource\Pages\ListPartnerAccessTokens::route('/'),
        ];
    }
}
