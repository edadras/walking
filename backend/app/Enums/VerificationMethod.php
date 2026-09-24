<?php

namespace App\Enums;

enum VerificationMethod: string
{
    /** Inside the geofence for the minimum stay (server-timed). */
    case GeofenceStay = 'geofence_stay';
    /** Geofence + minimum stay + the branch's rotating QR. */
    case GeofenceQr = 'geofence_qr';

    public function label(): string
    {
        return match ($this) {
            self::GeofenceStay => 'حضور در محدوده + حداقل زمان',
            self::GeofenceQr => 'حضور + زمان + QR چرخشی شعبه',
        };
    }
}
