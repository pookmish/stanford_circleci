<?php

namespace SWSCircleCi\Commands;

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * @command phpunit
   */
  public function phpunit($root_path) {
    if (!is_file($root_path) || $this->isDirEmpty($root_path)) {
      $this->taskComposerCreateProject()
        ->arg('drupal-composer/drupal-project:8.x-dev')
        ->arg($root_path)
        ->option('no-interaction')
        ->run();

      $this->taskComposerRequire()
        ->dir($root_path)
        ->arg('wikimedia/composer-merge-plugin')
        ->run();

      $this->taskComposerConfig()
        ->dir($root_path)
        ->arg('extra.merge-plugin.require')
        ->arg('../composer.json')
        ->run();
    }

    $this->taskComposerUpdate()
      ->dir($root_path)
      ->run();

    $extension_type = $this->getExtensionType("$root_path/../");
    $name = $this->getExtensionName("$root_path/../");

    $this->_deleteDir("$root_path/web/{$extension_type}s/custom");
    $this->_mkdir("$root_path/web/{$extension_type}s/custom");

    $this->_copy(dirname(dirname(dirname(__FILE__))) . '/config/phpunit.xml', "$root_path/web/core/phpunit.xml", TRUE);
    $this->taskRsync()
      ->fromPath("$root_path/../")
      ->toPath("$root_path/web/{$extension_type}s/custom/$name")
      ->recursive()
      ->option('exclude', 'html')
      ->dir("$root_path/../")
      ->run();

    $this->taskExec('../vendor/bin/phpunit')
      ->dir("$root_path/web")
      ->option('config', 'core', '=')
      ->arg("{$extension_type}s/custom/$name")
      ->run();
  }

  protected function isDirEmpty($dir) {
    if (!is_readable($dir)) {
      return NULL;
    }
    return (count(scandir($dir)) == 2);
  }

  protected function getExtensionName($dir) {
    $files = glob("$dir/*.info.yml");
    $info_file = basename($files[0]);
    return str_replace('.info.yml', '', $info_file);
  }

  protected function getExtensionType($dir) {
    $name = $this->getExtensionName($dir);
    $info_contents = file_get_contents("$dir/$name.info.yml");
    $matches = preg_grep('/^type:.*?$/x', explode("\n", $info_contents));
    return trim(str_replace('type: ', '', reset($matches)));
  }

}
