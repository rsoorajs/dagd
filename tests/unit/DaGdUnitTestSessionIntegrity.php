<?php

final class DaGdUnitTestSessionIntegrity extends DaGdUnitTest {
  public function runUnits() {
    $this->path = 'DaGdUnitTestSessionIntegrity';
    $config = DaGdConfig::$config;
    try {
      DaGdConfig::$config['session.encryption_key'] = 'session integrity test key';
      // Existing deployment configuration must not enable unauthenticated CBC.
      DaGdConfig::$config['session.encryption_method'] = 'aes-256-cbc';
      $this->checkIntegrity();
    } finally {
      DaGdConfig::$config = $config;
    }
  }

  private function checkIntegrity() {
    $session = id(new DaGdSession())->set('darkmode', 'true');
    $cookie = implode('', $session->emit());
    $parts = explode('.', $cookie);
    $this->assertTrue(
      count($parts) === 4 && $parts[0] === 'v1',
      'legacy cipher configuration still emits authenticated cookies');
    if (count($parts) !== 4) {
      return;
    }

    $next = explode('.', implode('', $session->emit()));
    $this->assertTrue($parts[1] !== $next[1], 'each emission uses a fresh IV');

    // Flip a bit in each authenticated component, preserving its encoding.
    foreach (array(1 => 'IV', 2 => 'tag', 3 => 'ciphertext') as $i => $name) {
      $changed = $parts;
      $raw = $i === 3 ? base64_decode($parts[$i]) : hex2bin($parts[$i]);
      $raw[0] = chr(ord($raw[0]) ^ 1);
      $changed[$i] = $i === 3 ? base64_encode($raw) : bin2hex($raw);
      $this->assertRejected(implode('.', $changed), 'modified '.$name);
    }

    // GCM must not accept even a correct prefix of the original tag.
    for ($length = 0; $length < 16; $length++) {
      $changed = $parts;
      $changed[2] = substr($parts[2], 0, $length * 2);
      $this->assertRejected(implode('.', $changed), 'tag length '.$length);
    }

    $invalid = array(
      '',
      'garbage',
      'v2.'.$parts[1].'.'.$parts[2].'.'.$parts[3],
      $cookie.'.extra',
      'v1..'.$parts[2].'.'.$parts[3],
      'v1.'.substr($parts[1], 2).'.'.$parts[2].'.'.$parts[3],
      'v1.'.$parts[1].'00.'.$parts[2].'.'.$parts[3],
      'v1.'.str_repeat('z', 24).'.'.$parts[2].'.'.$parts[3],
      'v1.'.$parts[1].'.'.str_repeat('z', 32).'.'.$parts[3],
      'v1.'.$parts[1].'.'.$parts[2].'00.'.$parts[3],
      'v1.'.$parts[1].'.'.$parts[2].'.',
      'v1.'.$parts[1].'.'.$parts[2].'.!'.$parts[3],
      'v1.'.$parts[1].'.'.$parts[2].'.'.base64_encode(
        substr(base64_decode($parts[3]), 0, -1)),
    );
    foreach ($invalid as $i => $value) {
      $this->assertRejected($value, 'malformed cookie '.$i);
    }

    $key = DaGdConfig::$config['session.encryption_key'];
    DaGdConfig::$config['session.encryption_key'] = 'different session test key';
    $this->assertRejected($cookie, 'wrong encryption key');
    DaGdConfig::$config['session.encryption_key'] = $key;

    // Reproduce the reported CBC attack: change darkmode to Darkmode via IV.
    $plaintext = serialize(array('darkmode' => 'true'));
    $iv = random_bytes(16);
    $legacy = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
    $this->assertRejected(bin2hex($iv).'.'.$legacy, 'legacy CBC cookie');
    $offset = strpos($plaintext, 'darkmode');
    $iv[$offset] = chr(ord($iv[$offset]) ^ ord('d') ^ ord('D'));
    $this->assertTrue(
      openssl_decrypt($legacy, 'aes-256-cbc', $key, 0, $iv) ===
        serialize(array('Darkmode' => 'true')),
      'CBC fixture demonstrates predictable session key modification');
    $this->assertRejected(bin2hex($iv).'.'.$legacy, 'bit-flipped legacy CBC');

    // Exercise chunk assembly and reissuance through the real request API.
    $values = array('enabled' => false, 'count' => 42, 'nested' => array(null));
    $large = str_repeat('session data ', 700);
    $session->set('values', $values)->set('large', $large);
    $cookies = $session->emit();
    $this->assertTrue(count($cookies) > 1, 'large session spans cookies');
    $request = id(new DaGdRequest())->setCookies(array_reverse($cookies, true));
    $loaded = $request->getSession();
    $this->assertTrue(
      $loaded->get('large') === $large && $loaded->get('values') === $values,
      'multiple cookie chunks and typed values round-trip');
    unset($cookies['DaGdSession_1']);
    $request->setCookies($cookies);
    $this->assertTrue(
      $request->getSession()->get('large') === null,
      'missing cookie chunk invalidates entire session');
    $request->setCookies($request->getSession()->emit());
    $this->assertTrue(
      $request->getSession()->get('darkmode') === null,
      'reissued rejected session stays empty');
    $request->getSession()->set('fresh', 'value');
    $request->setCookies($request->getSession()->emit());
    $this->assertTrue(
      $request->getSession()->get('fresh') === 'value',
      'session works normally after rejection');
  }

  private function assertRejected($cookie, $description) {
    // Invalid client input must clear existing state without emitting warnings.
    set_error_handler(function($severity, $message, $file, $line) {
      throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
      $session = id(new DaGdSession())->set('stale', 'value');
      $session->loadFromCookies($cookie);
      $this->assertTrue(
        $session->get('stale') === null &&
        $session->get('darkmode') === null &&
        $session->get('Darkmode') === null,
        $description.' resets session');
    } finally {
      restore_error_handler();
    }
  }
}
