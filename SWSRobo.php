<?php

use Robo\Tasks;

class SWSRobo extends Tasks {

  /**
   * @command sws-hello
   */
  public function hello() {
    $this->yell('HELLO');
  }


}
