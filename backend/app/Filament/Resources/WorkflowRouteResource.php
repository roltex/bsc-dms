<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowRouteResource\Pages;
use App\Models\DocumentCategory;
use App\Models\WorkflowRoute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowRouteResource extends Resource
{
    protected static ?string $model = WorkflowRoute::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Route Types';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Standard (6-step)'),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->unique(table: 'workflow_routes', ignoreRecord: true)
                    ->placeholder('e.g. standard'),
                Textarea::make('description')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Select::make('documentCategories')
                    ->label('Document Categories')
                    ->relationship('documentCategories', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText('Link this route to specific document categories. Leave empty to show for all categories.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive routes won\'t be available for new tasks.'),
                Toggle::make('is_default')
                    ->label('Default Route')
                    ->helperText('The default route is pre-selected when creating tasks.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('documentCategories.name')
                    ->label('Categories')
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->limitList(3),
                TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Steps')
                    ->badge()
                    ->color('primary'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Tasks')
                    ->badge()
                    ->color('success'),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                \Filament\Actions\Action::make('flow_builder')
                    ->label('Edit Flow')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (WorkflowRoute $record) => url('/admin/workflow-builder?record='.$record->id))
                    ->color('primary'),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
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
            'index' => Pages\ListWorkflowRoutes::route('/'),
            'create' => Pages\CreateWorkflowRoute::route('/create'),
            'edit' => Pages\EditWorkflowRoute::route('/{record}/edit'),
        ];
    }
}
