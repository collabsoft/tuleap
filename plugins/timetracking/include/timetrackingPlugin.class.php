<?php
/**
 * Copyright (c) Enalean, 2017 - 2018. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\Layout\IncludeAssets;
use Tuleap\Request\CurrentPage;
use Tuleap\Timetracking\Admin\AdminController;
use Tuleap\Timetracking\Admin\AdminDao;
use Tuleap\Timetracking\Admin\TimetrackingEnabler;
use Tuleap\Timetracking\Admin\TimetrackingUgroupDao;
use Tuleap\Timetracking\Admin\TimetrackingUgroupRetriever;
use Tuleap\Timetracking\Admin\TimetrackingUgroupSaver;
use Tuleap\Timetracking\ArtifactView\ArtifactViewBuilder;
use Tuleap\Timetracking\Permissions\PermissionsRetriever;
use Tuleap\Timetracking\Plugin\TimetrackingPluginInfo;
use Tuleap\Timetracking\REST\ResourcesInjector;
use Tuleap\Timetracking\Time\DateFormatter;
use Tuleap\Timetracking\Time\TimeChecker;
use Tuleap\Timetracking\Time\TimeController;
use Tuleap\Timetracking\Time\TimeDao;
use Tuleap\Timetracking\Time\TimePresenterBuilder;
use Tuleap\Timetracking\Time\TimeRetriever;
use Tuleap\Timetracking\Time\TimeUpdater;
use Tuleap\Timetracking\Router;
use Tuleap\Timetracking\Widget\UserWidget;

require_once 'constants.php';
require_once __DIR__ . '/../vendor/autoload.php';

class timetrackingPlugin extends Plugin // @codingStandardsIgnoreLine
{
    public function __construct($id)
    {
        parent::__construct($id);
        $this->setScope(Plugin::SCOPE_PROJECT);

        bindtextdomain('tuleap-timetracking', __DIR__.'/../site-content');
    }

    public function getHooksAndCallbacks()
    {
        $this->addHook('cssfile');
        $this->addHook('permission_get_name');
        $this->addHook('project_admin_ugroup_deletion');
        $this->addHook(\Tuleap\Widget\Event\GetWidget::NAME);
        $this->addHook(\Tuleap\Widget\Event\GetUserWidgetList::NAME);
        $this->addHook('fill_project_history_sub_events');
        $this->addHook(Event::BURNING_PARROT_GET_JAVASCRIPT_FILES);
        $this->addHook(Event::BURNING_PARROT_GET_STYLESHEETS);
        $this->addHook(Event::REST_RESOURCES);

        if (defined('TRACKER_BASE_URL')) {
            $this->addHook(TRACKER_EVENT_FETCH_ADMIN_BUTTONS);
            $this->addHook(Tracker_Artifact_EditRenderer::EVENT_ADD_VIEW_IN_COLLECTION);
        }

        return parent::getHooksAndCallbacks();
    }

    public function getPluginInfo()
    {
        if (! is_a($this->pluginInfo, TimetrackingPluginInfo::class)) {
            $this->pluginInfo = new TimetrackingPluginInfo($this);
        }

        return $this->pluginInfo;
    }

    public function getDependencies()
    {
        return array('tracker');
    }

    public function cssfile($params)
    {
        if (strpos($_SERVER['REQUEST_URI'], $this->getPluginPath()) === 0 ||
            strpos($_SERVER['REQUEST_URI'], TRACKER_BASE_URL) === 0
        ) {
            echo '<link rel="stylesheet" type="text/css" href="'.$this->getThemePath().'/css/style.css" />';
        }
    }

    /**
     * @see TRACKER_EVENT_FETCH_ADMIN_BUTTONS
     */
    public function trackerEventFetchAdminButtons($params)
    {
        $url = TIMETRACKING_BASE_URL . '/?'. http_build_query(array(
                'tracker' => $params['tracker_id'],
                'action'  => 'admin-timetracking'
        ));

        $params['items']['timetracking'] = array(
            'url'         => $url,
            'short_title' => dgettext('tuleap-timetracking', 'Time tracking'),
            'title'       => dgettext('tuleap-timetracking', 'Time tracking'),
            'description' => dgettext('tuleap-timetracking', 'Time tracking for Tuleap artifacts'),
            'img'         => TIMETRACKING_BASE_URL . '/images/icon-timetracking.png'
        );
    }

    public function process(Codendi_Request $request)
    {
        $router = new Router(
            TrackerFactory::instance(),
            Tracker_ArtifactFactory::instance(),
            $this->getAdminController(),
            $this->getTimeController()
        );

        $router->route($request);
    }

    /**
     * @return AdminController
     */
    private function getAdminController()
    {
        return new AdminController(
            new TrackerManager(),
            $this->getTimetrackingEnabler(),
            new User_ForgeUserGroupFactory(new UserGroupDao()),
            new PermissionsNormalizer(),
            new TimetrackingUgroupSaver(new TimetrackingUgroupDao()),
            $this->getTimetrackingUgroupRetriever(),
            new ProjectHistoryDao()
        );
    }

    /**
     * @return TimeController
     */
    private function getTimeController()
    {
        $time_dao     = new TimeDao();
        $time_updater = new TimeUpdater(
            $time_dao,
            new TimeChecker(),
            $this->getPermissionsRetriever()
        );

        return new TimeController(
            $time_updater,
            new TimeRetriever($time_dao, $this->getPermissionsRetriever())
        );
    }

    /**
     * @return TimetrackingUgroupRetriever
     */
    private function getTimetrackingUgroupRetriever()
    {
        return new TimetrackingUgroupRetriever(new TimetrackingUgroupDao());
    }

    /**
     * @return PermissionsRetriever
     */
    private function getPermissionsRetriever()
    {
        return new PermissionsRetriever($this->getTimetrackingUgroupRetriever());
    }

    /** @see Tracker_Artifact_EditRenderer::EVENT_ADD_VIEW_IN_COLLECTION */
    public function tracker_artifact_editrenderer_add_view_in_collection(array $params) // @codingStandardsIgnoreLine
    {
        $user       = $params['user'];
        $request    = $params['request'];
        $artifact   = $params['artifact'];

        $permissions_retriever = $this->getPermissionsRetriever();
        $time_retriever        = new TimeRetriever(new TimeDao(), $permissions_retriever);
        $date_formatter        = new DateFormatter();
        $builder               = new ArtifactViewBuilder(
            $this,
            $this->getTimetrackingEnabler(),
            $permissions_retriever,
            $time_retriever,
            new TimePresenterBuilder($date_formatter, UserManager::instance()),
            $date_formatter
        );

        $view = $builder->build($user, $request, $artifact);

        if ($view) {
            $collection = $params['collection'];
            $collection->add($view);
        }
    }

    /**
     * @return TimetrackingEnabler
     */
    private function getTimetrackingEnabler()
    {
        return new TimetrackingEnabler(new AdminDao());
    }

    public function permission_get_name(array $params) // @codingStandardsIgnoreLine
    {
        if (! $params['name']) {
            switch ($params['permission_type']) {
                case AdminController::WRITE_ACCESS:
                    $params['name'] = dgettext('tuleap-timetracking', 'Write');
                    break;
                case AdminController::READ_ACCESS:
                    $params['name'] = dgettext('tuleap-timetracking', 'Read');
                    break;
                default:
                    break;
            }
        }
    }

    public function project_admin_ugroup_deletion(array $params) // @codingStandardsIgnoreLine
    {
        $ugroup = $params['ugroup'];

        $dao = new TimetrackingUgroupDao();
        $dao->deleteByUgroupId($ugroup->getId());
    }

    public function widgetInstance(\Tuleap\Widget\Event\GetWidget $get_widget_event)
    {
        if ($get_widget_event->getName() === UserWidget::NAME) {
            $get_widget_event->setWidget(new UserWidget());
        }
    }

    public function getUserWidgetList(\Tuleap\Widget\Event\GetUserWidgetList $event)
    {
        $event->addWidget(UserWidget::NAME);
    }

    public function fill_project_history_sub_events($params) // @codingStandardsIgnoreLine
    {
        array_push(
            $params['subEvents']['event_others'],
            'timetracking_enabled',
            'timetracking_disabled',
            'timetracking_permissions_updated'
        );
    }

    public function burningParrotGetJavascriptFiles(array $params)
    {
        if ($this->isInDashboard()) {
            $include_assets = new IncludeAssets(
                TIMETRACKING_BASE_DIR . '/www/assets',
                $this->getPluginPath() . '/assets'
            );

            $params['javascript_files'][] = $include_assets->getFileURL('widget-timetracking.js');
        }
    }

    public function burningParrotGetStylesheets(array $params)
    {
        if ($this->isInDashboard()) {
            $theme_include_assets = new IncludeAssets(
                $this->getFilesystemPath() . '/www/themes/BurningParrot/assets',
                $this->getThemePath() . '/assets'
            );

            $variant                 = $params['variant'];
            $params['stylesheets'][] = $theme_include_assets->getFileURL('style-' . $variant->getName() . '.css');
        }
    }

    private function isInDashboard()
    {
        $current_page = new CurrentPage();

        return $current_page->isDashboard();
    }

    /** @see Event::REST_RESOURCES */
    public function restResources(array $params)
    {
        $injector = new ResourcesInjector();
        $injector->populate($params['restler']);
    }
}
