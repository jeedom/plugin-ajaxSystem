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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class ajaxSystem extends eqLogic {
  /*     * *************************Attributs****************************** */
  
  
  
  /*     * ***********************Methode static*************************** */
  
  public static function request($_path,$_data = null,$_type='GET'){
    $url = config::byKey('service::cloud::url').'/service/ajaxSystem';
    $url .='?path='.urlencode($_path);
    if($_data !== null && $_type == 'GET'){
      $url .='&options='.urlencode(json_encode($_data));
    }
    $request_http = new com_http($url);
    $request_http->setHeader(array(
      'Content-Type: application/json',
      'Autorization: '.sha512(mb_strtolower(config::byKey('market::username')).':'.config::byKey('market::password'))
    ));
    if($_type == 'POST'){
      $request_http->setPost(json_encode($_data));
    }
    $return = json_decode($request_http->exec(30,1),true);
    $return = is_json($return,$return);
    if(isset($return['state']) && $return['state'] != 'ok'){
      throw new \Exception(__('Erreur lors de la requete à Netatmo : ',__FILE__).json_encode($return));
    }
    if(isset($return['error'])){
      throw new \Exception(__('Erreur lors de la requete à Netatmo : ',__FILE__).json_encode($return));
    }
    if(isset($return['body'])){
      return $return['body'];
    }
    return $return;
  }
  
  
  /*     * *********************Méthodes d'instance************************* */
  
  
  /*     * **********************Getteur Setteur*************************** */
}

class ajaxSystemCmd extends cmd {
  /*     * *************************Attributs****************************** */
  
  /*
  public static $_widgetPossibility = array();
  */
  
  /*     * ***********************Methode static*************************** */
  
  
  /*     * *********************Methode d'instance************************* */
  
  
  
  // Exécution d'une commande  
  public function execute($_options = array()) {
    
  }
  
  /*     * **********************Getteur Setteur*************************** */
}


