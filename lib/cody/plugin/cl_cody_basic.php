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
  * Plugin tiny basic language for Cody class
  *
  * Plugin allowing to interpret a simple BASIC
  * @package sioux
  * @category plugin
  * @copyright &copy; 2004-2005 by Cyril
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |         Plugin BASIC      |
// +---------------------------+
/**
  * @subpackage cody
*/

class cl_cody_basic {
  public $labels, $stack;

  function __construct() {}

  function onStopInterpret() {
    $this->labels = $this->stack = null;
    return true;
  }

  function onStartLex() {
    $obj = &$this->PARENT;
    $this->labels = $this->stack = array();
    $obj->code = preg_replace('/^\s*rem .*$/im', '', $obj->code);
    return true;
  }

  function onStartInterpret() {
    $obj = &$this->PARENT;
    $c = $obj->cmd;
    $obj->cmd = array();
    for($k = $i = 0; $k < count($c); $k++)
      if (strtolower($c[$k]) == 'label' && $c[$k + 1] != '(') {
        $this->labels[$c[++$k]] = $i;
      } else $obj->cmd[$i++] = $c[$k];
    return true;
  }

  function onInstall() {
    return array(
      'main' => true,
      'func'  => array('count', 'debug', 'mid', 'lcase', 'ucase', 'len', 'instr', 'sprintf', 'rnd', 'repl', 'date', 'time', 'mtime', 'urlenc', 'htmlenc', 'fcontents'),
      'word'  => array(';', ':', '$', 'die', 'dim', 'obj', 'on', 'clear', 'gosub', 'goto', 'return', 'if', 'end', 'exit', 'run', 'print', 'let', 'for', 'rem', 'label')
    );
  }

  function onFunction($args, $f) {
    switch($f) {
      case 'debug': return '<pre>'.var_export($args[0], true).'</pre>';
      case 'mid': return substr($args[0], $args[1], $args[2]);
      case 'lcase': return strtolower($args[0]);
      case 'ucase': return strtoupper($args[0]);
      case 'len': return strlen($args[0]);
      case 'count': return count($args[0]);
      case 'instr': return strpos($args[0], $args[1]);
      case 'sprintf': return vsprintf(array_shift($args), $args);
      case 'rnd': return mt_rand($args[0], $args[1]);
      case 'repl': return str_replace($args[0], $args[1], $args[2]);
      case 'date': return date($args[0]);
      //case 'phpcst': return constant($args[0]);
      //case 'phpglb': return $GLOBALS[$args[0]];
      //case 'phpeval': return @eval($args[0]);
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
      case 'rem': $obj->cy_err('Do not use [rem] here'); break;
      case 'die': die();
      case 'dim': $obj->cy_setVar($obj->cy_needId(), array()); break;
      case 'obj': $obj->cy_setVar($obj->cy_needId(), new object()); break;
      case 'clear': $obj->vars = array(); break;
      case 'label':
        $label = $obj->cy_nextTok();
        if (!isset($this->labels[$label])) $this->labels[$label] = $obj->pc + 1;
        $obj->cy_nextTok();
        break;
      case 'on':
        $args = $obj->cy_getArgs(C_CODY_GETEXPR, array('goto', 'gosub'));
        $ww = $args[1];
        $g = $args[0];
        $args = $obj->cy_getArgs(C_CODY_GETALLEXPR, '', ',', '');
        $args[0] = $args[intval($g)];
      case 'gosub':
      case 'goto':
        if ($w == 'gosub' ||$ww == 'gosub') $this->stack[] = $obj->pc + 2;
        if ($w != 'on') $args = $obj->cy_getArgs(C_CODY_GETEXPR);
        if (!isset($this->labels[$args[0]])) $obj->cy_err('['.$args[0].' ?] unknown label');
        $obj->pc = $this->labels[$args[0]] - 1;
        $obj->cy_nextTok();
        break;
      case 'return':
        if (!count($this->stack)) $obj->cy_err('Gosub expected');
        $obj->pc = array_pop($this->stack) - 1;
        $obj->cy_nextTok();
        break;
      case 'if':
        $args = $obj->cy_getArgs('(', C_CODY_GETBEXPR, ')', 'then');
    	  $b = $args[1];
    	  while ($obj->look && $obj->look != 'endif') {
    	    if (($obj->look == 'else' && $obj->cy_nextTok())) $b = !$b;
    	      else if ($b) {
    	    	  $w = $obj->cy_statement($obj->look);
    	      } else $obj->cy_nextTok();
    	  }
        $obj->cy_nextTok();
    		break;
      case 'end': case 'exit': return C_CODY_CODE_STOP;
      case 'run':
        $args = $obj->cy_getArgs('(', C_CODY_GETEXPR, ')');
        if (!file_exists($args[1])) $obj->cy_err('no file '.$args[1]);
        $obj->cy_lex(file_get_contents($args[1]));
        $obj->cy_startInterpret();
        $obj->cy_nextTok();
        break;
      case 'print':
        $args = $obj->cy_getArgs(C_CODY_GETALLEXPR, '', ',', '');
        echo implode('', $args);
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

  function basic_include($file) {
    @include($file);
  }

}

?>
