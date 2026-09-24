<?php

namespace App\Filament\Sponsor\Pages;

use App\Domain\Audit\AuditLogger;
use App\Enums\SponsorRole;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\SponsorUser;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/** Self sign-up: creates a PENDING sponsor and its owner; nothing goes live before admin approval. */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sponsor_name')->label('نام کسب‌وکار')->required()->maxLength(120),
            $this->getNameFormComponent()->label('نام و نام خانوادگی'),
            TextInput::make('phone')->label('تلفن همراه')->tel()->required()->regex('/^09\d{9}$/'),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    protected function getUserModel(): string
    {
        return SponsorUser::class;
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $sponsor = Sponsor::query()->create(['name' => $data['sponsor_name'], 'contact_phone' => $data['phone'], 'contact_email' => $data['email'], 'status' => SponsorStatus::Pending]);
            $owner = SponsorUser::query()->create([
                'sponsor_id' => $sponsor->id, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'password' => $data['password'], 'role' => SponsorRole::Owner,
            ]);
            app(AuditLogger::class)->log('sponsor.registered', $sponsor, actor: $owner);

            return $owner;
        });
    }
}
