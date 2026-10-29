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

use cm_info;
use completion_info;
use context_module;
use core_courseformat\formatactions;
use core_date;
use DateTime;
use stdClass;

/**
 * Reads, validates and saves the link (mod_url) settings edited on the bulk page.
 *
 * Settings are written directly rather than through url_update_instance(),
 * which expects the full settings form: given a partial record it would drop
 * the pop-up size, "display URL description" and URL parameters. Link fields
 * go to the url table, completion to the course module; the follow-up work the
 * settings form would do is reproduced: completion calendar events, resetting
 * students' completion state when the tracking changes, the course cache and
 * the course_module_updated log event.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updater {
    /** @var array Table columns by group, in display order. */
    public const COLUMNGROUPS = [
        'general' => ['name', 'externalurl', 'visible'],
        'appearance' => ['display', 'popupwidth', 'popupheight', 'printintro'],
        'completion' => ['completion', 'completionexpected'],
    ];

    /** @var string[] Columns shown until the user picks their own. */
    public const DEFAULTCOLUMNS = ['externalurl', 'visible', 'display', 'completion'];

    /** @var string[] All editable fields. */
    public const FIELDS = ['name', 'externalurl', 'visible', 'display', 'popupwidth', 'popupheight', 'printintro',
        'completion', 'completionexpected'];

    /** @var string[] Fields stored in the url table's display options. */
    public const DISPLAYFIELDS = ['display', 'popupwidth', 'popupheight', 'printintro'];

    /** @var string[] Completion fields, stored on the course module. */
    public const COMPLETIONFIELDS = ['completion', 'completionexpected'];

    /** @var string Completion value meaning "automatic: student must view". */
    public const COMPLETION_VIEW = '2';

    /** @var int[] Display types that show the "display URL description" option: automatic, embed, frame. */
    public const PRINTINTRODISPLAYS = [0, 1, 2];

    /** @var string[] URL schemes never accepted, even though the settings form's loose check would let some through. */
    protected const BADSCHEMES = ['javascript', 'data', 'vbscript'];

    /**
     * Load what this class needs from mod_url and resourcelib.
     */
    public static function require_libs(): void {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        require_once($CFG->dirroot . '/mod/url/locallib.php');
        require_once($CFG->libdir . '/completionlib.php');
    }

    /**
     * The table columns, in display order.
     *
     * @return string[]
     */
    public static function columns(): array {
        return array_merge(...array_values(self::COLUMNGROUPS));
    }

    /**
     * Whether completion tracking can be used in this course.
     *
     * @param stdClass $course
     * @return bool
     */
    public static function completion_enabled(stdClass $course): bool {
        self::require_libs();
        return (new completion_info($course))->is_enabled();
    }

    /**
     * The links in a course the current user may edit, in course order.
     *
     * Each url record also carries visible, completion ('0', '1' or '2' = must view),
     * completionexpected, popupwidth, popupheight and printintro.
     *
     * @param stdClass $course
     * @return array url id => ['cm' => cm_info, 'url' => stdClass]
     */
    public static function get_links(stdClass $course): array {
        global $DB;
        self::require_libs();

        $modinfo = get_fast_modinfo($course);
        $cms = [];
        foreach ($modinfo->get_instances_of('url') as $cm) {
            if (has_capability('moodle/course:manageactivities', $cm->context)) {
                $cms[$cm->instance] = $cm;
            }
        }
        if (!$cms) {
            return [];
        }
        $records = $DB->get_records_list('url', 'id', array_keys($cms));
        $order = array_flip(array_keys($modinfo->get_cms()));
        uasort($cms, fn(cm_info $a, cm_info $b) => $order[$a->id] <=> $order[$b->id]);

        $config = get_config('url');
        $links = [];
        foreach ($cms as $urlid => $cm) {
            if (!isset($records[$urlid])) {
                continue;
            }
            $url = $records[$urlid];
            $url->visible = (int) $cm->visible;
            $url->completion = self::completion_value($cm);
            $url->completionexpected = (int) $cm->completionexpected;
            $options = empty($url->displayoptions) ? [] : (array) unserialize_array($url->displayoptions);
            $url->popupwidth = (int) ($options['popupwidth'] ?? $config->popupwidth);
            $url->popupheight = (int) ($options['popupheight'] ?? $config->popupheight);
            $url->printintro = (int) !empty($options['printintro']);
            $links[$urlid] = ['cm' => $cm, 'url' => $url];
        }
        return $links;
    }

    /**
     * A course module's completion tracking as one value.
     *
     * @param cm_info $cm
     * @return string '0' none, '1' manual, '2' automatic on view
     */
    protected static function completion_value(cm_info $cm): string {
        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            return self::COMPLETION_VIEW;
        }
        return (string) (int) $cm->completion;
    }

    /**
     * All display types with their labels.
     *
     * @return array type => label
     */
    public static function display_labels(): array {
        self::require_libs();
        return resourcelib_get_displayoptions([
            RESOURCELIB_DISPLAY_AUTO, RESOURCELIB_DISPLAY_EMBED, RESOURCELIB_DISPLAY_FRAME, RESOURCELIB_DISPLAY_NEW,
            RESOURCELIB_DISPLAY_DOWNLOAD, RESOURCELIB_DISPLAY_OPEN, RESOURCELIB_DISPLAY_POPUP,
        ]);
    }

    /**
     * The allowed values of a drop-down field.
     *
     * @param string $field
     * @return array|null value => label, or null if the field is free input
     */
    public static function choices(string $field): ?array {
        self::require_libs();
        switch ($field) {
            case 'visible':
                return [
                    1 => get_string('visible_show', 'local_urlbulkedit'),
                    0 => get_string('visible_hide', 'local_urlbulkedit'),
                ];
            case 'printintro':
                return [0 => get_string('no'), 1 => get_string('yes')];
            case 'display':
                // Only the display types the site allows for links, as in the settings form.
                $enabled = array_filter(explode(',', (string) get_config('url', 'displayoptions')), 'strlen');
                return resourcelib_get_displayoptions($enabled);
            case 'completion':
                return [
                    COMPLETION_TRACKING_NONE => get_string('completion_none', 'local_urlbulkedit'),
                    COMPLETION_TRACKING_MANUAL => get_string('completion_manual', 'local_urlbulkedit'),
                    self::COMPLETION_VIEW => get_string('completion_view', 'local_urlbulkedit'),
                ];
        }
        return null;
    }

    /**
     * Format a stored value as the string shown in the form input.
     *
     * @param string $field
     * @param mixed $value
     * @return string
     */
    public static function format_value(string $field, $value): string {
        if ($field === 'completionexpected') {
            if (empty($value)) {
                return '';
            }
            $dt = new DateTime('@' . $value);
            $dt->setTimezone(core_date::get_user_timezone_object());
            return $dt->format('Y-m-d\TH:i');
        }
        if (in_array($field, ['name', 'externalurl', 'completion'])) {
            return (string) $value;
        }
        return (string) (int) $value;
    }

    /**
     * Parse a submitted input string into a stored value.
     *
     * @param string $field
     * @param string $input
     * @return mixed the value, or null if invalid
     */
    public static function parse_value(string $field, string $input) {
        $input = trim($input);
        $choices = self::choices($field);
        if ($choices !== null) {
            foreach (array_keys($choices) as $key) {
                if ((string) $key === $input) {
                    return $field === 'completion' ? (string) $key : (int) $key;
                }
            }
            return null;
        }
        switch ($field) {
            case 'completionexpected':
                if ($input === '') {
                    return 0;
                }
                $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $input, core_date::get_user_timezone_object());
                return ($dt && $dt->format('Y-m-d\TH:i') === $input) ? $dt->getTimestamp() : null;
            case 'name':
                $name = clean_param($input, PARAM_TEXT);
                return ($name === '' || \core_text::strlen($name) > 255) ? null : $name;
            case 'externalurl':
                return self::parse_url($input);
            case 'popupwidth':
            case 'popupheight':
                return (preg_match('/^\d{1,4}$/', $input) && (int) $input >= 1) ? (int) $input : null;
        }
        return null;
    }

    /**
     * Check and tidy a link address the way the settings form does.
     *
     * Adds http:// when no scheme is given (as the settings form does), and
     * rejects addresses the form would reject, plus script and data URLs.
     *
     * @param string $input
     * @return string|null the address to store, or null if invalid
     */
    public static function parse_url(string $input): ?string {
        self::require_libs();
        if ($input === '' || \core_text::strlen($input) > 2048 || preg_match('/\s/', $input)) {
            return null;
        }
        $url = url_fix_submitted_url($input);
        if (!url_appears_valid_url($url)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, self::BADSCHEMES, true)) {
            return null;
        }
        return $url;
    }

    /**
     * Work out which fields changed and validate them.
     *
     * A field counts as changed only when the submitted string differs from
     * the string originally sent to the browser.
     *
     * @param array $links from get_links()
     * @param array $submitted url id => field => submitted string
     * @param array $original url id => field => string originally shown
     * @param stdClass $course
     * @return array [changes (url id => field => value), errors (url id => field => message)]
     */
    public static function collect_changes(array $links, array $submitted, array $original, stdClass $course): array {
        $changes = [];
        $errors = [];
        $completionon = self::completion_enabled($course);
        foreach ($submitted as $urlid => $fields) {
            if (!isset($links[$urlid]) || !is_array($fields)) {
                continue;
            }
            ['cm' => $cm, 'url' => $url] = $links[$urlid];
            foreach (self::FIELDS as $field) {
                if (!isset($fields[$field])) {
                    continue;
                }
                $input = (string) $fields[$field];
                $orig = (string) ($original[$urlid][$field] ?? self::format_value($field, $url->$field));
                if ($input === $orig) {
                    continue;
                }
                $value = self::parse_value($field, $input);
                if ($value === null) {
                    $errors[$urlid][$field] = get_string(self::error_string($field), 'local_urlbulkedit');
                    continue;
                }
                if ($field === 'visible' && !has_capability('moodle/course:activityvisibility', $cm->context)) {
                    $errors[$urlid][$field] = get_string('errorvisibility', 'local_urlbulkedit');
                    continue;
                }
                if (in_array($field, self::COMPLETIONFIELDS) && !$completionon) {
                    $errors[$urlid][$field] = get_string('errorcompletionoff', 'local_urlbulkedit');
                    continue;
                }
                if ((string) $value !== (string) $url->$field) {
                    $changes[$urlid][$field] = $value;
                }
            }
            if (!empty($changes[$urlid])) {
                self::validate_combination($url, $changes[$urlid], $errors[$urlid]);
            }
            if (empty($errors[$urlid])) {
                unset($errors[$urlid]);
            }
        }
        return [$changes, $errors];
    }

    /**
     * Rules that involve several fields, checked on the merged values.
     *
     * @param stdClass $url current link
     * @param array $changes field => new value
     * @param array|null $errors field => message, added to
     */
    protected static function validate_combination(stdClass $url, array $changes, ?array &$errors): void {
        $display = (int) ($changes['display'] ?? $url->display);
        foreach (['popupwidth', 'popupheight'] as $field) {
            if (array_key_exists($field, $changes) && $display != RESOURCELIB_DISPLAY_POPUP && empty($errors[$field])) {
                $errors[$field] = get_string('errorpopuponly', 'local_urlbulkedit');
            }
        }
        if (
            array_key_exists('printintro', $changes) && !in_array($display, self::PRINTINTRODISPLAYS)
                && empty($errors['printintro'])
        ) {
            $errors['printintro'] = get_string('errorprintintro', 'local_urlbulkedit');
        }
        $completion = (string) ($changes['completion'] ?? $url->completion);
        if (
            !empty($changes['completionexpected']) && $completion === (string) COMPLETION_TRACKING_NONE
                && empty($errors['completionexpected'])
        ) {
            $errors['completionexpected'] = get_string('errorexpectednone', 'local_urlbulkedit');
        }
    }

    /**
     * The lang string shown when a value can't be parsed.
     *
     * @param string $field
     * @return string
     */
    protected static function error_string(string $field): string {
        switch ($field) {
            case 'completionexpected':
                return 'errordate';
            case 'name':
            case 'externalurl':
                return 'error' . $field;
            case 'popupwidth':
            case 'popupheight':
                return 'errorpopupsize';
        }
        return 'errorchoice';
    }

    /**
     * The serialised display options for a link, as url_update_instance() builds them.
     *
     * @param int $display
     * @param int $popupwidth
     * @param int $popupheight
     * @param int $printintro
     * @return string
     */
    public static function display_options(int $display, int $popupwidth, int $popupheight, int $printintro): string {
        $options = [];
        if ($display == RESOURCELIB_DISPLAY_POPUP) {
            $options['popupwidth'] = $popupwidth;
            $options['popupheight'] = $popupheight;
        }
        if (in_array($display, self::PRINTINTRODISPLAYS)) {
            $options['printintro'] = $printintro;
        }
        return serialize($options);
    }

    /**
     * Save changes to the links of one course.
     *
     * @param stdClass $course
     * @param array $changes url id => field => value, from collect_changes()
     * @return int number of links updated
     */
    public static function apply(stdClass $course, array $changes): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        self::require_libs();

        $links = self::get_links($course);
        $transaction = $DB->start_delegated_transaction();
        $count = 0;
        $resetcompletion = [];
        foreach ($changes as $urlid => $fields) {
            $fields = array_intersect_key($fields, array_flip(self::FIELDS));
            if (!$fields || !isset($links[$urlid])) {
                continue;
            }
            ['cm' => $cm, 'url' => $url] = $links[$urlid];
            $context = context_module::instance($cm->id);
            require_capability('moodle/course:manageactivities', $context);
            $v = fn($f) => array_key_exists($f, $fields) ? $fields[$f] : $url->$f;

            // Link fields.
            $update = [];
            if (isset($fields['externalurl'])) {
                $update['externalurl'] = $fields['externalurl'];
            }
            if (array_intersect_key($fields, array_flip(self::DISPLAYFIELDS))) {
                $update['display'] = (int) $v('display');
                $update['displayoptions'] = self::display_options(
                    (int) $v('display'),
                    (int) $v('popupwidth'),
                    (int) $v('popupheight'),
                    (int) $v('printintro')
                );
            }
            if ($update) {
                $update['id'] = $urlid;
                $update['timemodified'] = time();
                $DB->update_record('url', (object) $update);
            }

            if (isset($fields['name'])) {
                formatactions::cm($course->id)->rename($cm->id, $fields['name']);
            }
            if (isset($fields['visible']) && $fields['visible'] != $cm->visible) {
                require_capability('moodle/course:activityvisibility', $context);
                set_coursemodule_visible($cm->id, $fields['visible'], 1, false);
            }

            // Completion, as the settings form saves it.
            if (array_intersect_key($fields, array_flip(self::COMPLETIONFIELDS))) {
                $completion = (string) $v('completion');
                $expected = $completion === (string) COMPLETION_TRACKING_NONE ? 0 : (int) $v('completionexpected');
                $DB->update_record('course_modules', (object) [
                    'id' => $cm->id,
                    'completion' => (int) $completion,
                    'completionview' => $completion === self::COMPLETION_VIEW ? COMPLETION_VIEW_REQUIRED
                        : COMPLETION_VIEW_NOT_REQUIRED,
                    'completionexpected' => $expected,
                ]);
                \core_completion\api::update_completion_date_event($cm->id, 'url', $urlid, $expected ?: null);
                if ($completion !== (string) $url->completion) {
                    $resetcompletion[] = $cm->id;
                }
            }

            \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();
            $count++;
        }
        $transaction->allow_commit();
        if ($count) {
            rebuild_course_cache($course->id, true);
        }
        if ($resetcompletion) {
            // Recalculate each student's completion under the new rule (views are kept; manual ticks are not).
            $modinfo = get_fast_modinfo($course->id);
            $completion = new completion_info($modinfo->get_course());
            foreach ($resetcompletion as $cmid) {
                $completion->reset_all_state($modinfo->get_cm($cmid));
            }
        }
        return $count;
    }
}
