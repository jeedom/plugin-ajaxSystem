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
log::add('ajaxSystem', 'debug', json_encode($results));
if (!isset($results['devices'])) {
    die;
}

$eqLogics = ajaxSystem::byType('ajaxSystem');

$globals = array('CL', 'OP', 'NL');

foreach ($results['devices'] as $id => $info) {
    foreach ($eqLogics as $eqLogic) {
        if (in_array($info['code'], $globals)) {
            $eqLogic->checkAndUpdateCmd('sia_code', $info['code']);
            if (isset($info['sia_code'])) {
                $eqLogic->checkAndUpdateCmd('sia_type', $info['sia_code']['type']);
                $eqLogic->checkAndUpdateCmd('sia_description', $info['sia_code']['description']);
                $eqLogic->checkAndUpdateCmd('sia_concerns', $info['sia_code']['concerns']);
            }
            continue;
        }
        if ($eqLogic->getConfiguration('device_number') != $id) {
            continue;
        }
        $eqLogic->checkAndUpdateCmd('sia_code', $info['code']);
        if (isset($info['sia_code'])) {
            $eqLogic->checkAndUpdateCmd('sia_type', $info['sia_code']['type']);
            $eqLogic->checkAndUpdateCmd('sia_description', $info['sia_code']['description']);
            $eqLogic->checkAndUpdateCmd('sia_concerns', $info['sia_code']['concerns']);
        }
    }
}
