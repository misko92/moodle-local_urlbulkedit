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

namespace local_urlbulkedit\local;

use context_course;
use section_info;
use stdClass;

/**
 * Create several links at once, each with its own name, address and section.
 *
 * Links are created with Moodle's own create_module(), the same code path as
 * the "Add an activity" form, with the display and completion settings taken
 * from a model link or from the site's defaults for links.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class creator {
    /**
     * Whether the current user may add links in this course.
     *
     * @param stdClass $course
     * @return bool
     */
    public static function can_create(stdClass $course): bool {
        $context = context_course::instance($course->id);
        return has_all_capabilities(['moodle/course:manageactivities', 'mod/url:addinstance'], $context)
            && array_key_exists('url', \core\plugininfo\mod::get_enabled_plugins() ?? []);
    }

    /**
     * The course's sections, with each subsection listed after its parent section.
     *
     * @param stdClass $course
     * @return array section number => name (plain text)
     */
    public static function sections(stdClass $course): array {
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->is_delegated()) {
                continue;
            }
            $sections[$section->section] = self::section_name($course, $section);
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $sub = $modinfo->get_cm($cmid)->get_delegated_section_info();
                if ($sub) {
                    $sections[$sub->section] = self::section_name($course, $sub);
                }
            }
        }
        return $sections;
    }

    /**
     * A section's name as plain text, for templates (which escape it themselves).
     *
     * Subsections are shown as "Parent › Subsection".
     *
     * @param stdClass $course
     * @param section_info $section
     * @return string
     */
    public static function section_name(stdClass $course, section_info $section): string {
        if ((string) $section->name !== '') {
            $context = context_course::instance($course->id);
            $name = format_string($section->name, true, ['context' => $context, 'escape' => false]);
        } else {
            $name = course_get_format($course)->get_default_section_name($section);
        }
        $parent = $section->is_delegated() ? $section->get_component_instance()?->get_parent_section() : null;
        if ($parent) {
            return get_string('subsectionname', 'local_urlbulkedit', (object) [
                'parent' => self::section_name($course, $parent),
                'name' => $name,
            ]);
        }
        return $name;
    }

    /**
     * Read and check the submitted rows.
     *
     * Rows left completely empty are ignored.
     *
     * @param stdClass $course
     * @param array $input row index => field => submitted string (name, externalurl, section)
     * @return array [rows (index => stdClass with name, externalurl, section), errors (index => field => message)]
     */
    public static function parse_rows(stdClass $course, array $input): array {
        $sections = self::sections($course);
        $rows = [];
        $errors = [];
        foreach ($input as $i => $fields) {
            $fields = array_map(fn($value) => trim((string) $value), (array) $fields);
            if (($fields['name'] ?? '') === '' && ($fields['externalurl'] ?? '') === '') {
                continue;
            }
            $row = new stdClass();
            $row->name = updater::parse_value('name', $fields['name'] ?? '');
            if ($row->name === null) {
                $errors[$i]['name'] = get_string('errorname', 'local_urlbulkedit');
            }
            $row->externalurl = updater::parse_url($fields['externalurl'] ?? '');
            if ($row->externalurl === null) {
                $errors[$i]['externalurl'] = get_string('errorexternalurl', 'local_urlbulkedit');
            }
            $section = $fields['section'] ?? '';
            if (!preg_match('/^\d+$/', $section) || !isset($sections[(int) $section])) {
                $errors[$i]['section'] = get_string('errorchoice', 'local_urlbulkedit');
            }
            $row->section = (int) $section;
            $rows[$i] = $row;
        }
        return [$rows, $errors];
    }

    /**
     * The display and completion settings new links get.
     *
     * @param stdClass $course
     * @param int $modelid url id of a link to copy them from, or 0 for the site defaults
     * @return stdClass display, popupwidth, popupheight, printintro, completion ('0', '1' or '2')
     */
    public static function settings(stdClass $course, int $modelid): stdClass {
        global $DB;
        updater::require_libs();
        if ($modelid) {
            $links = updater::get_links($course);
            if (!isset($links[$modelid])) {
                throw new \moodle_exception('invalidrecord', 'error', '', 'url');
            }
            $url = $links[$modelid]['url'];
            $completion = updater::completion_enabled($course) ? $url->completion : '0';
            return (object) [
                'display' => (int) $url->display,
                'popupwidth' => $url->popupwidth,
                'popupheight' => $url->popupheight,
                'printintro' => $url->printintro,
                'completion' => $completion,
            ];
        }
        $config = get_config('url');
        $completion = '0';
        if (updater::completion_enabled($course)) {
            // The site's default completion for links (Course > Default activity completion, or site-wide).
            $defaults = \core_completion\manager::get_default_completion($course, (object) [
                'name' => 'url',
                'id' => $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST),
            ]);
            if (($defaults->completion ?? 0) == COMPLETION_TRACKING_AUTOMATIC) {
                $completion = !empty($defaults->completionview) ? updater::COMPLETION_VIEW : '0';
            } else {
                $completion = (string) (int) ($defaults->completion ?? 0);
            }
        }
        return (object) [
            'display' => (int) $config->display,
            'popupwidth' => (int) $config->popupwidth,
            'popupheight' => (int) $config->popupheight,
            'printintro' => (int) $config->printintro,
            'completion' => $completion,
        ];
    }

    /**
     * Create the links.
     *
     * Links are created one by one; if one fails, the earlier ones stay and the
     * exception says which row failed.
     *
     * @param stdClass $course
     * @param stdClass $settings from settings()
     * @param array $rows from parse_rows(), without errors
     * @param bool $hidden create them hidden from students
     * @return int[] course module ids of the new links
     */
    public static function create(stdClass $course, stdClass $settings, array $rows, bool $hidden): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        updater::require_libs();
        if (!self::can_create($course)) {
            throw new \required_capability_exception(
                context_course::instance($course->id),
                'mod/url:addinstance',
                'nopermissions',
                ''
            );
        }
        if ($hidden === false) {
            require_capability('moodle/course:activityvisibility', context_course::instance($course->id));
        }
        \core_php_time_limit::raise(max(120, 5 * count($rows)));

        $cmids = [];
        foreach ($rows as $row) {
            $info = (object) [
                'modulename' => 'url',
                'course' => $course->id,
                'section' => $row->section,
                'visible' => $hidden ? 0 : 1,
                'visibleoncoursepage' => 1,
                'name' => $row->name,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'externalurl' => $row->externalurl,
                'display' => $settings->display,
                'popupwidth' => $settings->popupwidth,
                'popupheight' => $settings->popupheight,
                'printintro' => $settings->printintro,
                'completion' => (int) $settings->completion,
                'completionview' => $settings->completion === updater::COMPLETION_VIEW ? COMPLETION_VIEW_REQUIRED
                    : COMPLETION_VIEW_NOT_REQUIRED,
                'completionexpected' => 0,
                'cmidnumber' => '',
            ];
            try {
                $cmids[] = (int) create_module($info)->coursemodule;
            } catch (\moodle_exception $e) {
                throw new \moodle_exception('errorcreate', 'local_urlbulkedit', '', s($row->name), $e->getMessage());
            }
        }
        return $cmids;
    }
}
