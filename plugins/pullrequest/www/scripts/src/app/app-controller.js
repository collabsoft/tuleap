import { fixRightClickBug } from "./fix-ui-bootstrap-dropdown-right-click.js";

export default MainController;

MainController.$inject = [
    '$element',
    'gettextCatalog',
    'amMoment',
    'SharedPropertiesService'
];

function MainController(
    $element,
    gettextCatalog,
    amMoment,
    SharedPropertiesService
) {
    init();

    function init() {
        fixRightClickBug();
        const pullrequest_init_data = $element[0].querySelector('.pullrequest-init-data').dataset;

        const repository_id = pullrequest_init_data.repositoryId;
        SharedPropertiesService.setRepositoryId(repository_id);

        const user_id = pullrequest_init_data.userId;
        SharedPropertiesService.setUserId(user_id);

        const nb_pull_request_badge = pullrequest_init_data.nbPullRequestBadge;
        SharedPropertiesService.setNbPullRequestBadge(nb_pull_request_badge);

        const is_there_at_least_one_pull_request = pullrequest_init_data.isThereAtLeastOnePullRequest;
        SharedPropertiesService.setIsThereAtLeastOnePullRequest(is_there_at_least_one_pull_request);

        const language = pullrequest_init_data.language;
        initLocale(language);

        const is_merge_commit_allowed = pullrequest_init_data.isMergeCommitAllowed;
        SharedPropertiesService.setIsMergeCommitAllowed(is_merge_commit_allowed);
    }

    function initLocale(language) {
        gettextCatalog.setCurrentLanguage(language);
        amMoment.changeLocale(language);
    }
}
