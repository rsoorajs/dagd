<?php

function id($a) {
  return $a;
}

function tag(
  $name,
  $body = null,
  array $attributes = array(),
  $cdata = false) {

  return id(new DaGdTag($name, $body, $attributes, $cdata));
}

function class_repr($obj) {
  if (is_string($obj)) {
    return 'string';
  }

  // PHP 8 throws a TypeError when get_class() is called with a non-object.
  return is_object($obj) ? get_class($obj) : 'non-object';
}

/**
 * Return true if the key exists in config, false otherwise.
 */
function config_key_exists($key) {
  return array_key_exists($key, DaGdConfig::$config);
}
