<?php

/**
 * @package     mod_ciwv_radiocharts
 * @subpackage  tmpl
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Gonzoradio\Module\CiwvRadiocharts\Site\Helper\RadiochartsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Variables injected by Dispatcher::getLayoutData():
 *
 * @var  \Joomla\CMS\Application\SiteApplication  $app
 * @var  \Joomla\Registry\Registry                $params
 * @var  string                                   $weekDate
 * @var  string                                   $previousWeekDate
 * @var  string[]                                 $showSources
 * @var  bool                                     $showComparison
 * @var  array<string, object[]>                  $chartEntries
 * @var  array<string, object[]>                  $previousEntries
 */

$previousPositionMap = $showComparison
    ? RadiochartsHelper::buildPreviousPositionMap($previousEntries)
    : [];

$sourceLabels = [
    'mediabase_national' => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_MEDIABASE_NATIONAL'),
    'mediabase_local'    => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_MEDIABASE_LOCAL'),
    'luminate'           => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_LUMINATE'),
    'musicmaster'        => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_MUSICMASTER'),
];

// Source display order (as specified in the requirements).
$sourceOrder = ['mediabase_national', 'mediabase_local', 'luminate', 'musicmaster'];
$activeSources = array_values(array_intersect($sourceOrder, $showSources));
?>

<div class="mod-ciwv-radiocharts">
    <div class="radiocharts-header">
        <h3 class="radiocharts-week-label">
            <?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_WEEK_OF', HTMLHelper::_('date', $weekDate, Text::_('DATE_FORMAT_LC3'))); ?>
        </h3>
        <?php if ($showComparison) : ?>
            <small class="radiocharts-comparison-note text-muted">
                <?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_COMPARED_TO', HTMLHelper::_('date', $previousWeekDate, Text::_('DATE_FORMAT_LC3'))); ?>
            </small>
        <?php endif; ?>
    </div>

    <?php if (empty($chartEntries)) : ?>
        <p class="radiocharts-no-data"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_NO_DATA'); ?></p>
    <?php else : ?>

        <?php foreach ($activeSources as $source) : ?>
            <?php if (!isset($chartEntries[$source]) || empty($chartEntries[$source])) : ?>
                <?php continue; ?>
            <?php endif; ?>

            <section class="radiocharts-source radiocharts-source--<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
                <h4 class="radiocharts-source-title">
                    <?php echo htmlspecialchars($sourceLabels[$source] ?? $source, ENT_QUOTES, 'UTF-8'); ?>
                </h4>

                <table class="radiocharts-table table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th class="radiocharts-col-pos" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_POSITION'); ?></th>
                            <?php if ($showComparison) : ?>
                                <th class="radiocharts-col-change" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CHANGE'); ?></th>
                            <?php endif; ?>
                            <th class="radiocharts-col-artist" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_ARTIST'); ?></th>
                            <th class="radiocharts-col-title" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_TITLE'); ?></th>
                            <th class="radiocharts-col-label" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_LABEL'); ?></th>
                            <?php if ($source === 'luminate') : ?>
                                <th class="radiocharts-col-streams" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_STREAMS'); ?></th>
                            <?php else : ?>
                                <th class="radiocharts-col-plays" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_PLAYS'); ?></th>
                            <?php endif; ?>
                            <th class="radiocharts-col-peak" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_PEAK'); ?></th>
                            <th class="radiocharts-col-woc" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_WEEKS_ON_CHART'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chartEntries[$source] as $entry) : ?>
                            <?php
                                $mapKey      = strtolower($source . '|' . $entry->artist . '|' . $entry->title);
                                $prevPos     = $previousPositionMap[$mapKey] ?? null;
                                $currentPos  = (int) $entry->position;
                                $changeClass = '';
                                $changeIcon  = '';

                                if ($showComparison && $prevPos !== null) {
                                    $diff = $prevPos - $currentPos;

                                    if ($diff > 0) {
                                        $changeClass = 'text-success';
                                        $changeIcon  = '&#9650;'; // ▲
                                    } elseif ($diff < 0) {
                                        $changeClass = 'text-danger';
                                        $changeIcon  = '&#9660;'; // ▼
                                    } else {
                                        $changeClass = 'text-muted';
                                        $changeIcon  = '&#9654;'; // ► (no change)
                                    }
                                } elseif ($showComparison && $prevPos === null) {
                                    $changeClass = 'text-primary';
                                    $changeIcon  = '&#11088;'; // ★ (new entry)
                                }
                            ?>
                            <tr>
                                <td class="radiocharts-col-pos text-center fw-bold"><?php echo $currentPos; ?></td>
                                <?php if ($showComparison) : ?>
                                    <td class="radiocharts-col-change text-center <?php echo $changeClass; ?>">
                                        <?php if ($prevPos !== null) : ?>
                                            <?php $diff = $prevPos - $currentPos; ?>
                                            <span title="<?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_PREV_POS', $prevPos); ?>">
                                                <?php echo $changeIcon; ?>
                                                <?php if ($diff !== 0) : ?>
                                                    <small><?php echo abs($diff); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="text-primary" title="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_NEW_ENTRY'); ?>"><?php echo $changeIcon; ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="radiocharts-col-artist"><?php echo htmlspecialchars($entry->artist, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="radiocharts-col-title"><?php echo htmlspecialchars($entry->title, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="radiocharts-col-label"><?php echo htmlspecialchars($entry->label ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php if ($source === 'luminate') : ?>
                                    <td class="radiocharts-col-streams text-end"><?php echo number_format((int) $entry->streams); ?></td>
                                <?php else : ?>
                                    <td class="radiocharts-col-plays text-end"><?php echo number_format((int) $entry->plays); ?></td>
                                <?php endif; ?>
                                <td class="radiocharts-col-peak text-center"><?php echo $entry->peak_position ?? '—'; ?></td>
                                <td class="radiocharts-col-woc text-center"><?php echo $entry->weeks_on_chart ?? '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

        <?php endforeach; ?>

    <?php endif; ?>
</div>
