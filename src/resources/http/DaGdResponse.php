<?php

abstract class DaGdResponse {
  private $code = 200;
  private $message;
  private $headers = array();
  private $body = '';

  public function setCode($code) {
    $this->code = $code;
    return $this;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function setHeaders($headers) {
    $this->headers = $headers;
    return $this;
  }

  public function setTrailingNewline($trailing_newline) {
    $this->trailing_newline = $trailing_newline;
    return $this;
  }

  public function addHeader($key, $value, $throw_on_existing = false) {
    if ($throw_on_existing && array_key_exists($key, $this->getHeaders())) {
      throw new Exception('Header "'.$key.'" would be redefined.');
    }
    $this->headers[$key] = $value;
    return $this;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  public function getCode() {
    return $this->code;
  }

  public function getMessage() {
    if ($this->message) {
      return $this->message;
    }

    $codes = array(
      100 => 'Continue',
      101 => 'Switching Protocols',
      102 => 'Processing',
      103 => 'Early Hints',
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      207 => 'Multi-Status',
      208 => 'Already Reported',
      226 => 'IM Used',
      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      306 => 'Switch Proxy',
      307 => 'Temporary Redirect',
      308 => 'Permanent Redirect',
      400 => 'Bad Request',
      401 => 'Unauthorized',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Timeout',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Payload Too Large',
      414 => 'URI Too Long',
      415 => 'Unsupported Media Type',
      416 => 'Range Not Satisfiable',
      417 => 'Expectation Failed',
      418 => "I'm a teapot",
      421 => 'Misdirected Request',
      422 => 'Unprocessable Entity',
      423 => 'Locked',
      424 => 'Failed Dependency',
      425 => 'Too Early',
      426 => 'Upgrade Required',
      428 => 'Precondition Required',
      429 => 'Too Many Requests',
      431 => 'Request Header Fields Too Large',
      451 => 'Unavailable For Legal Reasons',
      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout',
      505 => 'HTTP Version Not Supported',
      506 => 'Variant Also Negotiates',
      507 => 'Insufficient Storage',
      508 => 'Loop Detected',
      510 => 'Not Extended',
      511 => 'Network Authentication Required',
    );

    $message = idx($codes, $this->getCode());
    if (!$message) {
      $strcode = (string)$this->getCode();
      throw new Exception(
        'No message given and no default message found for HTTP '.$strcode);
    }
    return $message;
  }

  public function getHeaders() {
    $headers = array();

    // These unfortunately are "k: v" strings
    $global_headers = DaGdConfig::get('general.extra_headers');
    foreach ($global_headers as $header) {
      $header_sp = explode(':', $header, 2);
      if (count($header_sp) != 2) {
        throw new Exception('Parse error in general.extra_headers');
      }
      $key = trim($header_sp[0]);
      $value = trim($header_sp[1]);
      $headers[$key] = $value;
    }

    $headers = array_merge($headers, $this->headers);
    return $headers;
  }

  private function sendHeaders() {
    if ($this->getCode() != 200) {
      header('HTTP/1.1 '.$this->getCode().' '.$this->getMessage());
    }
    foreach ($this->getHeaders() as $key => $value) {
      header($key.': '.$value);
    }
  }

  public function render() {
    $this->sendHeaders();
    echo $this->getBody();
  }
}
