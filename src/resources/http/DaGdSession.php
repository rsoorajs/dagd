<?php

/**
 * A custom "fake session" implementation.
 *
 * Session information is authenticated, encrypted and stored in cookies.
 *
 * We need to ensure we never go over the 4k cookie size limit in modern
 * browsers.
 */
final class DaGdSession {
  private const ENCRYPTION_METHOD = 'aes-256-gcm';
  private const IV_LENGTH = 12;
  private const TAG_LENGTH = 16;

  private $data = array();

  public function loadSession(DaGdRequest $request) {
    $cookies = $request->getCookies();
    $encrypted_data = '';

    // We need to make sure the components get concatenated in order
    $tmp = array();
    foreach ($cookies as $k => $v) {
      if (strpos($k, 'DaGdSession_') === 0) {
        $tmp[$k] = $v;
      }
    }

    // If we have no session data to load, return the empty session
    if (count($tmp) == 0) {
      return $this;
    }

    ksort($tmp);

    foreach ($tmp as $k => $v) {
      $encrypted_data .= $v;
    }

    return $this->loadFromCookies($encrypted_data);
  }

  // This is abstracted out mainly for tests to call into, without having an
  // actual DaGdRequest at their disposal. In normal cases, first-party code
  // should go through loadSession instead.
  public function loadFromCookies($encrypted_data) {
    $parts = explode('.', $encrypted_data);
    // Legacy unauthenticated cookies cannot be safely migrated.
    if (count($parts) != 4 || $parts[0] !== 'v1') {
      return $this->destroy();
    }

    // OpenSSL accepts truncated GCM tags; require the full tag ourselves.
    if (strlen($parts[1]) !== self::IV_LENGTH * 2 ||
        !ctype_xdigit($parts[1]) ||
        strlen($parts[2]) !== self::TAG_LENGTH * 2 ||
        !ctype_xdigit($parts[2])) {
      return $this->destroy();
    }
    $iv = hex2bin($parts[1]);
    $tag = hex2bin($parts[2]);
    $data = base64_decode($parts[3], true);
    if ($data === false || $data === '') {
      return $this->destroy();
    }

    $plaintext = $this->decryptData($data, $iv, $tag);
    if ($plaintext === false) {
      return $this->destroy();
    }
    // Never deserialize data before authenticating it.
    $unser = @unserialize($plaintext);
    if (!is_array($unser)) {
      return $this->destroy();
    }
    $this->data = $unser;
    return $this;
  }

  public function destroy() {
    $this->data = array();
    return $this;
  }

  private function decryptData($str, $iv, $tag) {
    $key = DaGdConfig::get('session.encryption_key');
    if (!$key) {
      throw new Exception('You must set session.encryption_key to use sessions');
    }

    return openssl_decrypt(
      $str, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
  }

  private function encryptData($str) {
    $iv = random_bytes(self::IV_LENGTH);

    $key = DaGdConfig::get('session.encryption_key');
    if (!$key) {
      throw new Exception('You must set session.encryption_key to use sessions');
    }
    $tag = '';
    $data = openssl_encrypt(
      $str, self::ENCRYPTION_METHOD, $key, 0, $iv, $tag, '', self::TAG_LENGTH);
    if ($data === false || strlen($tag) !== self::TAG_LENGTH) {
      throw new Exception('Unable to encrypt session data');
    }
    return array(
      'iv' => bin2hex($iv),
      'tag' => bin2hex($tag),
      'data' => $data,
    );
  }

  public function emit() {
    $serialized = serialize($this->data);
    $data = $this->encryptData($serialized);
    // We use 3500 because the max is 4000 and we want some leeway
    // We still hit the server header limit on requests eventually, though.
    $data_chunks = str_split(
      'v1.'.$data['iv'].'.'.$data['tag'].'.'.$data['data'], 3500);
    $cookies = array();
    $i = 0;
    foreach ($data_chunks as $chunk) {
      $cookies['DaGdSession_'.$i] = $chunk;
      $i++;
    }
    return $cookies;
  }

  public function get($key, $default = null) {
    return idx($this->data, $key, $default);
  }

  public function set($key, $value) {
    $this->data[$key] = $value;
    return $this;
  }

  public function remove($key) {
    unset($this->data[$key]);
    return $this;
  }
}
