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

    foreach ($data['updates'] as $key => &$value) {      
      //Mapping du statut d'armement vers des valeurs traduites en texte facilement compréhensible
      if (in_array($data['type'], array('HUB', 'GROUP')) && $key == 'state') {
        if ($value == 0) {
          $value = 'DISARMED';
        } elseif ($value == 1) {
          $value = 'ARMED';
        } elseif ($value == 2) {
          $value = 'NIGHT_MODE';
        }
      }

      if ($key == 'batteryCharge') {
        //Actualisation de la charge de la batterie au niveau de l'équipement jeedom
        $ajaxSystem->batteryStatus($value);
      }

      //Manipulation de valeur pour inverser le statut des équipements relais et équipements prises
      if ($key == 'realState') {
        $value = ($value == 0) ? 1 : 0;
      }

      //Determiner quelle commande correspond à cette updateKey
      $logicalIdCorrespondingToUpdateKey = '';

      foreach ($ajaxSystem->getCmd('info') as $cmd)
      {
        $updateKey = $cmd->getConfiguration('updateKey');
        if($key == $updateKey)
        {
          $logicalIdCorrespondingToUpdateKey = $cmd->getLogicalId();
          break;
        }
      }

      //Si correspondance trouvée entre l'update key et un logicalId dans les mappings
      //Alors mise à jour de la valeur
      if($logicalIdCorrespondingToUpdateKey != '')
      {
        $ajaxSystem->checkAndUpdateCmd($logicalIdCorrespondingToUpdateKey, $value);
      }
      else{
        log::add('ajaxSystem', 'debug', __('No corresponding command found for the update key' . $key . ' - for device ' .$ajaxSystem->getHumanName, __FILE__));
      }

      //Calcul de la puissance spécifique pour les devices de type prise électrique (socket)    
      if ($ajaxSystem->getConfiguration('device') == 'Socket') {
        //Optimisation du code pour ne recalculer la puissance des équipements Socket que si on a reçu un update
        //Des valeurs de voltage ou d'intensité
        if(($key == 'currentMA' || $key == 'voltage'))
        {
          $current = $ajaxSystem->getCmd('info', 'currentMA');
          $voltage = $ajaxSystem->getCmd('info', 'voltage');
          if (is_object($current) && is_object($voltage)) {
            $ajaxSystem->checkAndUpdateCmd('power', $current->execCmd() * $voltage->execCmd());
          }
        }
      }
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
    $ajaxSystem->checkAndUpdateCmd('sourceObjectName', $data['event']['sourceObjectName'], date('Y-m-d H:i:s', $data['event']['timestamp'] / 1000));
  }
}
