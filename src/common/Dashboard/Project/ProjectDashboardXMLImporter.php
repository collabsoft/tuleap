<?php
/**
 *  Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 *  This file is a part of Tuleap.
 *
 *  Tuleap is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  Tuleap is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Dashboard\Project;

use PFUser;
use Project;
use Codendi_Request;
use Tuleap\Dashboard\NameDashboardAlreadyExistsException;
use Tuleap\Dashboard\NameDashboardDoesNotExistException;
use Tuleap\Widget\WidgetFactory;
use Tuleap\Dashboard\Widget\DashboardWidgetDao;

class ProjectDashboardXMLImporter
{
    /**
     * @var ProjectDashboardSaver
     */
    private $project_dashboard_saver;
    /**
     * @var \Logger
     */
    private $logger;
    /**
     * @var WidgetFactory
     */
    private $widget_factory;
    /**
     * @var DashboardWidgetDao
     */
    private $widget_dao;

    public function __construct(ProjectDashboardSaver $project_dashboard_saver, WidgetFactory $widget_factory, DashboardWidgetDao $widget_dao, \Logger $logger)
    {
        $this->project_dashboard_saver = $project_dashboard_saver;
        $this->widget_factory          = $widget_factory;
        $this->widget_dao              = $widget_dao;
        $this->logger                  = new \WrapperLogger($logger, 'Dashboards');
    }

    public function import(\SimpleXMLElement $xml_element, PFUser $user, Project $project)
    {
        $this->logger->info('Start import');
        if ($xml_element->dashboards) {
            foreach ($xml_element->dashboards->dashboard as $dashboard) {
                try {
                    $dashboard_name = trim((string) $dashboard["name"]);
                    $this->logger->info("Create dashboard $dashboard_name");
                    $dashboard_id = $this->project_dashboard_saver->save($user, $project, $dashboard_name);
                    $this->importWidgets($dashboard_id, $project, $dashboard);
                } catch (UserCanNotUpdateProjectDashboardException $e) {
                    $this->logger->warn($e->getMessage());
                } catch (NameDashboardDoesNotExistException $e) {
                    $this->logger->warn($e->getMessage());
                } catch (NameDashboardAlreadyExistsException $e) {
                    $this->logger->warn($e->getMessage());
                }
            }
        }
        $this->logger->info('Import completed');
    }

    private function importWidgets($dashboard_id, Project $project, \SimpleXMLElement $dashboard)
    {
        $this->logger->info("Import widgets");
        if (! isset($dashboard->line)) {
            return;
        }

        $line_rank = 1;
        $all_widgets = [];
        foreach ($dashboard->line as $line) {
            $this->createLine($line, $project, $dashboard_id, $line_rank, $all_widgets);
            $line_rank++;
        }
        $this->logger->info("Import of widgets: Done");
    }

    private function createLine(\SimpleXMLElement $line, Project $project, $dashboard_id, $line_rank, array &$all_widgets)
    {
        $line_id = -1;
        $column_rank = 1;
        foreach ($line->column as $column) {
            $this->createColumn($column, $project, $dashboard_id, $line_id, $line_rank, $column_rank, $all_widgets);
            $column_rank++;
        }
        if ($column_rank > 2) {
            $this->widget_dao->adjustLayoutAccordinglyToNumberOfWidgets($column_rank - 1, $line_id);
        }
    }

    private function createColumn(\SimpleXMLElement $column, Project $project, $dashboard_id, &$line_id, $line_rank, $column_rank, array &$all_widgets)
    {
        $column_id = -1;
        $widget_rank = 1;
        foreach ($column->widget as $widget_xml) {
            list($widget, $content_id) = $this->getWidget($project, $widget_xml, $all_widgets);
            if (! $this->isWidgetCreated($widget, $content_id)) {
                continue;
            }
            if (! $this->isLineCreated($line_id)) {
                $line_id = $this->widget_dao->createLine($dashboard_id, ProjectDashboardController::DASHBOARD_TYPE, $line_rank);
            }
            if (! $this->isColumnCreated($line_id, $column_id)) {
                $column_id = $this->widget_dao->createColumn($line_id, $column_rank);
            }
            if ($column_id) {
                $this->widget_dao->insertWidgetInColumnWithRank($widget->getId(), $content_id, $column_id, $widget_rank);
                $all_widgets[$widget->getId()] = true;
            } else {
                $this->logger->warn("Impossible to created line or column, widget {$widget->getId()} not added");
            }
            $widget_rank++;
        }
    }

    private function isWidgetCreated($widget, $content_id)
    {
        return $widget !== null && $content_id !== null;
    }

    private function isLineCreated($line_id)
    {
        return $line_id !== -1;
    }

    private function isColumnCreated($line_id, $column_id)
    {
        return $line_id && $column_id !== -1;
    }

    /**
     * @param Project $project
     * @param \SimpleXMLElement $widget_xml
     * @return []
     */
    private function getWidget(Project $project, \SimpleXMLElement $widget_xml, array $all_widgets)
    {
        $widget_name = trim((string) $widget_xml['name']);
        $this->logger->info("Import widget $widget_name");
        $widget = $this->widget_factory->getInstanceByWidgetName($widget_name);
        if ($widget === null) {
            $this->logger->error("Impossible to instantiate widget named '".$widget_name."', is name valid ?.  Widget skipped");
            return [null, null];
        }
        if ($widget->isUnique() && isset($all_widgets[$widget->getId()])) {
            $this->logger->warn("Impossible to instantiate twice widget named '".$widget_name."'.  Widget skipped");
            return [null, null];
        }
        if (! $this->widget_factory->isProjectWidget($widget)) {
            $this->logger->error("Impossible to instantiate a personal widget ($widget_name) on a Project Dashboard.  Widget skipped");
            return [null, null];
        }
        $content_id = $this->configureWidget($project, $widget, $widget_xml);
        if ($content_id === false) {
            $this->logger->error("Impossible to create content for widget $widget_name. Widget skipped");
            return [null, null];
        }
        return [$widget, (int) $content_id];
    }

    /**
     * @param Project $project
     * @param \Widget $widget
     * @param \SimpleXMLElement $widget_xml
     *
     * @return null|false|int
     */
    private function configureWidget(Project $project, \Widget $widget, \SimpleXMLElement $widget_xml)
    {
        $widget->setOwner($project->getID(), ProjectDashboardController::LEGACY_DASHBOARD_TYPE);
        $params = [];
        if (isset($widget_xml->preference)) {
            foreach ($widget_xml->preference as $preference) {
                $preference_name = trim((string)$preference['name']);
                foreach ($preference->value as $value) {
                    $key = trim((string)$value['name']);
                    $val = trim((string)$value);
                    $params[$preference_name][$key] = $val;
                }
            }
        }
        return $widget->create(new Codendi_Request($params));
    }
}