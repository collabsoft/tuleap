/**
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

import Vue from 'vue';
import GetTextPlugin from 'vue-gettext';
import french_translations from '../../po/fr.po';
import CallMeBack from './CallMeBack.vue';
import { Settings } from 'luxon';

document.addEventListener('DOMContentLoaded', () => {
    Vue.use(GetTextPlugin, {
        translations: {
            fr: french_translations.messages
        },
        silent: true
    });


    const locale           = document.body.dataset.userLocale;
    const short_locale     = locale.substring(0, 2);
    Vue.config.language    = locale;
    Settings.defaultLocale = short_locale;

    const call_me_back     = document.createElement('div');

    document.body.appendChild(call_me_back);
    const RootComponent = Vue.extend(CallMeBack);

    new RootComponent({
        propsData: {
            locale: short_locale
        }
    }).$mount(call_me_back);
});
