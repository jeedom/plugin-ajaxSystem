<?php
// Standalone regression tests: no Jeedom installation or Ajax credentials are required.
// Run with: php tests/session.php (pcntl is required for the concurrency tests).

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

$testRoot = sys_get_temp_dir() . '/ajaxSystem-session-tests-' . getmypid();
mkdir($testRoot . '/plugins/ajaxSystem/core/class', 0700, true);
mkdir($testRoot . '/core/php', 0700, true);
file_put_contents($testRoot . '/core/php/core.inc.php', '<?php');
copy(__DIR__ . '/../core/class/ajaxSystem.class.php', $testRoot . '/plugins/ajaxSystem/core/class/ajaxSystem.class.php');

function state($callback) {
  global $testRoot;
  $file = fopen($testRoot . '/state.json', 'c+');
  flock($file, LOCK_EX);
  try {
    $content = stream_get_contents($file);
    $data = $content === '' ? array() : json_decode($content, true);
    $result = $callback($data);
    rewind($file);
    ftruncate($file, 0);
    fwrite($file, json_encode($data));
    return $result;
  } finally {
    flock($file, LOCK_UN);
    fclose($file);
  }
}

class config {
  public static $local = array();
  public static function byKey($key, $plugin = 'core', $default = '', $fresh = false) {
    $key = $plugin . '::' . $key;
    if (!$fresh && array_key_exists($key, self::$local)) {
      return self::$local[$key];
    }
    return self::$local[$key] = state(function (&$data) use ($key, $default) {
      return isset($data['config'][$key]) ? $data['config'][$key] : $default;
    });
  }
  public static function save($key, $value, $plugin = 'core') {
    $key = $plugin . '::' . $key;
    unset(self::$local[$key]);
    state(function (&$data) use ($key, $value) { $data['config'][$key] = $value; });
  }
}

class cache {
  private $key;
  public static function byKey($key) {
    $entry = new self();
    $entry->key = $key;
    return $entry;
  }
  public function getValue($default = '') {
    return state(function (&$data) use ($default) {
      return isset($data['cache'][$this->key]) ? $data['cache'][$this->key]['value'] : $default;
    });
  }
  public static function set($key, $value, $ttl) {
    state(function (&$data) use ($key, $value, $ttl) { $data['cache'][$key] = array('value' => $value, 'ttl' => $ttl); });
  }
  public static function delete($key) {
    state(function (&$data) use ($key) { unset($data['cache'][$key]); });
  }
}

class jeedom {
  public static function getTmpFolder($plugin) { global $testRoot; return $testRoot; }
  public static function getApiKey($plugin) { return 'plugin-api-secret'; }
}
class network {
  public static function getNetworkAccess($type) { return 'https://jeedom.example'; }
}
class log {
  public static $entries = array();
  public static function add($plugin, $level, $message) { self::$entries[] = $message; }
}
class eqLogic {}
class cmd {}
function __($message, $file = null) { return $message; }
function sha512($value) { return hash('sha512', $value); }
if (!function_exists('mb_strtolower')) {
  function mb_strtolower($value) { return strtolower($value); }
}
function is_json($value, $default) {
  if (!is_string($value)) { return $default; }
  $decoded = json_decode($value, true);
  return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

class com_http {
  public static $handler;
  public $url;
  public $path;
  public $query;
  public $body;
  public $method = 'GET';
  public $logError = true;
  public function __construct($url) {
    $this->url = $url;
    parse_str(parse_url($url, PHP_URL_QUERY), $this->query);
    $this->path = $this->query['path'];
  }
  public function setLogError($value) { $this->logError = $value; }
  public function setHeader($value) {}
  public function setPost($value) { $this->body = json_decode($value, true); $this->method = 'POST'; }
  public function setPut($value) { $this->body = json_decode($value, true); $this->method = 'PUT'; }
  public function exec($timeout, $retries) {
    state(function (&$data) use ($retries) {
      $data['requests'][] = array('path' => $this->path, 'body' => $this->body, 'query' => $this->query, 'method' => $this->method, 'retries' => $retries);
    });
    return call_user_func(self::$handler, $this, $retries);
  }
}

require $testRoot . '/plugins/ajaxSystem/core/class/ajaxSystem.class.php';

function check($condition, $message) {
  if (!$condition) { throw new Exception($message); }
}
function equal($expected, $actual, $message) {
  check($expected === $actual, $message . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}
function expectError($callback, $contains) {
  try { $callback(); } catch (Exception $error) {
    check(strpos($error->getMessage(), $contains) !== false, 'Unexpected error: ' . $error->getMessage());
    return $error->getMessage();
  }
  throw new Exception('Expected an error containing ' . $contains);
}
function snapshot() { return state(function (&$data) { return $data; }); }
function resetSession($cached = 'session-old') {
  config::$local = array();
  log::$entries = array();
  state(function (&$data) {
    $data = array('config' => array('core::service::cloud::url' => 'https://cloud.example', 'core::market::username' => 'test', 'core::market::password' => 'market-secret', 'ajaxSystem::userId' => 'user-old', 'ajaxSystem::refreshToken' => 'refresh-old'), 'cache' => array(), 'requests' => array());
  });
  if ($cached !== null) { cache::set('ajaxSystem::sessionToken', $cached, 840); }
  com_http::$handler = function ($request) {
    if ($request->path === '/refresh') {
      equal('refresh-old', $request->body['refreshToken'], 'Refresh must use the latest persistent token');
      return json_encode(array('sessionToken' => 'session-new', 'refreshToken' => 'refresh-new'));
    }
    return json_encode(array('body' => array('success' => true)));
  };
}
function requestsFor($path) {
  return array_values(array_filter(snapshot()['requests'], function ($request) use ($path) { return $request['path'] === $path; }));
}
function workers($callbacks) {
  $children = array();
  foreach ($callbacks as $callback) {
    $pid = pcntl_fork();
    check($pid >= 0, 'Cannot fork test worker');
    if ($pid === 0) {
      try { $callback(); exit(0); } catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
    }
    $children[] = $pid;
  }
  foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'Test worker failed');
  }
}

$tests = array();
$tests['cached session'] = function () {
  resetSession();
  equal(array('success' => true), ajaxSystem::request('/user/{userId}/hubs'), 'Request result');
  equal(0, count(requestsFor('/refresh')), 'Cached session must not refresh');
  equal('session-old', snapshot()['requests'][0]['query']['session_token'], 'Cached token');
};
$tests['refresh after cache expiry and reload stale configuration'] = function () {
  resetSession(null);
  config::byKey('refreshToken', 'ajaxSystem');
  config::byKey('userId', 'ajaxSystem');
  state(function (&$data) { $data['config']['ajaxSystem::refreshToken'] = 'refresh-latest'; $data['config']['ajaxSystem::userId'] = 'user-latest'; });
  com_http::$handler = function ($request) {
    if ($request->path === '/refresh') {
      equal('refresh-latest', $request->body['refreshToken'], 'Do not reuse process-local configuration');
      equal('user-latest', $request->body['userId'], 'Reload user id');
      return json_encode(array('sessionToken' => 'session-new', 'refreshToken' => 'refresh-new'));
    }
    equal('/user/user-latest/hubs', $request->path, 'User and token snapshot');
    equal('session-new', $request->query['session_token'], 'Refreshed session');
    return '{}';
  };
  ajaxSystem::request('/user/{userId}/hubs');
  equal('refresh-new', snapshot()['config']['ajaxSystem::refreshToken'], 'Save rotated refresh token');
  equal(840, snapshot()['cache']['ajaxSystem::sessionToken']['ttl'], 'Session cache lifetime');
};
$tests['retry an explicit session rejection once'] = function () {
  resetSession();
  com_http::$handler = function ($request) {
    if ($request->path === '/refresh') { return json_encode(array('sessionToken' => 'session-new', 'refreshToken' => 'refresh-new')); }
    return $request->query['session_token'] === 'session-old' ? json_encode(array('state' => 'nok', 'error' => 'Can no post Ajax server : Wrong session token')) : '{"success":true}';
  };
  equal(array('success' => true), ajaxSystem::request('/command', array('command' => 'SWITCH_ON'), 'POST'), 'Retry with new token');
  equal(1, count(requestsFor('/refresh')), 'Only one renewal');
  equal(2, count(requestsFor('/command')), 'Only one command retry');
};
$tests['reuse a session renewed while the first request was in flight'] = function () {
  resetSession();
  com_http::$handler = function ($request) {
    if ($request->query['session_token'] === 'session-old') {
      cache::set('ajaxSystem::sessionToken', 'session-other-worker', 840);
      config::save('userId', 'user-other-worker', 'ajaxSystem');
      return '{"error":"User is not authorized"}';
    }
    equal('/user/user-other-worker/hubs', $request->path, 'Rebuild path after concurrent login');
    equal('session-other-worker', $request->query['session_token'], 'Reuse concurrent renewal');
    return '{}';
  };
  ajaxSystem::request('/user/{userId}/hubs');
  equal(0, count(requestsFor('/refresh')), 'Do not rotate a fresh session again');
};
$tests['bound repeated authentication failures'] = function () {
  resetSession();
  com_http::$handler = function ($request) {
    return $request->path === '/refresh' ? '{"sessionToken":"session-new","refreshToken":"refresh-new"}' : '{"state":"nok","code":401,"error":"Unauthorized"}';
  };
  expectError(function () { ajaxSystem::request('/command', array(), 'PUT'); }, 'Unauthorized');
  equal(1, count(requestsFor('/refresh')), 'Renew only once');
  equal(2, count(requestsFor('/command')), 'Stop after second rejection');
};
$tests['do not replay validation errors or opaque PUT errors'] = function () {
  foreach (array('Validation error', 'Can no put Ajax server', 'Forbidden', 'Rate limit exceeded') as $message) {
    resetSession();
    com_http::$handler = function () use ($message) { return json_encode(array('error' => $message)); };
    expectError(function () { ajaxSystem::request('/command', array('command' => 'SWITCH_ON'), 'PUT'); }, $message);
    equal(1, count(requestsFor('/command')), 'Do not replay a non-authentication failure');
    equal(0, count(requestsFor('/refresh')), 'Do not rotate on a non-authentication failure');
  }
};
$tests['revoked refresh token and lock release after failure'] = function () {
  resetSession(null);
  com_http::$handler = function () { return '{"error":"Wrong session refresh token"}'; };
  expectError(function () { ajaxSystem::refreshToken(); }, 'Reconnectez-vous');
  equal(1, count(requestsFor('/refresh')), 'Do not repeat rejected refresh');
  equal('refresh-old', snapshot()['config']['ajaxSystem::refreshToken'], 'Preserve credentials on failure');
  com_http::$handler = function () { return '{"userId":"user-login","sessionToken":"session-login","refreshToken":"refresh-login"}'; };
  ajaxSystem::login('test@example.com', 'password-secret');
  equal('refresh-login', snapshot()['config']['ajaxSystem::refreshToken'], 'Subsequent login can acquire the lock');
};
$tests['missing credentials do not call the cloud'] = function () {
  resetSession(null);
  config::save('refreshToken', '', 'ajaxSystem');
  expectError(function () { ajaxSystem::refreshToken(); }, 'Reconnectez-vous');
  equal(0, count(snapshot()['requests']), 'Do not submit incomplete refresh data');
};
$tests['incomplete authentication responses cannot overwrite valid tokens'] = function () {
  foreach (array('null', '{}', '{"sessionToken":"session-partial"}', '{"sessionToken":12,"refreshToken":"refresh-partial"}') as $response) {
    foreach (array('login', 'refresh') as $operation) {
      resetSession($operation === 'login' ? 'session-old' : null);
      com_http::$handler = function () use ($response) { return $response; };
      expectError(function () use ($operation) {
        if ($operation === 'login') { ajaxSystem::login('test', 'password-secret'); } else { ajaxSystem::refreshToken(); }
      }, 'Réponse Ajax incomplète');
      equal('refresh-old', snapshot()['config']['ajaxSystem::refreshToken'], 'Do not overwrite refresh token');
      if ($operation === 'login') {
        equal('session-old', cache::byKey('ajaxSystem::sessionToken')->getValue(), 'Failed login must preserve the existing session');
      }
      equal(1, count(snapshot()['requests']), 'Do not retry an incomplete auth response');
    }
  }
};
$tests['do not replay ambiguous transport failures and redact secrets'] = function () {
  foreach (array('POST', 'PUT') as $method) {
    resetSession('session/with+special=chars');
    com_http::$handler = function ($request, $retries) {
      equal(1, $retries, 'Disable transport retries for writes');
      check(!$request->logError, 'Native HTTP log must not expose URL tokens');
      throw new Exception('cURL timeout for ' . $request->url);
    };
    $message = expectError(function () use ($method) { ajaxSystem::request('/command', array('command' => 'SWITCH_ON'), $method); }, 'timeout');
    check(strpos($message, 'session/with') === false && strpos($message, 'session%2Fwith') === false, 'Redact encoded and raw session tokens');
    equal(1, count(snapshot()['requests']), 'Do not replay an ambiguous write');
  }
  resetSession(null);
  com_http::$handler = function ($request, $retries) { equal(1, $retries, 'Single refresh transport attempt'); throw new Exception('Lost refresh response'); };
  expectError(function () { ajaxSystem::refreshToken(); }, 'Lost refresh response');
  equal(1, count(requestsFor('/refresh')), 'Never repeat an ambiguous refresh');
};
$tests['redact debug logs and structured errors'] = function () {
  resetSession();
  com_http::$handler = function () { return '{"userId":"user-login","sessionToken":"session-login-secret","refreshToken":"refresh-login-secret"}'; };
  ajaxSystem::login('test@example.com', 'password-secret');
  $logs = implode("\n", log::$entries);
  foreach (array('password-secret', 'plugin-api-secret', 'session-login-secret', 'refresh-login-secret') as $secret) {
    check(strpos($logs, $secret) === false, 'Secret leaked in debug log');
  }
  com_http::$handler = function () { return '{"error":"Validation error","details":{"refreshToken":"secret-in-error"}}'; };
  $message = expectError(function () { ajaxSystem::request('/command', array(), 'POST'); }, 'Validation error');
  check(strpos($message, 'secret-in-error') === false, 'Secret leaked in exception');
  check(strpos($message, '/command') !== false, 'Include endpoint in error');
};
$tests['malformed JSON fails without losing the persistent refresh token'] = function () {
  resetSession(null);
  com_http::$handler = function () { return '<html>Bad gateway</html>'; };
  expectError(function () { ajaxSystem::refreshToken(); }, 'Réponse JSON invalide');
  equal('refresh-old', snapshot()['config']['ajaxSystem::refreshToken'], 'Preserve token on invalid JSON');
};
$tests['retain empty and double-encoded response compatibility'] = function () {
  resetSession();
  com_http::$handler = function () { return ''; };
  equal(null, ajaxSystem::request('/command', array(), 'POST'), 'Successful empty command response');
  com_http::$handler = function () { return json_encode('{"body":{"success":true}}'); };
  equal(array('success' => true), ajaxSystem::request('/data'), 'Decode legacy double-encoded JSON');
};
$tests['parallel cache misses produce a single renewal'] = function () {
  resetSession(null);
  com_http::$handler = function ($request) {
    if ($request->path === '/refresh') {
      usleep(200000);
      equal('refresh-old', $request->body['refreshToken'], 'Only the first worker consumes refresh token');
      return '{"sessionToken":"session-new","refreshToken":"refresh-new"}';
    }
    equal('session-new', $request->query['session_token'], 'All workers use renewed token');
    return '{}';
  };
  workers(array_fill(0, 8, function () { ajaxSystem::request('/command', array(), 'POST'); }));
  equal(1, count(requestsFor('/refresh')), 'Exactly one refresh across eight PHP processes');
  equal(8, count(requestsFor('/command')), 'All commands proceed');
};
$tests['parallel rejections share a single renewal'] = function () {
  resetSession();
  com_http::$handler = function ($request) {
    if ($request->path === '/refresh') {
      usleep(100000);
      equal('refresh-old', $request->body['refreshToken'], 'Do not consume the refresh token twice');
      return '{"sessionToken":"session-new","refreshToken":"refresh-new"}';
    }
    if ($request->query['session_token'] === 'session-old') {
      $deadline = microtime(true) + 5;
      while (count(requestsFor('/command')) < 8 && microtime(true) < $deadline) { usleep(10000); }
      check(count(requestsFor('/command')) >= 8, 'All requests must reach the rejection barrier');
      return '{"error":"Wrong session token"}';
    }
    equal('session-new', $request->query['session_token'], 'Reuse the new session after rejection');
    return '{}';
  };
  workers(array_fill(0, 8, function () { ajaxSystem::request('/command', array(), 'POST'); }));
  equal(1, count(requestsFor('/refresh')), 'Eight rejected sessions share one refresh');
  equal(16, count(requestsFor('/command')), 'Each rejected request is retried exactly once');
};
$tests['login shares the renewal lock'] = function () {
  resetSession(null);
  global $testRoot;
  $started = $testRoot . '/login-started';
  com_http::$handler = function ($request) use ($started) {
    if ($request->path === '/login') {
      file_put_contents($started, 'started');
      usleep(300000);
      return '{"userId":"user-login","sessionToken":"session-login","refreshToken":"refresh-login"}';
    }
    check($request->path !== '/refresh', 'Waiting worker must reuse completed login');
    equal('session-login', $request->query['session_token'], 'Reuse login token');
    equal('/user/user-login/hubs', $request->path, 'Reuse login user id');
    return '{}';
  };
  workers(array(function () { ajaxSystem::login('test', 'password-secret'); }, function () use ($started) {
    $deadline = microtime(true) + 5;
    while (!file_exists($started) && microtime(true) < $deadline) { usleep(10000); }
    check(file_exists($started), 'Login worker did not start');
    ajaxSystem::request('/user/{userId}/hubs');
  }));
  equal(0, count(requestsFor('/refresh')), 'No concurrent refresh during login');
};

$failed = 0;
foreach ($tests as $name => $test) {
  try { $test(); echo 'PASS ' . $name . "\n"; } catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n"); }
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
echo count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed === 0 ? 0 : 1);
