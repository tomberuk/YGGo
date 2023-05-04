<?php

// Load system dependencies
require_once('../config/app.php');
require_once('../library/curl.php');
require_once('../library/robots.php');
require_once('../library/filter.php');
require_once('../library/parser.php');
require_once('../library/mysql.php');
require_once('../library/sphinxql.php');

// Connect Sphinx search server
$sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

// Connect database
$db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

// Define page basics
$totalPages = $db->getTotalPages();

$placeholder = Filter::plural($totalPages, [sprintf(_('Over %s page or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            sprintf(_('Over %s pages or enter the new one...'), $totalPages),
                                            ]);

// Filter request data
$t = !empty($_GET['t']) ? Filter::url($_GET['t']) : 'page';
$m = !empty($_GET['m']) ? Filter::url($_GET['m']) : 'default';
$q = !empty($_GET['q']) ? Filter::url($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Crawl request
if (filter_var($q, FILTER_VALIDATE_URL) && preg_match(CRAWL_URL_REGEXP, $q)) {

  $db->beginTransaction();

  try {

    // Parse host info
    if ($hostURL = Parser::hostURL($q)) {

      // Host exists
      if ($host = $db->getHost(crc32($hostURL->string))) {

        $hostStatus        = $host->status;
        $hostPageLimit     = $host->crawlPageLimit;
        $hostId            = $host->hostId;
        $hostRobots        = $host->robots;
        $hostRobotsPostfix = $host->robotsPostfix;

      // Register new host
      } else {

        // Disk quota not reached
        if (CRAWL_STOP_DISK_QUOTA_MB_LEFT < disk_free_space('/') / 1000000) {

          // Get robots.txt if exists
          $curl = new Curl($hostURL->string . '/robots.txt');

          if (200 == $curl->getCode() && false !== stripos($curl->getContent(), 'user-agent:')) {
            $hostRobots = $curl->getContent();
          } else {
            $hostRobots = null;
          }

          $hostRobotsPostfix = CRAWL_ROBOTS_POSTFIX_RULES;

          $hostStatus    = CRAWL_HOST_DEFAULT_STATUS;
          $hostPageLimit = CRAWL_HOST_DEFAULT_PAGES_LIMIT;
          $hostId        = $db->addHost($hostURL->scheme,
                                        $hostURL->name,
                                        $hostURL->port,
                                        crc32($hostURL->string),
                                        time(),
                                        null,
                                        $hostPageLimit,
                                        (string) CRAWL_HOST_DEFAULT_META_ONLY,
                                        (string) $hostStatus,
                                        $hostRobots,
                                        $hostRobotsPostfix);
        }
      }

      // Parse page URI
      $hostPageURI = Parser::uri($q);

      // Init robots parser
      $robots = new Robots((!$hostRobots ? (string) $hostRobots : (string) CRAWL_ROBOTS_DEFAULT_RULES) . PHP_EOL . (string) $hostRobotsPostfix);

      // Save page info
      if ($hostStatus && // host enabled
          $robots->uriAllowed($hostPageURI->string) && // page allowed by robots.txt rules
          $hostPageLimit > $db->getTotalHostPages($hostId) && // pages quantity not reached host limit
         !$db->getHostPage($hostId, crc32($hostPageURI->string))) {  // page not exists

          $db->addHostPage($hostId, crc32($hostPageURI->string), $hostPageURI->string, time());
      }
    }

    $db->commit();

  } catch(Exception $e){

    $db->rollBack();
  }
}

// Search request
if (!empty($q)) {

  if (!empty($t) && $t == 'image') {

    $resultsTotal = $sphinx->searchHostImagesTotal(Filter::searchQuery($q, $m));
    $results      = $sphinx->searchHostImages(Filter::searchQuery($q, $m), $p * WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT, $resultsTotal);

  } else {

    $resultsTotal = $sphinx->searchHostPagesTotal(Filter::searchQuery($q, $m));
    $results      = $sphinx->searchHostPages(Filter::searchQuery($q, $m), $p * WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT - WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT, WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT, $resultsTotal);
  }

} else {

  $resultsTotal = 0;
  $results      = [];
}

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo (empty($q) ? _('Empty request - YGGo!') : ($p > 1 ? sprintf(_('%s - #%s - YGGo!'), htmlentities($q), $p) : sprintf(_('%s - YGGo!'), htmlentities($q)))) ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo _('Javascript-less Open Source Web Search Engine') ?>" />
    <meta name="keywords" content="<?php echo _('web, search, engine, crawler, php, pdo, mysql, sphinx, yggdrasil, js-less, open source') ?>" />
    <style>

      * {
        border: 0;
        margin: 0;
        padding: 0;
        font-family: Sans-serif;
      }

      body {
        background-color: #2e3436;
      }

      header {
        background-color: #34393b;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
      }

      main {
        margin-top: 92px;
        margin-bottom: 76px;
      }

      h1 {
        position: fixed;
        top: 8px;
        left: 24px;
      }

      h1 > a,
      h1 > a:visited,
      h1 > a:active,
      h1 > a:hover {
        color: #fff;
        font-weight: normal;
        font-size: 26px;
        margin: 16px 0;
        text-decoration: none;
      }

      h2 {
        display: block;
        font-size: 16px;
        font-weight: normal;
        margin: 4px 0;
        color: #fff;
      }

      h3 {
        display: block;
        font-size: 16px;
        font-weight: normal;
        margin: 8px 0;
        color: #fff;
      }

      form {
        display: block;
        max-width: 678px;
        margin: 0 auto;
        text-align: center;
      }

      input {
        width: 100%;
        margin: 16px 0;
        padding: 14px 0;
        border-radius: 32px;
        background-color: #000;
        color: #fff;
        font-size: 16px;
        text-align: center;
      }

      input:hover {
        background-color: #111
      }

      input:focus {
        outline: none;
        background-color: #111
      }

      input:focus::placeholder {
        color: #090808
      }

      label {
        font-size: 14px;
        position: fixed;
        top: 30px;
        right: 120px;
        color: #fff
      }

      label > input {
        width: auto;
        margin: 0 4px;
      }

      button {
        padding: 12px 16px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
        position: fixed;
        top: 18px;
        right: 24px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      a, a:visited, a:active {
        color: #9ba2ac;
        display: block;
        font-size: 12px;
        margin-top: 8px;
      }

      a:hover {
        color: #54a3f7;
      }

      img.icon {
        float: left;
        border-radius: 50%;
        margin-right: 8px;
      }

      img.image {
        max-width: 100%;
        border-radius: 3px;
      }

      div {
        max-width: 640px;
        margin: 0 auto;
        padding: 16px 0;
        border-top: 1px #000 dashed;
        font-size: 14px
      }

      span {
        color: #ccc;
        display: block;
        margin: 8px 0;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo WEBSITE_DOMAIN; ?>/search.php">
        <h1><a href="<?php echo WEBSITE_DOMAIN; ?>"><?php echo _('YGGo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="<?php echo htmlentities($q) ?>" />
        <label><input type="checkbox" name="t" value="image" <?php echo (!empty($t) && $t == 'image' ? 'checked="checked"' : false) ?>/> <?php echo _('Images') ?></label>
        <button type="submit"><?php echo _('Search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($results) { ?>
        <div>
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($queueTotal = $db->getTotalPagesByHttpCode(null)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
        <?php foreach ($results as $result) { ?>
          <?php if (!empty($t) && $t == 'image' &&
                                  $hostImage = $db->getFoundHostImage($result->id)) { ?>
            <?php

              // Built image url
              $hostImageURL = $hostImage->scheme . '://' .
                              $hostImage->name .
                             ($hostImage->port ? ':' . $hostImage->port : false) .
                              $hostImage->uri;

              // Get remote image data
              $hostImageCurl = curl_init($hostImageURL);
                               curl_setopt($hostImageCurl, CURLOPT_RETURNTRANSFER, true);
                               curl_setopt($hostImageCurl, CURLOPT_CONNECTTIMEOUT, 5);
              $hostImageData = curl_exec($hostImageCurl);

              // Skip item render on timeout
              if (curl_error($hostImageCurl)) continue;

              // Convert remote image data to base64 string to prevent direct URL call
              if (!$hostImageType   = @pathinfo($hostImageURL, PATHINFO_EXTENSION)) continue;
              if (!$hostImageBase64 = @base64_encode($hostImageData)) continue;

              $hostImageURLencoded  = 'data:image/' . $hostImageType . ';base64,' . $hostImageBase64;

            ?>
            <div>
              <a href="<?php echo $hostImageURL ?>">
                <img src="<?php echo $hostImageURLencoded ?>" alt="<?php echo $hostImage->description ?>" title="<?php echo $hostImageURL ?>" class="image" />
              </a>
              <?php foreach ((array) $db->getHostImageHostPages($result->id) as $hostPage) { ?>
                <?php if ($hostPage = $db->getFoundHostPage($hostPage->hostPageId)) { ?>
                  <?php $hostPageURL = $hostPage->scheme . '://' . $hostPage->name . ($hostPage->port ? ':' . $hostPage->port : false) . $hostPage->uri ?>
                  <h3><?php echo $hostPage->metaTitle ?></h3>
                  <?php if (!empty($hostImage->description)) { ?>
                    <span><?php echo $hostImage->description ?></span>
                  <?php } ?>
                  <a href="<?php echo $hostPageURL ?>">
                    <img src="<?php echo WEBSITE_DOMAIN ?>/image.php?q=<?php echo urlencode($hostPage->name) ?>" alt="favicon" width="16" height="16" class="icon" />
                    <?php echo $hostPageURL ?>
                  </a>
                <?php } ?>
              <?php } ?>
            </div>
          <?php } else if ($hostPage = $db->getFoundHostPage($result->id)) { ?>
              <?php

                $hostPageURL = $hostPage->scheme . '://' .
                $hostPage->name .
               ($hostPage->port ? ':' . $hostPage->port : false) .
                $hostPage->uri;

              ?>
            <div>
              <h2><?php echo $hostPage->metaTitle ?></h2>
              <?php if (!empty($hostPage->metaDescription)) { ?>
              <span><?php echo $hostPage->metaDescription ?></span>
              <?php } ?>
              <a href="<?php echo $hostPageURL ?>">
                <img src="<?php echo WEBSITE_DOMAIN; ?>/image.php?q=<?php echo urlencode($hostPage->name) ?>" alt="favicon" width="16" height="16" class="icon" />
                <?php echo $hostPageURL ?>
              </a>
            </div>
          <?php } ?>
        <?php } ?>
        <?php if ($p * WEBSITE_PAGINATION_SEARCH_RESULTS_LIMIT <= $resultsTotal) { ?>
          <div>
            <a href="<?php echo WEBSITE_DOMAIN; ?>/search.php?q=<?php echo urlencode(htmlentities($q)) ?>&p=<?php echo $p + 1 ?>"><?php echo _('Next page') ?></a>
          </div>
          <?php } ?>
      <?php } else { ?>
        <div style="text-align:center">
          <span><?php echo sprintf(_('Total found: %s'), $resultsTotal) ?></span>
          <?php if ($q && $queueTotal = $db->getTotalPagesByHttpCode(null)) { ?>
            <span><?php echo sprintf(_('* Please wait for all pages crawl to complete (%s in queue).'), $queueTotal) ?></span>
          <?php } ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>