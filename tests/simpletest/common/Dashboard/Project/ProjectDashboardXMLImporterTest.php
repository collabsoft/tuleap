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
 *
 * @codingStandardsIgnoreFile
 */

namespace Tuleap\Dashboard\Project;

use Mockery\Exception;
use SimpleXMLElement;
use Tuleap\Dashboard\NameDashboardAlreadyExistsException;
use Tuleap\Dashboard\NameDashboardDoesNotExistException;
use Tuleap\Widget\Event\ConfigureAtXMLImport;
use Tuleap\Widget\WidgetFactory;
use Tuleap\Dashboard\Widget\DashboardWidgetDao;
use Tuleap\XML\MappingsRegistry;

class ProjectDashboardXMLImporter_Base extends \TuleapTestCase
{
    /**
     * @var ProjectDashboardSaver
     */
    protected $project_dashboard_saver;
    /**
     * @var ProjectDashboardDao
     */
    protected $dao;
    /**
     * @var \Logger
     */
    protected $logger;
    /**
     * @var \Project
     */
    protected $project;

    /**
     * @var \PFUser
     */
    protected $user;

    /**
     * @var ProjectDashboardXMLImporter
     */
    protected $project_dashboard_importer;
    /**
     * @var \Tuleap\Dashboard\Widget\WidgetCreator
     */
    protected $widget_creator;
    /**
     * @var WidgetFactory
     */
    protected $widget_factory;
    /**
     * @var DashboardWidgetDao
     */
    protected $widget_dao;
    /**
     * @var MappingsRegistry
     */
    protected $mappings_registry;
    /**
     * @var \EventManager
     */
    protected $event_manager;

    public function setUp()
    {
        parent::setUp();

        $this->dao                     = mock('Tuleap\Dashboard\Project\ProjectDashboardDao');
        $this->project_dashboard_saver = new ProjectDashboardSaver(
            $this->dao
        );
        $this->widget_creator = mock('Tuleap\Dashboard\Widget\WidgetCreator');
        $this->widget_factory = mock('Tuleap\Widget\WidgetFactory');
        $this->widget_dao     = mock('Tuleap\Dashboard\Widget\DashboardWidgetDao');
        $this->event_manager  = mock('EventManager');

        $this->logger                     = mock('Logger');
        $this->project_dashboard_importer = new ProjectDashboardXMLImporter(
            $this->project_dashboard_saver,
            $this->widget_factory,
            $this->widget_dao,
            $this->logger,
            $this->event_manager
        );

        $this->mappings_registry = new MappingsRegistry();

        $this->user    = mock('PFUser');
        $this->project = aMockProject()->withId(101)->build();

    }
}

class ProjectDashboardXMLImporterTest extends ProjectDashboardXMLImporter_Base
{
    public function itLogsAWarningWhenUserDontHavePrivilegeToAddAProjectDashboard()
    {
        stub($this->user)->isAdmin(101)->returns(false);

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="Project dashboard" />
              </dashboards>
              </project>'
        );

        $expected_exception = new UserCanNotUpdateProjectDashboardException();
        stub($this->logger)->warn('[Dashboards] '.$expected_exception->getMessage(), null)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itLogsAWarningWhenDashboardNameIsNull()
    {
        stub($this->user)->isAdmin(101)->returns(true);

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="" />
              </dashboards>
              </project>'
        );

        $expected_exception = new NameDashboardDoesNotExistException();
        stub($this->logger)->warn('[Dashboards] '.$expected_exception->getMessage(), null)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itLogsAWarningWhenDashboardNameAlreadyExistsInTheSameProject()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar(
            array(1, 101, 'test')
        );

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="test" />
              </dashboards>
              </project>'
        );

        $expected_exception = new NameDashboardAlreadyExistsException();
        stub($this->logger)->warn('[Dashboards] '.$expected_exception->getMessage(), null)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsAProjectDashboard()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1" />
                <dashboard name="dashboard 2" />
              </dashboards>
              </project>'
        );

        stub($this->logger)->warn()->never();
        stub($this->project_dashboard_saver)->expectCallCount('save', 3);
        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }
}

class ProjectDashboardXMLImporter_LinesTest extends ProjectDashboardXMLImporter_Base
{
    public function itImportsALine()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->dao)->save()->returns(10001);
        $widget = mock('Widget');
        stub($widget)->getId()->returns('projectmembers');
        stub($widget)->getInstanceId()->returns(null);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns($widget);

        expect($this->widget_dao)->createLine(10001, ProjectDashboardController::DASHBOARD_TYPE, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsAColumn()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->dao)->save()->returns(10001);

        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));

        stub($this->widget_dao)->createLine()->returns(12);
        expect($this->widget_dao)->createColumn(12, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itSetOwnerAndIdExplicitlyToOvercomeWidgetDesignedToGatherThoseDataFromHTTP()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);

        $widget = stub('Widget')->getId()->returns('projectmembers');
        expect($widget)->setOwner(101, ProjectDashboardController::LEGACY_DASHBOARD_TYPE)->once();

        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns($widget);

        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsAWidget()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));

        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itErrorsWhenWidgetNameIsUnknown()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="stuff"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);
        stub($this->widget_factory)->getInstanceByWidgetName()->returns(null);

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->never();

        stub($this->logger)->error()->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itErrorsWhenWidgetContentCannotBeCreated()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="stuff"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        expect($this->widget_dao)->createLine()->never();
        expect($this->widget_dao)->createColumn()->never();
        stub($this->widget_factory)->getInstanceByWidgetName()->returns(stub('Widget')->getInstanceId()->returns(false));

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->never();

        stub($this->logger)->error()->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsTwoWidgetsInSameColumn()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->logger)->error()->never();

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns(stub('Widget')->getId()->returns('projectheartbeat'));

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(2);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->at(0);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 122, 2)->at(1);

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itDoesntImportTwiceUniqueWidgets()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                  <line>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);
        $widget = mock('Widget');
        stub($widget)->getId()->returns('projectheartbeat');
        stub($widget)->isUnique()->returns(true);
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns($widget);

        stub($this->logger)->warn()->once();
        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(1);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 122, 1);

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsUniqueWidgetsWhenThereAreInDifferentDashboards()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
                <dashboard name="dashboard 2">
                  <line>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returnsAt(0, 12);
        stub($this->widget_dao)->createLine()->returnsAt(0, 22);
        stub($this->widget_dao)->createColumn()->returnsAt(0, 122);
        stub($this->widget_dao)->createColumn()->returnsAt(1, 222);
        $widget = mock('Widget');
        stub($widget)->getId()->returns('projectheartbeat');
        stub($widget)->isUnique()->returns(true);
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns($widget);

        stub($this->logger)->warn()->never();
        stub($this->logger)->error()->never();
        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(2);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 122, 1)->at(0);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 222, 1)->at(1);

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itDoesntCreateLineAndColumnWhenWidgetIsNotValid()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="stuff"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        expect($this->widget_dao)->createLine()->never();
        expect($this->widget_dao)->createColumn()->never();
        expect($this->widget_dao)->insertWidgetInColumnWithRank()->never();

        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(null);

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itDoesntImportAPersonalWidget()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="myprojects"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        expect($this->widget_dao)->createLine()->never();
        expect($this->widget_dao)->createColumn()->never();
        stub($this->widget_factory)->getInstanceByWidgetName('myprojects')->returns(stub('Widget')->getId()->returns('myprojects'));

        expect($this->logger)->error()->once();
        expect($this->widget_dao)->insertWidgetInColumnWithRank()->never();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsTwoWidgetsInTwoColumns()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->logger)->error()->never();

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returnsAt(0, 122);
        stub($this->widget_dao)->createColumn()->returnsAt(1, 124);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns(stub('Widget')->getId()->returns('projectheartbeat'));

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(2);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->at(0);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 124, 1)->at(1);

        expect($this->widget_dao)->adjustLayoutAccordinglyToNumberOfWidgets(2, 12)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsTwoWidgetsWithSetLayout()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line layout="two-columns-small-big">
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->dao)->save()->returns(144);

        stub($this->logger)->error()->never();

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returnsAt(0, 122);
        stub($this->widget_dao)->createColumn()->returnsAt(1, 124);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns(stub('Widget')->getId()->returns('projectheartbeat'));

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(2);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->at(0);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 124, 1)->at(1);

        expect($this->widget_dao)->updateLayout(12, 'two-columns-small-big')->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itFallsbackToAutomaticLayoutWhenLayoutIsUnknown()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line layout="foobar">
                    <column>
                      <widget name="projectmembers"></widget>
                    </column>
                    <column>
                      <widget name="projectheartbeat"></widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->logger)->error()->never();
        stub($this->logger)->warn()->once();

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returnsAt(0, 122);
        stub($this->widget_dao)->createColumn()->returnsAt(1, 124);
        stub($this->widget_factory)->getInstanceByWidgetName('projectmembers')->returns(stub('Widget')->getId()->returns('projectmembers'));
        stub($this->widget_factory)->getInstanceByWidgetName('projectheartbeat')->returns(stub('Widget')->getId()->returns('projectheartbeat'));

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->count(2);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectmembers', 0, 122, 1)->at(0);
        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectheartbeat', 0, 124, 1)->at(1);

        expect($this->widget_dao)->updateLayout()->never();
        expect($this->widget_dao)->adjustLayoutAccordinglyToNumberOfWidgets()->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itImportsAWidgetWithPreferences()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectrss">
                        <preference name="rss">
                            <value name="title">Da feed</value>
                            <value name="url">https://stuff</value>
                        </preference>
                      </widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);

        $widget = \Mockery::mock(\Widget::class, [ 'getId' => 'projectrss' ])->shouldIgnoreMissing();
        $widget->shouldReceive('create')->with(\Mockery::on(function (\Codendi_Request $request) {
            if ($request->get('rss') &&
                $request->getInArray('rss', 'title') === 'Da feed' &&
                $request->getInArray('rss', 'url') === 'https://stuff') {
                return true;
            }
            return false;
        }))->once()->andReturns(35);

        stub($this->widget_factory)->getInstanceByWidgetName('projectrss')->returns($widget);

        expect($this->widget_dao)->insertWidgetInColumnWithRank('projectrss', 35, 122, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }

    public function itSkipWidgetCreationWhenCreateRaisesExceptions()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="projectimageviewer">
                        <preference name="image">
                            <value name="title">Da kanban</value>
                            <value name="url">https://stuff</value>
                        </preference>
                      </widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        expect($this->widget_dao)->createLine()->never();
        expect($this->widget_dao)->createColumn()->never();

        $this->mappings_registry->addReference('K123', 78998);

        $widget = mock('Widget');
        stub($widget)->getId()->returns('projectimageviewer');
        stub($widget)->create()->throws(new \Exception("foo"));

        stub($this->widget_factory)->getInstanceByWidgetName('projectimageviewer')->returns($widget);

        expect($this->widget_dao)->insertWidgetInColumnWithRank()->never();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }
}

class ProjectDashboardXMLImporter_PluginTest extends ProjectDashboardXMLImporter_Base {

    public function setUp()
    {
        parent::setUp();
        $this->event_manager = \Mockery::mock(\EventManager::class);

        $this->project_dashboard_importer = new ProjectDashboardXMLImporter(
            $this->project_dashboard_saver,
            $this->widget_factory,
            $this->widget_dao,
            $this->logger,
            $this->event_manager
        );
    }

    function tearDown()
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function itImportsAWidgetDefinedInAPlugin()
    {
        stub($this->user)->isAdmin(101)->returns(true);
        stub($this->dao)->searchByProjectIdAndName()->returnsDar();

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>
            <project>
              <dashboards>
                <dashboard name="dashboard 1">
                  <line>
                    <column>
                      <widget name="plugin_agiledashboard_projects_kanban">
                        <preference name="kanban">
                            <value name="title">Da kanban</value>
                            <reference name="id" REF="K123"/>
                        </preference>
                      </widget>
                    </column>
                  </line>
                </dashboard>
              </dashboards>
              </project>'
        );

        stub($this->widget_dao)->createLine()->returns(12);
        stub($this->widget_dao)->createColumn()->returns(122);

        $this->mappings_registry->addReference('K123', 78998);

        $widget = mock('Widget');
        stub($widget)->getId()->returns('kanban');

        $this->event_manager->shouldReceive('processEvent')->with(\Mockery::on(function (ConfigureAtXMLImport $event) {
            $event->setContentId(35);
            $event->setWidgetIsConfigured();
            return true;
        }))->once();

        stub($this->widget_factory)->getInstanceByWidgetName('plugin_agiledashboard_projects_kanban')->returns($widget);

        expect($this->widget_dao)->insertWidgetInColumnWithRank('kanban', 35, 122, 1)->once();

        $this->project_dashboard_importer->import($xml, $this->user, $this->project, $this->mappings_registry);
    }
}
