<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Filament\Resources\PartnerResource\RelationManagers;
use App\Models\Partner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Business';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('bin_iin')
                    ->required()
                    ->maxLength(20)
                    ->unique(table: 'partners', ignoreRecord: true)
                    ->label('BIN/IIN'),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Textarea::make('bank_details')
                    ->maxLength(65535)
                    ->rows(3)
                    ->columnSpanFull(),
                Fieldset::make('Blacklist')
                    ->schema([
                        Toggle::make('is_blacklisted')
                            ->label('Blacklisted')
                            ->dehydrated(false)
                            ->afterStateHydrated(fn ($state, $set, $record) => $set('is_blacklisted', $record?->blacklisted_at !== null))
                            ->reactive(),
                        Textarea::make('blacklist_reason')
                            ->label('Reason (mandatory when blacklisting)')
                            ->rows(2)
                            ->visible(fn ($get) => $get('is_blacklisted'))
                            ->required(fn ($get) => $get('is_blacklisted')),
                        DateTimePicker::make('blacklisted_at')
                            ->label('Blacklisted Since')
                            ->visible(fn ($get) => $get('is_blacklisted')),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->components([
            Section::make('Partner Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('bin_iin')->label('BIN/IIN'),
                        TextEntry::make('email')->placeholder('—'),
                    ]),
                    TextEntry::make('bank_details')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Reliability')
                ->schema([
                    TextEntry::make('reliability_data')
                        ->label('Data')
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : 'No data')
                        ->columnSpanFull(),
                ]),
            Section::make('Blacklist Status')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('blacklisted_at')
                            ->label('Status')
                            ->formatStateUsing(fn ($state) => $state ? 'BLACKLISTED' : 'Active')
                            ->badge()
                            ->color(fn ($state) => $state ? 'danger' : 'success'),
                        TextEntry::make('blacklist_reason')->placeholder('—'),
                        TextEntry::make('blacklistedByUser.name')->label('Blacklisted by')->placeholder('—'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('bin_iin')->searchable()->sortable()->label('BIN/IIN'),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('bank_details')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('blacklisted_at')
                    ->label('Blacklisted')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->blacklisted_at !== null)
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Docs')
                    ->badge(),
                TextColumn::make('created_at')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('blacklisted')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('blacklisted_at'),
                        false: fn ($query) => $query->whereNull('blacklisted_at'),
                    ),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'view' => Pages\ViewPartner::route('/{record}'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'bin_iin'];
    }
}
