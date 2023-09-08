<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\PostcodesIo\Tests;

use BeastBytes\PostcodesIo\PostcodesIo;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostcodesIoTest extends TestCase
{
    private const EMPTY_OUTCODE = '';
    private const INVALID_OUTCODE = 'ZZ1';
    private const VALID_OUTCODES = ['B12', 'L19', 'NN4', 'SW1A'];
    private const TERMINATED_POSTCODES = ['BN1 1AU', 'E1W 1UU'];
    private const INVALID_POSTCODES = ['ZZ1 2XY', '23A 1NN', 'QY12 123E'];
    private const PARTIAL_POSTCODES = ['B12', 'L19 2', 'NN4 8L', 'SW1A'];
    private const VALID_POSTCODES = ['SW1A 2AA', 'SA99 1AR', 'CF99 1SN', 'EH99 1SP', 'L3 1HU'];
    private const PLACES_QUERY = ['North', 'East', 'South', 'West'];

    private PostcodesIo $postcodesIo;

    public static function randomPostcodeProvider(): Generator
    {
        foreach ([self::EMPTY_OUTCODE, ...self::VALID_OUTCODES, self::INVALID_OUTCODE] as $outcode) {
            $key = $outcode === self::EMPTY_OUTCODE ? 'Random' : $outcode;
            yield $key => ['outcode' => $outcode];
        }
    }

    public static function partPostcodeProvider(): Generator
    {
        foreach (self::PARTIAL_POSTCODES as $postcode) {
            yield $postcode => ['postcode' => $postcode, 'limit' => random_int(1, PostcodesIo::LIMIT_MAX)];
        }
    }

    public static function terminatedPostcodeProvider(): Generator
    {
        foreach (self::TERMINATED_POSTCODES as $terminatedPostcode) {
            yield $terminatedPostcode => ['postcode' => $terminatedPostcode, 'isTerminated' => true];
        }

        $postcode = array_rand(array_flip(self::VALID_POSTCODES));
        yield $postcode => ['postcode' => $postcode, 'isTerminated' => false];
    }

    public static function postcodeValidationProvider(): Generator
    {
        foreach (self::INVALID_POSTCODES as $postcode) {
            yield $postcode => ['postcode' => $postcode, 'isValid' => false];
        }

        foreach (self::VALID_POSTCODES as $postcode) {
            yield $postcode => ['postcode' => $postcode, 'isValid' => true];
        }
    }

    public static function postcodeLookupProvider(): Generator
    {
        $validPostcode = [
            'postcode' => array_rand(array_flip(self::VALID_POSTCODES)),
            'isValid' => true
        ];
        $invalidPostcode = [
            'postcode' => array_rand(array_flip(self::INVALID_POSTCODES)),
            'isValid' => false
        ];

        foreach ([$invalidPostcode, $validPostcode] as $postcode) {
            yield $postcode['postcode'] => $postcode;
        }
    }

    public static function outcodeProvider(): Generator
    {
        foreach (self::VALID_OUTCODES as $outcode) {
            yield $outcode => [
                'outcode' => $outcode,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX),
                'radius' => random_int(1, PostcodesIo::OUTCODE_RADIUS_MAX)
            ];
        }
    }

    public static function postcodeProvider(): Generator
    {
        foreach (self::VALID_POSTCODES as $postcode) {
            yield $postcode => [
                'postcode' => $postcode,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX),
                'radius' => random_int(1, PostcodesIo::POSTCODE_RADIUS_MAX)
            ];
        }
    }

    public static function reverseGeocodingProvider(): Generator
    {
        foreach ([
            'Northampton Lift Tower' => [
                'lat' =>52.2364669,
                'lon' => -0.9189112,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX)
            ],
            'Blackpool Tower' => [
                'lat' => 53.8158865,
                'lon' => -3.060163,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX)
            ],
            'Dundas Castle' => [
                'lat' => 55.975125,
                'lon' => -3.4517133,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX)
            ],
            'Manorbier Castle' => [
                'lat' => 51.6413659,
                'lon' => -4.7933543,
                'limit' => random_int(1, PostcodesIo::LIMIT_MAX)
            ],
        ] as $name => $location) {
            yield $name => $location;
        }
    }

    public static function placeProvider(): Generator
    {
        foreach (self::PLACES_QUERY as $place) {
            yield $place => ['place' => $place];
        }
    }

    protected function setUp(): void
    {
        $this->postcodesIo = new PostcodesIo();
    }

    #[dataProvider('randomPostcodeProvider')]
    public function testRandomPostcode(string $outcode): void
    {
        $result = $this
            ->postcodesIo
            ->randomPostcode($outcode)
        ;

        if ($outcode === self::INVALID_OUTCODE) {
            $this->assertNull($result);
        } else {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('incode', $result);
            $this->assertArrayHasKey('outcode', $result);
            $this->assertArrayHasKey('postcode', $result);

            if ($outcode !== self::EMPTY_OUTCODE) {
                $this->assertSame($outcode, $result['outcode']);
            }
        }
    }

    #[dataProvider('terminatedPostcodeProvider')]
    public function testTerminatedPostcode(string $postcode, bool $isTerminated): void
    {
        $result = $this
            ->postcodesIo
            ->terminatedPostcode($postcode)
        ;

        if (!$isTerminated) {
            $this->assertFalse($result);
        } else {
            $this->assertIsArray($result);
            $this->arrayHasKey('month_terminated', $result);
            $this->arrayHasKey('year_terminated', $result);
        }
    }

    public function testPostcodeBulkReverseGeocoding(): void
    {
        $geolocations = [
            [
                'longitude' => -3.15807731271522,
                'latitude' => 51.4799900627036
            ],
            [
                'longitude' => -1.12935802905177,
                'latitude' => 50.7186356978817,
                'limit' => 99,
                'radius' => 500
            ]
        ];

        $result = $this
            ->postcodesIo
            ->postcodeBulkReverseGeocoding($geolocations)
        ;

        $this->assertIsArray($result);
        $this->assertCount(count($geolocations), $result);
        $this->assertArrayHasKey('result', $result[0]);
        $this->assertArrayHasKey('postcode', $result[0]['result'][0]);
    }

    #[dataprovider('reverseGeocodingProvider')]
    public function testOutcodeReverseGeocoding(float $lat, float $lon, int $limit): void
    {
        $radius = random_int(5000, PostcodesIo::OUTCODE_RADIUS_MAX);
        $result = $this
            ->postcodesIo
            ->outcodeReverseGeocoding($lat, $lon, $limit, $radius)
        ;

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual($limit, count($result));
        $index = array_rand($result);
        $this->assertArrayHasKey('outcode', $result[$index]);
    }

    #[dataprovider('reverseGeocodingProvider')]
    public function testPostcodeReverseGeocoding(float $lat, float $lon, int $limit): void
    {
        $radius = random_int(100, PostcodesIo::POSTCODE_RADIUS_MAX);
        $result = $this
            ->postcodesIo
            ->postcodeReverseGeocoding($lat, $lon, $limit, $radius);
        ;

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual($limit, count($result));
        $index = array_rand($result);
        $this->assertArrayHasKey('postcode', $result[$index]);
    }

    #[dataProvider('partPostcodeProvider')]
    public function testPostcodeAutocomplete(string $postcode, int $limit): void
    {
        $result = $this
            ->postcodesIo
            ->postcodeAutocomplete($postcode, $limit)
        ;

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual($limit, count($result));
    }

    public function testPostcodeBulkLookup(): void
    {
        $result = $this
            ->postcodesIo
            ->postcodeBulkLookup(self::VALID_POSTCODES)
        ;

        $this->assertIsArray($result);
        $this->assertCount(count(self::VALID_POSTCODES), $result);

        foreach ($result as $rslt) {
            $this->assertCount(2, $rslt);
            $this->assertTrue(in_array($rslt['query'], self::VALID_POSTCODES));
            $this->assertTrue(in_array($rslt['result']['postcode'], self::VALID_POSTCODES));
        }
    }

    #[dataProvider('partPostcodeProvider')]
    public function testPostcodeQuery(string $postcode): void
    {
        $result = $this
            ->postcodesIo
            ->postcodeQuery($postcode)
        ;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('postcode', $result[0]);
    }

    #[dataProvider('postcodeValidationProvider')]
    public function testPostcodeValidation(string $postcode, bool $isValid): void
    {
        $result = $this
            ->postcodesIo
            ->postcodeValidation($postcode)
        ;

        $this->assertSame($isValid, $result);
    }

    #[dataProvider('outcodeProvider')]
    public function testNearestOutcode(string $outcode, int $limit, int $radius): void
    {
        $result = $this
            ->postcodesIo
            ->nearestOutcode($outcode, $limit, $radius)
        ;

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual($limit, count($result));
        $index = array_rand($result);
        $this->arrayHasKey('outcode', $result[$index]);
    }

    #[dataProvider('postcodeProvider')]
    public function testNearestPostcode(string $postcode, int $limit, int $radius): void
    {
        $result = $this
            ->postcodesIo
            ->nearestPostcode($postcode, $limit, $radius)
        ;

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual($limit, count($result));
        $index = array_rand($result);
        $this->arrayHasKey('postcode', $result[$index]);
    }

    #[dataProvider('outcodeProvider')]
    public function testOutcodeLookup(string $outcode): void
    {
        $result = $this
            ->postcodesIo
            ->outcodeLookup($outcode)
        ;

        $this->assertIsArray($result);
        $this->arrayHasKey('outcode', $result);
        $this->assertSame($outcode, $result['outcode']);
    }

    #[dataProvider('postcodeLookupProvider')]
    public function testPostcodeLookup(string $postcode, bool $isValid): void
    {
        $result = $this
            ->postcodesIo
            ->postcodeLookup($postcode)
        ;

        if ($isValid) {
            $this->assertIsArray($result);
            $this->arrayHasKey('postcode', $result);
            $this->assertSame($postcode, $result['postcode']);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testScottishPostcodeLookup(): void
    {
        $postcode = 'EH99 1SP';
        $result = $this
            ->postcodesIo
            ->scottishPostcodeLookup($postcode)
        ;

        $this->assertIsArray($result);
        $this->arrayHasKey('postcode', $result);
        $this->assertSame($postcode, $result['postcode']);
    }

    #[dataProvider('placeProvider')]
    public function testPlaceQuery(string $place): void
    {
        $result = $this
            ->postcodesIo
            ->placeQuery($place)
        ;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result[0]);
    }

    public function testPlaceLookup(): void
    {
        $place = $this
            ->postcodesIo
            ->randomPlace()
        ;
        $result = $this
            ->postcodesIo
            ->placeLookup($place['code'])
        ;

        $this->assertSame($place, $result);
    }

    public function testRandomPlace(): void
    {
        $result = $this
            ->postcodesIo
            ->randomPlace()
        ;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
    }
}
