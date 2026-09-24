<?php

namespace App\Filament\Admin\Concerns;

use App\Domain\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * For Filament create/edit pages: records an audit entry with only the changed
 * attributes. Hidden attributes (passwords, secrets) are never written.
 */
trait AuditsRecordChanges
{
    /** @var array<string, mixed> */
    protected array $auditOriginal = [];

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->auditOriginal = $record->getOriginal();
        $record->update($data);

        $changes = array_diff_key($record->getChanges(), array_flip([...$record->getHidden(), 'updated_at']));
        if ($changes !== []) {
            app(AuditLogger::class)->log(
                $this->auditAction('updated', $record),
                $record,
                array_intersect_key($this->auditOriginal, $changes),
                $changes,
            );
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        $this->auditCreate();
    }

    protected function auditCreate(): void
    {
        $record = $this->getRecord();
        app(AuditLogger::class)->log(
            $this->auditAction('created', $record),
            $record,
            new: array_diff_key($record->attributesToArray(), array_flip($record->getHidden())),
        );
    }

    private function auditAction(string $verb, Model $record): string
    {
        return str($record->getTable())->singular().'.'.$verb;
    }
}
