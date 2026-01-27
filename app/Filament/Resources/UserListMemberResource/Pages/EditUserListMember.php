<?php

namespace App\Filament\Resources\UserListMemberResource\Pages;

use App\Filament\Resources\UserListMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserListMember extends EditRecord
{
    protected static string $resource = UserListMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
