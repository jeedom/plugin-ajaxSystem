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

  

  public static function templateWidget() {
    $return = array('info' => array('string' => array()));
    $return['info']['string']['state'] = array(
      'template' => 'tmplmultistate',
      'test' => array(
        array('operation' => '#value# == "ARMED"', 'state_light' => '<i class="fas fa-lock"></i>'),
        array('operation' => '#value# == "DISARMED"', 'state_light' => '<i class="fas fa-lock-open"></i>'),
        array('operation' => '#value# == "ARMED_NIGHT_MODE_OFF"', 'state_light' => '<i class="fas fa-lock"></i>'),
        array('operation' => '#value# == "DISARMED_NIGHT_MODE_ON"', 'state_light' => '<i class="fas fa-moon"></i>'),
        array('operation' => '#value# == "DISARMED_NIGHT_MODE_OFF"', 'state_light' => '<i class="fas fa-lock-open"></i>'),
        array('operation' => '#value# == "NIGHT_MODE"', 'state_light' => '<i class="fas fa-moon"></i>'),
        array('operation' => '#value# == "ARMED_NIGHT_MODE_ON"', 'state_light' => '<i class="fas fa-lock"></i>'),
        array('operation' => '#value# == "PANIC"', 'state_light' => '<i class="fas fa-exclamation-circle"></i>')
      )
    );
    return $return;
  }

  public static function request($_path, $_data = null, $_type = 'GET') {
    $authenticated = ($_path != '/login' && $_path != '/refresh');
    $session = $authenticated ? self::getSession() : null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
      $path = str_replace('{userId}', $authenticated ? $session['userId'] : '', $_path);
      $url = config::byKey('service::cloud::url') . '/service/ajaxSystem?path=' . urlencode($path);
      // Never include tokens in the debug log or in com_http's own error log.
      log::add('ajaxSystem', 'debug', '[request] ' . $_type . ' ' . $path . ' => ' . json_encode(self::redactSecrets($_data)));
      if ($authenticated) {
        $url .= '&session_token=' . urlencode($session['sessionToken']);
      }
      if ($_data !== null && $_type == 'GET') {
        $url .= '&options=' . urlencode(json_encode($_data));
      }
      $request_http = new com_http($url);
      $request_http->setLogError(false);
      $request_http->setHeader(array(
        'Content-Type: application/json',
        'Autorization: ' . sha512(mb_strtolower(config::byKey('market::username')) . ':' . config::byKey('market::password'))
      ));
      if ($_type == 'POST') {
        $request_http->setPost(json_encode($_data));
      }
      if ($_type == 'PUT') {
        $request_http->setPut(json_encode($_data));
      }
      try {
        // A timed-out POST/PUT may already have rotated tokens or executed a command.
        $response = $request_http->exec(60, $_type == 'GET' ? 3 : 1);
      } catch (\Exception $e) {
        $message = $e->getMessage();
        foreach (array($authenticated ? $session['sessionToken'] : '', isset($_data['refreshToken']) ? $_data['refreshToken'] : '', isset($_data['passwordHash']) ? $_data['passwordHash'] : '') as $secret) {
          if (is_string($secret) && $secret !== '') {
            $message = str_replace(array($secret, urlencode($secret)), '[masqué]', $message);
          }
        }
        throw new \Exception(__('Echec de la requête à Ajax System : ', __FILE__) . $path . ' : ' . $message);
      }
      if ($response === '') {
        return null;
      }
      $return = json_decode($response, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception(__('Réponse JSON invalide pour la requête à Ajax System : ', __FILE__) . $path);
      }
      $return = is_json($return, $return);
      if (is_array($return) && (isset($return['error']) || isset($return['errors']) || (isset($return['state']) && $return['state'] == 'nok'))) {
        if ($authenticated && $attempt == 0 && self::isSessionError($return)) {
          log::add('ajaxSystem', 'debug', '[request] Session refusée, renouvellement avant une nouvelle tentative');
          $session = self::getSession($session['sessionToken']);
          continue;
        }
        throw new \Exception(__('Erreur lors de la requete à Ajax System : ', __FILE__) . $path . ' : ' . json_encode(self::redactSecrets($return)));
      }
      if (is_array($return) && isset($return['body'])) {
        return $return['body'];
      }
      return $return;
    }
  }

  private static function redactSecrets($_data) {
    if (!is_array($_data)) {
      return $_data;
    }
    foreach ($_data as $key => $value) {
      $_data[$key] = preg_match('/password|token|apikey|authorization|autorization/i', (string) $key) ? '[masqué]' : self::redactSecrets($value);
    }
    return $_data;
  }

  private static function isSessionError($_error) {
    foreach (array('code', 'status', 'statusCode', 'httpCode') as $key) {
      if (isset($_error[$key]) && is_numeric($_error[$key]) && (int) $_error[$key] == 401) {
        return true;
      }
    }
    $message = json_encode($_error);
    foreach (array('Wrong session token', 'Invalid session token', 'Session token is invalid', 'Session token expired', 'Session token has expired', 'Session token is expired', 'User is not authorized') as $error) {
      if (stripos($message, $error) !== false) {
        return true;
      }
    }
    return false;
  }

  private static function lockSession() {
    $lock = fopen(jeedom::getTmpFolder('ajaxSystem') . '/session.lock', 'c');
    if ($lock === false) {
      throw new \Exception(__('Impossible de verrouiller la session Ajax', __FILE__));
    }
    $deadline = microtime(true) + 70;
    do {
      if (flock($lock, LOCK_EX | LOCK_NB)) {
        return $lock;
      }
      usleep(100000);
    } while (microtime(true) < $deadline);
    fclose($lock);
    throw new \Exception(__('Une connexion Ajax est déjà en cours. Réessayez dans quelques instants.', __FILE__));
  }

  private static function getSession($_rejectedToken = null) {
    $lock = self::lockSession();
    try {
      // Reload both cache and persistent configuration after waiting for another process.
      $sessionToken = cache::byKey('ajaxSystem::sessionToken')->getValue('');
      $userId = config::byKey('userId', 'ajaxSystem', '', true);
      if (is_string($sessionToken) && trim($sessionToken) !== '' && $sessionToken !== $_rejectedToken && $userId !== '') {
        return array('userId' => $userId, 'sessionToken' => $sessionToken);
      }
      cache::delete('ajaxSystem::sessionToken');
      $refreshToken = config::byKey('refreshToken', 'ajaxSystem', '', true);
      if (trim($userId) === '' || trim($refreshToken) === '') {
        throw new \Exception(__('Connexion Ajax absente. Reconnectez-vous au compte Ajax depuis la configuration du plugin.', __FILE__));
      }
      try {
        $data = self::request('/refresh', array('userId' => $userId, 'refreshToken' => $refreshToken), 'POST');
      } catch (\Exception $e) {
        if (self::isSessionError(array('error' => $e->getMessage())) || preg_match('/wrong session refresh token|invalid refresh token|refresh token.*expired/i', $e->getMessage())) {
          throw new \Exception(__('Session Ajax expirée ou révoquée. Reconnectez-vous au compte Ajax depuis la configuration du plugin.', __FILE__) . ' ' . $e->getMessage());
        }
        throw $e;
      }
      self::validateSession($data);
      config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
      cache::set('ajaxSystem::sessionToken', $data['sessionToken'], 60 * 14);
      log::add('ajaxSystem', 'debug', '[refreshToken] Session Ajax renouvelée');
      return array('userId' => $userId, 'sessionToken' => $data['sessionToken']);
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  private static function validateSession($_data, $_login = false) {
    foreach ($_login ? array('userId', 'refreshToken', 'sessionToken') : array('refreshToken', 'sessionToken') as $key) {
      if (!isset($_data[$key]) || !is_string($_data[$key]) || trim($_data[$key]) === '') {
        throw new \Exception(__('Réponse Ajax incomplète : jetons de connexion invalides. Réessayez de vous connecter depuis la configuration du plugin.', __FILE__));
      }
    }
  }

  public static function start() {
    self::refreshAllData();
  }

  public static function refreshAllData() {
    foreach (eqLogic::byType('ajaxSystem', true) as $eqLogic) {
      try {
        sleep(rand(10, 60));
        $eqLogic->refreshData();
        if ($eqLogic->getCache('failedAjaxRequest', 0) > 0) {
          $eqLogic->setCache('failedAjaxRequest', 0);
        }
      } catch (\Exception $e) {
        $eqLogic->setCache('failedAjaxRequest', $eqLogic->getCache('failedAjaxRequest', 0) + 1);
        if ($eqLogic->getCache('failedAjaxRequest', 0) > 3) {
          log::add('ajaxSystem', 'error', __('Erreur lors de la mise à jour des données de :', __FILE__) . ' ' . $eqLogic->getHumanName() . ' => ' . $e->getMessage(), 'ajaxSystem::failedGetData' . $eqLogic->getId());
        }
      }
    }
  }

  public static function login($_username, $_password) {
    if (trim(network::getNetworkAccess('external')) == '') {
      throw new Exception(__('URL d\'accès externe de votre Jeedom invalide. Merci de la configurer dans Réglage -> Système -> Configuration puis onglet Réseaux'));
    }
    $lock = self::lockSession();
    try {
      $data = self::request('/login', array(
        'login' => $_username,
        'passwordHash' => $_password,
        'userRole' => 'USER',
        'apikey' => jeedom::getApiKey('ajaxSystem'),
        'url' => network::getNetworkAccess('external')
      ), 'POST');
      self::validateSession($data, true);
      config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
      config::save('userId', $data['userId'], 'ajaxSystem');
      cache::set('ajaxSystem::sessionToken', $data['sessionToken'], 60 * 14);
      log::add('ajaxSystem', 'debug', '[login] Session Ajax enregistrée');
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  public static function refreshToken() {
    $session = self::getSession();
    return $session['sessionToken'];
  }

  public static function sync() {
    $hubs = self::request('/user/{userId}/hubs');
    log::add('ajaxSystem', 'debug', json_encode($hubs));
    foreach ($hubs as $hub) {
      $hub_info = self::request('/user/{userId}/hubs/' . $hub['hubId']);
      log::add('ajaxSystem', 'debug', json_encode($hub_info));
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
      $eqLogic->setConfiguration('color', $hub_info['color']);
      $eqLogic->setConfiguration('device', $hub_info['hubSubtype']);
      $eqLogic->setConfiguration('ip', $hub_info['ethernet']['ip']);
      $eqLogic->setConfiguration('firmware', $hub_info['firmware']['version']);
      $eqLogic->setLogicalId($hub['hubId']);
      $eqLogic->save();
      $eqLogic->refreshData();

      $devices = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/devices');
      log::add('ajaxSystem', 'debug', json_encode($devices));
      foreach ($devices as $device) {
        if (!isset($device['id'])) {
              continue;
        }
        $eqLogic = eqLogic::byLogicalId($device['id'], 'ajaxSystem');
        $device_info = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/devices/' . $device['id']);
        usleep(200000);
        if(!isset($device_info['deviceName']) || $device_info['deviceName'] == ''){
          continue;
        }
        if (!is_object($eqLogic)) {
          $eqLogic = new ajaxSystem();
          $eqLogic->setEqType_name('ajaxSystem');
          $eqLogic->setIsEnable(1);
          $eqLogic->setName($device_info['deviceName']);
          $eqLogic->setCategory('security', 1);
          $eqLogic->setIsVisible(1);
        }
        $eqLogic->setConfiguration('hub_id', $hub['hubId']);
        $eqLogic->setConfiguration('type', 'device');
        $eqLogic->setConfiguration('color', $device_info['color']);
        $eqLogic->setConfiguration('device', $device_info['deviceType']);
        $eqLogic->setConfiguration('firmware', $device_info['firmwareVersion']);
        $eqLogic->setLogicalId($device['id']);
        $eqLogic->save();
        $eqLogic->refreshData($device_info);
      }

      $groups = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/groups');
      log::add('ajaxSystem', 'debug', json_encode($groups));
      if (!is_array($groups)) {
        log::add('ajaxSystem', 'warning', __('Impossible de récupérer les groupes pour le hub ', __FILE__) . $hub['hubId'] . ' : ' . json_encode($groups));
      } else {
        foreach ($groups as $group) {
          if($group['groupName'] == ''){
            continue;
          }
          $groupLogicalId = $hub['hubId'] . '_' . $group['id'];
          $eqLogic = eqLogic::byLogicalId($groupLogicalId, 'ajaxSystem');
          // Migration: check for old-style logicalId without hub prefix
          if (!is_object($eqLogic)) {
            $eqLogic = eqLogic::byLogicalId($group['id'], 'ajaxSystem');
            if (is_object($eqLogic) && $eqLogic->getConfiguration('type') == 'group') {
              log::add('ajaxSystem', 'info', __('Migration du groupe ', __FILE__) . $group['groupName'] . ' vers le nouveau logicalId ' . $groupLogicalId);
            } else {
              $eqLogic = null;
            }
          }
          if (!is_object($eqLogic)) {
            $eqLogic = new ajaxSystem();
            $eqLogic->setEqType_name('ajaxSystem');
            $eqLogic->setIsEnable(1);
            $eqLogic->setName($group['groupName']);
            $eqLogic->setCategory('security', 1);
            $eqLogic->setIsVisible(1);
          }
          $eqLogic->setConfiguration('hub_id', $hub['hubId']);
          $eqLogic->setConfiguration('type', 'group');
          $eqLogic->setConfiguration('device', 'group');
          $eqLogic->setConfiguration('group_id', $group['id']);
          $eqLogic->setLogicalId($groupLogicalId);
          $eqLogic->save();
          $eqLogic->refreshData($group);
        }
      }
    }
  }


  public static function devicesParameters($_device = '') {
    $return = array();
    $files = ls(__DIR__ . '/../config/devices', '*.json', false, array('files', 'quiet'));
    foreach ($files as $file) {
      try {
        $return[str_replace('.json', '', $file)] = is_json(file_get_contents(__DIR__ . '/../config/devices/' . $file), false);
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
    foreach ($this->getCmd() as $cmd) {
      if(strpos($cmd->getLogicalId(),'sia') !== false){
        $cmd->remove();
      }
    }
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
    $this->import($device, true);
  }

  public function getImage() {
    if(method_exists($this,'getCustomImage')){
         $customImage = $this->getCustomImage();
         if($customImage !== null){
            return $customImage;
         }
      }
    if (file_exists(__DIR__ . '/../config/devices/' .  $this->getConfiguration('device') . '_' . strtolower($this->getConfiguration('color')) . '.png')) {
      return 'plugins/ajaxSystem/core/config/devices/' .  $this->getConfiguration('device') . '_' . strtolower($this->getConfiguration('color')) . '.png';
    }
    if (file_exists(__DIR__ . '/../config/devices/' .  $this->getConfiguration('device') . '.png')) {
      return 'plugins/ajaxSystem/core/config/devices/' .  $this->getConfiguration('device') . '.png';
    }
    return false;
  }

  public function refreshData($_data = null) {
    if($_data == null){
      if ($this->getConfiguration('type') == 'hub') {
        $datas = self::request('/user/{userId}/hubs/' . $this->getLogicalId());
      }
      if ($this->getConfiguration('type') == 'device') {
        $datas = self::request('/user/{userId}/hubs/' . $this->getConfiguration('hub_id') . '/devices/' . $this->getLogicalId());
      }
      if ($this->getConfiguration('type') == 'group') {
        $groupId = $this->getConfiguration('group_id', $this->getLogicalId());
        $allGroups = self::request('/user/{userId}/hubs/' . $this->getConfiguration('hub_id') . '/groups');
        $datas = null;
        if (is_array($allGroups)) {
          foreach ($allGroups as $g) {
            if (isset($g['id']) && $g['id'] == $groupId) {
              $datas = $g;
              break;
            }
          }
        }
        if ($datas === null) {
          return;
        }
      }
    }else{
      $datas = $_data;
    }
    if (isset($datas['firmwareVersion']) && $datas['firmwareVersion'] != $this->getConfiguration('firmware')) {
      $this->setConfiguration('firmware', $datas['firmwareVersion']);
      $this->save(true);
    }
    foreach ($this->getCmd('info') as $cmd) {
      $paths = explode('::', $cmd->getLogicalId());
      $value = $datas;
      foreach ($paths as $key) {
        if (!isset($value[$key])) {
          continue 2;
        }
        $value = $value[$key];
      }
      $this->checkAndUpdateCmd($cmd, $value);
    }
    //Refresh batterie depuis trame de synchronisation / refresh
    $batteryChargeLevel = '-1';
    if (isset($datas['batteryChargeLevelPercentage'])) {
      $batteryChargeLevel = $datas['batteryChargeLevelPercentage'];  
    }
    if (isset($datas['battery']) && isset($datas['battery']['chargeLevelPercentage'])) {
      $batteryChargeLevel = $datas['battery']['chargeLevelPercentage'];
    }
    //Si niveau de charge numérique disponible, mise à jour de l'information
    if($batteryChargeLevel != '-1'){
      //Au niveau de l'équipement
      $this->batteryStatus($batteryChargeLevel);
      $this->checkAndUpdateCmd('battery::chargeLevelPercentage', $value);
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}

class ajaxSystemCmd extends cmd {
  /*     * *************************Attributs****************************** */


  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */


  public function alreadyInState($_options) {
    $eqLogic = $this->getEqLogic();
    if ($eqLogic->getConfiguration('type') == 'hub') {
      $cmdValue = $this->getCmdValue();
      $value =  $cmdValue->execCmd();
      if ($this->getLogicalId() == 'ARM') {
        return ($value == 'ARMED');
      }
      if ($this->getLogicalId() == 'DISARM') {
        return ($value == 'DISARMED_NIGHT_MODE_OFF' || $value == 'DISARMED');
      }
      if ($this->getLogicalId() == 'NIGHT_MODE') {
        return ($value == 'NIGHT_MODE');
      }
      if ($this->getLogicalId() == 'PANIC' || $this->getLogicalId() == 'muteFireDetectors') {
        return false;
      }
    }
    if ($eqLogic->getConfiguration('type') == 'group') {
      return false;
    }
    return parent::alreadyInState($_options);
  }

  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if ($eqLogic->getConfiguration('type') == 'hub') {
      if ($this->getLogicalId() == 'ARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getLogicalId() . '/commands/arming', array('command' => 'ARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'DISARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getLogicalId() . '/commands/arming', array('command' => 'DISARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'NIGHT_MODE') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getLogicalId() . '/commands/arming', array('command' => 'NIGHT_MODE_ON', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'PANIC') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getLogicalId() . '/commands/panic', array('location' => array('latitude' => 0, 'longitude' => 0, 'accuracy' => 0, 'speed' => 0, 'timestamp' => 0)), 'PUT');
      } else if ($this->getLogicalId() == 'muteFireDetectors') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getLogicalId() . '/commands/muteFireDetectors', array('muteType' => 'ALL_FIRE_DETECTORS'), 'PUT');
      }
    } else if ($eqLogic->getConfiguration('type') == 'device') {
      $command = array(
        'command' => explode(" ", $this->getLogicalId())[0],
        'deviceType' => $eqLogic->getConfiguration('device')
      );
      if($this->getConfiguration('AdditionalParam',null) != null){
        $command['AdditionalParam'] = $this->getConfiguration('AdditionalParam',null);
      }
      if(!isset($command['command']) || $command['command'] == ''){
        $command['command'] = $this->getLogicalId();
      }
      if(isset(explode(" ", $this->getLogicalId())[1]) && explode(" ", $this->getLogicalId())[1] != ""){
          $command['additionalParam'] = array(
            'additionalParamType' => 'CHANNELS',
            'channels' => array("CHANNEL_".explode(" ", $this->getLogicalId())[1])
          );
      }
      log::add('ajaxSystem','debug','Command send to ajax : '.json_encode($command));
      ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/devices/' . $eqLogic->getLogicalId() . '/command', $command, 'POST');
    } else if ($eqLogic->getConfiguration('type') == 'group') {
      $groupId = $eqLogic->getConfiguration('group_id', $eqLogic->getLogicalId());
      if ($this->getLogicalId() == 'ARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $groupId  . '/commands/arming', array('command' => 'ARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'DISARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $groupId . '/commands/arming', array('command' => 'DISARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'NIGHT_MODE') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $groupId . '/commands/arming', array('command' => 'NIGHT_MODE_ON', 'ignoreProblems' => true), 'PUT');
      }
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}

