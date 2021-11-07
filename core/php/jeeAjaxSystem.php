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
if (init('apikey') != '') {
  $apikey = init('apikey');
  if (!jeedom::apiAccess($apikey, 'ajaxSystem')) {
    echo __('Vous n\'etes pas autorisé à effectuer cette action. Clef API invalide.', __FILE__);
    die();
  } else {
    echo __('Configuration OK', __FILE__);
    die();
  }
}
header('Content-type: application/json');
$datas = json_decode(file_get_contents('php://input'), true);
if (!isset($datas['apikey']) || !jeedom::apiAccess($datas['apikey'], 'ajaxSystem')) {
  die();
}
log::add('ajaxSystem', 'debug', 'Received : ' . json_encode($datas));

foreach ($datas['data'] as $data) {
  if (isset($data['updates'])) {
    if (!isset($data['id'])) {
      continue;
    }
    $ajaxSystem = ajaxSystem::byLogicalId($data['id'], 'ajaxSystem');
    if (!is_object($ajaxSystem)) {
      continue;
    }
    foreach ($data['updates'] as $key => $value) {
      if ($data['type'] == 'HUB' && $key == 'state') {
        if ($value == 0) {
          $value = 'DISARMED';
        } elseif ($value == 1) {
          $value = 'ARMED';
        } elseif ($value == 2) {
          $value = 'NIGHT_MODE';
        }
      }
      $ajaxSystem->checkAndUpdateCmd($key, $value);
    }
  } else if (isset($data['event'])) {
    if (!isset($data['event']['sourceObjectId'])) {
      continue;
    }
    $ajaxSystem = ajaxSystem::byLogicalId($data['event']['sourceObjectId'], 'ajaxSystem');
    if (!is_object($ajaxSystem)) {
      continue;
    }
    $ajaxSystem->checkAndUpdateCmd('event', $data['event']['eventType'], date('Y-m-d H:i:s', $data['event']['timestamp'] / 1000));
    $ajaxSystem->checkAndUpdateCmd('eventCode', $data['event']['eventCode'], date('Y-m-d H:i:s', $data['event']['timestamp'] / 1000));
  }
}
