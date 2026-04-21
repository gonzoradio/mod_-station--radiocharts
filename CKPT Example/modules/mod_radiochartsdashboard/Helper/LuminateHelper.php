<?php
namespace Joomla\Module\Radiochartsdashboard\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;

class LuminateHelper
{
    // TODO: Set in config or .env or Joomla params
    private static $apiKey = 'YOUR_API_KEY';
    private static $username = 'jay.owen@dri.fm';
    private static $password = '!Jay20031998';

    private static $authToken = null;
    private static $tokenExpiry = 0;

    /**
     * Authenticate and get an access token.
     * Caches the token for repeated use.
     */
    public static function authenticate()
    {
        // Check if cached and not expired
        if (self::$authToken && time() < self::$tokenExpiry) {
            return self::$authToken;
        }

        $http = HttpFactory::getHttp();
        $headers = [
            'accept' => 'application/json',
            'content-type' => 'application/x-www-form-urlencoded',
            'x-api-key' => self::$apiKey,
        ];
        $body = http_build_query([
            'username' => self::$username,
            'password' => self::$password,
        ]);
        $response = $http->post('https://api.luminatedata.com/auth', $body, $headers);

        if ($response->code !== 200) {
            throw new \RuntimeException('Failed to authenticate with Luminate API: ' . $response->body);
        }

        $data = json_decode($response->body, true);
        self::$authToken = $data['access_token'];
        self::$tokenExpiry = time() + (int)($data['expires_in'] ?? 86400) - 60; // Renew 1 min early

        return self::$authToken;
    }

    /**
     * Search for a song by title and artist, return Luminate song ID.
     * Returns null if not found.
     */
    public static function searchSong($artist, $title)
    {
        $token = self::authenticate();
        $http = HttpFactory::getHttp();

        $headers = [
            'accept' => 'application/vnd.luminate-data.svc-apibff.v1+json',
            'content-type' => 'application/json',
            'x-api-key' => self::$apiKey,
            'authorization' => $token,
        ];
        $query = trim($artist . ' ' . $title);
        $body = json_encode([
            'query' => $query,
            'entity_type' => 'song',
            'from' => 0,
            'size' => 1,
        ]);
        $response = $http->post('https://api.luminatedata.com/search', $body, $headers);

        if ($response->code !== 200) {
            return null;
        }

        $data = json_decode($response->body, true);
        if (!empty($data['results'][0]['id'])) {
            return $data['results'][0]['id'];
        }
        return null;
    }

    /**
     * Fetch on-demand audio streams for a song in a market and date range.
     * Returns the stream count, or null on error.
     */
    public static function getODStreamsTW($songId, $startDate, $endDate, $marketId = 132, $locationId = 'CA')
    {
        $token = self::authenticate();
        $http = HttpFactory::getHttp();

        $headers = [
            'accept' => 'application/vnd.luminate-data.svc-apibff.v1+json',
            'x-api-key' => self::$apiKey,
            'authorization' => $token,
        ];
        $params = [
            'market_filter' => $marketId,
            'location_id' => $locationId,
            'service_type' => 'on_demand',
            'content_type' => 'audio',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        $url = 'https://api.luminatedata.com/songs/' . $songId . '?' . http_build_query($params);

        $response = $http->get($url, $headers);

        if ($response->code !== 200) {
            return null;
        }

        $data = json_decode($response->body, true);
        // Parse "streams" -> "service_type" -> "on_demand"
        if (!empty($data['metrics'])) {
            foreach ($data['metrics'] as $metric) {
                if ($metric['name'] === 'Streams') {
                    // Look for "service_type" => "on_demand"
                    foreach ($metric['value'] as $v) {
                        if ($v['name'] === 'service_type') {
                            foreach ($v['value'] as $stype) {
                                if ($stype['name'] === 'on_demand') {
                                    return $stype['value'];
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }
}