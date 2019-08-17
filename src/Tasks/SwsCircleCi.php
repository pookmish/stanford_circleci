<?php

namespace Robo\Tasks;

use Robo\Tasks;

class SwsCircleCi extends Tasks {

  public static function setup() {
    $tasks = new static();
    $tasks->hello();
  }

  /**
   * @command sws-hello
   */
  public function hello() {
    $this->yell('HELLO');
  }


}
