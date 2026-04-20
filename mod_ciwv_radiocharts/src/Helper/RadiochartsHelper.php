<?php

/**
 * @package     mod_ciwv_radiocharts
 * @subpackage  Helper
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Module\CiwvRadiocharts\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

/**
 * RadiochartsHelper provides database query methods for the CIWV Radiocharts module.
 *
 * All chart data (national Mediabase, local Mediabase, Luminate streaming, and
 * Music Master PD picks) is stored in the shared `#__ciwv_radiocharts` table and
 * retrieved through this helper.
 *
 * @since  1.0.0
 */
class RadiochartsHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Returns the Monday of the week that is `$offset` weeks from the current week.
     *
     * @param   int  $offset  Number of weeks to offset (0 = current week, -1 = last week, etc.)
     *
     * @return  string  Date string in YYYY-MM-DD format.
     *
     * @since   1.0.0
     */
    public function getWeekDate(int $offset = 0): string
    {
        $monday = new \DateTime('monday this week');

        if ($offset !== 0) {
            $monday->modify(($offset > 0 ? '+' : '') . $offset . ' weeks');
        }

        return $monday->format('Y-m-d');
    }

    /**
     * Fetches chart entries from the database for the given week and sources.
     *
     * Returns an associative array keyed by source, each containing an ordered
     * list of chart entry objects.
     *
     * @param   string    $weekDate   Week start date (YYYY-MM-DD).
     * @param   string[]  $sources    Data sources to include.
     * @param   int       $limit      Maximum rows per source.
     *
     * @return  array<string, object[]>  Entries grouped by source.
     *
     * @since   1.0.0
     */
    public function getChartEntries(string $weekDate, array $sources, int $limit = 50): array
    {
        if (empty($sources)) {
            return [];
        }

        // A limit of 0 would remove the SQL LIMIT clause entirely; enforce a minimum of 1.
        $limit = max(1, $limit);

        $query = $this->getDatabase()->getQuery(true)
            ->select($this->getDatabase()->quoteName(
                ['id', 'week_date', 'source', 'position', 'artist', 'title', 'label', 'plays', 'streams', 'peak_position', 'weeks_on_chart']
            ))
            ->from($this->getDatabase()->quoteName('#__ciwv_radiocharts'))
            ->where($this->getDatabase()->quoteName('week_date') . ' = ' . $this->getDatabase()->quote($weekDate))
            ->whereIn($this->getDatabase()->quoteName('source'), array_map([$this->getDatabase(), 'quote'], $sources), false)
            ->order($this->getDatabase()->quoteName('source') . ' ASC, ' . $this->getDatabase()->quoteName('position') . ' ASC')
            ->setLimit($limit * count($sources));

        try {
            $this->getDatabase()->setQuery($query);
            $rows = $this->getDatabase()->loadObjectList();
        } catch (\RuntimeException $e) {
            // Table may not exist yet (module installed without running the SQL installer).
            return [];
        }

        // Group by source and honour per-source limit.
        $grouped = [];

        foreach ($rows as $row) {
            $src = $row->source;

            if (!isset($grouped[$src])) {
                $grouped[$src] = [];
            }

            if (count($grouped[$src]) < $limit) {
                $grouped[$src][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * Returns a lookup map of previous-week positions keyed by "source|artist|title".
     *
     * Used by the template to render position-change arrows for week-over-week comparison.
     *
     * @param   array<string, object[]>  $previousEntries  Entries from the previous week.
     *
     * @return  array<string, int>  Map of composite key → previous position.
     *
     * @since   1.0.0
     */
    public static function buildPreviousPositionMap(array $previousEntries): array
    {
        $map = [];

        foreach ($previousEntries as $source => $entries) {
            foreach ($entries as $entry) {
                $key       = strtolower($source . '|' . $entry->artist . '|' . $entry->title);
                $map[$key] = (int) $entry->position;
            }
        }

        return $map;
    }

    /**
     * Upserts (inserts or updates) a single chart entry into the database.
     *
     * This method is called by the import task plugins after fetching data
     * from each source.
     *
     * @param   string       $weekDate        Week start date (YYYY-MM-DD).
     * @param   string       $source          Data source identifier.
     * @param   int          $position        Chart position.
     * @param   string       $artist          Artist name.
     * @param   string       $title           Track title.
     * @param   string|null  $label           Record label (optional).
     * @param   int          $plays           Spin / play count.
     * @param   int          $streams         Stream count (for Luminate data).
     * @param   int|null     $peakPosition    All-time peak chart position.
     * @param   int|null     $weeksOnChart    Number of weeks the track has charted.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   1.0.0
     */
    public function upsertEntry(
        string $weekDate,
        string $source,
        int $position,
        string $artist,
        string $title,
        ?string $label = null,
        int $plays = 0,
        int $streams = 0,
        ?int $peakPosition = null,
        ?int $weeksOnChart = null
    ): bool {
        $db = $this->getDatabase();

        // Check for an existing record for this week / source / position.
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ciwv_radiocharts'))
            ->where($db->quoteName('week_date') . ' = ' . $db->quote($weekDate))
            ->where($db->quoteName('source') . ' = ' . $db->quote($source))
            ->where($db->quoteName('position') . ' = ' . (int) $position);

        $db->setQuery($query);
        $existingId = $db->loadResult();

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        if ($existingId) {
            // Update.
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ciwv_radiocharts'))
                ->set([
                    $db->quoteName('artist') . ' = ' . $db->quote($artist),
                    $db->quoteName('title') . ' = ' . $db->quote($title),
                    $db->quoteName('label') . ' = ' . ($label !== null ? $db->quote($label) : 'NULL'),
                    $db->quoteName('plays') . ' = ' . (int) $plays,
                    $db->quoteName('streams') . ' = ' . (int) $streams,
                    $db->quoteName('peak_position') . ' = ' . ($peakPosition !== null ? (int) $peakPosition : 'NULL'),
                    $db->quoteName('weeks_on_chart') . ' = ' . ($weeksOnChart !== null ? (int) $weeksOnChart : 'NULL'),
                    $db->quoteName('updated_at') . ' = ' . $db->quote($now),
                ])
                ->where($db->quoteName('id') . ' = ' . (int) $existingId);
        } else {
            // Insert.
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ciwv_radiocharts'))
                ->columns($db->quoteName(['week_date', 'source', 'position', 'artist', 'title', 'label', 'plays', 'streams', 'peak_position', 'weeks_on_chart', 'created_at', 'updated_at']))
                ->values(implode(',', [
                    $db->quote($weekDate),
                    $db->quote($source),
                    (int) $position,
                    $db->quote($artist),
                    $db->quote($title),
                    $label !== null ? $db->quote($label) : 'NULL',
                    (int) $plays,
                    (int) $streams,
                    $peakPosition !== null ? (int) $peakPosition : 'NULL',
                    $weeksOnChart !== null ? (int) $weeksOnChart : 'NULL',
                    $db->quote($now),
                    $db->quote($now),
                ]));
        }

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }
}
