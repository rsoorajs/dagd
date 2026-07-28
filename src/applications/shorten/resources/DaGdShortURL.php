<?php

/**
 * A super simple read-only representation of a short URL.
 *
 * This is just a disguised 4-tuple.
 */
final class DaGdShortURL {
  private $id;
  private $short_url;
  private $long_url;
  private $owner_ip;
  private $enabled;
  private $creation_dt;

  public function __construct(
    $id,
    $short_url,
    $long_url,
    $owner_ip,
    $enabled,
    $creation_dt) {
    $this->id = $id;
    $this->short_url = $short_url;
    $this->long_url = $long_url;
    $this->owner_ip = $owner_ip;
    $this->enabled = $enabled;
    $this->creation_dt = $creation_dt === null ? null : (int)$creation_dt;
  }

  public function getId() {
    return $this->id;
  }

  public function getShortUrl() {
    return $this->short_url;
  }

  public function getLongUrl() {
    return $this->long_url;
  }

  public function getOwnerIp() {
    return $this->owner_ip;
  }

  public function getEnabled() {
    return $this->enabled;
  }

  public function getCreationDt() {
    return $this->creation_dt;
  }
}
