<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Business';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('category')
                    ->required()
                    ->options(array_combine(InventoryItem::CATEGORIES, InventoryItem::CATEGORIES))
                    ->searchable(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('€')
                    ->maxValue(99999999.99),
                Select::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'GBP' => 'GBP',
                        'GEL' => 'GEL',
                    ])
                    ->default('EUR'),
                TextInput::make('serial_number')
                    ->maxLength(255)
                    ->unique(table: 'inventory_items', ignoreRecord: true),
                TextInput::make('model_number')
                    ->maxLength(255),
                Select::make('status')
                    ->options([
                        'available' => 'Available',
                        'in_use' => 'In Use',
                        'damaged' => 'Damaged',
                        'retired' => 'Retired',
                    ])
                    ->default('available')
                    ->required(),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->directory('inventory-items')
                    ->disk('public')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->maxLength(5000)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->components([
            Section::make('Item Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('title'),
                        TextEntry::make('category')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'available' => 'success',
                                'in_use' => 'info',
                                'damaged' => 'warning',
                                'retired' => 'gray',
                                default => 'gray',
                            }),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('price')
                            ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                            ->placeholder('—'),
                        TextEntry::make('serial_number')->placeholder('—'),
                        TextEntry::make('model_number')->placeholder('—'),
                    ]),
                    TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                    ImageEntry::make('image_path')
                        ->label('Image')
                        ->disk('public')
                        ->height(200)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->size(36)
                    ->defaultImageUrl(fn () => 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="rgb(148,163,184)" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>')),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('category')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->searchable()
                    ->toggleable()
                    ->fontFamily('mono'),
                TextColumn::make('model_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'in_use' => 'info',
                        'damaged' => 'warning',
                        'retired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Available',
                        'in_use' => 'In Use',
                        'damaged' => 'Damaged',
                        'retired' => 'Retired',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'in_use' => 'In Use',
                        'damaged' => 'Damaged',
                        'retired' => 'Retired',
                    ]),
                SelectFilter::make('category')
                    ->options(array_combine(InventoryItem::CATEGORIES, InventoryItem::CATEGORIES)),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
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
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'serial_number', 'model_number'];
    }
}
