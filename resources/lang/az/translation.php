<?php

use App\Enums\BookingStatusType;
use App\Enums\ResidenceStatusType;

return [

    /*
    |--------------------------------------------------------------------------
    | Tərcümə Sətirləri
    |--------------------------------------------------------------------------
    |
    | Bu fayl, rezervasiya və yaşayış statuslarının tərcümələrini ehtiva edir.
    |
    */

    ResidenceStatusType::Created->name => 'Yaradıldı',
    ResidenceStatusType::Edited->name => 'Redaktə edildi',
    ResidenceStatusType::Approved->name => 'Təsdiqləndi',
    ResidenceStatusType::Rejected->name => 'Rədd edildi',
    BookingStatusType::Canceled->name => 'Ləğv edildi',
    BookingStatusType::Paid->name => 'Ödənildi',

    'booking_successfully_created' => 'Rezervasiya uğurla yaradıldı',
    'booking_successfully_updated' => 'Rezervasiya uğurla yeniləndi',
    'booking_found' => 'Rezervasiya tapıldı',

    'residence_successfully_created' => 'Yaşayış yeri uğurla yaradıldı',
    'residence_successfully_updated' => 'Yaşayış yeri uğurla yeniləndi',
    'residence_found' => 'Yaşayış yeri tapıldı',

    'user_details_successfully_updated' => 'İstifadəçi məlumatları uğurla yeniləndi',

    'guide_successfully_created' => 'Bələdçi uğurla yaradıldı',
    'guide_successfully_updated' => 'Bələdçi uğurla yeniləndi',
    'guide_found' => 'Bələdçi tapıldı',

    'transport_successfully_created' => 'Nəqliyyat uğurla yaradıldı',
    'transport_successfully_updated' => 'Nəqliyyat uğurla yeniləndi',
    'transport_found' => 'Nəqliyyat tapıldı',
];
