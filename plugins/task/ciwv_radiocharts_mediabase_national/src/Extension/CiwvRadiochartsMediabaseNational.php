<?php

/**
 * @package     plg_task_ciwv_radiocharts_mediabase_national
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Plugin\Task\CiwvRadiochartsMediabaseNational\Extension;

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
 * Task plugin: imports the Mediabase **national** chart for station CIWV.
 *
 * This plugin integrates with the Joomla Task Scheduler (com_scheduler) to
 * periodically pull the national radio chart from the Mediabase API and store
 * it in the `d6f25_ciwv_radiocharts` database table.
 *
 * Configuration (set in Joomla Task Scheduler admin):
 * - `api_endpoint`   – Mediabase API base URL
 * - `api_key`        – Mediabase API key / credential
 * - `chart_format`   – Chart format/genre identifier used by Mediabase
 * - `week_date`      – Optional override date (YYYY-MM-DD); defaults to current week's Monday
 *
 * @since  1.0.0
 */
class CiwvRadiochartsMediabaseNational extends CMSPlugin implements DatabaseAwareInterface, SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * Routine definitions.  The key maps to a task type identifier; the value
     * is a configuration array consumed by {@see TaskPluginTrait}.
     *
     * @var    array
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        'ciwv.radiocharts.import.mediabase_national' => [
            'langConstPrefix' => 'PLG_TASK_CIWV_RADIOCHARTS_MEDIABASE_NATIONAL',
            'form'            => 'import_mediabase_national',
            'method'          => 'importMediabaseNational',
        ],
    ];

    /**
     * Autoload the module's language strings so lang keys are available.
     *
     * @var    bool
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor – receives the database from the service provider.
     *
     * @param   DispatcherInterface  $dispatcher  Joomla event dispatcher.
     * @param   array                $config      Plugin configuration.
     * @param   DatabaseInterface    $db          Database driver.
     *
     * @since   1.0.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config, DatabaseInterface $db)
    {
        parent::__construct($dispatcher, $config);
        $this->setDatabase($db);
    }

    /**
     * Returns the events this subscriber listens to.
     *
     * @return  array<string, string>
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'     => 'advertiseRoutines',
            'onExecuteTask'         => 'standardRoutineHandler',
            'onContentPrepareForm'  => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Fetches the Mediabase national chart and persists it to the database.
     *
     * Called by {@see TaskPluginTrait::standardRoutineHandler()} when the
     * scheduler executes the `ciwv.radiocharts.import.mediabase_national` task.
     *
     * @param   ExecuteTaskEvent  $event  The task execution event.
     *
     * @return  int  A {@see TaskStatus} constant.
     *
     * @since   1.0.0
     */
    private function importMediabaseNational(ExecuteTaskEvent $event): int
    {
        $params      = $event->getArgument('params', new \stdClass());
        $apiEndpoint = trim($params->api_endpoint ?? '');
        $apiKey      = trim($params->api_key ?? '');
        $chartFormat = trim($params->chart_format ?? '');
        $weekDate    = trim($params->week_date ?? '');

        if ($apiEndpoint === '' || $apiKey === '') {
            $this->logTask($this->getApplication()->getLanguage()->_('PLG_TASK_CIWV_RADIOCHARTS_MEDIABASE_NATIONAL_ERR_NO_CREDENTIALS'), 'error');

            return TaskStatus::INVALID_EXIT;
        }

        // Resolve the target week (default: current week's Monday).
        if ($weekDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) {
            $weekDate = (new \DateTime('monday this week'))->format('Y-m-d');
        }

        $tracks = $this->fetchMediabaseNationalChart($apiEndpoint, $apiKey, $chartFormat, $weekDate);

        if ($tracks === null) {
            return TaskStatus::KNOCKOUT;
        }

        $helper  = new RadiochartsHelper($this->getDatabase());
        $saved   = 0;

        foreach ($tracks as $track) {
            $ok = $helper->upsertEntry(
                weekDate:     $weekDate,
                source:       'mediabase_national',
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
            sprintf('Mediabase National: saved %d/%d tracks for week %s.', $saved, count($tracks), $weekDate)
        );

        return TaskStatus::OK;
    }

    /**
     * Makes an HTTP GET request to the Mediabase API and returns the chart data.
     *
     * The Mediabase API returns JSON in the form:
     * ```json
     * {
     *   "chart": [
     *     {
     *       "position": 1,
     *       "artist": "Artist Name",
     *       "title": "Song Title",
     *       "label": "Label Name",
     *       "plays": 12345,
     *       "peak_position": 1,
     *       "weeks_on_chart": 5
     *     },
     *     ...
     *   ]
     * }
     * ```
     *
     * Replace or extend this method when the actual API contract is known.
     *
     * @param   string  $endpoint     Base API URL.
     * @param   string  $apiKey       Authentication key.
     * @param   string  $chartFormat  Format/genre identifier.
     * @param   string  $weekDate     Target week (YYYY-MM-DD).
     *
     * @return  array[]|null  Array of track arrays, or null on failure.
     *
     * @since   1.0.0
     */
    private function fetchMediabaseNationalChart(string $endpoint, string $apiKey, string $chartFormat, string $weekDate): ?array
    {
        $url = rtrim($endpoint, '/') . '/national?' . http_build_query([
            'api_key' => $apiKey,
            'format'  => $chartFormat,
            'week'    => $weekDate,
        ]);

        return $this->httpGetJson($url);
    }

    /**
     * Performs a simple HTTP GET and decodes the JSON response body.
     *
     * Uses Joomla's Http factory when available, falling back to cURL.
     *
     * @param   string  $url  Fully-qualified URL to fetch.
     *
     * @return  array[]|null  Decoded `chart` array from the response, or null on failure.
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
                $this->logTask('Invalid JSON response from Mediabase National API.', 'error');

                return null;
            }

            return $data['chart'];
        } catch (\Exception $e) {
            $this->logTask('Exception fetching Mediabase National chart: ' . $e->getMessage(), 'error');

            return null;
        }
    }
}
