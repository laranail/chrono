<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * The four shapes a timezone picker actually wants.
 *
 * `Flat`, `Grouped` and `Formed` reproduce the output of `simtabi/pheg`'s `Time::getTimezones()`
 * so a migrating caller gets the same arrays. `Payload` is the one that package lacked: an array of
 * objects with search tokens and text direction, which is what a JavaScript component needs.
 */
enum SelectShape: string
{
    /** `['Africa/Nairobi' => 'Nairobi, Kenya (UTC +03:00)']` */
    case Flat = 'flat';

    /** `['Africa' => ['Africa/Nairobi' => 'Nairobi (UTC +03:00)']]` */
    case Grouped = 'grouped';

    /** `['Africa' => ['Africa/Nairobi' => 'Africa/Nairobi (UTC +03:00)']]` */
    case Formed = 'formed';

    /** `[['id' => …, 'label' => …, 'offset' => …, 'search' => …, 'dir' => …], …]` */
    case Payload = 'payload';
}
