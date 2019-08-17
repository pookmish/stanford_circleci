<?php

namespace SWSCircleCi\Commands;

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * @command phpunit
   */
  public function phpunit($root_path) {
    if ($this->isDirEmpty($root_path)) {
      $this->taskComposerCreateProject()
        ->arg('drupal-composer/drupal-project:8.x-dev')
        ->option($root_path)
        ->option('no-interaction')
        ->run();

      $this->taskComposerRequire()
        ->option('wikimedia/composer-merge-plugin')
        ->run();

      $this->taskComposerConfig()
        ->option('xtra.merge-plugin.require', '../test/composer.json')
        ->run();
    }
    else {
      $this->taskComposerUpdate()->run();
    }

    $this->_symlink("$root_path/web", "$root_path/docroot");
    $this->_deleteDir("$root_path/docroot/modules/custom");
    $this->_mkdir("$root_path/docroot/modules/custom");
    $this->_copy(dirname(dirname(dirname(__FILE__))) . '/config/phpunit.xml', "$root_path/docroot/core/phpunit.xml", TRUE);

    $this->_symlink("$root_path/../test", "$root_path/docroot/modules/custom");

    $this->taskExec('../vendor/bin/phpunit')
      ->dir("$root_path/docroot")
      ->option('config', 'core', '=')
      ->arg('modules/custom')
      ->run();
  }

  protected function isDirEmpty($dir) {
    if (!is_readable($dir)) {
      return NULL;
    }
    return (count(scandir($dir)) == 2);
  }

}
