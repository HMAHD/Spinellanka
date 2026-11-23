<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Identity\Services\UserService;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data, UserService $userService) {
                    $role = $data['roles'] ?? 'Staff';
                    unset($data['roles']);

                    return $userService->create($data, $role);
                }),
        ];
    }
}
