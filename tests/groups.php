<?php
// Standalone regression tests: php tests/groups.php. No Jeedom or Ajax account required.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

$testRoot = sys_get_temp_dir() . '/ajaxSystem-group-tests-' . getmypid();
mkdir($testRoot . '/plugins/ajaxSystem/core/class', 0700, true);
mkdir($testRoot . '/plugins/ajaxSystem/core/php', 0700, true);
mkdir($testRoot . '/core/php', 0700, true);
file_put_contents($testRoot . '/core/php/core.inc.php', '<?php');
copy(__DIR__ . '/../core/class/ajaxSystem.class.php', $testRoot . '/plugins/ajaxSystem/core/class/ajaxSystem.class.php');

class eqLogic {
  public static $objects = array();
  private $id;
  private $logicalId;
  private $configuration = array();
  public $values = array();
  public function getId() { return $this->id; }
  public function getLogicalId() { return $this->logicalId; }
  public function setLogicalId($value) { $this->logicalId = $value; }
  public function getConfiguration($key, $default = '') { return isset($this->configuration[$key]) ? $this->configuration[$key] : $default; }
  public function setConfiguration($key, $value) { $this->configuration[$key] = $value; }
  public function setEqType_name($value) {}
  public function setIsEnable($value) {}
  public function setName($value) {}
  public function setCategory($key, $value) {}
  public function setIsVisible($value) {}
  public function save() {
    if ($this->id === null) { $this->id = count(self::$objects) + 1; }
    self::$objects[$this->id] = $this;
  }
  public static function byLogicalId($id, $type) {
    foreach (self::$objects as $object) {
      if ($object->getLogicalId() === $id) { return $object; }
    }
    return null;
  }
  public function getCmd($type = null) { return array(new cmd($this, 'state')); }
  public function checkAndUpdateCmd($key, $value) {
    $this->values[is_object($key) ? $key->getLogicalId() : $key] = $value;
  }
}
class cmd {
  private $eqLogic;
  private $logicalId;
  public function __construct($eqLogic, $logicalId) { $this->eqLogic = $eqLogic; $this->logicalId = $logicalId; }
  public function getEqLogic() { return $this->eqLogic; }
  public function getLogicalId() { return $this->logicalId; }
}
class config {
  public static function byKey($key, $plugin = 'core', $default = '', $fresh = false) { return 'fixture'; }
}
class cache {
  public static function byKey($key) { return new self(); }
  public function getValue($default = '') { return 'session-fixture'; }
}
class jeedom {
  public static function getTmpFolder($plugin) { global $testRoot; return $testRoot; }
  public static function apiAccess($key, $plugin) { return $key === 'fixture'; }
}
class log {
  public static $entries = array();
  public static function add($plugin, $level, $message) { self::$entries[] = array($level, $message); }
}
class com_http {
  public static $responses = array();
  public static $rawResponses = array();
  public static $requests = array();
  private $path;
  private $method = 'GET';
  private $body;
  public function __construct($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $this->path = $query['path'];
  }
  public function setLogError($value) {}
  public function setHeader($value) {}
  public function setPut($value) { $this->method = 'PUT'; $this->body = json_decode($value, true); }
  public function exec($timeout, $retries) {
    self::$requests[] = array($this->path, $this->method, $this->body);
    if (array_key_exists($this->path, self::$rawResponses)) { return self::$rawResponses[$this->path]; }
    if (!array_key_exists($this->path, self::$responses)) { throw new Exception('Unexpected API path: ' . $this->path); }
    return json_encode(self::$responses[$this->path]);
  }
}
function __($message, $file = null) { return $message; }
function sha512($value) { return hash('sha512', $value); }
if (!function_exists('mb_strtolower')) {
  function mb_strtolower($value) { return strtolower($value); }
}
function is_json($value, $default) { return $default; }
function init($key) { return ''; }
require $testRoot . '/plugins/ajaxSystem/core/class/ajaxSystem.class.php';

function check($condition, $message) {
  if (!$condition) { throw new Exception($message); }
}
function equipment($logicalId, $type, $hubId = '') {
  $eqLogic = new ajaxSystem();
  $eqLogic->setLogicalId($logicalId);
  $eqLogic->setConfiguration('type', $type);
  $eqLogic->setConfiguration('hub_id', $hubId);
  $eqLogic->save();
  return $eqLogic;
}
function resetFixtures() {
  eqLogic::$objects = array();
  log::$entries = array();
  com_http::$requests = array();
  com_http::$rawResponses = array();
  com_http::$responses = array('/user/fixture/hubs' => array(array('hubId' => 'HUBA'), array('hubId' => 'HUBB')));
  foreach (array('HUBA', 'HUBB') as $hubId) {
    $path = '/user/fixture/hubs/' . $hubId;
    com_http::$responses[$path] = array('name' => $hubId, 'color' => 'BLACK', 'hubSubtype' => 'HUB', 'ethernet' => array('ip' => ''), 'firmware' => array('version' => '1'));
    com_http::$responses[$path . '/devices'] = array();
    com_http::$responses[$path . '/groups'] = array(array('id' => '00000001', 'groupName' => $hubId . ' group', 'state' => 'DISARMED'));
    com_http::$responses[$path . '/groups/00000001/commands/arming'] = array();
  }
}

$tests = array();
$tests['distinct groups across hubs and repeat synchronization'] = function () {
  resetFixtures();
  ajaxSystem::sync();
  $a = eqLogic::byLogicalId('HUBA_00000001', 'ajaxSystem');
  $b = eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem');
  check(is_object($a) && is_object($b) && $a !== $b, 'Same group id must produce two distinct equipments');
  ajaxSystem::sync();
  check(count(eqLogic::$objects) === 4, 'Repeated synchronization must preserve equipment ids');
};
$tests['legacy group migrates only with its own hub'] = function () {
  resetFixtures();
  $legacy = equipment('00000001', 'group', 'HUBB');
  ajaxSystem::sync();
  check(eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem') === $legacy, 'Migration must preserve the original hub and equipment id');
  check(eqLogic::byLogicalId('HUBA_00000001', 'ajaxSystem') !== $legacy, 'The first hub must not take another hub\'s legacy equipment');
};
$tests['group refresh and commands use the hub and raw group id'] = function () {
  resetFixtures();
  ajaxSystem::sync();
  $group = eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem');
  com_http::$responses['/user/fixture/hubs/HUBB/groups'][0]['state'] = 'ARMED';
  $group->refreshData();
  check($group->values['state'] === 'ARMED', 'Refresh must fetch this hub\'s group');
  foreach (array('ARM', 'DISARM', 'NIGHT_MODE') as $command) {
    (new ajaxSystemCmd($group, $command))->execute();
    $request = end(com_http::$requests);
    check($request[0] === '/user/fixture/hubs/HUBB/groups/00000001/commands/arming' && $request[1] === 'PUT', 'Commands must never send the prefixed Jeedom logical id');
  }
};
$tests['invalid group response is logged without deleting existing groups'] = function () {
  resetFixtures();
  $group = equipment('HUBB_00000001', 'group', 'HUBB');
  com_http::$responses['/user/fixture/hubs/HUBB/groups'] = false;
  ajaxSystem::sync();
  check(eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem') === $group, 'Invalid response must preserve existing groups');
  check(count(array_filter(log::$entries, function ($entry) { return $entry[0] === 'warning' && strpos($entry[1], 'HUBB') !== false; })) === 1, 'Invalid response must identify the hub in a warning');
};
$tests['callbacks route prefixed and legacy groups to the correct hub'] = function () {
  global $testRoot;
  resetFixtures();
  $a = equipment('HUBA_00000001', 'group', 'HUBA');
  $b = equipment('HUBB_00000001', 'group', 'HUBB');
  $legacy = equipment('00000002', 'group', 'HUBB');
  $device = equipment('DEVICE', 'device', 'HUBA');
  $updates = array(
    array('type' => 'GROUP', 'hubId' => 'HUBA', 'id' => '00000001', 'updates' => array('state' => 1)),
    array('type' => 'Group', 'hubId' => 'HUBB', 'id' => '00000001', 'updates' => array('state' => 0)),
    array('type' => 'GROUP', 'hubId' => 'HUBB', 'id' => '00000002', 'updates' => array('state' => 2)),
    array('type' => 'GROUP', 'hubId' => 'HUBA', 'id' => '00000002', 'updates' => array('state' => 1)),
    array('type' => 'GROUP', 'id' => '00000002', 'updates' => array('state' => 1)),
    array('type' => 'DEVICE', 'hubId' => 'HUBA', 'id' => 'DEVICE', 'updates' => array('state' => 7))
  );
  $input = $testRoot . '/callback.json';
  file_put_contents($input, json_encode(array('apikey' => 'fixture', 'data' => $updates)));
  // Execute the actual callback, substituting only its HTTP input stream with a fixture file.
  $callback = file_get_contents(__DIR__ . '/../core/php/jeeAjaxSystem.php');
  $callback = str_replace("file_get_contents('php://input')", 'file_get_contents(' . var_export($input, true) . ')', $callback);
  file_put_contents($testRoot . '/plugins/ajaxSystem/core/php/jeeAjaxSystem.php', $callback);
  include $testRoot . '/plugins/ajaxSystem/core/php/jeeAjaxSystem.php';
  check(isset($a->values['state']) && $a->values['state'] === 'ARMED', 'Hub A group callback must be applied');
  check(isset($b->values['state']) && $b->values['state'] === 'DISARMED', 'Hub B group callback must be applied independently');
  check($legacy->values['state'] === 'NIGHT_MODE', 'Legacy group must accept only its own hub\'s callbacks');
  check($device->values['state'] === 7, 'Device callbacks must keep working');
};
function syncError() {
  try { ajaxSystem::sync(); } catch (Exception $e) { return $e->getMessage(); }
  return null;
}
function logged($level, $text) {
  return count(array_filter(log::$entries, function ($entry) use ($level, $text) { return $entry[0] === $level && strpos($entry[1], $text) !== false; }));
}
$tests['unreadable device does not stop the synchronization'] = function () {
  resetFixtures();
  com_http::$responses['/user/fixture/hubs/HUBA/devices'] = array(array('id' => 'BROKEN', 'deviceName' => 'Fuite'), array('id' => 'GOOD', 'deviceName' => 'Porte'));
  com_http::$rawResponses['/user/fixture/hubs/HUBA/devices/BROKEN'] = '<html>Bad gateway</html>';
  com_http::$responses['/user/fixture/hubs/HUBA/devices/GOOD'] = array('deviceName' => 'Porte', 'color' => 'WHITE', 'deviceType' => 'DoorProtect', 'firmwareVersion' => '1');
  $error = syncError();
  check(is_object(eqLogic::byLogicalId('GOOD', 'ajaxSystem')), 'Devices after an unreadable one must still be synchronized');
  check(!is_object(eqLogic::byLogicalId('BROKEN', 'ajaxSystem')), 'An unreadable device must not be created');
  check(is_object(eqLogic::byLogicalId('HUBA_00000001', 'ajaxSystem')) && is_object(eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem')), 'Groups of every hub must still be synchronized');
  check(logged('warning', 'BROKEN (Fuite)') === 1, 'The unreadable device must be identified in a warning');
  check(logged('debug', '<html>Bad gateway</html>') === 1, 'The start of the invalid response must be logged');
  check($error !== null && strpos($error, '1') !== false, 'The synchronization must still report its error count');
};
$tests['failure of one hub does not stop the other hubs'] = function () {
  resetFixtures();
  com_http::$rawResponses['/user/fixture/hubs/HUBA/groups'] = 'not json';
  $error = syncError();
  check(is_object(eqLogic::byLogicalId('HUBB_00000001', 'ajaxSystem')), 'The next hub must still be synchronized');
  check(logged('warning', 'HUBA') === 1, 'The failing hub must be identified in a warning');
  check($error !== null, 'The synchronization must report the failure');
};

$results = array();
$failed = 0;
foreach ($tests as $name => $test) {
  try { $test(); $results[] = 'PASS ' . $name; } catch (Throwable $error) { $failed++; $results[] = 'FAIL ' . $name . ': ' . $error->getMessage(); }
}
function removeTestDirectory($path) {
  foreach (scandir($path) as $entry) {
    if ($entry === '.' || $entry === '..') { continue; }
    $child = $path . '/' . $entry;
    if (is_dir($child)) { removeTestDirectory($child); } else { unlink($child); }
  }
  rmdir($path);
}
removeTestDirectory($testRoot);
echo implode("\n", $results) . "\n" . count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed === 0 ? 0 : 1);
