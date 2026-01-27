<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model as EloquentModel;

trait SyncsUserRole
{
    private function syncRoleAndLegacy(EloquentModel $user): void
    {
        $user->refresh()->loadMissing('roles');

        // Get the Spatie role id that was just synced
        $spatieRoleId = $user->roles()->pluck('id')->first(); // integer|null

        // Persist to users.role_id (silent)
        $user->forceFill(['role_id' => $spatieRoleId])->saveQuietly();
        // or, if fillable: $user->updateQuietly(['role_id' => $spatieRoleId]);
    }
}
