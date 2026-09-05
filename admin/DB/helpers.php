<?php

function countryFlag(?string $country): string
{
    return match (strtolower(trim((string)$country))) {
        'united states', 'usa', 'us' => '🇺🇸',
        'canada' => '🇨🇦',
        'india' => '🇮🇳',
        'mexico' => '🇲🇽',
        default => '🌐',
    };
}
