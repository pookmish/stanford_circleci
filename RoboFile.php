<?php

use Robo\Tasks;

class RoboFile extends Tasks {

  /**
   * @command sws-hello
   */
  public function hello() {
    $this->yell('HELLO');
  }


}
