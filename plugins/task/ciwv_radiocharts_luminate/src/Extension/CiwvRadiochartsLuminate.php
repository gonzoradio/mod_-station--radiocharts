<?php

/**
 * @package     plg_task_ciwv_radiocharts_luminate
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Plugin\Task\CiwvRadiochartsLuminate\Extension;

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
 * Task plugin: imports Luminate streaming data for station CIWV.
 *
 * Luminate (formerly Soundscan / BDS) provides streaming counts through its
 * Data API.  This plugin queries the API for the weekly stream data relevant
 * to CIWV's market/format and stores it in the shared `#__ciwv_radiocharts`
 * table under the `luminate` source.
 *
 * Configuration (set in Joomla Task Scheduler admin):
 * - `api_endpoint`  – Luminate Data API base URL
 * - `api_key`       – Luminate API key
 * - `market_id`     – Market identifier for CIWV (city/DMA)
 * - `format_id`     – Format code (e.g. AC, CHR)
 * - `week_date`     – Optional override date (YYYY-MM-DD)
 *
 * @since  1.0.0
 */
class CiwvRadiochartsLuminate extends CMSPlugin implements DatabaseAwareInterface, SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var    array
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        'ciwv.radiocharts.import.luminate' => [
            'langConstPrefix' => 'PLG_TASK_CIWV_RADIOCHARTS_LUMINATE',
            'form'            => 'import_luminate',
            'method'          => 'importLuminate',
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
     * Fetches Luminate streaming data and persists it to the database.
     *
     * @param   ExecuteTaskEvent  $event
     *
     * @return  int
     *
     * @since   1.0.0
     */
    private function importLuminate(ExecuteTaskEvent $event): int
    {
        $params      = $event->getArgument('params', new \stdClass());
        $apiEndpoint = trim($params->api_endpoint ?? '');
        $apiKey      = trim($params->api_key ?? '');
        $marketId    = trim($params->market_id ?? '');
        $formatId    = trim($params->format_id ?? '');
        $weekDate    = trim($params->week_date ?? '');

        if ($apiEndpoint === '' || $apiKey === '') {
            $this->logTask(
                $this->getApplication()->getLanguage()->_('PLG_TASK_CIWV_RADIOCHARTS_LUMINATE_ERR_NO_CREDENTIALS'),
                'error'
            );

            return TaskStatus::INVALID_EXIT;
        }

        if ($weekDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) {
            $weekDate = (new \DateTime('monday this week'))->format('Y-m-d');
        }

        $tracks = $this->fetchLuminateStreams($apiEndpoint, $apiKey, $marketId, $formatId, $weekDate);

        if ($tracks === null) {
            return TaskStatus::KNOCKOUT;
        }

        $helper = new RadiochartsHelper($this->getDatabase());
        $saved  = 0;

        foreach ($tracks as $track) {
            $ok = $helper->upsertEntry(
                $weekDate,
                'luminate',
                (int) ($track['position'] ?? 0),
                (string) ($track['artist'] ?? ''),
                (string) ($track['title'] ?? ''),
                (string) ($track['label'] ?? '') ?: null,
                0,
                (int) ($track['streams'] ?? 0),
                isset($track['peak_position']) ? (int) $track['peak_position'] : null,
                isset($track['weeks_on_chart']) ? (int) $track['weeks_on_chart'] : null
            );

            if ($ok) {
                $saved++;
            }
        }

        $this->logTask(
            sprintf('Luminate: saved %d/%d tracks for week %s.', $saved, count($tracks), $weekDate)
        );

        return TaskStatus::OK;
    }

    /**
     * Calls the Luminate Data API streaming chart endpoint.
     *
     * Expected response JSON:
     * ```json
     * {
     *   "chart": [
     *     { "position": 1, "artist": "…", "title": "…", "label": "…", "streams": 5000000, … }
     *   ]
     * }
     * ```
     *
     * Replace with the actual Luminate API contract once credentials are available.
     *
     * @param   string  $endpoint   Base API URL.
     * @param   string  $apiKey     Authentication key.
     * @param   string  $marketId   Market / DMA identifier.
     * @param   string  $formatId   Format/genre code.
     * @param   string  $weekDate   Target week (YYYY-MM-DD).
     *
     * @return  array[]|null
     *
     * @since   1.0.0
     */
    private function fetchLuminateStreams(string $endpoint, string $apiKey, string $marketId, string $formatId, string $weekDate): ?array
    {
        $url = rtrim($endpoint, '/') . '/streaming/chart'
            . '?api_key=' . urlencode($apiKey)
            . '&market=' . urlencode($marketId)
            . '&format=' . urlencode($formatId)
            . '&week=' . urlencode($weekDate);

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
                $this->logTask('Invalid JSON response from Luminate API.', 'error');

                return null;
            }

            return $data['chart'];
        } catch (\Exception $e) {
            $this->logTask('Exception fetching Luminate data: ' . $e->getMessage(), 'error');

            return null;
        }
    }
}
