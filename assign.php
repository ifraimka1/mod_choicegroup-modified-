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
 * Random feature page
 *
 * @package    mod
 * @subpackage choicegroup
 * @author     Ifraim Solomonov <mr.ifraim@yandex.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once("assignlib.php");

$id = required_param('id', PARAM_INT); // module id
$action = optional_param('action', '', PARAM_ALPHA);
$selectedgroups = optional_param_array('groups', array(), PARAM_INT);

if (!$cm = get_coursemodule_from_id('choicegroup', $id)) {
    print_error("invalidcoursemodule");
}

if (!$choicegroup = choicegroup_get_choicegroup($cm->instance)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("coursemisconf");
}

require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/choicegroup:randomassign', $context);

$notification = null;

if ($action == 'confirm') {
    if (!empty($selectedgroups)) {
        $autoassignresult = auto_assign($choicegroup, $cm, $course, $selectedgroups);
        if (is_null($autoassignresult)) {
            redirect(new moodle_url('report.php', array('id' => $cm->id)));
        } else {
            $notification = $autoassignresult;
        }
    } else {
        $notification = get_string('notification:nogroupsselected', 'mod_choicegroup');
    }
}

$PAGE->set_url('/mod/choicegroup/assign.php', array('id' => $id));
$PAGE->set_title(get_string('assignstudents', 'mod_choicegroup'));
$PAGE->set_heading(format_string($choicegroup->name));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignstudents', 'mod_choicegroup'));

if (!is_null($notification)) {
    echo $OUTPUT->notification($notification, 'notifyproblem');
}

// Получаем все группы, доступные в данном choice group
$sql = "SELECT g.id,
                g.name,
                cgo.maxanswers,
                COUNT(gm.userid) AS countofmembers
            FROM {choicegroup} cg
                INNER JOIN {choicegroup_options} cgo ON cg.id = cgo.choicegroupid
                INNER JOIN {groups} g ON g.id = cgo.groupid
                LEFT JOIN {groups_members} gm ON gm.groupid = g.id
            WHERE cg.id = ".$choicegroup->id."
            GROUP BY g.id, g.name, cgo.maxanswers
            ORDER BY g.id, g.name, cgo.maxanswers";
$groups = $DB->get_records_sql($sql);

// Форма с чекбоксами для выбора групп
echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'assign.php?id='.$id));
echo html_writer::start_tag('div');

// Скрытое поле для передачи значения 'confirm'
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'confirm'));

// Кнопка для выбора/снятия всех групп
echo html_writer::tag('button', get_string('selectall', 'mod_choicegroup'), array('type' => 'button', 'id' => 'toggleall', 'class' => 'btn btn-secondary mb-3'));

// Выводим чекбоксы для каждой группы
foreach ($groups as $group) {
    echo html_writer::start_tag('div', array('class' => 'group-checkbox'));
    echo html_writer::checkbox('groups[]', $group->id, in_array($group->id, $selectedgroups), format_string($group->name), array('class' => 'group-checkbox-item'));
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div');

// Кнопка "Подтвердить" с селектором 'btn-primary'
$options = array('id' => $id, 'action' => 'confirm');
$confirmbutton = html_writer::tag('button', get_string('confirm', 'mod_choicegroup'), array('type' => 'submit', 'class' => 'btn btn-primary'));

// Кнопка "Вернуться" с селектором 'btn-secondary', которая ведет на страницу отчета
$returnurl = new moodle_url('report.php', array('id' => $cm->id));
$returnbutton = html_writer::tag('a', get_string('return', 'mod_choicegroup'), array('href' => $returnurl, 'class' => 'btn btn-secondary ml-2'));

// Объединение кнопок в один контейнер
echo html_writer::start_tag('div', array('class' => 'mt-3'));
echo $confirmbutton;
echo $returnbutton;
echo html_writer::end_tag('div');

echo html_writer::end_tag('form');

echo $OUTPUT->footer();

echo "<script>
let toggleAllButton = document.getElementById('toggleall');
let checkboxes = document.querySelectorAll('.group-checkbox > input');
let allSelected = false;

console.log(checkboxes);

toggleAllButton.addEventListener('click', function() {
    allSelected = !allSelected;
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = allSelected;
    });
    
    if (allSelected) {
        toggleAllButton.textContent = '".get_string('deselectall', 'mod_choicegroup')."';
    } else {
        toggleAllButton.textContent = '".get_string('selectall', 'mod_choicegroup')."';
    }
});
</script>";