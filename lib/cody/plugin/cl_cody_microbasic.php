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
  * Plugin micro basic utilisable avec CODY
  *
  * Plugin permettant d'interpreter un BASIC tres simple
  * @package sioux
  * @category plugin
  * @copyright &copy; 2004-2005 by Cyril <phpsioux@free.fr>
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |    Plugin MICROBASIC      |
// +---------------------------+
/**
  * @subpackage cody
*/

class cl_cody_microbasic {

  function onInstall() {
    return array(
      'main' => true,
      'func'  => array('mid', 'lcase', 'ucase', 'len', 'instr', 'rnd', 'repl', 'date', 'time', 'mtime', 'urlenc', 'htmlenc', 'fcontents'),
      'word'      => array(';', ':', '$', 'if', 'end', 'print', 'let', 'for')
    );
  }

  function onFunction($args, $f) {
    switch($f) {
      case 'mid': return substr($args[0], $args[1], $args[2]);
      case 'lcase': return strtolower($args[0]);
      case 'ucase': return strtoupper($args[0]);
      case 'len': return strlen($args[0]);
      case 'instr': return strpos($args[0], $args[1]);
      case 'rnd': return mt_rand($args[0], $args[1]);
      case 'repl': return str_replace($args[0], $args[1], $args[2]);
      case 'date': return date($args[0]);
      case 'time': return time();
      case 'mtime': return microtime(true);
      case 'urlenc': return urlencode($args[0]);
      case 'htmlenc': return htmlspecialchars($args[0]);
      case 'fcontents': return file_get_contents($args[0]);
    }
  }

  function onStatement($w) {
    $obj = &$this->PARENT;
    switch($w) {
      case ';': case ':': $obj->cy_nextTok(); break;
      case 'if':
        $args = $obj->cy_getArgs('(', C_CODY_GETBEXPR, ')', 'then');
    	  $b = $args[1];
    	  while ($obj->look && $obj->look != 'endif') {
    	    if ($obj->look == 'else' && $obj->cy_nextTok()) $b = !$b;
    	      else if ($b) {
    	    	  $w = $obj->cy_statement($obj->look);
    	      } else $obj->cy_nextTok();
    	  }
        $obj->cy_nextTok();
    		break;
      case 'end': return C_CODY_CODE_STOP;
      case 'print':
        echo implode('', $obj->cy_getArgs(C_CODY_GETALLEXPR, '', ',', ''));
        break;
      case '$': $obj->pc--;
      case 'let':
        $args = $obj->cy_getArgs(C_CODY_GETID, '=', C_CODY_GETEXPR);
        $obj->cy_setVar($args[0], $args[2]);
        break;
      case 'for':
        $args = $obj->cy_getArgs(C_CODY_GETID, '=', C_CODY_GETEXPR, 'to', C_CODY_GETEXPR, C_CODY_GETOPT, 'step', C_CODY_GETEXPR);
        $pcfor = $obj->pc;
        $obj->cy_setVar($args[0], $args[2]);
        if ($args[2] > $args[4]) $obj->cy_err('start value less than end value in \'for\' statement');
        $step = ($args[5] === null)?1:$args[6];
        while ($obj->look && $args[2] <= $args[4]) {
          $obj->pc = $pcfor - 1;
          $obj->cy_nextTok();
          while($obj->look && $obj->look != 'next') $w = $obj->cy_statement($obj->look);
          $args[2] += $step;
          $obj->cy_setVar($args[0], $args[2]);
        }
        $obj->cy_nextTok();
        break;
    }
    return $w;
  }
}

?>
