/**
 * Copyright (c) Enalean, 2020 - present. All Rights Reserved.
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

import { FieldFocusManager } from "./FieldFocusManager";

describe("FieldFocusManager", () => {
    let selection_element: HTMLElement,
        source_select_box: HTMLSelectElement,
        search_field_element: HTMLInputElement;

    function getDocumentWithActiveElement(active_element: HTMLElement): HTMLDocument {
        return {
            activeElement: active_element,
        } as unknown as HTMLDocument;
    }

    beforeEach(() => {
        selection_element = document.createElement("span");
        source_select_box = document.createElement("select");
        search_field_element = document.createElement("input");

        jest.spyOn(selection_element, "focus");
        jest.spyOn(search_field_element, "focus");
    });

    describe("doesFieldHaveTheFocus", () => {
        it("should return false when the selection element does not have the focus", () => {
            const focus_manager = new FieldFocusManager(
                getDocumentWithActiveElement(document.createElement("body")),
                source_select_box,
                selection_element,
                search_field_element
            );

            expect(focus_manager.doesSelectionElementHaveTheFocus()).toBe(false);
        });

        it("should return true when the selection element has the focus", () => {
            const focus_manager = new FieldFocusManager(
                getDocumentWithActiveElement(selection_element),
                source_select_box,
                selection_element,
                search_field_element
            );

            expect(focus_manager.doesSelectionElementHaveTheFocus()).toBe(true);
        });
    });

    describe("applyFocusOnSelectionElement", () => {
        let focus_manager: FieldFocusManager;

        beforeEach(() => {
            focus_manager = new FieldFocusManager(
                getDocumentWithActiveElement(selection_element),
                source_select_box,
                selection_element,
                search_field_element
            );
        });

        it("When the source <select> is multiple, Then it should NOT apply the focus on the selection element", () => {
            source_select_box.setAttribute("multiple", "multiple");

            focus_manager.applyFocusOnSelectionElement();
            expect(selection_element.focus).not.toHaveBeenCalled();
        });

        it("When the source <select> is NOT multiple, Then it should apply the focus on the selection element", () => {
            focus_manager.applyFocusOnSelectionElement();
            expect(selection_element.focus).toHaveBeenCalled();
        });
    });

    describe("applyFocusOnSearchField", () => {
        it("should apply the focus on the search field element", () => {
            const focus_manager = new FieldFocusManager(
                getDocumentWithActiveElement(selection_element),
                source_select_box,
                selection_element,
                search_field_element
            );

            focus_manager.applyFocusOnSearchField();
            expect(search_field_element.focus).toHaveBeenCalled();
        });
    });
});
