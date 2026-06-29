<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubstitutionResource\Pages;
use App\Models\Substitution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SubstitutionResource extends Resource
{
    protected static ?string $model = Substitution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('user_id')
                    ->label('Original User')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('The user who will be absent.'),
                Select::make('substitute_user_id')
                    ->label('Substitute User')
                    ->relationship('substituteUser', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->different('user_id')
                    ->helperText('The user who takes over responsibilities.'),
                DatePicker::make('from_date')
                    ->required()
                    ->label('From'),
                DatePicker::make('to_date')
                    ->required()
                    ->label('To')
                    ->afterOrEqual('from_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Original User')->searchable()->sortable(),
                TextColumn::make('substituteUser.name')->label('Substitute')->searchable()->sortable(),
                TextColumn::make('from_date')->date('d M Y')->sortable(),
                TextColumn::make('to_date')->date('d M Y')->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->from_date <= now() && $record->to_date >= now())
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('from_date', 'desc')
            ->filters([
                TernaryFilter::make('active')
                    ->queries(
                        true: fn ($query) => $query->where('from_date', '<=', now())->where('to_date', '>=', now()),
                        false: fn ($query) => $query->where('to_date', '<', now()),
                    ),
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
            'index' => Pages\ListSubstitutions::route('/'),
            'create' => Pages\CreateSubstitution::route('/create'),
            'edit' => Pages\EditSubstitution::route('/{record}/edit'),
        ];
    }
}
