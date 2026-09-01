<?php

declare(strict_types=1);

return [
    'timezone' => 'The :attribute must be a valid timezone identifier.',
    'timezone_canonical' => 'The :attribute uses a deprecated timezone name. Use :canonical instead.',
    'timezone_allowed' => 'The :attribute is not one of the supported timezones.',
    'datetime_exists' => 'The :attribute did not happen in :timezone — the clock skips that time when daylight saving begins.',
    'datetime_unambiguous' => 'The :attribute happened twice in :timezone (:first and :second). Please choose which you mean.',
    'timezone_offset' => 'The :attribute must be a valid UTC offset, such as +03:00.',
    'timezone_offset_in_use' => 'No timezone is currently on the :attribute offset.',
    'timezone_abbr' => 'The :attribute must be a known timezone abbreviation.',
    'timezone_country' => 'The :attribute is not a timezone of :country.',
];
