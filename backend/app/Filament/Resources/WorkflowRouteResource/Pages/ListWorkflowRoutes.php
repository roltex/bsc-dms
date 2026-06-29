<?php

namespace App\Filament\Resources\WorkflowRouteResource\Pages;

use App\Filament\Resources\WorkflowRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowRoutes extends ListRecords
{
    protected static string $resource = WorkflowRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
