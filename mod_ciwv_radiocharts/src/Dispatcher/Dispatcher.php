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
 * Collects chart data from the database and passes it to the module template.
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data array that is passed to the module template.
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
        $limit       = (int) $params->get('limit', 50);
        $showSources = $params->get('show_sources', ['mediabase_national', 'mediabase_local', 'luminate', 'musicmaster']);

        // Normalise show_sources to an array (Joomla may store it as a comma-separated string).
        if (is_string($showSources)) {
            $showSources = array_filter(explode(',', $showSources));
        }

        $weekDate        = $helper->getWeekDate($weekOffset);
        $previousWeekDate = $helper->getWeekDate($weekOffset - 1);

        $data['weekDate']          = $weekDate;
        $data['previousWeekDate']  = $previousWeekDate;
        $data['showSources']       = $showSources;
        $data['showComparison']    = (bool) $params->get('show_comparison', 1);
        $data['chartEntries']      = $helper->getChartEntries($weekDate, $showSources, $limit);
        $data['previousEntries']   = $data['showComparison']
            ? $helper->getChartEntries($previousWeekDate, $showSources, $limit)
            : [];

        return $data;
    }
}
