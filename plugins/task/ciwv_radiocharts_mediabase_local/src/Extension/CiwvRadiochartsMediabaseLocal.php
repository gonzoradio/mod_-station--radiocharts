<?php

/**
 * @package     plg_task_ciwv_radiocharts_mediabase_local
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Plugin\Task\CiwvRadiochartsMediabaseLocal\Extension;

\defined('_JEXEC') or die;

use Gonzoradio\Module\CiwvRadiocharts\Site\Helper\RadiochartsHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Task plugin: imports the Mediabase **local** (CIWV station) chart.
 *
 * Unlike the national chart plugin this one scopes the API request to
 * CIWV's specific station ID so that only plays logged at CIWV are
 * returned.
 *
 * Configuration (set in Joomla Task Scheduler admin):
 * - `api_endpoint`  – Mediabase API base URL
 * - `api_key`       – Mediabase API key / credential
 * - `station_id`    – CIWV's station identifier in Mediabase (e.g. "CIWV")
 * - `chart_format`  – Chart format/genre identifier
 * - `week_date`     – Optional override date (YYYY-MM-DD); defaults to current week's Monday
 *
 * @since  1.0.0
 */
class CiwvRadiochartsMediabaseLocal extends CMSPlugin implements DatabaseAwareInterface, SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var    array
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        'ciwv.radiocharts.import.mediabase_local' => [
            'langConstPrefix' => 'PLG_TASK_CIWV_RADIOCHARTS_MEDIABASE_LOCAL',
            'form'            => 'import_mediabase_local',
            'method'          => 'importMediabaseLocal',
        ],
    ];

    /** @var bool */
    protected $autoloadLanguage = true;

    /**
     * @param   DispatcherInterface  $dispatcher
     * @param   array                $config
     * @param   DatabaseInterface    $db
     *
     * @since   1.0.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config, DatabaseInterface $db)
    {
        parent::__construct($dispatcher, $config);
        $this->setDatabase($db);
    }

    /**
     * @return  array<string, string>
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Fetches the Mediabase local (CIWV) chart and persists it to the database.
     *
     * @param   ExecuteTaskEvent  $event
     *
     * @return  int
     *
     * @since   1.0.0
     */
    private function importMediabaseLocal(ExecuteTaskEvent $event): int
    {
        $params      = $event->getArgument('params', new \stdClass());
        $apiEndpoint = trim($params->api_endpoint ?? '');
        $apiKey      = trim($params->api_key ?? '');
        $stationId   = trim($params->station_id ?? '');
        $chartFormat = trim($params->chart_format ?? '');
        $weekDate    = trim($params->week_date ?? '');

        if ($apiEndpoint === '' || $apiKey === '' || $stationId === '') {
            $this->logTask(
                $this->getApplication()->getLanguage()->_('PLG_TASK_CIWV_RADIOCHARTS_MEDIABASE_LOCAL_ERR_NO_CREDENTIALS'),
                'error'
            );

            return TaskStatus::INVALID_EXIT;
        }

        if ($weekDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) {
            $weekDate = (new \DateTime('monday this week'))->format('Y-m-d');
        }

        $tracks = $this->fetchMediabaseLocalChart($apiEndpoint, $apiKey, $stationId, $chartFormat, $weekDate);

        if ($tracks === null) {
            return TaskStatus::KNOCKOUT;
        }

        $helper = new RadiochartsHelper($this->getDatabase());
        $saved  = 0;

        foreach ($tracks as $track) {
            $ok = $helper->upsertEntry(
                weekDate:     $weekDate,
                source:       'mediabase_local',
                position:     (int) ($track['position'] ?? 0),
                artist:       (string) ($track['artist'] ?? ''),
                title:        (string) ($track['title'] ?? ''),
                label:        (string) ($track['label'] ?? '') ?: null,
                plays:        (int) ($track['plays'] ?? 0),
                peakPosition: isset($track['peak_position']) ? (int) $track['peak_position'] : null,
                weeksOnChart: isset($track['weeks_on_chart']) ? (int) $track['weeks_on_chart'] : null
            );

            if ($ok) {
                $saved++;
            }
        }

        $this->logTask(
            sprintf('Mediabase Local (CIWV): saved %d/%d tracks for week %s.', $saved, count($tracks), $weekDate)
        );

        return TaskStatus::OK;
    }

    /**
     * Calls the Mediabase station-level spins endpoint.
     *
     * Expected response JSON:
     * ```json
     * { "chart": [ { "position": 1, "artist": "…", "title": "…", "label": "…", "plays": 99, … } ] }
     * ```
     *
     * @param   string  $endpoint     Base API URL.
     * @param   string  $apiKey       Authentication key.
     * @param   string  $stationId    Station call letters / ID.
     * @param   string  $chartFormat  Format/genre code.
     * @param   string  $weekDate     Target week (YYYY-MM-DD).
     *
     * @return  array[]|null
     *
     * @since   1.0.0
     */
    private function fetchMediabaseLocalChart(string $endpoint, string $apiKey, string $stationId, string $chartFormat, string $weekDate): ?array
    {
        $url = rtrim($endpoint, '/') . '/station?' . http_build_query([
            'api_key' => $apiKey,
            'station' => $stationId,
            'format'  => $chartFormat,
            'week'    => $weekDate,
        ]);

        return $this->httpGetJson($url);
    }

    /**
     * Performs a simple HTTP GET and decodes the JSON response body.
     *
     * @param   string  $url
     *
     * @return  array[]|null
     *
     * @since   1.0.0
     */
    private function httpGetJson(string $url): ?array
    {
        try {
            $http     = \Joomla\CMS\Http\HttpFactory::getHttp();
            $response = $http->get($url, ['Accept' => 'application/json'], 30);

            if ($response->code !== 200) {
                $this->logTask(sprintf('HTTP %d from %s', $response->code, $url), 'error');

                return null;
            }

            $data = json_decode($response->body, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['chart'])) {
                $this->logTask('Invalid JSON response from Mediabase Local API.', 'error');

                return null;
            }

            return $data['chart'];
        } catch (\Exception $e) {
            $this->logTask('Exception fetching Mediabase Local chart: ' . $e->getMessage(), 'error');

            return null;
        }
    }
}
