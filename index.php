<?php

/**
 * Archivarix Content Loader
 *
 * See README.txt for instructions with NGINX and Apache 2.x web servers
 *
 * PHP version 5.6 or newer
 * Required extensions: PDO_SQLITE
 * Recommended extensions: mbstring
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author     Archivarix Team <hello@archivarix.com>
 * @telegram   https://t.me/archivarixsupport
 * @copyright  2017-2019 Archivarix LLC
 * @license    https://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @version    Release: 20190917
 * @link       https://archivarix.com
 */

@ini_set('display_errors', 0);

/**
 * Enable CMS mode to integrate with 3rd party CMS.
 * 0 = Disabled
 * 1 = Enabled
 * 2 = Enabled and homepage / path is passed to CMS
 * -1 = Return 404 error on missing urls
 */
const ARCHIVARIX_CMS_MODE = 0;

/**
 * Return 1px.png if image does not exist.
 */
const ARCHIVARIX_FIX_MISSING_IMAGES = 1;

/**
 * Return empty.css if css does not exist.
 */
const ARCHIVARIX_FIX_MISSING_CSS = 1;

/**
 * Return empty.js if javascript does not exist.
 */
const ARCHIVARIX_FIX_MISSING_JS = 1;

/**
 * Return empty.ico if favicon.ico does not exist.
 */
const ARCHIVARIX_FIX_MISSING_ICO = 1;

/**
 * Redirect missing html pages.
 */
const ARCHIVARIX_REDIRECT_MISSING_HTML = '/';

/**
 * Replace a custom key-phrase with a text file or php script.
 * You can do multiple custom replaces at once by adding more
 * array element.
 */
const ARCHIVARIX_INCLUDE_CUSTOM = array(
  [
    'FILE' => '', // place a file to .content.xxxxxxxx folder and enter its filename here
    'KEYPHRASE' => '', // an entry to look for
    'LIMIT' => 1, // how many matches to replace; -1 for unlimited
    'REGEX' => 0, // 1 to enable perl regex (important: escape ~ symbol); 0 - disabled
    'POSITION' => 1, // -1 to place before KEYPHRASE, 0 to replace, 1 to place after KEYPHRASE
  ],

  /**
   * Here are two most common predefined rules you may use.
   * Just fill out FILE to activate and don't forget to put
   * a file to .content.xxxxxxxx.
   */

  // before closing </head> rule, good for trackers and analytics
  [
    'FILE' => '',
    'KEYPHRASE' => '</head>',
    'LIMIT' => 1,
    'REGEX' => 0,
    'POSITION' => -1,
  ],

  // before closing </body> rule, good for counters or footer links
  [
    'FILE' => '',
    'KEYPHRASE' => '</body>',
    'LIMIT' => 1,
    'REGEX' => 0,
    'POSITION' => -1,
  ],
);

/**
 * Custom source directory name. By default this script searches
 * for .content.xxxxxxxx folder. Set the different value if you
 * renamed that directory.
 */
const ARCHIVARIX_CONTENT_PATH = '';

/**
 * Set Cache-Control header for static files.
 * By default set to 0 and Etag is used for caching.
 */
const ARCHIVARIX_CACHE_CONTROL_MAX_AGE = 2592000;

/**
 * Website can run on another domain by default.
 * Set a custom domain if it is not recognized automatically or
 * you want to run your restore on a subdomain of original domain.
 */
const ARCHIVARIX_CUSTOM_DOMAIN = '';

/**
 * XML Sitemap path. Example: /sitemap.xml
 * Do not use query in sitemap path as it will be ignored
 */
const ARCHIVARIX_SITEMAP_PATH = '';

/**
 * Catch urls with a missing content and store them for
 * later review in Archivarix CMS. This feature is experimental.
 */
const ARCHIVARIX_CATCH_MISSING = 0;

/**
 * @return string
 *
 * @throws Exception
 */
function getSourceRoot()
{
  if (ARCHIVARIX_CONTENT_PATH) {
    $path = ARCHIVARIX_CONTENT_PATH;
  } else {
    $path = '';
    $list = scandir(dirname(__FILE__));
    foreach ($list as $item) {
      if (preg_match('~^\.content\.[0-9a-zA-Z]+$~', $item) && is_dir(__DIR__ . DIRECTORY_SEPARATOR . $item)) {
        $path = $item;
        break;
      }
    }
    if (!$path) {
      header('X-Error-Description: Folder .content.xxxxxxxx not found');
      throw new \Exception('Folder .content.xxxxxxxx not found');
    }
  }
  $absolutePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $path;
  if (!realpath($absolutePath)) {
    header('X-Error-Description: Directory does not exist');
    throw new \Exception(sprintf('Directory %s does not exist', $absolutePath));
  }

  return $absolutePath;
}

/**
 * @param string $dsn
 * @param string $url
 *
 * @return array|false
 */
function getFileMetadata($dsn, $url)
{
  if (ARCHIVARIX_CUSTOM_DOMAIN) {
    if (!empty(ARCHIVARIX_SETTINGS['www']) && $_SERVER['HTTP_HOST'] == ARCHIVARIX_CUSTOM_DOMAIN) {
      $url = preg_replace('~' . preg_quote(ARCHIVARIX_CUSTOM_DOMAIN, '~') . '~', 'www.' . ARCHIVARIX_ORIGINAL_DOMAIN, $url, 1);
    } else {
      $url = preg_replace('~' . preg_quote(ARCHIVARIX_CUSTOM_DOMAIN, '~') . '~', ARCHIVARIX_ORIGINAL_DOMAIN, $url, 1);
    }
  } elseif (!preg_match('~^([-a-z0-9.]+\.)?' . preg_quote(ARCHIVARIX_ORIGINAL_DOMAIN, '~') . '$~i', $_SERVER['HTTP_HOST'])) {
    if (!empty(ARCHIVARIX_SETTINGS['www'])) {
      $url = preg_replace('~' . preg_quote($_SERVER['HTTP_HOST'], '~') . '~', 'www.' . ARCHIVARIX_ORIGINAL_DOMAIN, $url, 1);
    } else {
      $url = preg_replace('~' . preg_quote($_SERVER['HTTP_HOST'], '~') . '~', ARCHIVARIX_ORIGINAL_DOMAIN, $url, 1);
    }
  }

  if (preg_match('~[?]+$~', $url)) {
    $urlAlt = preg_replace('~[?]+$~', '', $url);
  } elseif (preg_match('~[/]+$~', $url)) {
    $urlAlt = preg_replace('~[/]+$~', '', $url);
  } elseif (!parse_url($url, PHP_URL_QUERY) && !parse_url($url, PHP_URL_FRAGMENT)) {
    $urlAlt = $url . '/';
  } else {
    $urlAlt = $url;
  }

  define('ARCHIVARIX_ORIGINAL_URL', $url);

  $pdo = new PDO($dsn);
  $sth = $pdo->prepare('SELECT rowid, * FROM structure WHERE (url = :url COLLATE NOCASE OR url = :urlAlt COLLATE NOCASE) AND enabled = 1 ORDER BY filetime DESC LIMIT 1');
  $sth->execute(['url' => $url, 'urlAlt' => $urlAlt]);
  $metadata = $sth->fetch(PDO::FETCH_ASSOC);

  return $metadata;
}

/**
 * @param array $metaData
 * @param string $sourcePath
 * @param string $url
 */
function render(array $metaData, $sourcePath, $url = '')
{
  if (isset($metaData['redirect']) && $metaData['redirect']) {
    header('Location: ' . $metaData['redirect']);
    http_response_code(301);
    exit(0);
  }
  $sourceFile = $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'];
  if (!file_exists($sourceFile)) {
    handle404($sourcePath, $url);
    exit(0);
  }
  header('Content-Type:' . $metaData['mimetype']);
  if (in_array($metaData['mimetype'], ['text/html', 'text/css', 'text/xml', 'application/javascript', 'application/x-javascript'])) {
    header('Content-Type:' . $metaData['mimetype'] . '; charset=' . $metaData['charset'], true);
  }
  if (in_array($metaData['mimetype'], ['application/x-javascript', 'application/font-woff', 'application/javascript', 'image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/tiff', 'image/webp', 'image/x-icon', 'image/x-ms-bmp', 'text/css', 'text/javascript'])) {
    $etag = md5_file($sourceFile);
    header('Etag: "' . $etag . '"');
    if (ARCHIVARIX_CACHE_CONTROL_MAX_AGE) {
      header('Cache-Control: public, max-age=' . ARCHIVARIX_CACHE_CONTROL_MAX_AGE);
    }
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
      http_response_code(304);
      exit(0);
    }
  }
  if (0 === strpos($metaData['mimetype'], 'text/html')) {
    echo prepareContent($sourceFile, $sourcePath);
  } else {
    $fp = fopen($sourceFile, 'rb');
    fpassthru($fp);
    fclose($fp);
  }
}

/**
 * @param $file
 * @param $path
 *
 * @return bool|mixed|string
 */
function prepareContent($file, $path)
{
  $content = file_get_contents($file);

  foreach (ARCHIVARIX_INCLUDE_CUSTOM as $includeCustom) {
    if ($includeCustom['FILE']) {
      global $includeRule;
      $includeRule = $includeCustom;
      ob_start();
      include $path . DIRECTORY_SEPARATOR . $includeCustom['FILE'];
      $includedContent = preg_replace('~\$(\d)~', '\\\$$1', ob_get_clean());

      if ($includeCustom['REGEX']) {
        $includeCustom['KEYPHRASE'] = str_replace('~', '\~', $includeCustom['KEYPHRASE']);
      } else {
        $includeCustom['KEYPHRASE'] = preg_quote($includeCustom['KEYPHRASE'], '~');
      }

      switch ($includeCustom['POSITION']) {
        case -1 :
          $includedContent = $includedContent . '$0';
          break;
        case 1 :
          $includedContent = '$0' . $includedContent;
          break;
      }

      $content = preg_replace('~' . $includeCustom['KEYPHRASE'] . '~is', $includedContent, $content, $includeCustom['LIMIT']);
    }
  }
  if (function_exists('mb_strlen')) {
    header('Content-Length: ' . mb_strlen($content, '8bit'), true);
  }

  return $content;
}

/**
 * @param string $sourcePath
 * @param string $url
 */
function handle404($sourcePath, $url)
{
  if (ARCHIVARIX_CATCH_MISSING) {
    global $dsn;
    $url = ARCHIVARIX_ORIGINAL_URL;

    $pdo = new PDO($dsn);
    $pdo->exec('CREATE TABLE IF NOT EXISTS missing (url TEXT PRIMARY KEY, status INTEGER, ignore INTEGER)');

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO missing VALUES(:url, 0, 0)');
    $stmt->bindParam(':url', $url, PDO::PARAM_STR);
    $stmt->execute();
  }

  $fileType = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
  switch (true) {
    case (in_array($fileType, ['jpg', 'jpeg', 'gif', 'png', 'bmp']) && ARCHIVARIX_FIX_MISSING_IMAGES):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . '1px.png';
      $size = filesize($fileName);
      render(['folder' => '', 'filename' => '1px.png', 'mimetype' => 'image/png', 'charset' => 'binary', 'filesize' => $size], $sourcePath);
      break;
    case ($fileType === 'ico' && ARCHIVARIX_FIX_MISSING_ICO):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.ico';
      $size = filesize($fileName);
      render(['folder' => '', 'filename' => 'empty.ico', 'mimetype' => 'image/x-icon', 'charset' => 'binary', 'filesize' => $size], $sourcePath);
      break;
    case($fileType === 'css' && ARCHIVARIX_FIX_MISSING_CSS):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.css';
      $size = filesize($fileName);
      render(['folder' => '', 'filename' => 'empty.css', 'mimetype' => 'text/css', 'charset' => 'utf-8', 'filesize' => $size], $sourcePath);
      break;
    case ($fileType === 'js' && ARCHIVARIX_FIX_MISSING_JS):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.js';
      $size = filesize($fileName);
      render(['folder' => '', 'filename' => 'empty.js', 'mimetype' => 'application/javascript', 'charset' => 'utf-8', 'filesize' => $size], $sourcePath);
      break;
    case (ARCHIVARIX_REDIRECT_MISSING_HTML && ARCHIVARIX_REDIRECT_MISSING_HTML !== $_SERVER['REQUEST_URI']):
      header('Location: ' . ARCHIVARIX_REDIRECT_MISSING_HTML);
      http_response_code(301);
      exit(0);
      break;
    default:
      http_response_code(404);
  }
}

/**
 * @param string $dsn
 *
 * @return bool
 */
function checkRedirects($dsn)
{
  $pdo = new PDO($dsn);
  $exit = false;
  $res = $pdo->query('SELECT param, value FROM settings');
  if ($res) {
    $settings = $res->fetchAll(PDO::FETCH_KEY_PAIR);
    define('ARCHIVARIX_ORIGINAL_DOMAIN', $settings['domain']);
    define('ARCHIVARIX_SETTINGS', $settings);
    if (!empty($settings['https']) && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
      $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      header('Location: ' . $location);
      http_response_code(301);
      exit(0);
    }
    if (!empty($settings['non-www']) && 0 === strpos($_SERVER['HTTP_HOST'], 'www.')) {
      $host = preg_replace('~^www\.~', '', $_SERVER['HTTP_HOST']);
      $location = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . '://' . $host . $_SERVER['REQUEST_URI'];
      header('Location: ' . $location);
      http_response_code(301);
      exit(0);
    }
    if (!empty($settings['www']) && $_SERVER['HTTP_HOST'] == $settings['domain']) {
      $location = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . '://www.' . $settings['domain'] . $_SERVER['REQUEST_URI'];
      header('Location: ' . $location);
      http_response_code(301);
      exit(0);
    }
  } else {
    header('X-Error-Description: Write permission problem.');
    throw new \Exception('Write permission problem. Make sure your files are under a correct user/group and avoid using PHP in a module mode.');
  }

  return $exit;
}

/**
 * @param string $dsn
 */
function renderSitemapXML($dsn)
{
  if (ARCHIVARIX_CUSTOM_DOMAIN) {
    $domain = preg_replace('~' . preg_quote(ARCHIVARIX_CUSTOM_DOMAIN, '~') . '$~', '', $_SERVER['HTTP_HOST']) . ARCHIVARIX_ORIGINAL_DOMAIN;
  } else {
    $domain = ARCHIVARIX_ORIGINAL_DOMAIN;
  }

  $pagesLimit = 50000;
  $pageProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';

  $pdo = new PDO($dsn);
  $res = $pdo->prepare('SELECT count(*) FROM "structure" WHERE hostname = :domain AND mimetype = "text/html" AND enabled = 1 AND redirect = ""');
  $res->execute(['domain' => $domain]);
  $pagesCount = $res->fetchColumn();

  if (!$pagesCount) {
    exit(0);
  }

  if ($pagesCount > $pagesLimit && empty($_GET['id'])) {
    header('Content-type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?' . '><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    for ($pageNum = 1; $pageNum <= ceil($pagesCount / $pagesLimit); $pageNum++) {
      echo '<sitemap><loc>' . htmlspecialchars("$pageProtocol://$_SERVER[HTTP_HOST]" . ARCHIVARIX_SITEMAP_PATH . "?id=$pageNum", ENT_XML1, 'UTF-8') . '</loc></sitemap>';
    }
    echo '</sitemapindex>';
    exit(0);
  }

  if (!empty($_GET['id']) && !ctype_digit($_GET['id'])) {
    http_response_code(404);
    exit(0);
  }

  if (!empty($_GET['id'])) {
    $pageId = $_GET['id'];
    if ($pageId < 1 || $pageId > ceil($pagesCount / $pagesLimit)) {
      http_response_code(404);
      exit(0);
    }
    $pagesOffset = ($pageId - 1) * $pagesLimit;
    $res = $pdo->prepare('SELECT * FROM "structure" WHERE hostname = :domain AND mimetype = "text/html" AND enabled = 1 AND redirect = "" ORDER BY request_uri LIMIT :limit OFFSET :offset');
    $res->execute(['domain' => $domain, 'limit' => $pagesLimit, 'offset' => $pagesOffset]);
    $pages = $res->fetchAll(PDO::FETCH_ASSOC);
  }

  if (empty($_GET['id'])) {
    $res = $pdo->prepare('SELECT * FROM "structure" WHERE hostname = :domain AND mimetype = "text/html" AND enabled = 1 AND redirect = "" ORDER BY request_uri');
    $res->execute(['domain' => $domain]);
    $pages = $res->fetchAll(PDO::FETCH_ASSOC);
  }

  header('Content-type: text/xml; charset=utf-8');
  echo '<?xml version="1.0" encoding="UTF-8"?' . '><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach ($pages as $page) {
    echo '<url><loc>' . htmlspecialchars("$pageProtocol://$_SERVER[HTTP_HOST]$page[request_uri]", ENT_XML1, 'UTF-8') . '</loc></url>';
  }
  echo '</urlset>';
}

try {
  if (ARCHIVARIX_CMS_MODE == 2 && $_SERVER['REQUEST_URI'] == '/') {
    return;
  }

  if (!in_array('sqlite', PDO::getAvailableDrivers())) {
    header('X-Error-Description: PDO_SQLITE driver is not loaded');
    throw new \Exception('PDO_SQLITE driver is not loaded.');
  }
  if ('cli' === php_sapi_name()) {
    echo "OK" . PHP_EOL;
    exit(0);
  }

  $url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $sourcePath = getSourceRoot();

  $dbm = new PDO('sqlite::memory:');
  if (version_compare($dbm->query('SELECT sqlite_version()')->fetch()[0], '3.7.0') >= 0) {
    $dsn = sprintf('sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.db');
  } else {
    $dsn = sprintf('sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.legacy.db');
  }
  $dbm = null;

  if (checkRedirects($dsn)) {
    exit(0);
  }

  if (ARCHIVARIX_SITEMAP_PATH && ARCHIVARIX_SITEMAP_PATH === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    renderSitemapXML($dsn);
    exit(0);
  }

  $metaData = getFileMetadata($dsn, $url);
  if ($metaData) {
    render($metaData, $sourcePath, $url);
    if (ARCHIVARIX_CMS_MODE > 0) {
      exit(0);
    }
  } else {
    if (ARCHIVARIX_CMS_MODE == -1) {
      http_response_code(404);
      exit(0);
    }
    if (ARCHIVARIX_CMS_MODE == 0) {
      handle404($sourcePath, $url);
    }
  }
  if (!ARCHIVARIX_CMS_MODE) {
    exit(0);
  }
} catch (\Exception $e) {
  http_response_code(503);
  error_log($e);
}