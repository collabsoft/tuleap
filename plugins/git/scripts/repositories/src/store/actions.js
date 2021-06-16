/*
 * Copyright (c) Enalean, 2018 - Present. All Rights Reserved.
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

import {
    getForkedRepositoryList,
    getRepositoryList,
    setRepositoriesSortedByPathUserPreference,
    deleteRepositoriesSortedByPathUserPreference,
} from "../api/rest-querier";
import { getProjectId, getUserId } from "../repository-list-presenter";
import { PROJECT_KEY, REPOSITORIES_SORTED_BY_PATH, ANONYMOUS_USER_ID } from "../constants";
import { getErrorCode } from "../support/error-handler";

export const setDisplayMode = async (context, new_mode) => {
    context.commit("setDisplayMode", new_mode);

    const user_id = getUserId();

    if (!user_id || user_id === ANONYMOUS_USER_ID) {
        return;
    }

    if (new_mode === REPOSITORIES_SORTED_BY_PATH) {
        await setRepositoriesSortedByPathUserPreference(user_id);
    } else {
        await deleteRepositoriesSortedByPathUserPreference(user_id);
    }
};

export const showAddRepositoryModal = ({ state }) => {
    state.add_repository_modal.toggle();
};

export const changeRepositories = async (context, new_owner_id) => {
    context.commit("setSelectedOwnerId", new_owner_id);
    context.commit("setFilter", "");

    if (context.getters.areRepositoriesAlreadyLoadedForCurrentOwner) {
        return;
    }

    const order_by = context.getters.isFolderDisplayMode ? "path" : "push_date";
    if (new_owner_id === PROJECT_KEY) {
        const getProjectRepositories = (callback) =>
            getRepositoryList(getProjectId(), order_by, callback);
        await getAsyncRepositoryList(context.commit, getProjectRepositories);

        if (context.getters.isGitlabUsed) {
            await context.dispatch("gitlab/getGitlabRepositories", order_by, { root: true });
        }
    } else {
        const getForkedRepositories = (callback) =>
            getForkedRepositoryList(
                getProjectId(),
                context.state.selected_owner_id,
                order_by,
                callback
            );
        await getAsyncRepositoryList(context.commit, getForkedRepositories);
    }
};

export async function getAsyncRepositoryList(commit, getRepositories) {
    commit("setIsLoadingInitial", true);
    commit("setIsLoadingNext", true);
    try {
        return await getRepositories((repositories) => {
            commit("pushRepositoriesForCurrentOwner", repositories);
            commit("setIsLoadingInitial", false);
        });
    } catch (e) {
        commit("setErrorMessageType", getErrorCode(e));
        throw e;
    } finally {
        commit("setIsLoadingNext", false);
        commit("setIsFirstLoadDone", true);
    }
}
