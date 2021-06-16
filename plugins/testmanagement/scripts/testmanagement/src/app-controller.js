import { moveBreadCrumbs } from "./move-breadcrumb.js";
import { replaceSkipToMainContentLink } from "./keyboard-navigation/replace-skip-to-main-content-link";

export default TestManagementCtrl;

TestManagementCtrl.$inject = [
    "$element",
    "amMoment",
    "gettextCatalog",
    "SharedPropertiesService",
    "UUIDGeneratorService",
];

function TestManagementCtrl(
    $element,
    amMoment,
    gettextCatalog,
    SharedPropertiesService,
    UUIDGeneratorService
) {
    this.$onInit = function () {
        const testmanagement_init_data = $element[0].querySelector(
            ".testmanagement-init-data"
        ).dataset;

        const uuid = UUIDGeneratorService.generateUUID();
        SharedPropertiesService.setUUID(uuid);
        SharedPropertiesService.setNodeServerVersion("2.0.0");
        const nodejs_server = testmanagement_init_data.nodejsServer;
        SharedPropertiesService.setNodeServerAddress(nodejs_server);
        var current_user = JSON.parse(testmanagement_init_data.currentUser);
        current_user.uuid = uuid;
        SharedPropertiesService.setCurrentUser(current_user);
        const project_id = testmanagement_init_data.projectId;
        SharedPropertiesService.setProjectId(project_id);
        const tracker_ids = JSON.parse(testmanagement_init_data.trackerIds);
        SharedPropertiesService.setCampaignTrackerId(tracker_ids.campaign_tracker_id);
        SharedPropertiesService.setDefinitionTrackerId(tracker_ids.definition_tracker_id);
        SharedPropertiesService.setExecutionTrackerId(tracker_ids.execution_tracker_id);
        SharedPropertiesService.setIssueTrackerId(tracker_ids.issue_tracker_id);
        const issue_tracker_config = JSON.parse(testmanagement_init_data.issueTrackerConfig);
        SharedPropertiesService.setIssueTrackerConfig(issue_tracker_config);
        const current_milestone = JSON.parse(testmanagement_init_data.currentMilestone);
        SharedPropertiesService.setCurrentMilestone(current_milestone);

        const project_public_name = testmanagement_init_data.projectPublicName;
        const project_url = testmanagement_init_data.projectUrl;
        const ttm_admin_url = testmanagement_init_data.ttmAdminUrl;
        const ttm_admin_label = testmanagement_init_data.ttmAdminLabel;

        const trackers_ids_using_list_picker = JSON.parse(
            testmanagement_init_data.trackersUsingListPicker
        );
        SharedPropertiesService.setTrackersUsingListPicker(trackers_ids_using_list_picker);

        const csrf_token = testmanagement_init_data.csrfTokenCampaignStatus;
        SharedPropertiesService.setCSRFTokenCampaignStatus(csrf_token);

        const language = testmanagement_init_data.language;
        amMoment.changeLocale(language);
        gettextCatalog.setCurrentLanguage(language);

        moveBreadCrumbs(project_public_name, project_url, ttm_admin_url, ttm_admin_label);
        replaceSkipToMainContentLink();
    };
}
