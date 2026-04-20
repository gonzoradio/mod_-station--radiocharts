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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Variables injected by Dispatcher::getLayoutData():
 *
 * @var  \Joomla\CMS\Application\SiteApplication  $app
 * @var  \Joomla\Registry\Registry                $params
 * @var  string                                   $weekDate
 * @var  string                                   $previousWeekDate
 * @var  string[]                                 $showSources
 * @var  bool                                     $showComparison
 * @var  bool                                     $allowCategoryEdit
 * @var  string                                   $stationCallsign
 * @var  array<string, object[]>                  $chartEntries
 * @var  array<string, object[]>                  $previousEntries
 */

$previousPositionMap = $showComparison
    ? RadiochartsHelper::buildPreviousPositionMap($previousEntries)
    : [];

// Human-readable labels for all six source types.
$sourceLabels = [
    'mediabase_national' => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_MEDIABASE_NATIONAL'),
    'mediabase_local'    => Text::sprintf('MOD_CIWV_RADIOCHARTS_SOURCE_MEDIABASE_LOCAL', $stationCallsign),
    'luminate'           => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_LUMINATE'),
    'luminate_market'    => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_LUMINATE_MARKET'),
    'musicmaster'        => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_MUSICMASTER'),
    'billboard'          => Text::_('MOD_CIWV_RADIOCHARTS_SOURCE_BILLBOARD'),
];

// Source display order.
$sourceOrder   = array_keys($sourceLabels);
$activeSources = array_values(array_intersect($sourceOrder, $showSources));

// Build category dropdown options HTML (TW / NW share the same option list).
$categoryOptionHtml = '<option value="">' . htmlspecialchars(Text::_('MOD_CIWV_RADIOCHARTS_CATEGORY_NONE'), ENT_QUOTES, 'UTF-8') . '</option>';

foreach (RadiochartsHelper::CATEGORY_ORDER as $cat) {
    $categoryOptionHtml .= '<option value="' . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8')
        . '</option>';
}

// CAT/CODE dropdown options.
$codeOptionHtml = '<option value="">' . htmlspecialchars(Text::_('MOD_CIWV_RADIOCHARTS_CATEGORY_NONE'), ENT_QUOTES, 'UTF-8') . '</option>';

foreach (RadiochartsHelper::CATEGORY_CODES as $code) {
    $codeOptionHtml .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
        . '</option>';
}

// Module unique ID used to namespace form/element IDs.
$modId = 'mod-ciwv-radiocharts-' . ($module->id ?? '0');
?>

<div class="mod-ciwv-radiocharts" id="<?php echo htmlspecialchars($modId, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="radiocharts-header">
        <h3 class="radiocharts-week-label">
            <?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_WEEK_OF', HTMLHelper::_('date', $weekDate, Text::_('DATE_FORMAT_LC3'))); ?>
        </h3>
        <?php if ($showComparison) : ?>
            <small class="radiocharts-comparison-note text-muted">
                <?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_COMPARED_TO', HTMLHelper::_('date', $previousWeekDate, Text::_('DATE_FORMAT_LC3'))); ?>
            </small>
        <?php endif; ?>
    </div><!-- /.radiocharts-header -->

    <?php if (empty($chartEntries)) : ?>
        <p class="radiocharts-no-data alert alert-info">
            <?php echo Text::_('MOD_CIWV_RADIOCHARTS_NO_DATA'); ?>
        </p>
    <?php else : ?>

        <?php
        // If category editing is enabled, wrap everything in a single form so
        // the PD / MD can save TW/NW choices for multiple songs in one submit.
        // The form action must be handled by a component or com_ajax endpoint.
        if ($allowCategoryEdit) :
        ?>
        <form method="post"
              action="<?php echo Route::_('index.php?option=com_ajax&module=ciwv_radiocharts&method=saveCategories&format=json'); ?>"
              id="<?php echo $modId; ?>-category-form"
              class="radiocharts-category-form">
            <input type="hidden" name="week_date" value="<?php echo htmlspecialchars($weekDate, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        <?php endif; ?>

        <?php foreach ($activeSources as $source) : ?>
            <?php if (!isset($chartEntries[$source]) || empty($chartEntries[$source])) : ?>
                <?php continue; ?>
            <?php endif; ?>

            <section class="radiocharts-source radiocharts-source--<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
                <h4 class="radiocharts-source-title">
                    <?php echo htmlspecialchars($sourceLabels[$source] ?? $source, ENT_QUOTES, 'UTF-8'); ?>
                </h4>

                <div class="table-responsive">
                <table class="radiocharts-table table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <!-- TW / NW category columns (always first, per original spec) -->
                            <th class="radiocharts-col-cat-tw" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CAT_TW'); ?></th>
                            <th class="radiocharts-col-cat-nw" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CAT_NW'); ?></th>
                            <!-- Chart position columns -->
                            <th class="radiocharts-col-pos" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_POSITION'); ?></th>
                            <?php if ($showComparison) : ?>
                                <th class="radiocharts-col-change" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CHANGE'); ?></th>
                            <?php endif; ?>
                            <!-- Song information columns -->
                            <th class="radiocharts-col-artist" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_ARTIST'); ?></th>
                            <th class="radiocharts-col-title" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_TITLE'); ?></th>
                            <th class="radiocharts-col-label" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_LABEL'); ?></th>
                            <!-- Source-specific data columns -->
                            <?php if ($source === 'luminate' || $source === 'luminate_market') : ?>
                                <th class="radiocharts-col-streams" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_STREAMS'); ?></th>
                            <?php elseif ($source === 'mediabase_local') : ?>
                                <th class="radiocharts-col-plays" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_PLAYS'); ?></th>
                                <th class="radiocharts-col-hist-spins" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_HIST_SPINS'); ?></th>
                                <th class="radiocharts-col-market-spins" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_MARKET_SPINS'); ?></th>
                                <th class="radiocharts-col-market-stns" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_MARKET_STNS'); ?></th>
                                <th class="radiocharts-col-avg-spins" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_AVG_SPINS'); ?></th>
                            <?php else : ?>
                                <th class="radiocharts-col-plays" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_PLAYS'); ?></th>
                            <?php endif; ?>
                            <th class="radiocharts-col-peak" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_PEAK'); ?></th>
                            <th class="radiocharts-col-woc" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_WEEKS_ON_CHART'); ?></th>
                            <!-- CAT/CODE column -->
                            <th class="radiocharts-col-code" scope="col"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CODE'); ?></th>
                            <?php if ($allowCategoryEdit) : ?>
                                <th class="radiocharts-col-save" scope="col"><span class="visually-hidden"><?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_ACTIONS'); ?></span></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chartEntries[$source] as $entry) :
                            $mapKey     = RadiochartsHelper::canonicalizeSongKey($source, $entry->artist, $entry->title);
                            $prevPos    = $previousPositionMap[$mapKey] ?? null;
                            $currentPos = (int) $entry->position;

                            // Calculate average spins (market) for station playlist.
                            // Use explicit > 0 check to guard against division by zero.
                            $avgSpins = null;
                            if ($source === 'mediabase_local'
                                && (int) $entry->market_spins_tw > 0
                                && (int) $entry->market_stations_tw > 0
                            ) {
                                $avgSpins = round((int) $entry->market_spins_tw / (int) $entry->market_stations_tw, 1);
                            }

                            $rowId = (int) $entry->id;
                        ?>
                        <tr data-row-id="<?php echo $rowId; ?>">

                            <!-- TW category dropdown -->
                            <td class="radiocharts-col-cat-tw">
                                <?php if ($allowCategoryEdit) : ?>
                                    <select name="category[<?php echo $rowId; ?>][tw]"
                                            class="radiocharts-cat-select form-select form-select-sm"
                                            aria-label="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CAT_TW'); ?>">
                                        <?php echo str_replace(
                                            'value="' . htmlspecialchars((string) $entry->category_tw, ENT_QUOTES, 'UTF-8') . '"',
                                            'value="' . htmlspecialchars((string) $entry->category_tw, ENT_QUOTES, 'UTF-8') . '" selected',
                                            $categoryOptionHtml
                                        ); ?>
                                    </select>
                                <?php else : ?>
                                    <span class="badge radiocharts-badge-tw">
                                        <?php echo htmlspecialchars($entry->category_tw ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- NW category dropdown -->
                            <td class="radiocharts-col-cat-nw">
                                <?php if ($allowCategoryEdit) : ?>
                                    <select name="category[<?php echo $rowId; ?>][nw]"
                                            class="radiocharts-cat-select form-select form-select-sm"
                                            aria-label="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CAT_NW'); ?>">
                                        <?php echo str_replace(
                                            'value="' . htmlspecialchars((string) $entry->category_nw, ENT_QUOTES, 'UTF-8') . '"',
                                            'value="' . htmlspecialchars((string) $entry->category_nw, ENT_QUOTES, 'UTF-8') . '" selected',
                                            $categoryOptionHtml
                                        ); ?>
                                    </select>
                                <?php else : ?>
                                    <span class="badge radiocharts-badge-nw">
                                        <?php echo htmlspecialchars($entry->category_nw ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Chart position -->
                            <td class="radiocharts-col-pos text-center fw-bold"><?php echo $currentPos; ?></td>

                            <!-- Week-over-week change -->
                            <?php if ($showComparison) :
                                $changeClass = '';
                                $changeIcon  = '';
                                $diff        = 0;

                                if ($prevPos !== null) {
                                    $diff        = $prevPos - $currentPos;
                                    $changeClass = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
                                    $changeIcon  = $diff > 0 ? '&#9650;' : ($diff < 0 ? '&#9660;' : '&#9654;');
                                } else {
                                    $changeClass = 'text-primary';
                                    $changeIcon  = '&#11088;';
                                }
                            ?>
                            <td class="radiocharts-col-change text-center <?php echo $changeClass; ?>">
                                <?php if ($prevPos !== null) : ?>
                                    <span title="<?php echo Text::sprintf('MOD_CIWV_RADIOCHARTS_PREV_POS', $prevPos); ?>">
                                        <?php echo $changeIcon; ?>
                                        <?php if ($diff !== 0) : ?><small><?php echo abs($diff); ?></small><?php endif; ?>
                                    </span>
                                <?php else : ?>
                                    <span class="text-primary" title="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_NEW_ENTRY'); ?>"><?php echo $changeIcon; ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <!-- Artist / Title / Label -->
                            <td class="radiocharts-col-artist"><?php echo htmlspecialchars($entry->artist, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="radiocharts-col-title"><?php echo htmlspecialchars($entry->title, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="radiocharts-col-label"><?php echo htmlspecialchars($entry->label ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                            <!-- Source-specific numeric columns -->
                            <?php if ($source === 'luminate' || $source === 'luminate_market') : ?>
                                <td class="radiocharts-col-streams text-end"><?php echo $entry->streams > 0 ? number_format((int) $entry->streams) : '—'; ?></td>
                            <?php elseif ($source === 'mediabase_local') : ?>
                                <td class="radiocharts-col-plays text-end"><?php echo number_format((int) $entry->plays); ?></td>
                                <td class="radiocharts-col-hist-spins text-end"><?php echo $entry->hist_spins !== null ? number_format((int) $entry->hist_spins) : '—'; ?></td>
                                <td class="radiocharts-col-market-spins text-end"><?php echo $entry->market_spins_tw !== null ? number_format((int) $entry->market_spins_tw) : '—'; ?></td>
                                <td class="radiocharts-col-market-stns text-end"><?php echo $entry->market_stations_tw !== null ? (int) $entry->market_stations_tw : '—'; ?></td>
                                <td class="radiocharts-col-avg-spins text-end"><?php echo $avgSpins !== null ? $avgSpins : '—'; ?></td>
                            <?php else : ?>
                                <td class="radiocharts-col-plays text-end"><?php echo number_format((int) $entry->plays); ?></td>
                            <?php endif; ?>

                            <td class="radiocharts-col-peak text-center"><?php echo $entry->peak_position ?? '—'; ?></td>
                            <td class="radiocharts-col-woc text-center"><?php echo $entry->weeks_on_chart ?? '—'; ?></td>

                            <!-- CAT/CODE column -->
                            <td class="radiocharts-col-code">
                                <?php if ($allowCategoryEdit) : ?>
                                    <select name="category[<?php echo $rowId; ?>][code]"
                                            class="radiocharts-code-select form-select form-select-sm"
                                            aria-label="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_COL_CODE'); ?>">
                                        <?php echo str_replace(
                                            'value="' . htmlspecialchars((string) $entry->category_code, ENT_QUOTES, 'UTF-8') . '"',
                                            'value="' . htmlspecialchars((string) $entry->category_code, ENT_QUOTES, 'UTF-8') . '" selected',
                                            $codeOptionHtml
                                        ); ?>
                                    </select>
                                <?php else : ?>
                                    <?php echo htmlspecialchars($entry->category_code ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </td>

                            <?php if ($allowCategoryEdit) : ?>
                            <td class="radiocharts-col-save text-center">
                                <button type="button"
                                        class="btn btn-sm btn-primary radiocharts-save-row"
                                        data-row-id="<?php echo $rowId; ?>"
                                        title="<?php echo Text::_('MOD_CIWV_RADIOCHARTS_SAVE_ROW'); ?>">
                                    &#10003;
                                </button>
                            </td>
                            <?php endif; ?>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- /.table-responsive -->

            </section><!-- /.radiocharts-source -->

        <?php endforeach; ?>

        <?php if ($allowCategoryEdit) : ?>
            <div class="radiocharts-form-actions mt-3">
                <button type="submit" class="btn btn-success">
                    <?php echo Text::_('MOD_CIWV_RADIOCHARTS_SAVE_ALL_CATEGORIES'); ?>
                </button>
            </div>
        </form>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- /.mod-ciwv-radiocharts -->

<?php if ($allowCategoryEdit) : ?>
<script>
(function () {
    'use strict';

    // Per-row save: collect only that row's selects and POST via fetch.
    // Copy the hidden CSRF token from the full-form submission so the per-row
    // save carries a valid Joomla form token.
    var formEl    = document.getElementById('<?php echo $modId; ?>-category-form');
    var tokenInput = formEl ? formEl.querySelector('input[type="hidden"][name]') : null;

    document.querySelectorAll('#<?php echo $modId; ?> .radiocharts-save-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rowId = this.dataset.rowId;
            var row   = document.querySelector('tr[data-row-id="' + rowId + '"]');
            if (!row || !formEl) return;

            var data = new FormData();
            data.append('week_date', formEl.querySelector('input[name="week_date"]').value);

            // Include the Joomla CSRF token copied from the full-form hidden input.
            if (tokenInput) {
                data.append(tokenInput.name, tokenInput.value);
            }

            row.querySelectorAll('select[name^="category["]').forEach(function (sel) {
                data.append(sel.name, sel.value);
            });

            fetch(formEl.action, {
                method : 'POST',
                body   : data,
            }).then(function (r) {
                btn.textContent = r.ok ? '✔' : '✘';
            }).catch(function () {
                btn.textContent = '✘';
            });
        });
    });
}());
</script>
<?php endif; ?>
