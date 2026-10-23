<?php

namespace App\Filament\Platform\Resources\UserResource\Pages;

use App\Filament\Platform\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
