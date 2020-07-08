<?php

namespace BugbirdCo\Cabinet\Examples;

use BugbirdCo\Cabinet\Data\Data;
use BugbirdCo\Cabinet\Model;

/**
 * Class Booking
 * @package BugbirdCo\Tests\Cabinet
 *
 * @property string $recordLocator
 * @property double $outstandingBalance
 * @property string $bookingId
 * @property Flight[] $flights
 */
class Booking extends Model
{
    /**
     * @return Booking
     * @throws \ReflectionException
     */
    public static function test()
    {
        var_dump(static::make([
            'recordLocator' => 'ABC123',
            'outstandingBalance' => 1,
            'bookingId' => '123456789',
            'flights' => [
                [
                    'flightId' => '987654321',
                    'origin' => 'LGW',
                    'destination' => 'JFK',
                    'etd' => '2018-01-01T00:00:00'
                ],
                [
                    'flightId' => '987654322',
                    'origin' => 'JFK',
                    'destination' => 'LGW',
                    'etd' => '2018-01-02T00:00:00'
                ],
            ]
        ])->flights[0]->destination);
    }

    public static function make($data, ...$hello)
    {
        return new static(new Data($data));
    }
}