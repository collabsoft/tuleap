(function () {
    angular
        .module('execution')
        .controller('ExecutionDetailCtrl', ExecutionDetailCtrl);

    ExecutionDetailCtrl.$inject = [
        '$scope',
        '$state',
        '$sce',
        '$rootScope',
        'ExecutionService',
        'DefinitionService',
        'SharedPropertiesService',
        'SocketService',
        'ArtifactLinksGraphService',
        'ArtifactLinksGraphModalLoading',
        'NewTuleapArtifactModalService',
        'TuleapArtifactModalLoading'
    ];

    function ExecutionDetailCtrl(
        $scope,
        $state,
        $sce,
        $rootScope,
        ExecutionService,
        DefinitionService,
        SharedPropertiesService,
        SocketService,
        ArtifactLinksGraphService,
        ArtifactLinksGraphModalLoading,
        NewTuleapArtifactModalService,
        TuleapArtifactModalLoading
    ) {
        var execution_id = +$state.params.execid,
            campaign_id  = +$state.params.id;

        ExecutionService.loadExecutions(campaign_id);
        if (isCurrentExecutionLoaded()) {
            retrieveCurrentExecution();
        } else {
            waitForExecutionToBeLoaded();
        }

        $scope.pass                               = pass;
        $scope.fail                               = fail;
        $scope.block                              = block;
        $scope.sanitizeHtml                       = sanitizeHtml;
        $scope.getStatusLabel                     = getStatusLabel;
        $scope.showArtifactLinksGraphModal        = showArtifactLinksGraphModal;
        $scope.showEditArtifactModal              = showEditArtifactModal;
        $scope.artifact_links_graph_modal_loading = ArtifactLinksGraphModalLoading.loading;
        $scope.edit_artifact_modal_loading        = TuleapArtifactModalLoading.loading;

        viewTestExecution(execution_id, SharedPropertiesService.getCurrentUser());

        $scope.$on('$destroy', function iVeBeenDismissed() {
            viewTestExecution(execution_id, null);
        });

        function showArtifactLinksGraphModal(execution, definition) {
            ArtifactLinksGraphService.showGraph(execution, definition);
        }

        function showEditArtifactModal(definition) {
            var old_category = $scope.execution.definition.category;

            var callback = function(artifact_id) {
                var executions = ExecutionService.getExecutionsByDefinitionId(artifact_id);

                return DefinitionService.getDefinitionById(artifact_id).then(function(definition) {
                    _(executions).forEach(function(execution) {
                        $scope.execution = ExecutionService.executions[execution.id];

                        $scope.execution.definition.category = definition.category;
                        $scope.execution.definition.description = definition.description;
                        $scope.execution.definition.summary = definition.summary;

                        updateExecution(definition, old_category);
                    });

                    retrieveCurrentExecution();
                });
            };

            DefinitionService.getArtifactById(definition.id).then(function(artifact) {
                NewTuleapArtifactModalService.showEdition(artifact.tracker.id, artifact.id, "inca_silver", undefined, callback);
            });

            TuleapArtifactModalLoading.loading.is_loading = true;
        }

        function viewTestExecution(execution_id, user) {
            SocketService.viewTestExecution({
                id: execution_id,
                user: user
            });
        }

        function waitForExecutionToBeLoaded() {
            var unbind = $rootScope.$on('bunchOfExecutionsLoaded', function () {
                if (isCurrentExecutionLoaded()) {
                    retrieveCurrentExecution();
                }
            });
            $scope.$on('$destroy', unbind);
        }

        function retrieveCurrentExecution() {
            $scope.execution         = ExecutionService.executions[execution_id];
            $scope.execution.results = '';
            $scope.execution.saving  = false;
        }

        function isCurrentExecutionLoaded() {
            return typeof ExecutionService.executions[execution_id] !== 'undefined';
        }

        function sanitizeHtml(html) {
            if (html) {
                return $sce.trustAsHtml(html);
            }

            return null;
        }

        function pass(execution) {
            setNewStatus(execution, "passed");
        }

        function fail(execution) {
            setNewStatus(execution, "failed");
        }

        function block(execution) {
            setNewStatus(execution, "blocked");
        }

        function setNewStatus(execution, new_status) {
            var execution_to_save = angular.copy(execution);

            execution.saving               = true;
            execution.error                = null;
            execution_to_save.error        = null;
            execution_to_save.status       = new_status;
            execution_to_save.submitted_by = SharedPropertiesService.getCurrentUser();

            SocketService.updateTestExecution(execution_to_save);
        }

        function getStatusLabel(status) {
            var labels = {
                passed: 'Passed',
                failed: 'Failed',
                blocked: 'Blocked',
                notrun: 'Not Run'
            };

            return labels[status];
        }

        function updateExecution(definition, old_category) {
            var category_updated = definition.category;

            if (category_updated === null) {
                category_updated = ExecutionService.UNCATEGORIZED;
            }

            if (old_category === null) {
                old_category = ExecutionService.UNCATEGORIZED;
            }

            var category_exist           = categoryExists(ExecutionService.categories, category_updated);
            var execution_already_placed = executionAlreadyPlaced($scope.execution, ExecutionService.categories, category_updated);

            if (! execution_already_placed) {
                removeCategory(ExecutionService.categories[old_category].executions, $scope.execution);
            }

            if (category_exist && ! execution_already_placed) {
                ExecutionService.categories[category_updated].executions.push($scope.execution);
            } else if (! category_exist && ! execution_already_placed) {
                ExecutionService.categories[category_updated] = {
                    label: category_updated,
                    executions: [$scope.execution]
                };
            }
        }

        function categoryExists(categories, category_updated) {
            return _.has(categories, category_updated);
        }

        function executionAlreadyPlaced(scopeExecution, categories, category_updated) {
            return _.has(categories, function(category) {
                return _.has(category.executions, scopeExecution.id, category_updated);
            });
        }

        function removeCategory(executions, scopeExecution) {
            _.remove(executions, function(execution) {
                return execution.id === scopeExecution.id;
            });
        }
    }
})();