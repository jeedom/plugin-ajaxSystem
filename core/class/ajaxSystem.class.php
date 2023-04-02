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
  public static $_SIA_GLOBALS = array('CL', 'OP', 'NL', 'BR', 'BA', 'KA', 'FA', 'GA', 'WA', 'PA');

  public static $_SIA_CONVERT = array(
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
    'AT' => array(array('cmd' => 'externallyPowered', 'value' => 0)),
    'AR' => array(array('cmd' => 'externallyPowered', 'value' => 1)),
    'BV' => array(array('cmd' => 'sia_state_intrusion', 'value' => 1)),
    'HV' => array(array('cmd' => 'sia_state_intrusion', 'value' => 1))
  );


  /*     * ***********************Methode static*************************** */

  public static function handleMqttMessage($_datas) {
    if (!isset($_datas['ajax'])) {
      return;
    }
    log::add('ajaxSystem', 'debug', json_encode($_datas));
    $eqLogics = self::byType('ajaxSystem');
    foreach ($_datas['ajax'] as $id => $value) {
      if ($id == '') {
        continue;
      }
      $info = (is_array($value)) ? $value : json_decode($value, true);
      if ($info == null || !is_array($info)) {
        continue;
      }
      if ($id == 'error') {
        if (isset($info['description'])) {
          log::add('ajaxSystem', 'error', __('Erreur renvoyé par MQTT : ', __FILE__) . $info['description']);
        }
        continue;
      }
      if (!isset($info['code']) || $info['code'] == '') {
        log::add('ajaxSystem', 'debug', 'Invalid code : ' . json_encode($info));
        continue;
      }
      if (!isset($info['datetime']) || $info['datetime'] == '') {
        log::add('ajaxSystem', 'debug', 'Invalid datetime : ' .  json_encode($info));
        continue;
      }
      $d = DateTime::createFromFormat('H:i:s,m-d-Y', $info['datetime'], new DateTimeZone('UTC'));

      if ($d->getTimestamp() < (strtotime('now') - 120)) {
        log::add('ajaxSystem', 'debug', 'Invalid too old datetime : ' .  json_encode($info));
        continue;
      }
      foreach ($eqLogics as $eqLogic) {
        if ($eqLogic->getConfiguration('device_number') != $id && (!in_array($info['code'], self::$_SIA_GLOBALS) || $eqLogic->getConfiguration('type') != 'hub')) {
          continue;
        }
        $eqLogic->checkAndUpdateCmd('sia_code', $info['code']);
        if (isset($info['type'])) {
          $eqLogic->checkAndUpdateCmd('sia_type', $info['type']);
        }
        if (isset($info['description'])) {
          $eqLogic->checkAndUpdateCmd('sia_description', $info['description']);
        }
        if (isset($info['concerns'])) {
          $eqLogic->checkAndUpdateCmd('sia_concerns', $info['concerns']);
        }
        if (isset(self::$_SIA_CONVERT[$info['code']])) {
          foreach (self::$_SIA_CONVERT[$info['code']] as $convert) {
            if (isset($convert['hubOnly']) && $convert['hubOnly'] && $eqLogic->getConfiguration('type') != 'hub') {
              continue;
            }
            log::add('ajaxSystem', 'debug', 'MQTT ' . $eqLogic->getHumanName() . ' ' . $convert['cmd'] . ' => ' . $convert['value']);
            $eqLogic->checkAndUpdateCmd($convert['cmd'], $convert['value']);
          }
        }
      }
    }
  }

  public static function postConfig_local_mode($_value) {
    $plugin = plugin::byId('ajaxSystem');
    switch ($_value) {
      case 'none':
        $plugin->dependancy_changeAutoMode(0);
        $plugin->deamon_info(0);
        mqtt2::removePluginTopic(config::byKey('mqtt::prefix', __CLASS__, 'ajax'));
        break;
      case 'sia':
        $plugin->dependancy_changeAutoMode(1);
        $plugin->deamon_info(1);
        mqtt2::removePluginTopic(config::byKey('mqtt::prefix', __CLASS__, 'ajax'));
        break;
      case 'mqtt':
        $plugin->dependancy_changeAutoMode(0);
        $plugin->deamon_info(0);
        if (!class_exists('mqtt2')) {
          throw new Exception(__('Le plugin MQTT Manager n\'est pas installé', __FILE__));
        }
        if (mqtt2::deamon_info()['state'] != 'ok') {
          throw new Exception(__('Le démon MQTT Manager n\'est pas démarré', __FILE__));
        }
        mqtt2::addPluginTopic(__CLASS__, config::byKey('mqtt::prefix', __CLASS__, 'ajax'));
        break;
    }
  }


  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'ajaxSystem_update';
    $return['progress_file'] = '/tmp/dependancy_ajaxSystem_in_progress';
    $return['state'] = 'ok';
    if (exec(system::getCmdSudo() . 'pip3 list | grep -E "pysiaalarm" | wc -l') < 1) {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    log::remove(__CLASS__ . '_update');
    return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('ajaxSystem') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
  }

  public static function templateWidget() {
    $return = array('info' => array('string' => array()));
    $return['info']['string']['state'] = array(
      'template' => 'tmplmultistate',
      'test' => array(
        array('operation' => '#value# == "ARMED"', 'state_light' => '<i class="fas fa-lock"></i>'),
        array('operation' => '#value# == "DISARMED"', 'state_light' => '<i class="fas fa-lock-open"></i>'),
        array('operation' => '#value# == "DISARMED_NIGHT_MODE_OFF"', 'state_light' => '<i class="fas fa-lock-open"></i>'),
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
    log::add('ajaxSystem', 'debug', '[request] ' . $url . ' => ' . json_encode($_data));
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
    $data = self::request('/login', array(
      'login' => $_username,
      'passwordHash' => $_password,
      'userRole' => 'USER',
      'apikey' => jeedom::getApiKey('ajaxSystem'),
      'url' => network::getNetworkAccess('external')
    ), 'POST');
    log::add('ajaxSystem', 'debug', '[login] ' . json_encode($data));
    config::save('refreshToken', $data['refreshToken'], 'ajaxSystem');
    config::save('userId', $data['userId'], 'ajaxSystem');
    cache::set('ajaxSystem::sessionToken', $data['sessionToken'], 60 * 14);
  }

  public static function refreshToken() {
    $data = self::request('/refresh', array(
      'userId' => config::byKey('userId', 'ajaxSystem'),
      'refreshToken' => config::byKey('refreshToken', 'ajaxSystem')
    ), 'POST');
    log::add('ajaxSystem', 'debug', '[refreshToken] ' . json_encode($data));
    if ($data['refreshToken'] == '') {
      log::add('ajaxSystem', 'debug', '[refreshToken] Empty refresh token, retry in 5s');
      sleep(5);
      $data = self::request('/refresh', array(
        'userId' => config::byKey('userId', 'ajaxSystem'),
        'refreshToken' => config::byKey('refreshToken', 'ajaxSystem')
      ), 'POST');
      log::add('ajaxSystem', 'debug', '[refreshToken] ' . json_encode($data));
    }
    if ($data['refreshToken'] == '') {
      throw new Exception(__('Impossible de mettre à jour les tokens d\'accès, refresh token vide : ', __FILE__) . json_encode($data));
    }
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
      $eqLogic->setConfiguration('color', $hub_info['color']);
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
        $eqLogic->setConfiguration('color', $device_info['color']);
        $eqLogic->setConfiguration('device', $device_info['deviceType']);
        $eqLogic->setConfiguration('firmware', $device_info['firmwareVersion']);
        $eqLogic->setLogicalId($device['id']);
        $eqLogic->save();
      }

      $groups = self::request('/user/{userId}/hubs/' . $hub['hubId'] . '/groups');
      log::add('ajaxSystem', 'debug', json_encode($groups));
      foreach ($groups as $group) {
        $eqLogic = eqLogic::byLogicalId($group['id'], 'ajaxSystem');
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
        $eqLogic->setLogicalId($group['id']);
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
      $cmd = new ajaxSystemCmd();
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
      $cmd = new ajaxSystemCmd();
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
      $cmd = new ajaxSystemCmd();
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
      $cmd = new ajaxSystemCmd();
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
    if (file_exists(__DIR__ . '/../config/devices/' .  $this->getConfiguration('device') . '_' . strtolower($this->getConfiguration('color')) . '.png')) {
      return 'plugins/ajaxSystem/core/config/devices/' .  $this->getConfiguration('device') . '_' . strtolower($this->getConfiguration('color')) . '.png';
    }
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


  public function alreadyInState($_options) {
    $eqLogic = $this->getEqLogic();
    if ($eqLogic->getConfiguration('type') == 'hub') {
      $cmdValue = $this->getCmdValue();
      $value =  $cmdValue->execCmd();
      if ($this->getLogicalId() == 'ARM' && $value == 'ARMED') {
        return true;
      }
      if ($this->getLogicalId() == 'DISARM' && ($value == 'DISARMED_NIGHT_MODE_OFF' || $value == 'DISARMED')) {
        return true;
      }
      if ($this->getLogicalId() == 'NIGHT_MODE' && $value == 'NIGHT_MODE') {
        return true;
      }
      if ($this->getLogicalId() == 'PANIC' || $this->getLogicalId() == 'muteFireDetectors') {
        return false;
      }
    }
    if ($eqLogic->getConfiguration('type') == 'group') {
      return false;
    }
    return parent::alreadyInState();
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
        'command' => $this->getLogicalId(),
        'deviceType' => $eqLogic->getConfiguration('device')
      );
      ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/devices/' . $eqLogic->getLogicalId() . '/command', $command, 'POST');
    } else if ($eqLogic->getConfiguration('type') == 'group') {
      if ($this->getLogicalId() == 'ARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $eqLogic->getLogicalId()  . '/commands/arming', array('command' => 'ARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'DISARM') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $eqLogic->getLogicalId() . '/commands/arming', array('command' => 'DISARM', 'ignoreProblems' => true), 'PUT');
      } else if ($this->getLogicalId() == 'NIGHT_MODE') {
        ajaxSystem::request('/user/{userId}/hubs/' . $eqLogic->getConfiguration('hub_id') . '/groups/' . $eqLogic->getLogicalId() . '/commands/arming', array('command' => 'NIGHT_MODE_ON', 'ignoreProblems' => true), 'PUT');
      }
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}
