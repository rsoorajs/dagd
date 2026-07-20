<?php

/**
 * This is a drop-in debug replacement for mysqli_stmt. It automatically times
 * execute() and stores the resulting query times.
 */
class DaGdMySQLiStmtDebug extends mysqli_stmt {
  private $query;
  private $milliseconds;

  public function __construct(mysqli $mysqli, $query) {
    $this->query = $query;
    parent::__construct($mysqli, $query);
  }

  public function getQuery() {
    return $this->query;
  }

  public function getMilliseconds() {
    return $this->milliseconds;
  }

  public function execute(?array $params = null): bool {
    $start = microtime(true);
    // mysqli_stmt::execute() gained the optional $params argument in PHP 8.1.
    // Calling the PHP 8.0 implementation with even a null argument is invalid.
    $res = PHP_VERSION_ID >= 80100
      ? parent::execute($params)
      : parent::execute();
    $end = microtime(true);
    $this->milliseconds = ($end - $start) * 1000;
    $this->store_result();
    return $res;
  }
}
