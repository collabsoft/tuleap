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

import type { State } from "../../type";
import type { Module } from "vuex";
import type { ProjectFlag, ProjectPrivacy } from "@tuleap/vue-breadcrumb-privacy";

export interface ConfigurationState {
    readonly public_name: string;
    readonly short_name: string;
    readonly privacy: ProjectPrivacy;
    readonly flags: Array<ProjectFlag>;
    readonly program_id: number;
    readonly accessibility: boolean;
    readonly user_locale: string;
    readonly can_create_program_increment: boolean;
    readonly tracker_program_increment_id: number;
    readonly tracker_program_increment_label: string;
    readonly tracker_program_increment_sub_label: string;
}

export function createConfigurationModule(
    initial_configuration_state: ConfigurationState
): Module<ConfigurationState, State> {
    return {
        namespaced: true,
        state: initial_configuration_state,
    };
}
