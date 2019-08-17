<?php

namespace Robo\Tasks;

use Robo\Tasks;

class SwsCircleCi extends Tasks {

  /**
   * @command sws-hello
   */
  public function hello() {
    $this->yell('HELLO');
  }

}
