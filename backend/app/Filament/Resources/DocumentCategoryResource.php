<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\DocumentCategoryResource\Pages;
use App\Models\DocumentCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Supply Contracts'),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(table: 'document_categories', ignoreRecord: true)
                    ->placeholder('e.g. supply_contracts')
                    ->helperText('Unique code used for routing and filtering.'),
                Select::make('default_lawyer_id')
                    ->label('Default Lawyer')
                    ->relationship(
                        'defaultLawyer',
                        'name',
                        fn ($query) => $query->where('role', UserRole::Lawyer)
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Tasks in this category will be auto-assigned to this lawyer for review.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable()->badge()->color('gray'),
                TextColumn::make('defaultLawyer.name')
                    ->label('Default Lawyer')
                    ->placeholder('Not assigned'),
                TextColumn::make('templates_count')
                    ->counts('templates')
                    ->label('Templates')
                    ->badge(),
            ])
            ->defaultSort('name')
            ->actions([
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
            'index' => Pages\ListDocumentCategories::route('/'),
            'create' => Pages\CreateDocumentCategory::route('/create'),
            'edit' => Pages\EditDocumentCategory::route('/{record}/edit'),
        ];
    }
}
