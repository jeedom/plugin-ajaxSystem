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
        array('operation' => '#value# == "NIGHT_MODE"', 'state_light' => '<i class="fas fa-moon"></i>'),
        array('operation' => '#value# == "PANIC"', 'state_light' => '<i class="fas fa-exclamation-circle"></i>')
      )
    );
    return $return;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = 'ajaxSystem';
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder('ajaxSystem') . '/deamon.pid';
    if (file_exists($pid_file)) {
      if (@posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    }
    $return['launchable'] = 'ok';
    return $return;
  }

  public static function deamon_start() {
    log::remove(__CLASS__ . '_update');
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    if (config::byKey('sia::key', 'ajaxSystem') == '') {
      config::save('sia::key', mb_strtolower(config::genKey(16)), 'ajaxSystem');
    }
    if (config::byKey('sia::account', 'ajaxSystem') == '') {
      config::save('sia::account', rand(11111, 99999), 'ajaxSystem');
    }
    $ajaxSystem_path = realpath(dirname(__FILE__) . '/../../resources/ajaxSystemd');
    chdir($ajaxSystem_path);
    $cmd = '/usr/bin/python3 ' . $ajaxSystem_path . '/ajaxSystemd.py';
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('ajaxSystem'));
    $cmd .= ' --siaport ' . config::byKey('sia::port', 'ajaxSystem');
    $cmd .= ' --account ' . config::byKey('sia::account', 'ajaxSystem');
    $cmd .= ' --key ' . config::byKey('sia::key', 'ajaxSystem');
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/ajaxSystem/core/php/jeeAjaxSystemSia.php';
    $cmd .= ' --apikey ' . jeedom::getApiKey('ajaxSystem');
    $cmd .= ' --cycle ' . config::byKey('cycle', 'ajaxSystem');
    $cmd .= ' --pid ' . jeedom::getTmpFolder('ajaxSystem') . '/deamon.pid';
    log::add('ajaxSystem', 'info', 'Lancement démon ajaxSystem : ' . $cmd);
    exec($cmd . ' >> ' . log::getPathToLog('ajaxSystemd') . ' 2>&1 &');
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('ajaxSystem', 'error', 'Impossible de lancer le démon ajaxSystemd, vérifiez le log', 'unableStartDeamon');
      return false;
    }
    message::removeAll('ajaxSystem', 'unableStartDeamon');
    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder('ajaxSystem') . '/deamon.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('ajaxSystemd.py');
    system::fuserk(config::byKey('socketport', 'ajaxSystem'));
  }

  public static function request($_path, $_data = null, $_type = 'GET') {
    $url = config::byKey('service::cloud::url') . '/service/ajaxSystem';
    $url .= '?path=' . urlencode(str_replace('{userId}', config::byKey('userId', 'ajaxSystem'), $_path));
    if ($_path != '/login' && $_path != '/refresh') {
      $mc = cache::byKey('ajaxSystem::sessionToken');
      $sessionToken = $mc->getValue();
      if (trim($mc->getValue()) == '') {
        $sessionToken = self::refreshToken();
      }
      $url .= '&session_token=' . $sessionToken;
    }
    if ($_data !== null && $_type == 'GET') {
      $url .= '&options=' . urlencode(json_encode($_data));
    }
    $request_http = new com_http($url);
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
    $return = json_decode($request_http->exec(30, 1), true);
    $return = is_json($return, $return);
    if (isset($return['error'])) {
      throw new \Exception(__('Erreur lors de la requete à Ajax System : ', __FILE__) . json_encode($return));
    }
    if (isset($return['errors'])) {
      throw new \Exception(__('Erreur lors de la requete à Ajax System : ', __FILE__) . json_encode($return));
    }
    if (isset($return['body'])) {
      return $return['body'];
    }
    return $return;
  }

  public static function refreshAllData() {
    foreach (eqLogic::byType('ajaxSystem', true) as $eqLogic) {
      try {
        sleep(rand(0, 120));
        $eqLogic->refreshData();
      } catch (\Exception $e) {
        log::add('ajaxSystem', 'error', __('Erreur lors de la mise à jour des données de :', __FILE__) . ' ' . $eqLogic->getHumanName() . ' => ' . $e->getMessage(), 'ajaxSystem::failedGetData' . $eqLogic->getId());
      }
    }
  }

  public static function login($_username, $_password) {
    $data = self::request('/login', array(
      'login' => $_username,
      'passwordHash' => $_password,
      'userRole' => 'USER',
      'apikey' => jeedom::getApiKey('ajaxSystem'),
      'url' => network::getNetworkAccess('external')
    ), 'POST');
    config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
    config::save('userId', $data['userId'], 'ajaxSystem');
    cache::set('ajaxSystem::sessionToken', $data['sessionToken'], 60 * 14);
  }

  public static function refreshToken() {
    $data = self::request('/refresh', array(
      'userId' => config::byKey('userId', 'ajaxSystem'),
      'refreshToken' => config::byKey('refreshToken', 'ajaxSystem')
    ), 'POST');
    config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
    cache::set('ajaxSystem::sessionToken', $data['sessionToken'], 60 * 14);
    return $data['sessionToken'];
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
      $eqLogic->setConfiguration('device', $hub_info['hubSubtype']);
      $eqLogic->setConfiguration('ip', $hub_info['ethernet']['ip']);
      $eqLogic->setConfiguration('firmware', $hub_info['firmware']['version']);
      $eqLogic->setLogicalId($hub['hubId']);
      $eqLogic->save();

      $devices = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/devices');
      log::add('ajaxSystem', 'debug', json_encode($devices));
      foreach ($devices as $device) {
        $device_info = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/devices/' . $device['id']);
        $eqLogic = eqLogic::byLogicalId($device['id'], 'ajaxSystem');
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
        $eqLogic->setConfiguration('device', $device_info['deviceType']);
        $eqLogic->setConfiguration('firmware', $device_info['firmwareVersion']);
        $eqLogic->setLogicalId($device['id']);
        $eqLogic->save();
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
    $cmd = $this->getCmd(null, 'sia_code');
    if (!is_object($cmd)) {
      $cmd = new docker2Cmd();
      $cmd->setLogicalId('sia_code');
      $cmd->setName(__('SIA code', __FILE__));
    }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setConfiguration('repeatEventManagement', 'always');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();


    $cmd = $this->getCmd(null, 'sia_type');
    if (!is_object($cmd)) {
      $cmd = new docker2Cmd();
      $cmd->setLogicalId('sia_type');
      $cmd->setName(__('SIA Type', __FILE__));
    }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setConfiguration('repeatEventManagement', 'always');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();


    $cmd = $this->getCmd(null, 'sia_description');
    if (!is_object($cmd)) {
      $cmd = new docker2Cmd();
      $cmd->setLogicalId('sia_description');
      $cmd->setName(__('SIA description', __FILE__));
    }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setConfiguration('repeatEventManagement', 'always');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();


    $cmd = $this->getCmd(null, 'sia_concerns');
    if (!is_object($cmd)) {
      $cmd = new docker2Cmd();
      $cmd->setLogicalId('sia_concerns');
      $cmd->setName(__('SIA concerns', __FILE__));
    }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setConfiguration('repeatEventManagement', 'always');
    $cmd->setEqLogic_id($this->getId());
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
    $this->import($device, true);
  }

  public function getImage() {
    if (file_exists(__DIR__ . '/../config/devices/' .  $this->getConfiguration('device') . '.png')) {
      return 'plugins/ajaxSystem/core/config/devices/' .  $this->getConfiguration('device') . '.png';
    }
    return false;
  }

  public function refreshData() {
    if ($this->getConfiguration('type') == 'hub') {
      $datas = self::request('/user/{userId}/hubs/' . $this->getLogicalId());
    }
    if ($this->getConfiguration('type') == 'device') {
      $datas = self::request('/user/{userId}/hubs/' . $this->getConfiguration('hub_id') . '/devices/' . $this->getLogicalId());
    }
    if (isset($datas['firmwareVersion']) && $datas['firmwareVersion'] != $this->getConfiguration('firmware')) {
      $this->setConfiguration('firmware', $datas['firmwareVersion']);
      $this->save();
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
    if (isset($datas['batteryChargeLevelPercentage'])) {
      $this->batteryStatus($datas['batteryChargeLevelPercentage']);
    }
    if (isset($datas['battery']) && isset($datas['battery']['chargeLevelPercentage'])) {
      $this->batteryStatus($datas['battery']['chargeLevelPercentage']);
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}

class ajaxSystemCmd extends cmd {
  /*     * *************************Attributs****************************** */


  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

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
      sleep(1);
    } else if ($eqLogic->getConfiguration('type') == 'device') {
      $command = array(
        'command' => $this->getLogicalId(),
        'deviceType' => $eqLogic->getConfiguration('device')
      );
      ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/devices/' . $eqLogic->getLogicalId() . '/command', $command, 'POST');
      sleep(1);
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}
