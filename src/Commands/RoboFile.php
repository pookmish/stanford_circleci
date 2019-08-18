<?php

namespace SWSCircleCi\Commands;

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * Path to this tool's library root.
   *
   * @var string
   */
  protected $toolDir;

  public function __construct() {
    $this->toolDir = dirname(dirname(dirname(__FILE__)));
  }

  /**
   * Run phpunit tests on the given extension.
   *
   * @param string $html_path
   *   Path to the drupal project.
   * @param string $extension_dir
   *   Path to the drupal extension.
   *
   * @option bool with-coverage
   *
   * @command phpunit
   */
  public function phpunit($html_path, $extension_dir = NULL, $options = ['with-coverage' => FALSE]) {
    $extension_dir = is_null($extension_dir) ? "$html_path/.." : $extension_dir;
    $this->setupDrupal($html_path, $extension_dir);

    $extension_type = $this->getExtensionType($extension_dir);
    $name = $this->getExtensionName($extension_dir);

    $this->_copy("{$this->toolDir}/config/phpunit.xml", "$html_path/web/core/phpunit.xml");

    // Switch to Robo phpunit when compatible.
    // @see https://www.drupal.org/project/drupal/issues/2950132
    $test = $this->taskExec('../vendor/bin/phpunit')
      ->dir("$html_path/web")
      ->arg("$html_path/web/{$extension_type}s/custom/$name")
      ->option('config', 'core', '=');

    if ($options['with-coverage']) {
      $test->option('filter', '/(Unit|Kernel)/', '=')
        ->option('coverage-html', "$html_path/artifacts/phpunit/html", '=')
        ->option('coverage-xml', "$html_path/artifacts/phpunit/xml", '=')
        ->option('whitelist', "$html_path/web/{$extension_type}s/custom/$name");
    }
    $test->option('log-junit', "$html_path/artifacts/phpunit/results.xml")
      ->run();
  }

  /**
   * Set up the directory with bare bones Drupal and get all dependencies.
   *
   * @param string $html_path
   *   Path where Drupal project gets created.
   * @param string $extension_dir
   *   Path to the extension being tested.
   */
  protected function setupDrupal($html_path, $extension_dir) {

    // The directory is completely empty, built all the dependencies.
    if (!is_file($html_path) || $this->isDirEmpty($html_path)) {
      $this->taskComposerCreateProject()
        ->arg('drupal-composer/drupal-project:8.x-dev')
        ->arg($html_path)
        ->option('no-interaction')
        ->option('no-install')
        ->run();

      $this->taskComposerRequire()
        ->dir($html_path)
        ->arg('wikimedia/composer-merge-plugin')
        ->option('no-update')
        ->run();

      $this->addComposerMergeFile("{$this->toolDir}/config/composer.json", "$extension_dir/composer.json");
      $this->addComposerMergeFile("$html_path/composer.json", "{$this->toolDir}/config/composer.json");

      $this->taskComposerUpdate()
        ->dir($html_path)
        ->run();
    }

    $this->taskComposerUpdate()
      ->dir($html_path)
      ->run();

    var_dump(scandir("$html_path/web/modules/custom"));
    var_dump(scandir("$html_path/web/libraries"));

    $extension_type = $this->getExtensionType($extension_dir);
    $name = $this->getExtensionName($extension_dir);

    // Create the custom directory if it doesn't already exist.
    if (!file_exists("$html_path/web/{$extension_type}s/custom")) {
      $this->_mkdir("$html_path/web/{$extension_type}s/custom");
    }
    // Ensure the extensions's directory is clean first.
    $this->_deleteDir("$html_path/web/{$extension_type}s/custom/$name");

    // Copy the extension into its appropriate path.
    $this->taskRsync()
      ->fromPath($extension_dir)
      ->toPath("$html_path/web/{$extension_type}s/custom/$name")
      ->recursive()
      ->option('exclude', 'html')
      ->run();
  }

  /**
   * @param $composer_path
   * @param $file_to_merge
   */
  protected function addComposerMergeFile($composer_path, $file_to_merge, $update = FALSE) {
    $composer = json_decode(file_get_contents($composer_path), TRUE);
    $composer['extra']['merge-plugin']['require'][] = $file_to_merge;
    $composer['extra']['merge-extra'] = TRUE;
    $composer['extra']['merge-extra-deep'] = TRUE;
    $composer['extra']['merge-scripts'] = TRUE;
    $composer['extra']['replace'] = FALSE;
    $composer['extra']['ignore-duplicates'] = TRUE;
    file_put_contents($composer_path, json_encode($composer));

    if ($update) {
      $this->taskComposerUpdate()
        ->dir(dirname($composer_path))
        ->run();
    }
  }

  /**
   * Check if the directory is empty.
   *
   * @param $dir
   *   Path to the directory.
   *
   * @return bool
   *   If the given directory is empty.
   */
  protected function isDirEmpty($dir) {
    return is_readable($dir) && (count(scandir($dir)) == 2);
  }

  /**
   * Get the machine name of the Drupal extension.
   *
   * @param string $dir
   *   Path to the extension.
   *
   * @return string
   *   Machine name.
   */
  protected function getExtensionName($dir) {
    $files = glob("$dir/*.info.yml");
    $info_file = basename($files[0]);
    return str_replace('.info.yml', '', $info_file);
  }

  /**
   * Get the Drupal extension type: module, theme, or profile.
   *
   * @param string $dir
   *   Path to the extension.
   *
   * @return string
   *   Drupal extension type.
   */
  protected function getExtensionType($dir) {
    $name = $this->getExtensionName($dir);
    $info_contents = file_get_contents("$dir/$name.info.yml");
    $matches = preg_grep('/^type:.*?$/x', explode("\n", $info_contents));
    return trim(str_replace('type: ', '', reset($matches)));
  }

}
