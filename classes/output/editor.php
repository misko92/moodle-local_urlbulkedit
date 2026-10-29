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

namespace local_urlbulkedit\output;

use local_urlbulkedit\local\creator;
use local_urlbulkedit\local\updater;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The bulk link settings table.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor implements renderable, templatable {
    /** @var array Input types of the free-input fields. */
    protected const TYPES = [
        'name' => 'text',
        // Plain text, not "url": the browser would refuse addresses without http://, which the save adds itself.
        'externalurl' => 'text',
        'popupwidth' => 'number',
        'popupheight' => 'number',
        'completionexpected' => 'datetime-local',
    ];

    /** @var string[] Columns with a hint under the heading. */
    protected const HINTS = ['popupwidth', 'popupheight', 'printintro', 'completion'];

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param array $links from updater::get_links()
     * @param array $submitted url id => field => string, to redisplay after a failed save
     * @param array $original url id => field => string originally shown, kept across a failed save
     * @param array $errors url id => field => message
     * @param string $filter keyword the link list is filtered by
     */
    public function __construct(
        /** @var stdClass course */
        protected stdClass $course,
        /** @var array links */
        protected array $links,
        /** @var array submitted values */
        protected array $submitted = [],
        /** @var array original values */
        protected array $original = [],
        /** @var array validation errors */
        protected array $errors = [],
        /** @var string keyword filter */
        protected string $filter = ''
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $columns = updater::columns();
        $shown = array_flip($this->shown_columns());
        $completionon = updater::completion_enabled($this->course);
        $displaylabels = updater::display_labels();

        $copychoices = [];
        foreach ($this->links as $urlid => ['cm' => $cm]) {
            $copychoices[$urlid] = get_string('copyfrom', 'local_urlbulkedit', $cm->get_formatted_name(['escape' => false]));
        }

        $fields = [];
        foreach ($columns as $field) {
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_urlbulkedit'),
                'hint' => in_array($field, self::HINTS) ? get_string($field . '_hint', 'local_urlbulkedit') : null,
                'colhidden' => !isset($shown[$field]),
            ] + $this->control($field, updater::choices($field), '');
        }

        $groups = [];
        foreach (updater::COLUMNGROUPS as $group => $groupcolumns) {
            $groups[] = [
                'label' => get_string('group_' . $group, 'local_urlbulkedit'),
                'columns' => array_map(fn($field) => [
                    'field' => $field,
                    'label' => get_string($field, 'local_urlbulkedit'),
                    'checked' => isset($shown[$field]),
                ], $groupcolumns),
            ];
        }

        $rows = [];
        foreach ($this->links as $urlid => ['cm' => $cm, 'url' => $url]) {
            // Plain text: the template escapes it.
            $name = $cm->get_formatted_name(['escape' => false]);
            $cells = [];
            foreach ($columns as $field) {
                $original = $this->original[$urlid][$field] ?? updater::format_value($field, $url->$field);
                $value = $this->submitted[$urlid][$field] ?? $original;
                $note = $this->lock_note($field, $cm, $completionon);
                $choices = updater::choices($field);
                // A display type the site no longer offers: keep it rather than silently change it.
                if ($field === 'display' && !array_key_exists($original, $choices)) {
                    $choices = [$original => $displaylabels[$original] ?? $original] + $choices;
                }
                $cells[] = [
                    'field' => $field,
                    'original' => $original,
                    'changed' => $value !== $original,
                    'error' => $this->errors[$urlid][$field] ?? null,
                    'label' => get_string($field, 'local_urlbulkedit') . ': ' . $name,
                    'colhidden' => !isset($shown[$field]),
                    'disabled' => $note !== null,
                    'note' => $note === '' ? null : $note,
                ] + $this->control($field, $choices, $value);
            }

            $rows[] = [
                'urlid' => $urlid,
                'name' => $name,
                'url' => (new moodle_url('/mod/url/view.php', ['id' => $cm->id]))->out(false),
                'editurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false),
                'section' => creator::section_name($this->course, $cm->get_modinfo()->get_section_info($cm->sectionnum)),
                'externalurl' => $url->externalurl,
                'hidden' => !$cm->visible,
                'cells' => $cells,
            ];
        }

        return [
            'actionurl' => (new moodle_url('/local/urlbulkedit/index.php'))->out(false),
            'courseid' => $this->course->id,
            'sesskey' => sesskey(),
            'fields' => $fields,
            'groups' => $groups,
            'defaultcolumns' => implode(',', updater::DEFAULTCOLUMNS),
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'filter' => $this->filter,
            'popup' => RESOURCELIB_DISPLAY_POPUP,
            'printintrodisplays' => implode(',', updater::PRINTINTRODISPLAYS),
            'allchoices' => array_map(
                fn($urlid, $label) => ['value' => $urlid, 'label' => $label],
                array_keys($copychoices),
                $copychoices
            ),
        ];
    }

    /**
     * Why a cell can't be edited.
     *
     * @param string $field
     * @param \cm_info $cm
     * @param bool $completionon whether completion tracking is on in the course
     * @return string|null null if editable, otherwise the note to show ('' for none)
     */
    protected function lock_note(string $field, \cm_info $cm, bool $completionon): ?string {
        if ($field === 'visible' && !has_capability('moodle/course:activityvisibility', $cm->context)) {
            return '';
        }
        if (in_array($field, updater::COMPLETIONFIELDS) && !$completionon) {
            return get_string('lockedcompletionoff', 'local_urlbulkedit');
        }
        return null;
    }

    /**
     * The columns to show: the user's choice, plus any with an error or unsaved change to redisplay.
     *
     * @return string[]
     */
    protected function shown_columns(): array {
        $columns = updater::columns();
        $pref = get_user_preferences('local_urlbulkedit_columns', '');
        $shown = $pref === '' ? updater::DEFAULTCOLUMNS : array_intersect($columns, explode(',', $pref));
        foreach ($this->errors as $fields) {
            array_push($shown, ...array_keys($fields));
        }
        foreach ($this->submitted as $urlid => $fields) {
            foreach ($fields as $field => $value) {
                if ($value !== ($this->original[$urlid][$field] ?? null)) {
                    $shown[] = $field;
                }
            }
        }
        // Keep display order.
        return array_values(array_intersect($columns, $shown));
    }

    /**
     * Template data for one input or drop-down.
     *
     * @param string $field
     * @param array|null $choices value => label, for drop-downs
     * @param string $value current value
     * @return array
     */
    protected function control(string $field, ?array $choices, string $value): array {
        if ($choices !== null) {
            $options = [];
            foreach ($choices as $optvalue => $label) {
                $options[] = ['value' => $optvalue, 'label' => $label, 'selected' => (string) $optvalue === $value];
            }
            return ['isselect' => true, 'options' => $options];
        }
        $type = self::TYPES[$field] ?? 'text';
        return [
            'isselect' => false,
            'type' => $type,
            'value' => $value,
            'isnumber' => $type === 'number',
            'isurl' => $field === 'externalurl',
        ];
    }
}
