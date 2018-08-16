/*
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

import { mockFetchSuccess } from "tlp-mocks";
import {
    getProjectList,
    getTrackerList,
    moveArtifact,
    moveDryRunArtifact
} from "./rest-querier.js";
import { restore as restoreFetch, rewire$patch, rewire$recursiveGet } from "tlp-fetch";

describe("API querier", () => {
    let recursiveGet, patch;
    beforeEach(() => {
        recursiveGet = jasmine.createSpy("recursiveGet");
        rewire$recursiveGet(recursiveGet);

        patch = jasmine.createSpy("patch");
        rewire$patch(patch);
    });

    afterEach(() => {
        restoreFetch();
    });

    describe("getProjectList", () => {
        it("it will get all project user is tracker admin of", () => {
            const return_json = [
                {
                    id: 102,
                    label: "Project name"
                }
            ];

            mockFetchSuccess(recursiveGet, { return_json });
            getProjectList();

            expect(recursiveGet).toHaveBeenCalledWith(
                "/api/projects",
                jasmine.objectContaining({
                    params: {
                        query: '{"is_tracker_admin":"true"}',
                        limit: 50,
                        offset: 0
                    }
                })
            );
        });
    });

    describe("getTrackerList", () => {
        it("Given a project id, then it will get all trackers user is admin of", () => {
            const return_json = [
                {
                    id: 10,
                    label: "Tracker name"
                }
            ];

            mockFetchSuccess(recursiveGet, { return_json });
            const project_id = 5;

            getTrackerList(project_id);

            expect(recursiveGet).toHaveBeenCalledWith(
                "/api/projects/5/trackers/",
                jasmine.objectContaining({
                    params: {
                        query: '{"is_tracker_admin":"true"}',
                        limit: 50,
                        offset: 0
                    }
                })
            );
        });
    });

    describe("moveArtifact", () => {
        it("Given a tracker id, and a project id then it will process the move", () => {
            const artifact_id = 101;
            const tracker_id = 5;

            moveArtifact(artifact_id, tracker_id);

            expect(patch).toHaveBeenCalledWith("/api/artifacts/" + artifact_id, {
                headers: { "content-type": "application/json" },
                body: `{"move":{"tracker_id":${tracker_id},"dry_run":false,"should_populate_feedback_on_success":true}}`
            });
        });
    });

    describe("moveDryRunArtifact", () => {
        it("Given a tracker id, and a project id then it will process the dry run move", () => {
            const artifact_id = 101;
            const tracker_id = 5;

            moveDryRunArtifact(artifact_id, tracker_id);

            expect(patch).toHaveBeenCalledWith("/api/artifacts/" + artifact_id, {
                headers: { "content-type": "application/json" },
                body: `{"move":{"tracker_id":${tracker_id},"dry_run":true,"should_populate_feedback_on_success":false}}`
            });
        });
    });
});
