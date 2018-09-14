<?php

// If we're in a development environment, we can set this variable to TRUE.
// This variable is used later in this function.
$development_environment = FALSE;

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];
$config_directories[CONFIG_SYNC_DIRECTORY] = '../config/sync';


/**
 * Source:
 * https://github.com/platformsh/platformsh-example-drupal8/blob/master/web/sites/default/settings.platformsh.php
 */

// Configure the database.
if (getenv('PLATFORM_RELATIONSHIPS')) {
  $relationships = json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS')), TRUE);
  if (empty($databases['default']) && !empty($relationships)) {
    foreach ($relationships as $key => $relationship) {
      $drupal_key = ($key === 'database') ? 'default' : $key;
      foreach ($relationship as $instance) {
        if (empty($instance['scheme']) || ($instance['scheme'] !== 'mysql' && $instance['scheme'] !== 'pgsql')) {
          continue;
        }
        $database = [
          'driver' => $instance['scheme'],
          'database' => $instance['path'],
          'username' => $instance['username'],
          'password' => $instance['password'],
          'host' => $instance['host'],
          'port' => $instance['port'],
        ];
        if (!empty($instance['query']['compression'])) {
          $database['pdo'][PDO::MYSQL_ATTR_COMPRESS] = TRUE;
        }
        if (!empty($instance['query']['is_master'])) {
          $databases[$drupal_key]['default'] = $database;
        }
        else {
          $databases[$drupal_key]['replica'][] = $database;
        }
      }
    }
  }
}
if (getenv('PLATFORM_APP_DIR')) {
  // Configure private and temporary file paths.
  if (!isset($settings['file_private_path'])) {
    $settings['file_private_path'] = getenv('PLATFORM_APP_DIR') . '/private';
  }
  if (!isset($config['system.file']['path']['temporary'])) {
    $config['system.file']['path']['temporary'] = getenv('PLATFORM_APP_DIR') . '/tmp';
  }
  // Configure the default PhpStorage and Twig template cache directories.
  if (!isset($settings['php_storage']['default'])) {
    $settings['php_storage']['default']['directory'] = $settings['file_private_path'];
  }
  if (!isset($settings['php_storage']['twig'])) {
    $settings['php_storage']['twig']['directory'] = $settings['file_private_path'];
  }
}
// Set trusted hosts based on Platform.sh routes.
if (getenv('PLATFORM_ROUTES') && !isset($settings['trusted_host_patterns'])) {
  $routes = json_decode(base64_decode(getenv('PLATFORM_ROUTES')), TRUE);
  $settings['trusted_host_patterns'] = [];
  foreach ($routes as $url => $route) {
    $host = parse_url($url, PHP_URL_HOST);
    if ($host !== FALSE && $route['type'] == 'upstream' && $route['upstream'] == getenv('PLATFORM_APPLICATION_NAME')) {
      // Replace asterisk wildcards with a regular expression.
      $host_pattern = str_replace('\*', '[^\.]+', preg_quote($host));
      $settings['trusted_host_patterns'][] = '^' . $host_pattern . '$';
    }
  }
  $settings['trusted_host_patterns'] = array_unique($settings['trusted_host_patterns']);
}
// Import variables prefixed with 'd8settings:' into $settings and 'd8config:'
// into $config.
if (getenv('PLATFORM_VARIABLES')) {
  $variables = json_decode(base64_decode(getenv('PLATFORM_VARIABLES')), TRUE);
  foreach ($variables as $name => $value) {
    // A variable named "d8settings:example-setting" will be saved in
    // $settings['example-setting'].
    if (strpos($name, 'd8settings:') === 0) {
      $settings[substr($name, 11)] = $value;
    }
    // A variable named "drupal:example-setting" will be saved in
    // $settings['example-setting'] (backwards compatibility).
    elseif (strpos($name, 'drupal:') === 0) {
      $settings[substr($name, 7)] = $value;
    }
    // A variable named "d8config:example-name:example-key" will be saved in
    // $config['example-name']['example-key'].
    elseif (strpos($name, 'd8config:') === 0 && substr_count($name, ':') >= 2) {
      list(, $config_key, $config_name) = explode(':', $name, 3);
      $config[$config_key][$config_name] = $value;
    }
    // A complex variable named "d8config:example-name" will be saved in
    // $config['example-name'].
    elseif (strpos($name, 'd8config:') === 0 && is_array($value)) {
      $config[substr($name, 9)] = $value;
    }
  }
}
// Set the project-specific entropy value, used for generating one-time
// keys and such.
if (getenv('PLATFORM_PROJECT_ENTROPY') && empty($settings['hash_salt'])) {
  $settings['hash_salt'] = getenv('PLATFORM_PROJECT_ENTROPY');
}
// Set the deployment identifier, which is used by some Drupal cache systems.
if (getenv('PLATFORM_TREE_ID') && empty($settings['deployment_identifier'])) {
  $settings['deployment_identifier'] = getenv('PLATFORM_TREE_ID');
}

if (getenv('LANDO_INFO')) {
  $lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
  $databases['default']['default'] = [
    'driver' => 'mysql',
    'database' => $lando_info['database']['creds']['database'],
    'username' => $lando_info['database']['creds']['user'],
    'password' => $lando_info['database']['creds']['password'],
    'host' => $lando_info['database']['internal_connection']['host'],
    'port' => $lando_info['database']['internal_connection']['port'],
  ];

  foreach ($lando_info as $connection) {
    // If there is a Solr connection available, set the connection details for the search_api module.
    if ($connection['type'] === 'solr') {
      $config['search_api.server.solr']['backend_config']['connector_config']['path'] = '/solr';
      $config['search_api.server.solr']['backend_config']['connector_config']['core'] = $connection['internal_connection']['core'];
      $config['search_api.server.solr']['backend_config']['connector_config']['host'] = $connection['internal_connection']['host'];
      $config['search_api.server.solr']['backend_config']['connector_config']['port'] = $connection['internal_connection']['port'];
    }
  }

  // If there is a LANDO_INFO env variable present, we're in a development
  // environment.
  $development_environment = TRUE;
}

if ($branch = getenv('PLATFORM_BRANCH')) {
  if (!in_array($branch, ['master', 'staging', 'production'])) {
    // If we aren't in a production-like environment on Platform, we set this as a
    // development environment.
    $development_environment = TRUE;
  }
}

// If there is no hash_salt, we generate one. Took this from the drupal_get_hash_salt
// function of Drupal 7.
if (empty($settings['hash_salt'])) {
  $settings['hash_salt'] = hash('sha256', serialize($databases));
}

// On development environments, we want to show all warnings.
if ($development_environment) {
  $config['system.logging']['error_level'] = 'verbose';
}

/**
 * Source:
 * https://github.com/platformsh/platformsh-example-drupal8/blob/master/web/sites/default/settings.php
 */

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
