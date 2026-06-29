<?php

namespace App\Filament\Resources\PlaceholderVariableResource\Pages;

use App\Filament\Resources\PlaceholderVariableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlaceholderVariable extends EditRecord
{
    protected static string $resource = PlaceholderVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => $this->record->is_system),
        ];
    }
}
