<?php

namespace App\Filament\Resources\UserListMemberResource\Pages;

use App\Filament\Resources\UserListMemberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserListMember extends ViewRecord
{
    protected static string $resource = UserListMemberResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()];
    }
}
