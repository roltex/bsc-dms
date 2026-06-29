<?php

namespace App\Filament\Resources\PlaceholderVariableResource\Pages;

use App\Filament\Resources\PlaceholderVariableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlaceholderVariables extends ListRecords
{
    protected static string $resource = PlaceholderVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
