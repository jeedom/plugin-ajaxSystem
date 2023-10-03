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

    $mappings = ajaxSystem::devicesMappings($ajaxSystem->getConfiguration('device'));

    foreach ($data['updates'] as $key => &$value) {      
      if ($key == 'batteryCharge') {
        //Actualisation de la charge de la batterie au niveau de l'équipement jeedom
        $ajaxSystem->batteryStatus($value);
      }

      if (in_array($data['type'], array('HUB', 'GROUP')) && $key == 'state') {
        if ($value == 0) {
          $value = 'DISARMED';
        } elseif ($value == 1) {
          $value = 'ARMED';
        } elseif ($value == 2) {
          $value = 'NIGHT_MODE';
        }
      }

      //Manipulation de valeur pour inverser le statut des équipements relais et équipements prises
      if ($convert_key == 'realState') {
        $value = ($value == 0) ? 1 : 0;
      }

      //Extraire l'info du mapping pour aller chercher dans le bon logicalId

      $logicalIdCorrespondingToUpdateKey = ' ';

      foreach($mappings['mappings'] as $mapping){
        if($mapping['updateKey'] == $key)
        {
          $logicalIdCorrespondingToUpdateKey = $mapping['logicalId'];
          break;
        }
      }

      //Si correspondance trouvée entre l'update key et un logicalId dans les mappings
      //Alors mise à jour de la valeur
      if($logicalIdCorrespondingToUpdateKey != ' ')
      {
        $ajaxSystem->checkAndUpdateCmd($logicalIdCorrespondingToUpdateKey, $value);
      }
      else{
        //TODO : Penser a ajouter un log de type warning pour dire qu'on reçu un update avec une clé inconnue
        //Celà permettrait aussi de logger des choses que l'on a pas encore implémenté pour le moment et qu'on voudrait
        //Peut être rajouter un jour / mettre à disposition des utilisateurs. Pratique aussi pour les device dont on ne connait
        //Pas tous les updates qui pourraient remonter
      }

      //Calcul de la puissance spécifique pour les devices de type prise électrique (socket)
      if ($ajaxSystem->getConfiguration('device') == 'Socket') {
        $current = $ajaxSystem->getCmd('info', 'currentMA');
        $voltage = $ajaxSystem->getCmd('info', 'voltage');
        if (is_object($current) && is_object($voltage)) {
          $ajaxSystem->checkAndUpdateCmd('power', $current->execCmd() * $voltage->execCmd());
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
