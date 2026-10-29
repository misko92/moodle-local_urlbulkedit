<?php
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
 * Library callbacks.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the "Bulk edit links" link to the course navigation (More menu).
 *
 * Shown when the course has links, or when the user could add some.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_urlbulkedit_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }
    $haslinks = !empty(get_fast_modinfo($course)->get_instances_of('url'));
    if (!$haslinks && !\local_urlbulkedit\local\creator::can_create($course)) {
        return;
    }
    $navigation->add(
        get_string('bulkeditlinks', 'local_urlbulkedit'),
        new moodle_url('/local/urlbulkedit/index.php', ['id' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_urlbulkedit',
        new pix_icon('i/settings', '')
    );
}

/**
 * User preferences this plugin may set from JavaScript.
 *
 * @return array[]
 */
function local_urlbulkedit_user_preferences(): array {
    return [
        'local_urlbulkedit_columns' => [
            'type' => PARAM_TEXT,
            'null' => NULL_NOT_ALLOWED,
            'default' => '',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
