/**
 * Copyright (c) Enalean, 2021 - Present. All Rights Reserved.
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
 * along with Tuleap. If not, see http://www.gnu.org/licenses/.
 */

import * as actions from "./actions";
import type { Feature, State } from "../type";
import type { ActionContext } from "vuex";
import type {
    FeatureIdWithProgramIncrement,
    HandleDropContextWithProgramId,
} from "../helpers/drag-drop";
import type { ProgramIncrement } from "../helpers/ProgramIncrement/program-increment-retriever";
import type { FeatureIdToMoveFromProgramIncrementToAnother } from "../helpers/drag-drop";
import { createElement } from "../helpers/jest/create-dom-element";
import * as dragDrop from "../helpers/drag-drop";
import * as tlp from "tlp";
import * as tlpFetch from "@tuleap/tlp-fetch";
import * as backlogAdder from "../helpers/ProgramIncrement/add-to-top-backlog";
import type { FetchWrapperError } from "tlp";
import { mockFetchError, mockFetchSuccess } from "@tuleap/tlp-fetch/mocks/tlp-fetch-mock-helper";
import type { UserStory } from "../helpers/UserStories/user-stories-retriever";

jest.mock("tlp");
jest.mock("@tuleap/tlp-fetch");

describe("Actions", () => {
    let context: ActionContext<State, State>;
    beforeEach(() => {
        context = ({
            commit: jest.fn(),
            state: {} as State,
            getters: {},
        } as unknown) as ActionContext<State, State>;
    });

    describe("planFeatureInProgramIncrement", () => {
        it("When a feature is planned in a program increment, Then it is deleted from to be planned elements and add to increment", () => {
            const feature: Feature = { id: 125 } as Feature;
            const feature_id_with_increment: FeatureIdWithProgramIncrement = {
                feature_id: 125,
                program_increment: {
                    id: 4,
                    features: [{ id: 14 } as Feature],
                } as ProgramIncrement,
            };

            context.getters = { getToBePlannedElementFromId: (): Feature => feature };

            actions.planFeatureInProgramIncrement(context, feature_id_with_increment);

            expect(context.commit).toHaveBeenCalledWith("removeToBePlannedElement", feature);
            expect(feature_id_with_increment.program_increment.features.length).toEqual(2);
            expect(feature_id_with_increment.program_increment.features[0]).toEqual({
                id: 14,
            });
            expect(feature_id_with_increment.program_increment.features[1]).toEqual({
                id: 125,
            });
        });
    });

    describe("unplanFeatureFromProgramIncrement", () => {
        it("When feature does not exist, Then error is thrown", () => {
            const feature_id_with_increment: FeatureIdWithProgramIncrement = {
                feature_id: 125,
                program_increment: {
                    id: 4,
                    features: [{ id: 14 } as Feature],
                } as ProgramIncrement,
            };

            expect(() =>
                actions.unplanFeatureFromProgramIncrement(context, feature_id_with_increment)
            ).toThrowError("No feature with id #125 in program increment #4");
        });

        it("When feature exist, Then it is removed from increment and add in to be planned elements", () => {
            const feature_id_with_increment: FeatureIdWithProgramIncrement = {
                feature_id: 125,
                program_increment: {
                    id: 4,
                    features: [{ id: 125 } as Feature],
                } as ProgramIncrement,
            };

            actions.unplanFeatureFromProgramIncrement(context, feature_id_with_increment);
            expect(context.commit).toHaveBeenCalledWith("addToBePlannedElement", {
                id: 125,
            });
            expect(feature_id_with_increment.program_increment.features.length).toEqual(0);
        });
    });

    describe("moveFeatureFromProgramIncrementToAnother", () => {
        it("When feature does not exist, Then error is thrown", () => {
            const feature_id_with_increment: FeatureIdToMoveFromProgramIncrementToAnother = {
                feature_id: 125,
                from_program_increment: {
                    id: 4,
                    features: [{ id: 14 } as Feature],
                } as ProgramIncrement,
                to_program_increment: {
                    id: 5,
                    features: [] as Feature[],
                } as ProgramIncrement,
            };

            expect(() =>
                actions.moveFeatureFromProgramIncrementToAnother(context, feature_id_with_increment)
            ).toThrowError("No feature with id #125 in program increment #4");
        });

        it("When feature exist, Then it is removed from increment and add in to be planned elements", () => {
            const feature_id_with_increment: FeatureIdToMoveFromProgramIncrementToAnother = {
                feature_id: 125,
                from_program_increment: {
                    id: 4,
                    features: [{ id: 14 } as Feature, { id: 125 } as Feature],
                } as ProgramIncrement,
                to_program_increment: {
                    id: 5,
                    features: [] as Feature[],
                } as ProgramIncrement,
            };

            actions.moveFeatureFromProgramIncrementToAnother(context, feature_id_with_increment);
            expect(feature_id_with_increment.from_program_increment.features.length).toEqual(1);
            expect(feature_id_with_increment.from_program_increment.features[0]).toEqual({
                id: 14,
            });
            expect(feature_id_with_increment.to_program_increment.features.length).toEqual(1);
            expect(feature_id_with_increment.to_program_increment.features[0]).toEqual({
                id: 125,
            });
        });
    });

    describe(`handleDrop()`, () => {
        it(`Plan elements`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "14");
            const source_dropzone = createElement();
            const target_dropzone = createElement();
            target_dropzone.setAttribute("data-program-increment-id", "1");
            target_dropzone.setAttribute("data-artifact-link-field-id", "1234");
            target_dropzone.setAttribute("data-planned-feature-ids", "12,13");

            const put = jest.spyOn(tlp, "put");
            mockFetchSuccess(put);
            const plan_feature = jest.spyOn(dragDrop, "planFeatureInProgramIncrement");

            const getProgramIncrementFromId = jest.fn().mockReturnValue({ id: 56, features: [] });
            const getToBePlannedElementFromId = jest.fn().mockReturnValue({ id: 125 });

            context.getters = { getProgramIncrementFromId, getToBePlannedElementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).toHaveBeenCalledWith(1);
            expect(getToBePlannedElementFromId).toHaveBeenCalledWith(14);

            expect(context.commit).toHaveBeenCalledWith("removeToBePlannedElement", {
                id: 125,
            });

            expect(plan_feature).toHaveBeenCalledWith(
                {
                    dropped_element,
                    program_id: 101,
                    source_dropzone,
                    target_dropzone,
                },
                1,
                14
            );
        });

        it(`When a error is thrown during plan elements, Then error is stored`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "14");
            const source_dropzone = createElement();
            const target_dropzone = createElement();
            target_dropzone.setAttribute("data-program-increment-id", "1");
            target_dropzone.setAttribute("data-artifact-link-field-id", "1234");
            target_dropzone.setAttribute("data-planned-feature-ids", "12,13");

            const put = jest.spyOn(tlp, "put");
            mockFetchError(put, {
                status: 404,
                error_json: { error: { code: 404, message: "Error" } },
            });

            const plan_feature = jest.spyOn(dragDrop, "planFeatureInProgramIncrement");

            const getProgramIncrementFromId = jest.fn().mockReturnValue({ id: 56, features: [] });
            const getToBePlannedElementFromId = jest.fn().mockReturnValue({ id: 125 });

            context.getters = { getProgramIncrementFromId, getToBePlannedElementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).toHaveBeenCalledWith(1);
            expect(getToBePlannedElementFromId).toHaveBeenCalledWith(14);

            expect(context.commit).toHaveBeenCalledWith("removeToBePlannedElement", {
                id: 125,
            });

            expect(plan_feature).toHaveBeenCalledWith(
                {
                    dropped_element,
                    program_id: 101,
                    source_dropzone,
                    target_dropzone,
                },
                1,
                14
            );

            expect(context.commit).toHaveBeenCalledWith("setModalErrorMessage", "404 Error");
        });

        it(`Removes elements from program increment`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "12");
            dropped_element.setAttribute("data-program-increment-id", "1");
            dropped_element.setAttribute("data-artifact-link-field-id", "1234");
            dropped_element.setAttribute("data-planned-feature-ids", "12,13");
            const source_dropzone = createElement();
            const target_dropzone = createElement();

            const move_element_from_program_increment_to_top_backlog = jest.spyOn(
                backlogAdder,
                "moveElementFromProgramIncrementToTopBackLog"
            );
            const patch = jest.spyOn(tlp, "patch");
            mockFetchSuccess(patch);

            const getProgramIncrementFromId = jest
                .fn()
                .mockReturnValue({ id: 56, features: [{ id: 12 }] });

            context.getters = { getProgramIncrementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).toHaveBeenCalledWith(1);

            expect(context.commit).toHaveBeenCalledWith("addToBePlannedElement", {
                id: 12,
            });

            expect(move_element_from_program_increment_to_top_backlog).toHaveBeenCalledWith(
                101,
                12
            );
        });

        it(`When an error is thrown during remove elements from program increment, Then error is stored`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "12");
            dropped_element.setAttribute("data-program-increment-id", "1");
            dropped_element.setAttribute("data-artifact-link-field-id", "1234");
            dropped_element.setAttribute("data-planned-feature-ids", "12,13");
            const source_dropzone = createElement();
            const target_dropzone = createElement();

            const move_element_from_program_increment_to_top_backlog = jest.spyOn(
                backlogAdder,
                "moveElementFromProgramIncrementToTopBackLog"
            );
            const patch = jest.spyOn(tlp, "patch");
            mockFetchError(patch, {
                status: 404,
                error_json: { error: { code: 404, message: "Error" } },
            });

            const getProgramIncrementFromId = jest
                .fn()
                .mockReturnValue({ id: 56, features: [{ id: 12 }] });

            context.getters = { getProgramIncrementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).toHaveBeenCalledWith(1);

            expect(context.commit).toHaveBeenCalledWith("addToBePlannedElement", {
                id: 12,
            });

            expect(move_element_from_program_increment_to_top_backlog).toHaveBeenCalledWith(
                101,
                12
            );

            expect(context.commit).toHaveBeenCalledWith("setModalErrorMessage", "404 Error");
        });

        it(`Moves elements from program increment to another`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "12");
            dropped_element.setAttribute("data-program-increment-id", "1");
            dropped_element.setAttribute("data-artifact-link-field-id", "1234");
            dropped_element.setAttribute("data-planned-feature-ids", "12,13");
            const source_dropzone = createElement();
            const target_dropzone = createElement();
            target_dropzone.setAttribute("data-program-increment-id", "2");
            target_dropzone.setAttribute("data-artifact-link-field-id", "3691");
            target_dropzone.setAttribute("data-planned-feature-ids", "125,126");

            const plan_feature = jest.spyOn(dragDrop, "planFeatureInProgramIncrement");
            const unplan_feature = jest.spyOn(dragDrop, "unplanFeature");
            const put = jest.spyOn(tlp, "put");
            mockFetchSuccess(put);

            const getProgramIncrementFromId = jest
                .fn()
                .mockReturnValueOnce({ id: 1, features: [{ id: 12 }] } as ProgramIncrement)
                .mockReturnValueOnce({ id: 2, features: [] as Feature[] } as ProgramIncrement);

            context.getters = { getProgramIncrementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).toHaveBeenNthCalledWith(1, 1);
            expect(getProgramIncrementFromId).toHaveBeenNthCalledWith(2, 2);

            expect(unplan_feature).toHaveBeenCalledWith(
                {
                    dropped_element,
                    program_id: 101,
                    source_dropzone,
                    target_dropzone,
                },
                1,
                12
            );
            expect(plan_feature).toHaveBeenCalledWith(
                {
                    dropped_element,
                    program_id: 101,
                    source_dropzone,
                    target_dropzone,
                },
                2,
                12
            );
        });

        it(`When feature are moving in the same program increment, Then nothing happen`, async () => {
            const dropped_element = createElement();
            dropped_element.setAttribute("data-element-id", "12");
            dropped_element.setAttribute("data-program-increment-id", "1");
            dropped_element.setAttribute("data-artifact-link-field-id", "1234");
            dropped_element.setAttribute("data-planned-feature-ids", "12,13");
            const source_dropzone = createElement();
            const target_dropzone = createElement();
            target_dropzone.setAttribute("data-program-increment-id", "1");
            target_dropzone.setAttribute("data-artifact-link-field-id", "1234");
            target_dropzone.setAttribute("data-planned-feature-ids", "12,13");

            const plan_feature = jest.spyOn(dragDrop, "planFeatureInProgramIncrement");
            const unplan_feature = jest.spyOn(dragDrop, "unplanFeature");

            const getProgramIncrementFromId = jest.fn();

            context.getters = { getProgramIncrementFromId };

            await actions.handleDrop(context, {
                dropped_element,
                source_dropzone,
                target_dropzone,
                program_id: 101,
            } as HandleDropContextWithProgramId);

            expect(getProgramIncrementFromId).not.toHaveBeenCalled();
            expect(getProgramIncrementFromId).not.toHaveBeenCalled();

            expect(unplan_feature).not.toHaveBeenCalled();
            expect(plan_feature).not.toHaveBeenCalled();
        });
    });

    describe(`handleModalError`, () => {
        it(`When a message can be extracted from the FetchWrapperError,
            it will set an error message that will show up in a modal window`, async () => {
            const error = {
                response: {
                    json: () =>
                        Promise.resolve({
                            error: { code: 500, message: "Internal Server Error" },
                        }),
                } as Response,
            } as FetchWrapperError;

            await actions.handleModalError(context, error);

            expect(context.commit).toHaveBeenCalledWith(
                "setModalErrorMessage",
                "500 Internal Server Error"
            );
        });

        it(`When a message can not be extracted from the FetchWrapperError,
            it will leave the modal error message empty`, async () => {
            const error = {
                response: {
                    json: () => Promise.reject(),
                } as Response,
            } as FetchWrapperError;

            await actions.handleModalError(context, error);

            expect(context.commit).toHaveBeenCalledWith("setModalErrorMessage", "");
        });
    });

    describe("linkUserStoriesToBePlannedElements", () => {
        it("When user stories are retrieved, Then they are linked to planned element and returned", async () => {
            const expected_stories = [{ id: 104 }] as UserStory[];

            const recursiveGet = jest.spyOn(tlpFetch, "recursiveGet");
            recursiveGet.mockResolvedValue(expected_stories);

            const stories = await actions.linkUserStoriesToBePlannedElements(context, 14);
            expect(context.commit).toHaveBeenCalledWith("linkUserStoriesToBePlannedElement", {
                user_stories: expected_stories,
                element_id: 14,
            });
            expect(stories).toEqual(expected_stories);
        });
    });

    describe("linkUserStoriesToFeature", () => {
        it("When user stories are retrieved, Then they are linked to planned element and returned", async () => {
            const expected_stories = [{ id: 104 }] as UserStory[];
            const program_increment: ProgramIncrement = { id: 45 } as ProgramIncrement;

            const recursiveGet = jest.spyOn(tlpFetch, "recursiveGet");
            recursiveGet.mockResolvedValue(expected_stories);

            const stories = await actions.linkUserStoriesToFeature(context, {
                artifact_id: 14,
                program_increment,
            });
            expect(context.commit).toHaveBeenCalledWith("linkUserStoriesToFeature", {
                user_stories: expected_stories,
                element_id: 14,
                program_increment,
            });
            expect(stories).toEqual(expected_stories);
        });
    });

    describe("retrieveToBePlannedElement", () => {
        it("retrieve to be planned element and store it", async () => {
            const expected_features = [{ id: 104 }] as Feature[];

            const recursiveGet = jest.spyOn(tlpFetch, "recursiveGet");
            recursiveGet.mockResolvedValue(expected_features);

            await actions.retrieveToBePlannedElement(context, 201);

            expect(context.commit).toHaveBeenCalledWith(
                "setToBePlannedElements",
                expected_features
            );
        });
    });

    describe("getFeatureAndStoreInProgramIncrement", () => {
        it("retrieve features, store in increment and return them", async () => {
            const expected_features = [{ id: 104 }] as Feature[];

            const recursiveGet = jest.spyOn(tlpFetch, "recursiveGet");
            recursiveGet.mockResolvedValue(expected_features);

            const features = await actions.getFeatureAndStoreInProgramIncrement(context, {
                id: 101,
            } as ProgramIncrement);
            expect(context.commit).toHaveBeenCalledWith("addProgramIncrement", {
                id: 101,
                features,
            });
            expect(features).toEqual(expected_features);
        });
    });
});
