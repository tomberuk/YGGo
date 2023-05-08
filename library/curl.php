<?php

class Curl {

  private $_connection;
  private $_response;

  public function __construct(string $url, mixed $userAgent = false, int $connectTimeout = 3) {

    $this->_connection = curl_init($url);

    curl_setopt($this->_connection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->_connection, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

    if ($userAgent) {
      curl_setopt($this->_connection, CURLOPT_USERAGENT, (string) $userAgent);
    }

    $this->_response = curl_exec($this->_connection);
  }

  public function __destruct() {

    curl_close($this->_connection);
  }

  public function getError() {

    if (curl_errno($this->_connection)) {

      return curl_errno($this->_connection);

    } else {

      return false;
    }
  }

  public function getCode() {

    return curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);

  }

  public function getContentType() {

    return curl_getinfo($this->_connection, CURLINFO_CONTENT_TYPE);
  }

  public function getSizeDownload() {

    return curl_getinfo($this->_connection, CURLINFO_SIZE_DOWNLOAD);
  }

  public function getSizeRequest() {

    return curl_getinfo($this->_connection, CURLINFO_REQUEST_SIZE);
  }

  public function getTotalTime() {

    return curl_getinfo($this->_connection, CURLINFO_TOTAL_TIME_T);
  }

  public function getContent() {

    return $this->_response;
  }
}