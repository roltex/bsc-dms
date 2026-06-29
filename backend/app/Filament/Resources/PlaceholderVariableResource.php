<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlaceholderVariableResource\Pages;
use App\Models\PlaceholderVariable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlaceholderVariableResource extends Resource
{
    protected static ?string $model = PlaceholderVariable::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static string|\UnitEnum|null $navigationGroup = 'Templates';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $navigationLabel = 'Placeholders';

    protected static ?string $modelLabel = 'Placeholder';

    protected static ?string $pluralModelLabel = 'Placeholders';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(100)
                    ->unique(table: 'placeholder_variables', ignoreRecord: true)
                    ->placeholder('e.g. CONTRACT_CITY')
                    ->helperText('Use UPPER_SNAKE_CASE. This is used as {{KEY}} in templates.')
                    ->formatStateUsing(fn (?string $state) => $state)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(str_replace(' ', '_', trim($state))) : null)
                    ->disabled(fn (?PlaceholderVariable $record) => $record?->is_system ?? false),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Contract City'),
                Select::make('source')
                    ->required()
                    ->options(PlaceholderVariable::SOURCES)
                    ->default('manual')
                    ->helperText('Where the value comes from when auto-filling.'),
                TextInput::make('default_value')
                    ->maxLength(255)
                    ->placeholder('Optional default value')
                    ->helperText('Pre-filled when this placeholder appears in a template.'),
                TextInput::make('description')
                    ->maxLength(255)
                    ->placeholder('Brief description of this placeholder')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive placeholders are hidden from suggestions.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Placeholder')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state) => '{{' . $state . '}}')
                    ->copyable()
                    ->copyableState(fn (string $state) => '{{' . $state . '}}')
                    ->color('primary'),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PlaceholderVariable::SOURCES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'partner', 'contact' => 'info',
                        'task', 'workflow' => 'warning',
                        'settings', 'auto', 'custom' => 'gray',
                        'user', 'approval' => 'success',
                        'date', 'numbering' => 'primary',
                        'signature', 'legal' => 'danger',
                        'company', 'category', 'template' => 'info',
                        'financial', 'bank' => 'warning',
                        'address' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('default_value')
                    ->label('Default')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_system')
                    ->boolean()
                    ->label('System')
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('source')
                    ->options(PlaceholderVariable::SOURCES),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->hidden(fn (PlaceholderVariable $record) => $record->is_system),
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
            'index' => Pages\ListPlaceholderVariables::route('/'),
            'create' => Pages\CreatePlaceholderVariable::route('/create'),
            'edit' => Pages\EditPlaceholderVariable::route('/{record}/edit'),
        ];
    }
}
