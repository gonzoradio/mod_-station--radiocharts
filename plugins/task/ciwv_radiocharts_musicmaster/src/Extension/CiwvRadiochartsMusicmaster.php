<?php

/**
 * @package     plg_task_ciwv_radiocharts_musicmaster
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Plugin\Task\CiwvRadiochartsMusicmaster\Extension;

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
 * Task plugin: imports the Program Director's Music Master export for station CIWV.
 *
 * Music Master is a broadcast scheduling application.  The PD generates a
 * weekly export (CSV) from Music Master and places it in an agreed location
 * (local server path or accessible URL).  This plugin reads that file and
 * imports the data into the `d6f25_ciwv_radiocharts` table.
 *
 * Expected CSV columns (configurable via column-index settings):
 *   Position, Artist, Title, Label, Plays, Peak Position, Weeks On Chart
 *
 * Configuration (set in Joomla Task Scheduler admin):
 * - `csv_path`            – Absolute server path to the CSV export file
 * - `has_header_row`      – Whether the CSV has a header row (1/0)
 * - `col_position`        – Zero-based column index for chart position
 * - `col_artist`          – Zero-based column index for artist name
 * - `col_title`           – Zero-based column index for track title
 * - `col_label`           – Zero-based column index for label (optional)
 * - `col_plays`           – Zero-based column index for play count (optional)
 * - `col_peak_position`   – Zero-based column index for peak position (optional)
 * - `col_weeks_on_chart`  – Zero-based column index for weeks on chart (optional)
 * - `week_date`           – Optional override date (YYYY-MM-DD)
 *
 * @since  1.0.0
 */
class CiwvRadiochartsMusicmaster extends CMSPlugin implements DatabaseAwareInterface, SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var    array
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        'ciwv.radiocharts.import.musicmaster' => [
            'langConstPrefix' => 'PLG_TASK_CIWV_RADIOCHARTS_MUSICMASTER',
            'form'            => 'import_musicmaster',
            'method'          => 'importMusicmaster',
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
     * Reads the Music Master CSV export and persists it to the database.
     *
     * @param   ExecuteTaskEvent  $event
     *
     * @return  int
     *
     * @since   1.0.0
     */
    private function importMusicmaster(ExecuteTaskEvent $event): int
    {
        $params   = $event->getArgument('params', new \stdClass());
        $csvPath  = trim($params->csv_path ?? '');
        $weekDate = trim($params->week_date ?? '');

        if ($csvPath === '') {
            $this->logTask(
                $this->getApplication()->getLanguage()->_('PLG_TASK_CIWV_RADIOCHARTS_MUSICMASTER_ERR_NO_PATH'),
                'error'
            );

            return TaskStatus::INVALID_EXIT;
        }

        if (!is_readable($csvPath)) {
            $this->logTask(sprintf('Music Master: CSV file not readable: %s', $csvPath), 'error');

            return TaskStatus::KNOCKOUT;
        }

        if ($weekDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) {
            $weekDate = (new \DateTime('monday this week'))->format('Y-m-d');
        }

        // Column index mapping (zero-based), configurable in task params.
        // A value of -1 means that column is not present in this export.
        $colPosition      = (int) ($params->col_position      ?? 0);
        $colArtist        = (int) ($params->col_artist         ?? 1);
        $colTitle         = (int) ($params->col_title          ?? 2);
        $colLabel         = isset($params->col_label)         && (int) $params->col_label         >= 0 ? (int) $params->col_label         : null;
        $colPlays         = isset($params->col_plays)         && (int) $params->col_plays         >= 0 ? (int) $params->col_plays         : null;
        $colPeakPosition  = isset($params->col_peak_position) && (int) $params->col_peak_position >= 0 ? (int) $params->col_peak_position : null;
        $colWeeksOnChart  = isset($params->col_weeks_on_chart) && (int) $params->col_weeks_on_chart >= 0 ? (int) $params->col_weeks_on_chart : null;
        $hasHeaderRow     = (bool) ($params->has_header_row ?? 1);

        $tracks = $this->parseCsv(
            $csvPath,
            $hasHeaderRow,
            $colPosition,
            $colArtist,
            $colTitle,
            $colLabel,
            $colPlays,
            $colPeakPosition,
            $colWeeksOnChart
        );

        if ($tracks === null) {
            return TaskStatus::KNOCKOUT;
        }

        $helper = new RadiochartsHelper($this->getDatabase());
        $saved  = 0;

        foreach ($tracks as $track) {
            $ok = $helper->upsertEntry(
                weekDate:     $weekDate,
                source:       'musicmaster',
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
            sprintf('Music Master: saved %d/%d tracks for week %s.', $saved, count($tracks), $weekDate)
        );

        return TaskStatus::OK;
    }

    /**
     * Parses a Music Master CSV export file and returns a normalised track array.
     *
     * Music Master exports are comma-separated with optional double-quote wrapping.
     * The column positions are configurable to accommodate different export layouts.
     *
     * @param   string    $path              Absolute path to the CSV file.
     * @param   bool      $hasHeaderRow      Skip the first row if true.
     * @param   int       $colPosition       Column index for chart position.
     * @param   int       $colArtist         Column index for artist name.
     * @param   int       $colTitle          Column index for track title.
     * @param   int|null  $colLabel          Column index for record label, or null to skip.
     * @param   int|null  $colPlays          Column index for play count, or null to skip.
     * @param   int|null  $colPeakPosition   Column index for peak position, or null to skip.
     * @param   int|null  $colWeeksOnChart   Column index for weeks on chart, or null to skip.
     *
     * @return  array[]|null  Array of normalised track arrays, or null on parse failure.
     *
     * @since   1.0.0
     */
    private function parseCsv(
        string $path,
        bool $hasHeaderRow,
        int $colPosition,
        int $colArtist,
        int $colTitle,
        ?int $colLabel,
        ?int $colPlays,
        ?int $colPeakPosition,
        ?int $colWeeksOnChart
    ): ?array {
        if (!is_readable($path)) {
            $this->logTask(sprintf('Music Master: could not open CSV file: %s', $path), 'error');

            return null;
        }

        $handle = fopen($path, 'r');

        // is_readable() passed, but fopen() can still fail in rare edge cases
        // (e.g. a race condition where the file is removed between the check and open).
        if ($handle === false) {
            $this->logTask(sprintf('Music Master: could not open CSV file: %s', $path), 'error');

            return null;
        }

        $tracks    = [];
        $lineNum   = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNum++;

            // Skip header row.
            if ($hasHeaderRow && $lineNum === 1) {
                continue;
            }

            // Skip empty rows.
            if (empty(array_filter($row, fn($v) => trim($v) !== ''))) {
                continue;
            }

            $track = [
                'position' => isset($row[$colPosition]) ? (int) trim($row[$colPosition]) : 0,
                'artist'   => isset($row[$colArtist])   ? trim($row[$colArtist])          : '',
                'title'    => isset($row[$colTitle])    ? trim($row[$colTitle])            : '',
            ];

            if ($colLabel !== null && isset($row[$colLabel])) {
                $track['label'] = trim($row[$colLabel]);
            }

            if ($colPlays !== null && isset($row[$colPlays])) {
                $track['plays'] = (int) trim($row[$colPlays]);
            }

            if ($colPeakPosition !== null && isset($row[$colPeakPosition])) {
                $track['peak_position'] = (int) trim($row[$colPeakPosition]);
            }

            if ($colWeeksOnChart !== null && isset($row[$colWeeksOnChart])) {
                $track['weeks_on_chart'] = (int) trim($row[$colWeeksOnChart]);
            }

            // Skip rows where both artist and title are empty (malformed CSV).
            if ($track['artist'] === '' && $track['title'] === '') {
                continue;
            }

            $tracks[] = $track;
        }

        fclose($handle);

        return $tracks;
    }
}
