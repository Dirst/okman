<?php

namespace Dirst\OkTools;

use Dirst\OkTools\OkToolsBase;

/**
 * Abstract class for other control classes.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
abstract class OkToolsBaseControl
{
  // @var OkToolsBase object.
  protected $okToolsBase;

  /**
   * Init Account control object.
   *
   * @param OkToolsBase $okTools
   *   Ok Tools Base object.
   */
  public function __construct(OkToolsBase $okTools)
  {
      $this->okToolsBase = $okTools;
  }
}
