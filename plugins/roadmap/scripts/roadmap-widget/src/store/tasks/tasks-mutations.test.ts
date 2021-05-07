/**
 * Copyright (c) Enalean, 2021 - present. All Rights Reserved.
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

import type { TasksState } from "./type";
import * as mutations from "./tasks-mutations";
import type { Row, Task } from "../../type";

describe("tasks-mutations", () => {
    it("setIsLoading set the corresponding boolean", () => {
        const state: TasksState = {
            is_loading: false,
        } as TasksState;

        mutations.setIsLoading(state, true);

        expect(state.is_loading).toBe(true);
    });

    it("setShouldDisplayEmptyState set the corresponding boolean", () => {
        const state: TasksState = {
            should_display_empty_state: false,
        } as TasksState;

        mutations.setShouldDisplayEmptyState(state, true);

        expect(state.should_display_empty_state).toBe(true);
    });

    it("setShouldDisplayErrorState set the corresponding boolean", () => {
        const state: TasksState = {
            should_display_error_state: false,
        } as TasksState;

        mutations.setShouldDisplayErrorState(state, true);

        expect(state.should_display_error_state).toBe(true);
    });

    it("setErrorMessage sets the error message", () => {
        const state: TasksState = {
            error_message: "",
        } as TasksState;

        mutations.setErrorMessage(state, "This is not right!");

        expect(state.error_message).toBe("This is not right!");
    });

    it("setRoms stores the task rows", () => {
        const state: TasksState = {
            rows: [] as Row[],
        } as TasksState;

        const rows: Row[] = [{ task: { id: 123 } as Task }, { task: { id: 124 } as Task }];
        mutations.setRows(state, rows);

        expect(state.rows).toBe(rows);
    });

    it("activateIsLoadingSubtasks should add some skeletons", () => {
        const task = { id: 123, is_loading_subtasks: false } as Task;
        const state: TasksState = {
            rows: [{ task }] as Row[],
        } as TasksState;

        mutations.activateIsLoadingSubtasks(state, task);

        expect(state.rows.length).toBe(3);
        expect("task" in state.rows[0] && state.rows[0].task.is_loading_subtasks).toBe(true);
        expect("is_skeleton" in state.rows[1] && state.rows[1].for_task.id === 123).toBe(true);
        expect("is_skeleton" in state.rows[2] && state.rows[2].for_task.id === 123).toBe(true);
    });

    it("deactivateIsLoadingSubtasks should remove skeletons", () => {
        const task_1 = { id: 123, is_loading_subtasks: false } as Task;
        const task_2 = { id: 124, is_loading_subtasks: false } as Task;
        const state: TasksState = {
            rows: [
                { task: task_1 },
                { for_task: task_1, is_skeleton: true, is_last_one: false },
                { for_task: task_1, is_skeleton: true, is_last_one: true },
                { task: task_2 },
            ] as Row[],
        } as TasksState;

        mutations.deactivateIsLoadingSubtasks(state, task_1);

        expect(state.rows.length).toBe(2);
        expect(
            "task" in state.rows[0] &&
                state.rows[0].task.id === 123 &&
                state.rows[0].task.is_loading_subtasks === false
        ).toBe(true);
        expect(
            "task" in state.rows[1] &&
                state.rows[1].task.id === 124 &&
                state.rows[1].task.is_loading_subtasks === false
        ).toBe(true);
    });
});
