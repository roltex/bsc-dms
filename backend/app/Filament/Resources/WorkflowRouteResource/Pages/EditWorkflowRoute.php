<?php

namespace App\Filament\Resources\WorkflowRouteResource\Pages;

use App\Filament\Resources\WorkflowRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowRoute extends EditRecord
{
    protected static string $resource = WorkflowRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
