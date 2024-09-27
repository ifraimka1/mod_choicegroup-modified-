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

    $unassignedusers = array_values($usergroups[0]);

    $unassigneduserscount = sizeof($unassignedusers);

    if ($unassigneduserscount == 0) {
        return get_string('notification:nounassignedusers', 'mod_choicegroup');
    }

    $sql = "SELECT g.id AS groupid,
                g.name AS groupname,
                cgo.maxanswers AS maxanswers,
                COUNT(gm.userid) AS countofmembers
            FROM {choicegroup} cg
                INNER JOIN {choicegroup_options} cgo ON cg.id = cgo.choicegroupid
                INNER JOIN {groups} g ON g.id = cgo.groupid
                LEFT JOIN {groups_members} gm ON gm.groupid = g.id
            WHERE cg.id = ".$choicegroup->id."
              AND g.id IN (".implode(',', $selectedgroups).")
            GROUP BY g.id, g.name, cgo.maxanswers
            ORDER BY groupid, groupname, maxanswers";
    $groups = $DB->get_records_sql($sql);

    $adgrouplist = array();    
    $totalcurrentusers = 0;
    foreach ($groups as $group) {        
        $totalcurrentusers += $group->countofmembers;
        $group->free = $group->maxanswers > 0 ?$group->maxanswers - $group->countofmembers : -1;
        array_push($adgrouplist, $group);
    }
    
    $allusers = $unassigneduserscount + $totalcurrentusers;
    $groupssize = sizeof($groups);
    $minimumgroupcount = floor($allusers / $groupssize);
    $usersremains = $allusers % $groupssize;   

    // Распределить пользователей по группам
    $userindex = 0;
    foreach ($adgrouplist as $group) {
        // Если группа не достигла равного распределения, добавляем студентов.
        $assigntogroup = $minimumgroupcount - $group->countofmembers;
        
        // Если у нас есть остаток, добавляем еще одного студента в эту группу.
        if ($usersremains > 0) {
            $assigntogroup += 1;
            $usersremains -= 1;
        }

        for ($i=0; $i < $assigntogroup; $i++) {
            choicegroup_user_submit_response($group->groupid, $choicegroup, $unassignedusers[$userindex], $course, $cm, true);
            $userindex++;

            if ($userindex == sizeof($unassignedusers)) {
                return null;
            }
        }
    }

    return null;
}
