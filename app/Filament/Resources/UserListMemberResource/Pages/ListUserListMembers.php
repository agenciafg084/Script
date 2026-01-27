<?php

namespace App\Filament\Resources\UserListMemberResource\Pages;

use App\Filament\Resources\UserListMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserListMembers extends ListRecords
{
    protected static string $resource = UserListMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
