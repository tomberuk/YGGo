<?php

class SphinxQL {

  private $_sphinx;

  public function __construct(string $host, int $port) {

    $this->_sphinx = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8', false, false, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_sphinx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_sphinx->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
  }

  public function searchHostPages(string $keyword, int $start, int $limit) {

    $query = $this->_sphinx->prepare('SELECT * FROM `hostPage` WHERE MATCH(?) LIMIT ' . (int) $start . ',' . (int) $limit);

    $query->execute([$keyword]);

    return $query->fetchAll();
  }

  public function searchHostPagesTotal(string $keyword) {

    $query = $this->_sphinx->prepare('SELECT COUNT(*) AS `total` FROM `hostPage` WHERE MATCH(?)');

    $query->execute([$keyword]);

    return $query->fetch()->total;
  }
}
