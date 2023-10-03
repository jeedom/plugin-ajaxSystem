<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if (!jeedom::apiAccess(init('apikey'), 'ajaxSystem')) {
    echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
    die();
}
if (isset($_GET['test'])) {
    echo 'OK';
    die();
}
$results = json_decode(file_get_contents("php://input"), true);
log::add('ajaxSystem', 'debug', '[SIA] ' . json_encode($results));
if (!isset($results['devices'])) {
    die();
}

$eqLogics = ajaxSystem::byType('ajaxSystem');

foreach ($results['devices'] as $id => $info) {
    foreach ($eqLogics as $eqLogic) {
        //Mise à jour des groupes
        if ($id == 501 && $eqLogic->getConfiguration('type') == 'group' && $info['ri'] == $eqLogic->getLogicalId()) {
            updateDevice($eqLogic, $info);
        }
        //Mise à jour device sur base du device_number
        if ($eqLogic->getConfiguration('device_number') == $id) {
            updateDevice($eqLogic, $info);
        }
        //Mise a jour Hub
        if (in_array($info['code'], ajaxSystem::$_SIA_GLOBALS) && $eqLogic->getConfiguration('type') == 'hub') {
            updateDevice($eqLogic, $info);
        }
    }
}

function updateDevice($_eqLogic, $info) {
    $_eqLogic->checkAndUpdateCmd('sia_code', $info['code']);
    if (isset($info['sia_code'])) {
        $_eqLogic->checkAndUpdateCmd('sia_type', $info['sia_code']['type']);
        $_eqLogic->checkAndUpdateCmd('sia_description', $info['sia_code']['description']);
        $_eqLogic->checkAndUpdateCmd('sia_concerns', $info['sia_code']['concerns']);
    }
    if (isset(ajaxSystem::$_SIA_CONVERT[$info['code']])) {
        foreach (ajaxSystem::$_SIA_CONVERT[$info['code']] as $convert) {
            if (isset($convert['hubOnly']) && $convert['hubOnly'] && $_eqLogic->getConfiguration('type') != 'hub') {
                continue;
            }
            log::add('ajaxSystem', 'debug', '[SIA] ' . $_eqLogic->getHumanName() . ' ' . $convert['cmd'] . ' => ' . $convert['value']);
            $_eqLogic->checkAndUpdateCmd($convert['cmd'], $convert['value']);
        }
    }
}
