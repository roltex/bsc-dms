<?php

namespace App\Filament\Resources;

use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->components([
            Section::make('Task Overview')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('id')->label('Task ID')->prefix('#'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (TaskStatus $state) => $state->label())
                            ->color(fn (TaskStatus $state) => match ($state) {
                                TaskStatus::Draft => 'gray',
                                TaskStatus::Approved => 'success',
                                TaskStatus::Rejected => 'danger',
                                TaskStatus::Archived => 'info',
                                default => 'warning',
                            }),
                        TextEntry::make('route_type')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => ucfirst($state)),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('category.name')->label('Category'),
                        TextEntry::make('partner.name')->label('Partner'),
                        TextEntry::make('initiator.name')->label('Initiator'),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('assignedLawyer.name')->label('Assigned Lawyer')->placeholder('Auto-assigned'),
                        TextEntry::make('current_step')
                            ->formatStateUsing(fn ($state, $record) => "Step {$state} of {$record->totalSteps()}"),
                        TextEntry::make('fast_tracked')
                            ->label('Fast-tracked')
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                    ]),
                ]),

            Section::make('Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('deadline')
                            ->dateTime('d M Y')
                            ->placeholder('No deadline')
                            ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
                        TextEntry::make('registration_number')->placeholder('Not yet assigned'),
                        TextEntry::make('created_at')->dateTime('d M Y, H:i'),
                    ]),
                    TextEntry::make('commercial_terms')
                        ->columnSpanFull()
                        ->placeholder('—'),
                    Grid::make(2)->schema([
                        TextEntry::make('validity_from')->date('d M Y')->placeholder('—'),
                        TextEntry::make('validity_to')->date('d M Y')->placeholder('—'),
                    ]),
                ]),

            Section::make('Documents')
                ->schema([
                    RepeatableEntry::make('documents')
                        ->schema([
                            Grid::make(5)->schema([
                                TextEntry::make('version')->prefix('v'),
                                TextEntry::make('mime_type')->label('Type'),
                                TextEntry::make('is_signed')
                                    ->label('Signed')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                                TextEntry::make('signer.name')->label('Signed by')->placeholder('—'),
                                TextEntry::make('created_at')->dateTime('d M Y, H:i')->label('Uploaded'),
                            ]),
                        ])
                        ->placeholder('No documents uploaded yet.'),
                ]),

            Section::make('Activity Log')
                ->schema([
                    RepeatableEntry::make('activities')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('action')
                                    ->badge()
                                    ->color(fn (string $state) => match ($state) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'created', 'submitted' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('user.name')->label('By'),
                                TextEntry::make('comment')->placeholder('—'),
                                TextEntry::make('created_at')->dateTime('d M Y, H:i')->label('When'),
                            ]),
                        ])
                        ->placeholder('No activity yet.'),
                ])
                ->collapsed(),

            Section::make('Reviewers')
                ->schema([
                    RepeatableEntry::make('reviewers')
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('email'),
                        ])
                        ->placeholder('No additional reviewers.'),
                ])
                ->collapsed(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                    ->required(),
                Select::make('document_category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('partner_id')
                    ->relationship('partner', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('initiator_id')
                    ->relationship('initiator', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('assigned_lawyer_id')
                    ->relationship('assignedLawyer', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('route_type')
                    ->options(['standard' => 'Standard (6-step)', 'simplified' => 'Simplified (2-step)'])
                    ->required(),
                TextInput::make('current_step')->numeric()->default(0),
                DatePicker::make('deadline')->nullable(),
                Textarea::make('commercial_terms')->columnSpanFull(),
                DatePicker::make('validity_from'),
                DatePicker::make('validity_to'),
                TextInput::make('registration_number')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('category.name')->label('Category')->searchable()->sortable(),
                TextColumn::make('partner.name')->label('Partner')->searchable()->sortable(),
                TextColumn::make('initiator.name')->label('Initiator')->sortable(),
                TextColumn::make('route_type')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (TaskStatus $state) => $state->label())
                    ->color(fn (TaskStatus $state) => match ($state) {
                        TaskStatus::Draft => 'gray',
                        TaskStatus::Approved => 'success',
                        TaskStatus::Rejected => 'danger',
                        TaskStatus::Archived => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('current_step')
                    ->formatStateUsing(fn ($state, $record) => "{$state}/{$record->totalSteps()}")
                    ->label('Step'),
                TextColumn::make('registration_number')->placeholder('—')->toggleable(),
                TextColumn::make('deadline')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Docs'),
                TextColumn::make('updated_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                    ->multiple(),
                SelectFilter::make('route_type')
                    ->options(['standard' => 'Standard', 'simplified' => 'Simplified']),
                SelectFilter::make('document_category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'commercial_terms'];
    }
}
