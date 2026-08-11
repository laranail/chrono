<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * A field a presented zone can carry.
 *
 * Choosing per use case is the point. A `<select>` needs two fields and a JSON API needs a dozen;
 * shipping the dozen to the select means sending eight times the bytes to render one string, and
 * shipping two to the API means the client has to ask again for anything else.
 */
enum ZoneField: string
{
    case Id = 'id';
    case Label = 'label';
    case City = 'city';
    case Country = 'country';
    case CountryName = 'country_name';
    case Continent = 'continent';
    case Offset = 'offset';
    case OffsetLabel = 'offset_label';
    case Abbreviation = 'abbreviation';
    case Dst = 'dst';
    case ObservesDst = 'observes_dst';
    case LocalTime = 'local_time';
    case Latitude = 'latitude';
    case Longitude = 'longitude';
    case Flag = 'flag';
    case Search = 'search';
    case Dir = 'dir';
    case Deprecated = 'deprecated';

    /** Fields whose value costs a transition scan, so a preset can avoid them when not needed. */
    public function isExpensive(): bool
    {
        return $this === self::ObservesDst;
    }
}
