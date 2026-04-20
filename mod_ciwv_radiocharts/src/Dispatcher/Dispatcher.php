<?php

/**
 * @package     mod_ciwv_radiocharts
 * @subpackage  Dispatcher
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Module\CiwvRadiocharts\Site\Dispatcher;

\defined('_JEXEC') or die;

use Gonzoradio\Module\CiwvRadiocharts\Site\Helper\RadiochartsHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Uri\Uri;

/**
 * Dispatcher class for mod_ciwv_radiocharts.
 *
 * Collects chart data from d6f25_ciwv_radiocharts and passes it to the template.
 * All six CSV source types are supported:
 *   mediabase_national, mediabase_local, luminate, luminate_market,
 *   musicmaster, billboard.
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * All known source identifiers. Used as the fallback when the saved param
     * resolves to an empty list.
     */
    private const ALL_SOURCES = 'mediabase_national,mediabase_local,luminate,luminate_market,musicmaster,billboard';

    /**
     * Returns the layout data array that is passed to the module template.
     *
     * NOTE: Defaults passed to $params->get() are intentionally plain strings
     * or scalars — never arrays or objects.  Passing a PHP array as the default
     * triggers Joomla's Registry JSON formatter which can exhaust memory when
     * the Registry object is later serialised by the cache layer.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];

        // Enqueue the module stylesheet.
        $this->getApplication()->getDocument()->addStyleSheet(
            Uri::root(true) . '/media/mod_ciwv_radiocharts/css/mod_ciwv_radiocharts.css'
        );

        /** @var RadiochartsHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('RadiochartsHelper');

        $weekOffset  = (int) $params->get('week_offset', 0);
        $limit       = max(1, (int) $params->get('limit', 50));

        // Use a STRING default — not an array — to avoid Registry JSON exhaustion.
        $showSources = $params->get('show_sources', self::ALL_SOURCES);

        // Normalise to a plain PHP array regardless of what the Registry returns.
        if (\is_string($showSources)) {
            $showSources = array_values(array_filter(explode(',', $showSources)));
        } elseif (!\is_array($showSources)) {
            $showSources = $showSources !== null ? array_values((array) $showSources) : [];
        }

        // Fall back to all sources when the param resolves to an empty array.
        if (empty($showSources)) {
            $showSources = explode(',', self::ALL_SOURCES);
        }

        $weekDate         = $helper->getWeekDate($weekOffset);
        $previousWeekDate = $helper->getWeekDate($weekOffset - 1);

        $data['weekDate']           = $weekDate;
        $data['previousWeekDate']   = $previousWeekDate;
        $data['showSources']        = $showSources;
        $data['showComparison']     = (bool) $params->get('show_comparison', 1);
        $data['allowCategoryEdit']  = (bool) $params->get('allow_category_edit', 0);
        $data['stationCallsign']    = (string) $params->get('station_callsign', 'CIWV');
        $data['chartEntries']       = $helper->getChartEntries($weekDate, $showSources, $limit);
        $data['previousEntries']    = $data['showComparison']
            ? $helper->getChartEntries($previousWeekDate, $showSources, $limit)
            : [];

        return $data;
    }
}
