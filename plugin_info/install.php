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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function ajaxSystem_install() {
  
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function ajaxSystem_update() {
  //Suppression du cron
  $cron = cron::byClassAndFunction('ajaxSystem', 'refreshAllData');
  if (is_object($cron)) {
    $cron->remove();
  }

  //Vérification et mise à jour des commandes pour chaque équipement
  foreach (eqLogic::byType('ajaxSystem') as $eqLogic) {   
    //Retrait de la commande action de refresh des équipements existants
    $cmd = $eqLogic->getCmd('action', 'refresh');
    if (is_object($cmd)) {
      $cmd->remove();
    }

    //Récupération des commandes prévues dans la config pour le type de device
    $deviceCommandsFromConfig = ajaxSystem::devicesParameters($eqLogic->getConfiguration('device'));

    //Suppression des commandes info obsoletes
    foreach ($eqLogic->getCmd('info') as $cmd){
      $commandLogicalId = $cmd->getLogicalId();
      $found = false;
      foreach($deviceCommandsFromConfig as $configCmd)
      {
        if($commandLogicalId == $configCmd['logicalId'])
        {
          //Correspondance trouvée dans la config, la commande est toujours utilisée
          $found=true;
          break;
        }
      }
      if($found == false)
      {
        //Pas de correspondance trouvée dans la config dans cette commande, on la supprime
        $cmd->remove();
      }
    }

    //Ajout des commandes manquantes et mise à jour des commandes existantes
    //En théorie le fait de sauver un équipement déclenche l'évènement postSave()
    //Ce même postSave() devrait provoquer un rafraichissement des commandes via l'execution de applyModuleConfiguration()
    $eqLogic->save();
  }
}

// Fonction exécutée automatiquement après la suppression du plugin
function ajaxSystem_remove() {
  $cron = cron::byClassAndFunction('ajaxSystem', 'refreshAllData');
  if (is_object($cron)) {
    $cron->remove();
  }
}
