<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Timezone datasets
|--------------------------------------------------------------------------
|
| Two kinds, deliberately kept apart.
|
| RULE-BASED fixtures come from statute or from history that cannot change —
| US and EU daylight saving, Samoa's 2011 dateline move. Their values are
| hard-coded, because if one of these ever changes the test *should* fail.
|
| DECREE-BASED fixtures depend on a government's current intent — Morocco's
| Ramadan shifts, Iran, Chile, Egypt, Fiji. Those are asserted by shape and
| carry the `tzdata` group, which the main suite excludes and a weekly
| workflow runs on its own. Mixing the two is how a build turns red because
| a CI image was rebuilt.
|
*/

dataset('dst savings', [
    // identifier, expected saving in seconds — never assume an hour
    'New York (1 hour)' => ['America/New_York', 3600],
    'Lord Howe (30 minutes)' => ['Australia/Lord_Howe', 1800],
    'Troll (2 hours)' => ['Antarctica/Troll', 7200],
    'Nairobi (none)' => ['Africa/Nairobi', 0],
]);

dataset('sub-hour offsets', [
    'Kathmandu +05:45' => ['Asia/Kathmandu', 20700, '+05:45'],
    'Chatham +12:45' => ['Pacific/Chatham', 45900, '+12:45'],
    'Eucla +08:45' => ['Australia/Eucla', 31500, '+08:45'],
    'St Johns -03:30' => ['America/St_Johns', -12600, '-03:30'],
]);

dataset('rule-less zones', [
    'UTC' => ['UTC'],
    'Etc/GMT+5' => ['Etc/GMT+5'],
    'offset zone' => ['+03:00'],
    'abbreviation zone' => ['CEST'],
]);

dataset('aliases', [
    'Asia/Calcutta' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'US/Eastern' => ['US/Eastern', 'America/New_York'],
    'Africa/Asmera' => ['Africa/Asmera', 'Africa/Asmara'],
    'America/Buenos_Aires' => ['America/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    'Europe/Kiev' => ['Europe/Kiev', 'Europe/Kyiv'],
    'America/Godthab' => ['America/Godthab', 'America/Nuuk'],
    'Japan' => ['Japan', 'Asia/Tokyo'],
    'Zulu' => ['Zulu', 'UTC'],
]);

dataset('resolvable inputs', [
    'exact identifier' => ['Africa/Nairobi', 'Africa/Nairobi'],
    'lowercased' => ['africa/nairobi', 'Africa/Nairobi'],
    'deprecated alias' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'offset string' => ['+03:00', '+03:00'],
    'GMT offset' => ['GMT+3', '+03:00'],
    'single-zone country' => ['KE', 'Africa/Nairobi'],
    'locale' => ['en_KE', 'Africa/Nairobi'],
    'city' => ['Nairobi', 'Africa/Nairobi'],
    'city with a space' => ['new york', 'America/New_York'],
    'Windows id' => ['Pacific Standard Time', 'America/Los_Angeles'],
]);

dataset('offset spellings', [
    '+03:00' => ['+03:00', 10800],
    '-0530' => ['-0530', -19800],
    '-05:30' => ['-05:30', -19800],
    '+3' => ['+3', 10800],
    'GMT+3' => ['GMT+3', 10800],
    'UTC-5' => ['UTC-5', -18000],
    'Z' => ['Z', 0],
    '+05:45' => ['+05:45', 20700],
    'bare seconds' => ['10800', 10800],
    'negative seconds' => ['-19800', -19800],
    'LMT with seconds' => ['-15:56:08', -57368],
]);
