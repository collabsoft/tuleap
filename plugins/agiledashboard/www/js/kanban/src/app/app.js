import angular from 'angular';
import ngSanitize from 'angular-sanitize';
import dragular from 'dragular';
import angular_artifact_modal from 'angular-artifact-modal';
import angular_tlp from 'angular-tlp';

import 'angular-locker';
import 'angular-gettext';
import 'ng-scrollbar';
import 'restangular';
import '../../po/fr.po';

import jwt                                 from './jwt/jwt.js';
import kanban_item                         from './kanban-item/kanban-item.js';
import shared_properties                   from './shared-properties/shared-properties.js';
import uuid_generator                      from './uuid-generator/uuid-generator.js';
import socket                              from './socket/socket.js';
import user_preferences                    from './user-preferences/user-preferences.js';
import error_modal                         from './error-modal/error-modal.js';
import ErrorCtrl                           from './error-modal/error-controller.js';
import KanbanConfig                        from './app-config.js';
import MainCtrl                            from './app-main-controller.js';
import KanbanService                       from './kanban-service.js';
import ColumnCollectionService             from './column-collection-service.js';
import DroppedService                      from './dropped-service.js';
import KanbanFilterValue                   from './filter-value.js';
import AddInPlaceDirective                 from './add-in-place/add-in-place-directive.js';
import ResizeDirective                     from './resize-directive.js';
import AddToDashboardDirective             from './add-to-dashboard/add-to-dashboard-directive.js';
import FilterTrackerReportDirective        from './filter-tracker-report/filter-tracker-report-directive.js';
import AutoFocusInputDirective             from './edit-kanban/edit-kanban-autofocus-directive.js';
import GoToKanbanDirective                 from './go-to-kanban/go-to-kanban-directive.js';
import EscKeyDirective                     from './esc-key/esc-key-directive.js';
import KanbanFilteredUpdatedAlertDirective from './kanban-filtered-updated-alert/kanban-filtered-updated-alert-directive.js';
import InPropertiesFilter                  from './in-properties-filter/in-properties-filter.js';
import KanbanColumnDirective               from './kanban-column/kanban-column-directive.js';
import KanbanColumnService                 from './kanban-column/kanban-column-service.js';
import KanbanItemRestService               from './kanban-item/kanban-item-rest-service.js';
import KanbanFilteredUpdatedAlertService   from './kanban-filtered-updated-alert/kanban-filtered-updated-alert-service.js';
import GraphDirective                      from './reports-modal/diagram-directive.js';
import DiagramRestService                  from './reports-modal/diagram-rest-service.js';
import ReportsModalController              from './reports-modal/reports-modal-controller.js';
import FilterTrackerReportController       from './filter-tracker-report/filter-tracker-report-controller.js';
import FilterTrackerReportService          from './filter-tracker-report/filter-tracker-report-service.js';
import WipPopoverDirective                 from './wip-popover/wip-popover-directive.js';
import KanbanColumnController              from './kanban-column/kanban-column-controller.js';
import KanbanDirective                     from './kanban-directive.js';
import ColumnWipHeaderDirective            from './kanban-column/column-wip-header/column-wip-header-directive.js';
import FeedbackComponent                   from './feedback-component.js';

export default angular.module('kanban', [
    'angular-locker',
    'gettext',
    'ngScrollbar',
    'restangular',
    angular_artifact_modal,
    angular_tlp,
    dragular,
    error_modal,
    jwt,
    kanban_item,
    ngSanitize,
    shared_properties,
    socket,
    user_preferences,
    uuid_generator
])
.config(KanbanConfig)
.controller('MainCtrl', MainCtrl)
.controller('ReportsModalController', ReportsModalController)
.controller('FilterTrackerReportController', FilterTrackerReportController)
.controller('KanbanColumnController', KanbanColumnController)
.controller('ErrorCtrl', ErrorCtrl)
.service('KanbanService', KanbanService)
.service('ColumnCollectionService', ColumnCollectionService)
.service('DroppedService', DroppedService)
.service('KanbanColumnService', KanbanColumnService)
.service('KanbanItemRestService', KanbanItemRestService)
.service('DiagramRestService', DiagramRestService)
.service('FilterTrackerReportService', FilterTrackerReportService)
.service('KanbanFilteredUpdatedAlertService', KanbanFilteredUpdatedAlertService)
.directive('kanban', KanbanDirective)
.directive('addInPlace', AddInPlaceDirective)
.directive('resize', ResizeDirective)
.directive('addToDashboard', AddToDashboardDirective)
.directive('filterTrackerReport', FilterTrackerReportDirective)
.directive('autoFocusInput', AutoFocusInputDirective)
.directive('escKey', EscKeyDirective)
.directive('kanbanColumn', KanbanColumnDirective)
.directive('graph', GraphDirective)
.directive('wipPopover', WipPopoverDirective)
.directive('goToKanban', GoToKanbanDirective)
.directive('kanbanFilteredUpdatedAlert', KanbanFilteredUpdatedAlertDirective)
.directive('columnWipHeader', ColumnWipHeaderDirective)
.value('KanbanFilterValue', KanbanFilterValue)
.filter('InPropertiesFilter', InPropertiesFilter)
.component('feedbackMessage', FeedbackComponent)
.name;

var kanban_elements = document.getElementsByClassName('widget-kanban');
[].forEach.call(kanban_elements, function (kanban_element) {
    angular.bootstrap(kanban_element, ['kanban']);
});
