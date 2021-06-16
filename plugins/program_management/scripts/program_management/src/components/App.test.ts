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
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

import type { Wrapper } from "@vue/test-utils";
import { shallowMount } from "@vue/test-utils";
import App from "./App.vue";
import { createProgramManagementLocalVue } from "../helpers/local-vue-for-test";
import * as drekkenov from "@tuleap/drag-and-drop";
import { createStoreMock } from "@tuleap/core/scripts/vue-components/store-wrapper-jest";

describe("App", () => {
    async function createWrapper(): Promise<Wrapper<App>> {
        return shallowMount(App, {
            mocks: {
                $store: createStoreMock({
                    state: {
                        has_modal_error: false,
                        configuration: {
                            can_create_program_increment: true,
                        },
                    },
                }),
            },
            localVue: await createProgramManagementLocalVue(),
        });
    }

    it("Displays the backlog section", async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find("[data-test=backlog-section]").exists()).toBe(true);
    });

    describe(`mounted()`, () => {
        it(`will create a "drek"`, async () => {
            const init = jest.spyOn(drekkenov, "init");
            await createWrapper();

            expect(init).toHaveBeenCalled();
        });
    });

    describe(`destroy()`, () => {
        it(`will destroy the "drek"`, async () => {
            const mock_drek = {
                destroy: jest.fn(),
            };
            jest.spyOn(drekkenov, "init").mockImplementation(() => mock_drek);
            const wrapper = await createWrapper();
            wrapper.destroy();

            expect(mock_drek.destroy).toHaveBeenCalled();
        });
    });
});
