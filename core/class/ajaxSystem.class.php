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
    $url .='?path='.urlencode(str_replace('{userId}',config::byKey('userId', 'ajaxSystem'),$_path));
    if($_path != '/login' && $_path != '/refresh'){
      $mc = cache::byKey('ajaxSystem::sessionToken');
      $sessionToken = $mc->getValue();
      if(trim($mc->getValue()) == ''){
        $sessionToken = self::refreshToken();
      }
      $url .= '&session_token='.$sessionToken;
    }
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
    if(isset($return['error'])){
      throw new \Exception(__('Erreur lors de la requete à Ajax System : ',__FILE__).json_encode($return));
    }
    if(isset($return['errors'])){
      throw new \Exception(__('Erreur lors de la requete à Ajax System : ',__FILE__).json_encode($return));
    }
    if(isset($return['body'])){
      return $return['body'];
    }
    return $return;
  }
  
  
  public static function login($_username,$_password){
    $data = self::request('/login',array(
      'login' => $_username,
      'passwordHash' => $_password,
      'userRole' => 'USER',
    ),'POST');
    config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
    config::save('userId', $data['userId'], 'ajaxSystem');
    cache::set('ajaxSystem::sessionToken', $data['sessionToken'],60*14);
  }
  
  public static function refreshToken(){
    $data = self::request('/refresh',array(
      'userId' => config::byKey('userId', 'ajaxSystem'),
      'refreshToken' => config::byKey('refreshToken', 'ajaxSystem')
    ),'POST');
    config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
    cache::set('ajaxSystem::sessionToken', $data['sessionToken'],60*14);
    return $data['sessionToken'];
  }
  
  public static function sync(){
    $hubs = self::request('/user/{userId}/hubs');
    foreach ($hubs as $hub) {
      $hub_info = self::request('/user/{userId}/hubs/'.$hub['hubId']);
      $eqLogic = eqLogic::byLogicalId($hub['hubId'], 'ajaxSystem');
      if (!is_object($eqLogic)) {
        $eqLogic = new ajaxSystem();
        $eqLogic->setEqType_name('ajaxSystem');
        $eqLogic->setIsEnable(1);
        $eqLogic->setName($hub_info['name']);
        $eqLogic->setCategory('security', 1);
        $eqLogic->setIsVisible(1);
      }
      $eqLogic->setConfiguration('type', 'hub');
      $eqLogic->setConfiguration('device', $hub_info['hubSubtype']);
      $eqLogic->setConfiguration('ip', $hub_info['ethernet']['ip']);
      $eqLogic->setConfiguration('firmware', $hub_info['firmware']['version']);
      $eqLogic->setLogicalId($hub['hubId']);
      $eqLogic->save();
    }
  }
  
  
  public static function devicesParameters($_device = '') {
    $return = array();
    $files = ls(__DIR__.'/../config/devices', '*.json', false, array('files', 'quiet'));
    foreach ($files as $file) {
      try {
        $return[str_replace('.json','',$file)] = is_json(file_get_contents(__DIR__.'/../config/devices/'. $file),false);
      } catch (Exception $e) {
        
      }
    }
    if (isset($_device) && $_device != '') {
      if (isset($return[$_device])) {
        return $return[$_device];
      }
      return array();
    }
    return $return;
  }
  
  /*     * *********************Méthodes d'instance************************* */
  
  public function postSave() {
    if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
      $this->applyModuleConfiguration();
    }
    $cmd = $this->getCmd(null, 'refresh');
    if (!is_object($cmd)) {
      $cmd = new ajaxSystemCmd();
      $cmd->setName(__('Rafraichir', __FILE__));
    }
    $cmd->setEqLogic_id($this->getId());
    $cmd->setLogicalId('refresh');
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->save();
  }
  
  public function applyModuleConfiguration() {
    $this->setConfiguration('applyDevice', $this->getConfiguration('device'));
    $this->save();
    if ($this->getConfiguration('device') == '') {
      return true;
    }
    $device = self::devicesParameters($this->getConfiguration('device'));
    if (!is_array($device)) {
      return true;
    }
    $this->import($device,true);
  }
  
  public function getImage() {
    if(file_exists(__DIR__.'/../config/devices/'.  $this->getConfiguration('device').'.png')){
      return 'plugins/ajaxSystem/core/config/devices/'.  $this->getConfiguration('device').'.png';
    }
    return false;
  }
  
  public function refreshData(){
    if($this->getConfiguration('type') == 'hub'){
      $datas = self::request('/user/{userId}/hubs/'.$this->getLogicalId());
    }
    foreach ($this->getCmd('info') as $cmd) {
      $paths = explode('::',$cmd->getLogicalId());
      $value = $datas;
      foreach ($paths as $key) {
        $value = $value[$key];
      }
      $this->checkAndUpdateCmd($cmd,$value);
    }
  }
  
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
    $eqLogic = $this->getEqLogic();
    if($this->getLogicalId() == 'refresh'){
      $eqLogic->refreshData();
    }
  }
  
  /*     * **********************Getteur Setteur*************************** */
}


