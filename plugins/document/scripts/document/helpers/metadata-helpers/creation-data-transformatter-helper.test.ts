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

import {
    transformCustomMetadataForItemCreation,
    transformItemMetadataForCreation,
} from "./creation-data-transformatter-helper";
import type { Folder, Item, Metadata, ListValue } from "../../type";

describe("creation metadata transformer", () => {
    it("Given an existing document, then the default status metadata is the parent one", () => {
        const item = {
            id: 7,
            type: "file",
        } as Item;

        const list_value: Array<ListValue> = [
            {
                id: 103,
            } as ListValue,
        ];

        const parent = {
            id: 7,
            type: "folder",
            metadata: [
                {
                    short_name: "status",
                    list_value: list_value,
                } as Metadata,
            ],
        } as Folder;

        transformItemMetadataForCreation(item, parent, true);

        expect(item.status).toEqual("rejected");
    });

    it("Given an existing document, when status is not used, default status is not set regardless of parent configuration", () => {
        const item = {
            id: 7,
            type: "file",
        } as Item;

        const list_value: Array<ListValue> = [
            {
                id: 103,
            } as ListValue,
        ];

        const parent = {
            id: 7,
            type: "folder",
            metadata: [
                {
                    short_name: "status",
                    list_value: list_value,
                } as Metadata,
            ],
        } as Folder;

        transformItemMetadataForCreation(item, parent, false);

        expect(item.status).toEqual(undefined);
    });

    it("Given parent has no metadata then it returns an empty array", () => {
        const parent_metadata: Array<Metadata> = [];

        const formatted_result = transformCustomMetadataForItemCreation(parent_metadata);

        expect(formatted_result).toEqual([]);
    });

    it(`Given parent has a text value,
        then the formatted metadata is bound to value`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom metadata",
                name: "field_1",
                value: "value",
                type: "text",
                is_multiple_value_allowed: false,
                is_required: false,
                list_value: null,
                is_used: true,
                description: "",
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom metadata",
                type: "text",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: "value",
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });

    it(`Given parent has a string value,
        then the formatted metadata is bound to value`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom metadata",
                name: "field_1",
                value: "value",
                type: "string",
                is_multiple_value_allowed: false,
                is_required: false,
                list_value: null,
                is_used: true,
                description: "",
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom metadata",
                type: "string",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: "value",
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });

    it(`Given parent has a single list value,
        then the formatted metadata is bound to value`, () => {
        const list_value: Array<ListValue> = [
            {
                id: 110,
                value: "My value to display",
            } as ListValue,
        ];

        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom metadata",
                name: "field_1",
                list_value: list_value,
                is_multiple_value_allowed: false,
                type: "list",
                is_required: false,
                is_used: true,
                description: "",
                value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom metadata",
                type: "list",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: 110,
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });

    it(`Given parent has a list with single value, and given list value is null,
        then the formatted metadata is bound to none`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                name: "field_1",
                list_value: [],
                is_multiple_value_allowed: false,
                type: "list",
                is_required: false,
                is_used: true,
                description: "",
                value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                type: "list",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: 100,
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });

    it(`Given parent has a multiple list
        then the formatted metadata only keeps list ids`, () => {
        const list_value: Array<ListValue> = [
            {
                id: 110,
                value: "My value to display",
            } as ListValue,
            {
                id: 120,
                value: "My other value to display",
            } as ListValue,
        ];

        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                name: "field_1",
                list_value: list_value,
                is_multiple_value_allowed: true,
                type: "list",
                is_required: false,
                is_used: true,
                description: "",
                value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                type: "list",
                name: "field_1",
                is_multiple_value_allowed: true,
                list_value: [110, 120],
                is_required: false,
                value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });

    it(`Given parent has a multiple list without any value
        then the formatted metadata should have the 100 id`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                name: "field_1",
                list_value: [],
                is_multiple_value_allowed: true,
                type: "list",
                is_required: false,
                is_used: true,
                description: "",
                value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom list metadata",
                type: "list",
                name: "field_1",
                is_multiple_value_allowed: true,
                list_value: [100],
                is_required: false,
                value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });
    it(`Given parent has a date value,
        then the formatted date metadata is bound to value with the formatted date`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom metadata",
                name: "field_1",
                value: "2019-08-30T00:00:00+02:00",
                type: "date",
                is_multiple_value_allowed: false,
                is_required: false,
                is_used: true,
                description: "",
                list_value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom metadata",
                type: "date",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: "2019-08-30",
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });
    it(`Given parent does not have a date value,
        then the formatted date metadata is bound to value with empty string`, () => {
        const parent_metadata: Array<Metadata> = [
            {
                short_name: "custom metadata",
                name: "field_1",
                value: null,
                type: "date",
                is_multiple_value_allowed: false,
                is_required: false,
                is_used: true,
                description: "",
                list_value: null,
            },
        ];

        const expected_list: Array<Metadata> = [
            {
                short_name: "custom metadata",
                type: "date",
                name: "field_1",
                is_multiple_value_allowed: false,
                value: "",
                is_required: false,
                list_value: null,
                description: "",
                is_used: true,
            },
        ];

        const formatted_list = transformCustomMetadataForItemCreation(parent_metadata);

        expect(expected_list).toEqual(formatted_list);
    });
});
