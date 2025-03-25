<?php
/**
 * Plugin Name: OPSONE - Stalker
 * Description: Module pour le suivi du projet depuis Stalker.
 * Version: 0.2.0
 */

class DependenciesController
{
  public function __construct()
  {
    add_action('parse_request', [$this, 'dependencies_lister']);
  }

  public function dependencies_lister($wp)
  {
    if ($wp->request == 'stalker/dependencies') {
      $this->list_dependencies();
      exit;
    }
  }

  public function list_dependencies()
  {
    // Get the access token from settings.php
    $token = defined('OPS_STALKER_TOKEN') ? OPS_STALKER_TOKEN : false;

    // Check the token if it's enabled
    if ($token && !(isset($_GET['token']) && hash_equals($token, $_GET['token']))) {
      wp_die('Access denied', 'Error', ['response' => 401]);
    }

    $node_bin = defined('OPS_NODE_BIN') ? OPS_NODE_BIN : 'node';
    $npm_bin = defined('OPS_NPM_BIN') ? OPS_NPM_BIN : 'npm';
    $php_bin = defined('OPS_PHP_BIN') ? OPS_PHP_BIN : 'php';
    $composer_bin = defined('OPS_COMPOSER_BIN') ? OPS_COMPOSER_BIN : 'composer';
    $elastic_search_api_url = defined('OPS_ELASTIC_SEARCH_API_URL') ? OPS_ELASTIC_SEARCH_API_URL : 'http://localhost:9200';

    // Get active theme path
    $theme_path = get_stylesheet_directory();

    // Get wordpress version
    global $wp_version;

    // Get all wordpress plugins
    include_once (ABSPATH . 'wp-admin/includes/plugin.php');
    $wp_dependencies = [];
    foreach (get_plugins() as $dependency) {
      $wp_dependencies[] = array(
        'name' => $dependency["Name"],
        'dep_type' => 'wp_plugin',
        'version' => $dependency["Version"]
      );
    }

    // Get node version if exists
    if (file_exists($theme_path . '/package.json')) {
      $node_version = shell_exec("cd $theme_path/; $node_bin -v");
      preg_match('/\d+\.\d+\.\d+/', $node_version, $node_version_formatted);

      $npm_version = shell_exec("cd $theme_path/; $npm_bin -v");
      preg_match('/\d+\.\d+\.\d+/', $npm_version, $npm_version_formatted);

      $npm_package = $this->getData("cat $theme_path/package.json");
      $npm_informations = shell_exec("cd $theme_path/; $npm_bin list --depth=0");

      $npm_dependencies = [];
      if (isset($npm_package, $npm_informations)) {

        $npm_wanted_dependencies = isset($npm_package->dependencies) ? get_object_vars($npm_package->dependencies)  : [];
        if (isset($npm_package->devDependencies)) {
          $npm_wanted_dependencies = array_merge($npm_wanted_dependencies, get_object_vars($npm_package->devDependencies));
        }
  
        $npm_informations_lines = explode("\n", $npm_informations);
        foreach ($npm_wanted_dependencies as $dep => $version) {
          foreach ($npm_informations_lines as $line) {
            if (preg_match('/^(├──|└──) (.+)@([\d.]+)$/', trim($line), $matches)) {
  
              $name = $matches[2];
              $version = $matches[3];
              if ($name == $dep) {
                $npm_dependencies[] = array(
                  'name' => $name,
                  'dep_type' => 'node_module',
                  'version' => $version
                );
                break;
              }
            }
          }
        }
      }
    } else {
      $node_version = null;
    }

    // Get composer informations if exists
    exec("cd $theme_path/; $php_bin $composer_bin --version", $output, $return_var);
    if ($return_var == 0) {
      // Get composer informations
      $composer_informations = shell_exec("cd $theme_path/; $php_bin $composer_bin show -D --format=json");
      $composer_dependencies = [];

      if ($composer_informations != null) {
        // Get composer version
        preg_match('/\d+\.\d+\.\d+/', $output[0], $composer_version_formatted);

        $composer_dependencies[] = array(
          'name' => 'composer',
          'dep_type' => 'composer',
          'version' => $composer_version_formatted[0]
        );

        foreach (json_decode($composer_informations)->installed as $dep) {
          $composer_dependencies[] = array(
            'name' => $dep->name,
            'dep_type' => 'composer_vendor',
            'version' => $dep->version
          );
        }
      } else {
        $composer_dependencies = null;
      }
    } else {
      $composer_dependencies = null;
    }

    // Get OS version
    $os_informations = null;
    $debian_version = shell_exec('cat /etc/debian_version');
    if (!empty($debian_version)) {
      $os_informations = array(
        'name' => 'debian',
        'dep_type' => 'os',
        'version' => trim($debian_version) ?? 'unknown'
      );
    } else {
      $os_release = shell_exec('cat /etc/os-release');

      if (!empty($os_release)) {
        $os_release_exploded = explode("\n", $os_release);
        $os_release_parsed = array();

        foreach ($os_release_exploded as $line) {
          if (empty($line)) {
            continue;
          }
          list($key, $value) = explode('=', $line, 2);
          $os_release_parsed[$key] = trim($value, '"');
        }
      }

      if (isset($os_release_parsed)) {
        $os_informations = array(
          'name' => $os_release_parsed['ID'],
          'dep_type' => 'os',
          'version' => $os_release_parsed['VERSION_ID'] ?? 'unknown'
        );
      }
    }


    // Get elasticsearch version if exist
    $elastic_options = [
      "http" => [
          "method" => "GET",
          "header" => "Content-Type: application/json\r\n"
      ]
    ];
    $elastic_context = stream_context_create($elastic_options);
    $elastic_result = @file_get_contents($elastic_search_api_url, false, $elastic_context);

    $list = [
      array(
        'name' => 'php',
        'dep_type' => 'php',
        'version' => phpversion()
      ),
      array(
        'name' => 'wordpress',
        'dep_type' => 'wordpress',
        'version' => $wp_version,
      )
    ];

    if ($os_informations != null) {
      array_push($list, $os_informations);
    }

    $list = array_merge($list, $wp_dependencies);

    // Add node version and npm modules to the list if exists
    if (!is_null($node_version)) {
      $list[] = array(
        'name' => 'node',
        'dep_type' => 'node',
        'version' => $node_version_formatted[0] ?? 'unknown'
      );

      $list = array_merge($list, $npm_dependencies);
    }

    if (!is_null($npm_version)) {
      $list[] = array(
        'name' => 'npm',
        'dep_type' => 'npm',
        'version' => $npm_version_formatted[0] ?? 'unknown'
      );
    }
        

    // Add composer version and dependencies to the list if exists
    if (!is_null($composer_dependencies)) {
      $list = array_merge($list, $composer_dependencies);
    }

    if ($elastic_result) {
      $elastic_result = json_decode($elastic_result);

      $elastic_version = [array(
        'name' => 'elasticsearch',
        'dep_type' => 'search_server',
        'version' => $elastic_result->version->number
      )];

      $list = array_merge($list, $elastic_version);
    }

    header('Content-Type: application/json');
    echo json_encode($list);
  }

  private function getData($command) {
    $result = shell_exec($command);
    $jsonResult = null;
    if (!empty($result)) {
      $jsonResult = json_decode($result);
    }
    return $jsonResult;
  }

}

new DependenciesController();
