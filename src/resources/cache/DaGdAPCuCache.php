<?php

/**
 * Lightweight wrapper around APCu that also does stats collection.
 */
final class DaGdAPCuCache extends DaGdCache {
  private $is_enabled = false;
  private $checked_enabled = false;

  const SENTINEL_KEY = '__dagd_cache_key_lock';

  public function getName() {
    return 'APCu';
  }

  public function isEnabled() {
    if (!$this->checked_enabled) {
      $has_extension = extension_loaded('apc') || extension_loaded('apcu');
      $has_config = ini_get('apc.enabled');
      $this->checked_enabled = true;
      $this->is_enabled = $has_extension && $has_config;
    }

    return $this->is_enabled;
  }

  public function getOrStore($key, DaGdCacheMissCallback $cb, $ttl = 0) {
    if ($this->isEnabled() && function_exists('apcu_entry')) {
      // apcu_entry only exists in APCu 5.1+
      //
      // This bypasses get() and set(), so account for the cache operation
      // here. The callback is only invoked when apcu_entry() has a miss.
      $sentinel = array();
      $callback = function($key) use (&$sentinel) {
        statsd_bump('cache_miss');
        $sentinel[self::SENTINEL_KEY] = random_bytes(32);
        return $sentinel;
      };

      statsd_bump('cache_get');
      $result = apcu_entry($key, $callback, $ttl);

      if (is_array($result) && array_key_exists(self::SENTINEL_KEY, $result)) {
        // If we get a sentinel back, we have to do the work so that we can
        // hopefully update the sentinel (assuming we are the one who set it).
        // (If we aren't the one who set it, we still need the work, we just
        // don't update the cache entry for it.)
        $lease = idx($result, self::SENTINEL_KEY);

        try {
          $start = microtime(true);
          $result = $cb->run($key);
          $end = microtime(true);
          statsd_time('cache_compute_time', ($end - $start) * 1000);
        } catch (Throwable $ex) {
          statsd_bump('cache_compute_exception');
          if ($lease === idx($sentinel, self::SENTINEL_KEY)) {
            apcu_delete($key);
          }
          throw $ex;
        }

        // Avoid updating a key we don't have a lease for.
        if ($lease === idx($sentinel, self::SENTINEL_KEY)) {
          statsd_bump('cache_set');
          apcu_store($key, $result, $ttl);
        } else {
          statsd_bump('cache_sentinel_key_conflict');
        }
      } else {
        statsd_bump('cache_hit');
      }

      return $result;
    }
    return parent::getOrStore($key, $cb, $ttl);
  }

  public function set($key, $value, $ttl = 0) {
    if ($this->isEnabled()) {
      parent::set($key, $value, $ttl);
      apcu_store($key, $value, $ttl);
    }
    return $value;
  }

  public function contains($key) {
    if ($this->isEnabled()) {
      return apcu_exists($key);
    }
    return false;
  }

  public function get($key, $default = false) {
    parent::get($key, $default);

    if (!$this->isEnabled()) {
      return $default;
    }

    $res = apcu_fetch($key);

    // Try to be nice. If we get back false, see if it's a "false" that was
    // stored in the cache, or if it means we missed.
    if (!$res) {
      if (!$this->contains($key)) {
        statsd_bump('cache_miss');
        return $default;
      }
    }
    statsd_bump('cache_hit');
    return $res;
  }

  public function flush() {
    parent::flush();

    if (!$this->isEnabled()) {
      return false;
    }

    return apcu_clear_cache();
  }

  public function delete($key) {
    parent::delete($key);

    if (!$this->isEnabled()) {
      return false;
    }

    return apcu_delete($key);
  }
}
