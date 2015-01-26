<?php
/**
 * UrlFetch class
 * 
 * @see https://cloud.google.com/appengine/docs/php/urlfetch
 */
class UrlFetch {
  private $base_url;
  private $auth;
  private $oauth;
  private $timeout;
  private $method;

  public $lastResponse;
  public $headers;

  public function __construct($base_url, $headers = array(), $method = 'GET', $timeout = 60) {
    if (empty($headers)) {
      $headers = $this->getHeadersDefault();
    }

    $this->base_url = $base_url;
    $this->method  = $method;
    $this->headers = $headers;
    $this->timeout = $timeout;
  }

  public function setMethod($method) {
    $this->method = $method;
  }

  private function getHeadersDefault() {
    $headers = array(
      "Content-type"  => "application/json",
      "Accept"        => "application/json",
    );

    return $headers;
  }

  public function setHeader($name, $value) {
    $this->headers[$name] = $value;
  }

  public function getHeaders() {
    return $this->headers;
  }

  public function getHeadersToString() {
    $headers = '';

    foreach ($this->headers as $key => $value) {
      $headers .= "{$key}: {$value}\r\n";
    }
    return $headers;
  }

  public function setBasicAuth($username, $password) {
    $this->auth = array(
      "username" => $username,
      "password" => $password,
    );
  }

  public function setOAuth($consumer_key, $consumer_secret, $token, $token_secret) {
    $this->oauth = array(
      'consumer_key'    => $consumer_key,
      'consumer_secret' => $consumer_secret,
      'token'           => $token,
      'token_secret'    => $token_secret,
    );
  }

  public function execute($append_url, $arguments = array(), $data = NULL) {
    $query = http_build_query($arguments);
    $headers = $this->getHeadersToString();
    $url = $this->base_url . $append_url . ($query ? "?" . $query : "");
    $content = $data;

    if (!empty($data)) {
      if (empty($data['is_upload'])) {
        $content = json_encode($data);
      }
      else {
        unset($data['is_upload']);
        $a = $this->prepare_upload_files($data);
        $headers = $a['headers'];
        $content = $a['content'];
      }
    }

    $opts = array('http' => array(
      'method'  => $this->method,
      'header'  => $headers,
      'content' => $content,
      'timeout' => $this->timeout,
      'ignore_errors' => TRUE,
    ));

    // Basic Authentication.
    if(!empty($this->auth)) {
      $opts['http']['header'] = ("Authorization: Basic " . base64_encode("{$this->auth['username']}:{$this->auth['password']}")) . ' ' . $opts['http']['header'];
    }
    elseif (!empty($this->oauth)) {
      $_url = $this->base_url . $append_url;
      $opts['http']['header'] = $this->buildOauth($_url, $arguments);
    }

    $this->lastResponse = array(
      "request" => array(
        "url"   => urldecode($url),
        "http"  => $opts["http"],
      ),
    );

    $context = stream_context_create($opts);
    $rs = file_get_contents($url, false, $context);

    // Just store the data structure on last request content.
    $this->lastResponse["http"]["content"] = $data;

    if (empty($rs)) {
      $error = error_get_last(); // Get PHP message at here.

      $error_msg = t('Something went wrong while requesting service.');
      if (isset($error['message'])) {
        $error_msg = $error['message'];
      }

      $this->lastResponse["response"] = array(
        'data'    => NULL,
        'error'   => array('message' => $error_msg),
        'header'  => empty($http_response_header) ? array() : $http_response_header,
      );

      throw new Exception("HTTP request failed. Error was: " . $error_msg);
    }

    $this->lastResponse["response"] = json_decode($rs);
    return $this->lastResponse["response"];
  }

  private function buildOauth($url, $params = array()) {
    $now = time();
    $oauth = array(
      'oauth_consumer_key' => $this->oauth['consumer_key'],
      'oauth_nonce' => $now,
      'oauth_signature_method' => 'HMAC-SHA1',
      'oauth_timestamp' => $now,
      'oauth_token' => $this->oauth['token'],
      'oauth_version' => '1.0',
    );
    $oauth += $params;

    $base_info = $this->buildBaseString($url, $this->method, $oauth);
    $composite_key = rawurlencode($this->oauth['consumer_secret']) . '&' . rawurlencode($this->oauth['token_secret']);
    $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));

    $headers = 'Authorization: OAuth '
      . 'oauth_consumer_key="' . $this->oauth['consumer_key'] . '", '
      . 'oauth_nonce="'. $now . '", '
      . 'oauth_signature="'. rawurlencode($oauth_signature) . '", '
      . 'oauth_signature_method="HMAC-SHA1", '
      . 'oauth_timestamp="'. $now . '", '
      . 'oauth_token="'. $this->oauth['token'] . '", '
      . 'oauth_version="1.0"';

    return $headers;
  }

  /**
   * Private method to generate the base string.
   *
   * @param string $baseURI
   * @param string $method
   * @param array $params
   *
   * @return string Built base string
   */
  private function buildBaseString($baseURI, $method, $params) {
    $return = array();
    ksort($params);
    foreach($params as $key=>$value) {
      $return[] = "$key=" . $value;
    }

    return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $return));
  }

  private function prepare_upload_files($post_files) {
    $boundary = "--------SVBoundary" . md5(microtime(true));
    $headers = "Content-type: multipart/form-data; boundary=" . $boundary;
    $content = '';

    foreach ($post_files as $data) {
      $file_contents = file_get_contents($data["path"]);
      $content .= "--" . $boundary . "\r\n"
        . "Content-Disposition: form-data; name=\"" . $data["fieldName"] ."\"; "
        . "filename=\"" . $data["name"] ."\"\r\n"
        . "Content-Type: " . $data["type"] . "\r\n\r\n"
        . $file_contents . "\r\n";
    }

    // signal end of request (note the trailing "--")
    $content .= "--" .$boundary ."--\r\n";

    return array(
      'headers' => $headers,
      'content' => $content,
    );
  }
}
