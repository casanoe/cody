<?php
// +--------------------------------------------------------------------------+
// |   CODY     |   php library                                               |
// +--------------------------------------------------------------------------+
// | Copyright (c) 2004-2005 by Cyril <phpsioux@free.fr>.                     |
// +--------------------------------------------------------------------------+
// | Ce code est distribue sous license GNU GPL                               |
// | Pour toute information supplementaire :                                  |
// |   http://www.gnu.org/copyleft/gpl.html                                   |
// | http://phpsioux.free.fr                                                  |
// +--------------------------------------------------------------------------+

/**
  * Plugin mathematic for Cody class
  *
  * Plugin allowing to calculate mathematic funtion
  * @package sioux
  * @category plugin
  * @copyright &copy; 2004-2005 by Cyril
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |         Plugin MATH       |
// +---------------------------+
/**
  * @subpackage cody
*/

class cl_cody_math {

  function __construct() {}

  function onInstall() {
    return array(
      'func'      => array('sin', 'cos', 'tan', 'log', 'dechex', 'hexdec', 'base', 'abs', 'sqrt'),
      'word'      => array(),
      'dependof'  => array('basic', 'microbasic') // depend du plugin basic OU microbasic
    );
  }

  function onFunction($args, $call) {
    switch($call) {
      case 'sin': return sin($args[0]);
      case 'cos': return cos($args[0]);
      case 'tan': return tan($args[0]);
      case 'log': return log($args[0], (isset($args[1]))?$args[1]:10);
      case 'dechex': return dechex($args[0]);
      case 'hexdec': return hexdec($args[0]);
      case 'base': return base_convert($args[0], $args[1], $args[2]);
      case 'abs': return abs($args[0]);
      case 'sqrt': return sqrt($args[0]);
    }
  }
}
?>
