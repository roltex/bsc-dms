<?php

namespace App\Filament\Resources\PartnerAccessTokenResource\Pages;

use App\Filament\Resources\PartnerAccessTokenResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerAccessTokens extends ListRecords
{
    protected static string $resource = PartnerAccessTokenResource::class;

    protected ?string $heading = 'Partner Access Links';
}
