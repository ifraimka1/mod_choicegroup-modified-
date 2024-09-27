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
 * Version information
 *
 * @package    mod
 * @subpackage choicegroup
 * @copyright  2013 Université de Lausanne
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('lib.php');

function auto_assign($choicegroup, $cm, $course, $selectedgroups) {
    global $DB; 

    // Получить список неответивших
    $usergroups = choicegroup_get_response_data($choicegroup, $cm, 0, false);

    $nogroupusers = array();
    foreach ($usergroups as $group) {
        foreach ($group as $user) {
            if (!isset($user->grpsmemberid)) {
                array_push($nogroupusers, $user->id);
            }
        }
    }

    if (!$nogroupusers) return;

    $adgrouplist = array();
    // Квоты
    foreach ($selectedgroups as $group) {
        $group->free = $group->max_answers - $group->count_of_members;
        array_push($adgrouplist, $group);
    }

    // Считаем распределение, если групп 2
    if (sizeof($adgrouplist) >= 2) {
        $countofusers = sizeof($nogroupusers);
        $diff = abs($adgrouplist[0]->count_of_members - $adgrouplist[1]->count_of_members);
        $adgrouplist[0]->quota = $adgrouplist[1]->quota = round(($countofusers - $diff) / 2);

        if ($diff > 0) {
            if ($adgrouplist[0]->count_of_members > $adgrouplist[1]->count_of_members) {
                $adgrouplist[0]->quota += $diff;
            } else {
                $adgrouplist[0]->quota += $diff;
            }
        }
    } else {
        $adgrouplist[0]->quote = sizeof($nogroupusers);
    }

    // Распределить пользователей по группам
    $counter = 0;
    $index = 0;

    foreach ($nogroupusers as $user) {
        choicegroup_user_submit_response($adgrouplist[$index]->group_id, $choicegroup, $user, $course, $cm, true);
        $counter++;
        
        if ($counter >= $adgrouplist[$index]->quota) {
            $index++;
        }
    }
}
