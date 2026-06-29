<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinalizedDocumentResource\Pages;
use App\Models\FinalizedDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FinalizedDocumentResource extends Resource
{
    protected static ?string $model = FinalizedDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Document name'),
                Select::make('category')
                    ->options(FinalizedDocument::CATEGORIES)
                    ->required()
                    ->native(false),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Uploaded by'),
                FileUpload::make('path')
                    ->label('Document File')
                    ->disk('local')
                    ->directory('finalized-documents')
                    ->maxSize(20480)
                    ->columnSpanFull()
                    ->helperText('Upload the finalized document. Max 20MB.'),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Optional notes about this document...'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => FinalizedDocument::CATEGORIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'licenses' => 'success',
                        'court_materials' => 'danger',
                        'corporate_docs' => 'info',
                        'government_inspections' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('Uploaded by')->sortable(),
                TextColumn::make('size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 1).' KB' : '—'),
                TextColumn::make('created_at')->date('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->options(FinalizedDocument::CATEGORIES),
            ])
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
            'index' => Pages\ListFinalizedDocuments::route('/'),
            'create' => Pages\CreateFinalizedDocument::route('/create'),
            'edit' => Pages\EditFinalizedDocument::route('/{record}/edit'),
        ];
    }
}
