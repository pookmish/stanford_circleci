<?php

namespace SWSCircleCi\Commands;

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * Run phpunit tests on the given extension.
   *
   * @param string $html_path
   *   Path to the drupal project.
   * @param string $extension_dir
   *   Path to the drupal extension.
   *
   * @command phpunit
   */
  public function phpunit($html_path, $extension_dir = NULL) {
    $extension_dir = is_null($extension_dir) ? "$html_path/../" : $extension_dir;
    $this->setupDrupal($html_path, $extension_dir);

    $extension_type = $this->getExtensionType($extension_dir);
    $name = $this->getExtensionName($extension_dir);

    $this->_copy(dirname(dirname(dirname(__FILE__))) . '/config/phpunit.xml', "$html_path/web/core/phpunit.xml", TRUE);

    $this->taskPhpUnit()
      ->dir("$html_path/web")
      ->option("$html_path/web/{$extension_type}s/custom/$name")
      ->configFile('core')
      ->option('filter', '/(Unit|Kernel)/')
      ->run();
  }

  /**
   * Modify the PHPUnit config to use test suites from our library.
   *
   * @param string $config_path
   *   Path to phpunit.xml.
   */
  protected function setPhpUnitTestSuites($config_path) {
    $dom = new \DOMDocument();
    $dom->load($config_path);
    $dom->getElementsByTagName('file')->item(3)->nodeValue = dirname(dirname(__FILE__)) . '/TestSuites/NonFunctionTestSuite.php';
    file_put_contents($config_path, $dom->saveXML());
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
        ->run();

      $this->taskComposerRequire()
        ->dir($html_path)
        ->arg('wikimedia/composer-merge-plugin')
        ->run();

      $this->taskComposerConfig()
        ->dir($html_path)
        ->arg('extra.merge-plugin.require')
        ->arg('../composer.json')
        ->run();
    }

    $this->taskComposerUpdate()
      ->dir($html_path)
      ->run();

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
