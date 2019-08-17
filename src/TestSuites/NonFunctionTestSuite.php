<?php

namespace Drupal\Tests\TestSuites;

use Drupal\simpletest\TestDiscovery;
use PHPUnit\Framework\TestSuite;

/**
 * NonFunctional test suite.
 *
 * Scans all module extensions and looks for tests in the Unit & Kernel
 * namespaces.
 */
class NonFunctionTestSuite extends TestSuite {

  /**
   * Factory method which loads up a suite with all functional tests.
   *
   * @return static
   *   The test suite.
   */
  public static function suite() {
    // Figure out the $docroot dynamically.
    $docroot = dirname(dirname(dirname(__DIR__))) . '/docroot';
    $suite = new static('nonfunctional');
    var_dump($docroot);
    var_dump(__DIR__);
//    $suite->addTestsBySuiteNamespace($docroot, 'Unit');
//    $suite->addTestsBySuiteNamespace($docroot, 'Kernel');
    return $suite;
  }

  /**
   * Finds extensions in a Drupal installation.
   *
   * An extension is defined as a directory with an *.info.yml file in it.
   *
   * @param string $root
   *   Path to the root of the Drupal installation.
   *
   * @return string[]
   *   Associative array of extension paths, with extension name as keys.
   */
  protected function findExtensionDirectories($root) {
    $extension_roots = \drupal_phpunit_contrib_extension_directory_roots($root);
    $extension_directories = array_map('drupal_phpunit_find_extension_directories', $extension_roots);
    return array_reduce($extension_directories, 'array_merge', []);
  }

  /**
   * Find and add tests to the suite for core and any extensions.
   *
   * @param string $docroot
   *   Path to the root of the Drupal installation.
   * @param string $suite_namespace
   *   SubNamespace used to separate test suite. Examples: Unit, Functional.
   */
  protected function addTestsBySuiteNamespace($docroot, $suite_namespace) {
    foreach ($this->findExtensionDirectories($docroot) as $extension_name => $dir) {
      $test_path = "$dir/tests/src/$suite_namespace";
      if (is_dir($test_path) && strpos($dir, '/custom/') !== FALSE) {
        $this->addTestFiles(TestDiscovery::scanDirectory("Drupal\\Tests\\$extension_name\\$suite_namespace\\", $test_path));
      }
    }
  }

}
