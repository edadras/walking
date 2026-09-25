<?php

namespace App\Filament\Admin\Resources\Organizations\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Organization\OrganizationService;
use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $org = Organization::query()->create([
                ...array_diff_key($data, array_flip(['owner_name', 'owner_email', 'owner_password'])),
                'join_code' => OrganizationService::newJoinCode(),
                'status' => 'active',
            ]);
            OrganizationUser::query()->create(['organization_id' => $org->id, 'name' => $data['owner_name'], 'email' => $data['owner_email'],
                'password' => $data['owner_password'], 'role' => 'admin']);
            app(AuditLogger::class)->log('organization.created', $org);

            return $org;
        });
    }
}
