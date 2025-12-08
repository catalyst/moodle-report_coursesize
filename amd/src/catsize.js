// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript to handle report_coursesize actions.
 *
 * @copyright  Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {get_string} from 'core/str';
import {renderPix} from 'core/templates';
import {relativeUrl} from 'core/url';

const Selectors = {
    catToggle: ".cattoggle",
    courseToggle: ".coursetoggle",
};

export const init = (sortorder, sortdir, displaysize, excludebackups) => {

    const expander = (type, id) => {
        const selector = type == 'cat' ? Selectors.catToggle : Selectors.courseToggle;

        if (document.getElementById(type + id).style.display == 'block') {
            document.getElementById(type + id).style.display = 'none';
            get_string('tdtoggle', 'report_coursesize').then(function (string) {
                renderPix('t/switch_plus', 'core', string).then(function (pix) {
                    $(selector + '[data-id=' + id + ']').html(pix);
                });
            });
        } else {
            if (document.getElementById(type + id).innerHTML == '') {
                if (id != 0) {
                    get_string('loading', 'core').then(function (string) {
                        renderPix('i/loading', 'core', string).then(function (pix) {
                            $(selector + '[data-id=' + id + ']').html(pix);
                        });
                    });
                }
                $.ajax({
                    type: 'GET',
                    url: relativeUrl('/report/coursesize/callback.php'),
                    data: {
                        id: id,
                        course: type == 'cat' ? 0 : 1,
                        sorder: sortorder,
                        sdir: sortdir,
                        display: displaysize,
                        excludebackups: excludebackups,
                    },
                }).done(function (data) {
                    document.getElementById(type + id).innerHTML = data;
                    document.getElementById(type + id).style.display = 'block';
                    if (id != 0) {
                        get_string('tdtoggle', 'report_coursesize').then(function (string) {
                            renderPix('t/switch_minus', 'core', string).then(function (pix) {
                                $(selector + '[data-id=' + id + ']').html(pix);
                            });
                        });
                    }
                });
            } else {
                document.getElementById(type + id).style.display = 'block';
                if (id != 0) {
                    get_string('tdtoggle', 'report_coursesize').then(function (string) {
                        renderPix('t/switch_minus', 'core', string).then(function (pix) {
                            $(selector + '[data-id=' + id + ']').html(pix);
                        });
                    });
                }
            }
        }
    };

    const registerEventListeners = () => {
        document.addEventListener('click', e => {
            var target = e.target;
            if ($(target).attr('data-id') === undefined) {
                target = e.target.parentNode;
            }
            if (e.target.closest(Selectors.catToggle)) {
                e.preventDefault();
                expander('cat', target.getAttribute('data-id'));
                return;
            }
            if (e.target.closest(Selectors.courseToggle)) {
                e.preventDefault();
                expander('course', target.getAttribute('data-id'));
                return;
            }
        });
    };

    expander('cat', 0);
    registerEventListeners();
};
