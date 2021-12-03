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

$SIA_GLOBALS = array('CL', 'OP', 'NL', 'BR', 'BA');

$SIA_CONVERT = array(
    'CL' => array(array('cmd' => 'state', 'value' => 'ARMED', 'hubOnly' => true)),
    'OP' => array(array('cmd' => 'state', 'value' => 'DISARMED', 'hubOnly' => true)),
    'NL' => array(array('cmd' => 'state', 'value' => 'NIGHT_MODE', 'hubOnly' => true)),
    'PA' => array(array('cmd' => 'state', 'value' => 'PANIC', 'hubOnly' => true)),
    'CF' => array(array('cmd' => 'state', 'value' => 'ARMED', 'hubOnly' => true)),
    'BA' => array(array('cmd' => 'sia_state', 'value' => 1), array('cmd' => 'reedClosed', 'value' => 1)), array('cmd' => 'sia_state_intrusion', 'value' => 1),
    'TA' => array(array('cmd' => 'sia_state_masking', 'value' => 1)),
    'TR' => array(array('cmd' => 'sia_state_masking', 'value' => 0)),
    'BR' => array(array('cmd' => 'sia_state', 'value' => 0), array('cmd' => 'reedClosed', 'value' => 1), array('cmd' => 'sia_state_intrusion', 'value' => 1)),
    'HA' => array(array('cmd' => 'sia_state', 'value' => 1)),
    'FA' => array(array('cmd' => 'sia_state', 'value' => 1)),
    'MA' => array(array('cmd' => 'sia_state', 'value' => 1)),
    'GA' => array(array('cmd' => 'sia_state', 'value' => 1)),
    'KA' => array(array('cmd' => 'sia_state', 'value' => 1)),
    'GH' => array(array('cmd' => 'sia_state', 'value' => 0)),
    'FH' => array(array('cmd' => 'sia_state', 'value' => 0)),
    'KH' => array(array('cmd' => 'sia_state', 'value' => 0)),
    'YP' => array(array('cmd' => 'externallyPowered', 'value' => 1)),
    'YQ' => array(array('cmd' => 'externallyPowered', 'value' => 0)),
    'WA' => array(array('cmd' => 'leakDetected', 'value' => 1)),
    'WH' => array(array('cmd' => 'leakDetected', 'value' => 1)),
    'AT' => array(array('cmd' => 'externallyPowered', 'value' => 1)),
    'AR' => array(array('cmd' => 'externallyPowered', 'value' => 0)),
    'BV' => array(array('cmd' => 'sia_state_intrusion', 'value' => 1)),
    'HV' => array(array('cmd' => 'sia_state_intrusion', 'value' => 1))
);

foreach ($results['devices'] as $id => $info) {
    foreach ($eqLogics as $eqLogic) {
        if ($eqLogic->getConfiguration('device_number') != $id && (!in_array($info['code'], $SIA_GLOBALS) || $eqLogic->getConfiguration('type') != 'hub')) {
            continue;
        }
        $eqLogic->checkAndUpdateCmd('sia_code', $info['code']);
        if (isset($info['sia_code'])) {
            $eqLogic->checkAndUpdateCmd('sia_type', $info['sia_code']['type']);
            $eqLogic->checkAndUpdateCmd('sia_description', $info['sia_code']['description']);
            $eqLogic->checkAndUpdateCmd('sia_concerns', $info['sia_code']['concerns']);
        }
        if (isset($SIA_CONVERT[$info['code']])) {
            foreach ($SIA_CONVERT[$info['code']] as $convert) {
                if (isset($convert['hubOnly']) && $convert['hubOnly'] && $eqLogic->getConfiguration('type') != 'hub') {
                    continue;
                }
                log::add('ajaxSystem', 'debug', 'SIA ' . $eqLogic->getHumanName() . ' ' . $convert['cmd'] . ' => ' . $convert['value']);
                $eqLogic->checkAndUpdateCmd($convert['cmd'], $convert['value']);
            }
        }
    }
}
