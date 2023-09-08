<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

namespace BeastBytes\PostcodesIo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;
use Yiisoft\Json\Json;

/**
 * PostcodesIo Class
 *
 * Integrates the @link{http://postcodes.io/ Postcodes.io Postcode & Geolocation API for the UK}
 */
final class PostcodesIo
{
    public const API_OUTCODES = 'outcodes';
    public const API_PLACES = 'places';
    public const API_POSTCODES = 'postcodes';
    public const BASE_URI = 'https://api.postcodes.io/';
    public const GEOLOCATIONS_MAX = 100;
    public const LATITUDE_MAX = 60.85; // Out Stack, Shetland
    public const LATITUDE_MIN = 49.85; // Pednathise Head, Western Rocks, Isles of Scilly
    public const LIMIT_MAX = 100;
    public const LONGITUDE_MAX = 52.483333; // Lowestoft Ness, Suffolk
    public const LONGITUDE_MIN = -8.638; // Soay, St Kilda
    public const OUTCODE_RADIUS_MAX = 25000;
    public const POSTCODE_RADIUS_MAX = 2000;
    public const POSTCODES_MAX = 100;

    private Client $client;
    private ResponseInterface $response;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => self::BASE_URI]);
    }

    /**
     * Postcode Lookup
     *
     * @param string $postcode postcode to lookup
     * @return array|bool Array of all available data or FALSE if postcode does not exist
     * @throws GuzzleException|JsonException
     */
    public function postcodeLookup(string $postcode): array|bool
    {
        return $this->lookup(self::API_POSTCODES, $postcode);
    }

    /**
     * Bulk Postcode Lookup
     * Returns all available data if found.
     *
     * @param array<string> $postcodes postcodes to lookup
     * @param string $filter Comma separated whitelist of attributes to be returned in the result object(s)
     * @return array|bool The data
     * @throws GuzzleException|JsonException
     */
    public function postcodeBulkLookup(array $postcodes, string $filter = ''): array|bool
    {
        if (!$this->isInRange(count($postcodes), 1, self::POSTCODES_MAX)) {
            throw new InvalidArgumentException(
                'There must be between 1 and ' . self::POSTCODES_MAX . ' postcodes'
            );
        }

        $options = [RequestOptions::JSON => ['postcodes' => $postcodes]];

        if ($filter !== '') {
            $options[RequestOptions::QUERY] = ['filter' => $filter];
        }

        try {
            $this->response = $this
                ->client
                ->post(
                    self::API_POSTCODES,
                    $options
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns nearest postcodes for a given latitude and longitude.
     *
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param int $limit Limits number of postcodes matches to return[1 - 100]. Defaults to 10.
     * @param int $radius Search radius in metres. Limits number of postcodes matches to return. Defaults to
     * 100m. Needs to be less than 2,000m.
     * @param bool $wideSearch Search up to 20km radius, but subject to a maximum of 10 results. Defaults to false.
     * When enabled, $radius and $limit > 10 are ignored.
     * @return array|bool postcodes
     * @throws GuzzleException|JsonException
     */
    public function postcodeReverseGeocoding(
        float $latitude,
        float $longitude,
        int $limit = 10,
        int $radius = 100,
        bool $wideSearch = false
    ): array|bool {
        return $this->reverseGeocoding(
            self::API_POSTCODES,
            $latitude,
            $longitude,
            $limit,
            $radius,
            $wideSearch,
            [
                'latitude' => ['min' => self::LATITUDE_MIN, 'max' => self::LATITUDE_MAX],
                'longitude' => ['min' => self::LONGITUDE_MIN, 'max' => self::LONGITUDE_MAX],
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX],
                'radius' => ['min' => 1, 'max' => self::POSTCODE_RADIUS_MAX]
            ]
        );
    }

    /**
     * Bulk translates geolocations into Postcodes. Accepts up to 100 geolocations.
     *
     * @param array $geolocations Geolocations to be reverse geocoded.
     * @param int $limit Number of results to return.
     * @param int $radius Radius in metres.
     * @param bool $wideSearch Sets radius to 20km and limits results to 10.
     * @param string $filter Comma separated whitelist of attributes to be returned in the result object(s)
     * @return array|bool postcodes
     * @throws GuzzleException|JsonException
     */
    public function postcodeBulkReverseGeocoding(
        array $geolocations,
        int $limit = 10,
        int $radius = 100,
        bool $wideSearch = false,
        string $filter = ''
    ): array|bool
    {
        if (!$this->isInRange(count($geolocations), 1, self::GEOLOCATIONS_MAX)) {
            throw new InvalidArgumentException(
                'There must be between 1 and ' . self::GEOLOCATIONS_MAX . ' geolocations'
            );
        }

        if (!$this->isInRange($limit, 1, self::LIMIT_MAX)) {
            throw new InvalidArgumentException( '`limit` must be between 1 and ' . self::LIMIT_MAX);
        }

        if (!$this->isInRange($radius, 1, self::POSTCODE_RADIUS_MAX)) {
            throw new InvalidArgumentException('`radius` must be between 1 and ' . self::POSTCODE_RADIUS_MAX);
        }

        foreach ($geolocations as $i => $geolocation) {
            foreach ([
                'latitude' => ['min' => self::LATITUDE_MIN, 'max' => self::LATITUDE_MAX],
                'longitude' => ['min' => self::LONGITUDE_MIN, 'max' => self::LONGITUDE_MAX],
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX],
                'radius' => ['min' => 1, 'max' => self::POSTCODE_RADIUS_MAX]
            ] as $param => $minMax) {
                if (isset($geolocation[$param])) {
                    if (!$this->isInRange($geolocation[$param], $minMax['min'], $minMax['max'])) {
                        throw new InvalidArgumentException(sprintf(
                            '`%s` must be between %f and %f in geolocation %d',
                            $param,
                            $minMax['min'],
                            $minMax['max'],
                            $i
                        ));
                    }
                } elseif ($param === 'latitude' || $param === 'longitude') {
                    throw new InvalidArgumentException(
                        sprintf('`%s` is required in geolocation %d', $param, $i)
                    );
                }
            }
        }

        $options = [
            RequestOptions::JSON => ['geolocations' => $geolocations],
            RequestOptions::QUERY => [
                'limit' => $limit,
                'radius' => $radius,
                'widesearch' => $wideSearch,
            ]
        ];

        if ($filter !== '') {
            $options[RequestOptions::QUERY]['filter'] = $filter;
        }

        try {
            $this->response = $this
                ->client
                ->post(
                    self::API_POSTCODES,
                    $options
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns a list of matching postcodes and associated data
     *
     * @param string $postcode Full or partial postcode
     * @param int $limit Limits number of postcodes matches to return[1 - 100]. Defaults to 10.
     * @return array|bool Matching postcodes and data
     * @throws GuzzleException|InvalidArgumentException|JsonException
     */
    public function postcodeQuery(string $postcode, int $limit = 10): array|bool
    {
        return $this->query(
            self::API_POSTCODES,
            $postcode,
            $limit,
            [
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX]
            ]
        );
    }

    /**
     * Validate a postcode.
     *
     * @param string $postcode The postcode to validate
     * @return bool TRUE if the postcode is valid, FALSE if not
     * @throws GuzzleException|JsonException
     */
    public function postcodeValidation(string $postcode): bool
    {
        try {
            $this->response = $this
                ->client
                ->get(
                    self::API_POSTCODES . '/' . $postcode . '/validate'
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns the nearest postcodes for a given postcode
     *
     * @param string $postcode The postcode
     * @param int $limit Limits number of postcodes matches to return. Defaults to 10. Needs to be less than 100.
     * @param int $radius Search radius in metres. Limits number of postcodes matches to return. Defaults to
     * 100m. Needs to be less than 2,000m.
     * @return array|bool Postcodes
     * @throws GuzzleException|JsonException
     */
    public function nearestPostcode(string $postcode, int $limit = 10, int $radius = 100): array|bool
    {
        return $this->nearest(
            self::API_POSTCODES,
            $postcode,
            $limit,
            $radius,
            [
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX],
                'radius' => ['min' => 1, 'max' => self::POSTCODE_RADIUS_MAX]
            ]
        );
    }

    /**
     * Returns a list of matching postcodes.
     *
     * @param string $postcode Partial postcode
     * @param int $limit Limits number of postcodes matches to return. Defaults to 10. Needs to be less than 100.
     * @return array|bool matching postcodes
     * @throws GuzzleException|JsonException
     */
    public function postcodeAutocomplete(string $postcode, int $limit = 10): array|bool
    {
        if (!$this->isInRange($limit, 1, self::LIMIT_MAX)) {
            throw new InvalidArgumentException('`limit` must be between 1 and ' . self::LIMIT_MAX);
        }

        try {
            $this->response = $this
                ->client
                ->get(
                    self::API_POSTCODES . '/' . $postcode . '/autocomplete',
                    [
                        RequestOptions::QUERY => [
                            'limit' => $limit
                        ]
                    ]
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns a random postcode and associated data
     *
     * @param string $outcode Filters random postcodes by outcode.
     * @return array|bool|null postcode data, null if invalid outcode, false if response not ok
     * @throws GuzzleException|JsonException
     */
    public function randomPostcode(string $outcode = ''): array|bool|null
    {
        return $this->random(self::API_POSTCODES, $outcode);
    }

    /**
     * Returns SPD data associated with postcode
     *
     * @param string $postcode The postcode.
     * @return array|bool postcode data, false if response not ok
     * @throws GuzzleException|JsonException
     */
    public function scottishPostcodeLookup(string $postcode): array|bool
    {
        try {
            $this->response = $this
                ->client
                ->get(
                    'scotland/postcodes/' . $postcode
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns data about a terminated postcode
     *
     * @param string $postcode The postcode.
     * @return array|bool postcode data, false if response not ok (e.g. postcode not terminated)
     * @throws GuzzleException|JsonException
     */
    public function terminatedPostcode(string $postcode): array|bool
    {
        try {
            $this->response = $this
                ->client
                ->get(
                    'terminated_postcodes/' . $postcode
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns geolocation data for the centroid of the outward code specified.
     *
     * @param string $outcode The outcode
     * @return array|bool outcode geolocation data or FALSE if invalid outcode
     * @throws GuzzleException|JsonException
     */
    public function outcodeLookup(string $outcode): array|bool
    {
        return $this->lookup(self::API_OUTCODES, $outcode);
    }

    /**
     * Returns nearest outcodes for a given longitude and latitude.
     *
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param int $limit Limits number of postcodes matches to return. Defaults to 10. Needs to be less than 100.
     * @param int $radius Search radius in metres. Limits number of postcodes matches to return. Defaults to
     * 5,000m. Needs to be less than 25,000m.
     * @return array|bool Outcodes
     * @throws GuzzleException|JsonException
     */
    public function outcodeReverseGeocoding(
        float $latitude,
        float $longitude,
        int $limit = 10,
        int $radius = 5000
    ): array|bool
    {
        return $this->reverseGeocoding(
            self::API_OUTCODES,
            $latitude,
            $longitude,
            $limit,
            $radius,
            false,
            [
                'latitude' => ['min' => self::LATITUDE_MIN, 'max' => self::LATITUDE_MAX],
                'longitude' => ['min' => self::LONGITUDE_MIN, 'max' => self::LONGITUDE_MAX],
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX],
                'radius' => ['min' => 1, 'max' => self::OUTCODE_RADIUS_MAX],
            ]
        );
    }

    /**
     * Returns the nearest outcodes for a given outcode.
     *
     * @param string $outcode Outcode
     * @param int $limit Limits number of postcodes matches to return. Defaults to 10. Needs to be less than 100.
     * @param int $radius Search radius in metres. Limits number of postcodes matches to return. Defaults to
     * 5,000m. Needs to be less than 25,000m.
     * @return array Outcodes
     * @throws GuzzleException|JsonException
     */
    public function nearestOutcode(string $outcode, int $limit = 10, int $radius = 5000): array
    {
        return $this->nearest(
            self::API_OUTCODES,
            $outcode,
            $limit,
            $radius,
            [
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX],
                'radius' => ['min' => 1, 'max' => self::OUTCODE_RADIUS_MAX],
            ]
        );
    }

    /**
     * Lookup a place by code.
     *
     * @param string $code The place code
     * @return array|bool All available data if found, FALSE if place does not exist.
     * @throws GuzzleException|JsonException
     */
    public function placeLookup(string $code): array|bool
    {
        return $this->lookup(self::API_PLACES, $code);
    }

    /**
     * Returns a list of matching places and associated data
     *
     * @param string $place Full or partial place code
     * @param int $limit Limits number of places matches to return [1 - 100]. Defaults to 10.
     * @return array|bool Place data or FALSE if place not found
     * @throws GuzzleException|InvalidArgumentException|JsonException
     */
    public function placeQuery(string $place, int $limit = 10): array|bool
    {
        return $this->query(
            self::API_PLACES,
            $place,
            $limit,
            [
                'limit' => ['min' => 1, 'max' => self::LIMIT_MAX]
            ]
        );
    }

    /**
     * Returns a random place and associated data
     *
     * @return array|bool The random place and its associated data
     * @throws GuzzleException|JsonException
     */
    public function randomPlace(): array|bool
    {
        return $this->random(self::API_PLACES);
    }

    /**
     * Returns the HTTP Client Response object.
     * Useful if the Response is not OK
     *
     * @return ResponseInterface HTTP Client Response object
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /*** Low level methods ***/
    /**
     * Lookup a code (postcode, outcode, place code)
     *
     * @param string $api The API to use [postcodes|outcodes|places]
     * @param string $code The code to lookup
     * @return bool|mixed
     * @throws GuzzleException|JsonException
     */
    private function lookup(string $api, string $code): mixed
    {
        try {
            $this->response = $this
                ->client
                ->get(
                    "$api/$code"
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns the nearest codes to the given code.
     *
     * The codes returned are the same type as the given code, i.e. a postcode returns the nearest postcodes, an
     * outcode returns outcodes
     *
     * @param string $api The API to use [postcodes|outcodes]
     * @param string $code The code
     * @param int $limit Limits the number of returned codes
     * @param int $radius Limit the search radius
     * @param array $ranges Valid ranges for $limit and $radius
     * @return bool|mixed
     * @throws InvalidArgumentException If $limit or $radius is out of range
     * @throws GuzzleException|JsonException
     */
    private function nearest(string $api, string $code, int $limit, int $radius, array $ranges): mixed
    {
        foreach ($ranges as $param => $minMax) {
            if (!$this->isInRange($$param, $minMax['min'], $minMax['max'])) {
                throw new InvalidArgumentException(
                    sprintf('%s must be between %d and %d', $param, $minMax['min'], $minMax['max'])
                );
            }
        }

        try {
            $this->response = $this
                ->client
                ->get(
                    "$api/$code/nearest",
                    [
                        RequestOptions::QUERY => [
                            'limit' => $limit,
                            'radius' => $radius
                        ]
                    ]
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Performs a query for the given code.
     *
     * @param string $api The API to use [postcodes|places]
     * @param string $query Full or partial postcode ($api === postcodes), or place code ($api === places)
     * @param int $limit Limits the number of results
     * @param array $ranges Valid ranges
     * @return array|bool Postcode or place data or FALSE if place not found
     * @throws GuzzleException|InvalidArgumentException|JsonException
     */
    private function query(string $api, string $query, int $limit, array $ranges): array|bool
    {
        foreach ($ranges as $param => $minMax) {
            if (!$this->isInRange($$param, $minMax['min'], $minMax['max'])) {
                throw new InvalidArgumentException(
                    sprintf('%s must be between %d and %d', $param, $minMax['min'], $minMax['max'])
                );
            }
        }

        try {
            $this->response = $this
                ->client
                ->get(
                    $api,
                    [
                        RequestOptions::QUERY => [
                            'limit' => $limit,
                            'query' => $query
                        ]
                    ]
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns a random postcode or place
     *
     * @param string $api The API to use [postcodes|places]
     * @param string $outcode Filters the returned results by outcode ($api === postcodes only)
     * @return array|bool|null
     * @throws GuzzleException|JsonException
     */
    private function random(string $api, string $outcode = ''): array|bool|null
    {
        $query = $api === self::API_POSTCODES && $outcode
            ? ['outcode' => $outcode]
            : []
        ;

        try {
            $this->response = $this
                ->client
                ->get(
                    "random/$api",
                    [
                        RequestOptions::QUERY => $query
                    ]
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Returns nearest postcodes or places for a given latitude and longitude.
     *
     * @param string $api The API to use [postcodes|outcodes]
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param int $limit Limits number of postcodes matches to return[1 - 100]. Defaults to 10.
     * @param int $radius Search radius in metres. The maximum radius depends on the API.
     * @param bool $wideSearch Search up to 20km radius, but subject to a maximum of 10 results. Defaults to false.
     * When enabled, $radius and $limit > 10 are ignored.
     * @param array $ranges Valid ranges for $limit
     * @return array|bool postcodes
     * @throws GuzzleException|JsonException
     */
    private function reverseGeocoding(
        string $api,
        float $latitude,
        float $longitude,
        int $limit,
        int $radius,
        bool $wideSearch,
        array $ranges
    ): array|bool {
        foreach ($ranges as $param => $minMax) {
            if (!$this->isInRange($$param, $minMax['min'], $minMax['max'])) {
                throw new InvalidArgumentException(
                    sprintf('%s must be between %d and %d', $param, $minMax['min'], $minMax['max'])
                );
            }
        }

        $query = [
            'lat' => $latitude,
            'lon' => $longitude,
            'limit' => $limit,
            'radius' => $radius
        ];

        if ($api === self::API_POSTCODES) {
            $query['wideSearch'] = $wideSearch;
        }

        try {
            $this->response = $this
                ->client
                ->get(
                    $api,
                    [
                        RequestOptions::QUERY => $query,
                    ]
                )
            ;
        } catch (GuzzleException $exception) {
            $this->response = $exception->getResponse();
            return false;
        }

        return $this->getResult();
    }

    /**
     * Checks a value is in range
     *
     * @param float|int $value The value to check
     * @param float|int $min The minimum allowed value
     * @param float|int $max The maximum allowed value
     * @return bool TRUE if $value is in range, FALSE if not
     *
     */
    private function isInRange(float|int $value, float|int $min, float|int $max): bool
    {
        return ($value >= $min && $value <= $max);
    }

    private function getResult(): array|bool|null
    {
        if ($this->response->getStatusCode() === Status::OK) {
            $result = Json::decode($this->response->getBody());
            return $result['result'];
        }

        return false;
    }
}
