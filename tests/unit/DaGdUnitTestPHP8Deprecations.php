<?php

final class DaGdUnitTestPHP8Deprecations extends DaGdUnitTest {
  public function runUnits() {
    $this->path = 'DaGdUnitTestPHP8Deprecations';

    $redirect = id(new DaGdRedirectResponse())
      ->setTo('https://example.com/')
      ->setTrailingNewline(true);

    $this->assertTrue(
      $redirect->getBody() === '',
      'Redirect responses have an empty string body');
    $this->assertTrue(
      $redirect->getHeaders()['Content-Length'] === 0,
      'Redirect responses have a zero Content-Length');

    $safe_browsing = new DaGdGoogleSafeBrowsing(
      'https://example.com/',
      true);
    $this->assertTrue(
      property_exists($safe_browsing, 'is_create'),
      'Safe Browsing create state is a declared property');
    $this->assertTrue(
      property_exists('DaGdCLIWeightedColumnTable', 'terminal_width'),
      'Weighted CLI table terminal width is a declared property');
  }
}
