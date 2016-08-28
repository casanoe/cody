<?php
// +--------------------------------------------------------------------------+
// |   CODY     |   php library                                               |
// +--------------------------------------------------------------------------+
// | Copyright (c) 2004-2005 by Cyril <phpsioux@free.fr>.                     |
// +--------------------------------------------------------------------------+
// | Ce code est distribu� sous license GNU GPL                               |
// | Pour toute information suppl�mentaire :                                  |
// |   http://www.gnu.org/copyleft/gpl.html                                   |
// | http://phpsioux.free.fr    |    phpsioux@free.fr                         |
// +--------------------------------------------------------------------------+

/**
  * Plugin internal language for Cody class
  *
  * Plugin allowing to launch internal function of CODY
  * @package sioux
  * @category plugin
  * @copyright &copy; 2004-2005 by Cyril
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |         Plugin MARKDOWN       |
// +---------------------------+
/**
  * @subpackage cody
*/

class cl_cody_markdown {
  private $stack = array();
  private $cr = false;
  private $icl = 0;

  function __construct() {
  }

  function onStartLex() {
    $obj = &$this->PARENT;
    $obj->code = str_replace("\r\n", "\n", $obj->code);
    return true;
  }
  function onInstall() {
    $obj = &$this->PARENT;
    //$obj->op['regex'] = '[A-Za-z0-9]+|\-{3,}|"".*""|[ ]+\-|\*+|\%+|\++|\$+|\{+|_+|\{\{+|\S|\s';
    //$obj->op['regex'] = '"".*""|[ ]+\-|\*{2,}|\%{2,}|\+{2,}|\${2,}|\{{2,}|_{2,}|\s|\S'.$obj->op['regex'];
    /*$obj->op['regex'] = //'\[(http|local|https|irc|gopher|ftp|mailto|news):\/\/|'.
        "\n|\r|(\W)\\1*|\s|".
        $obj->op['regex'];*/
        //$obj->op['regex'] = "\[\[[^\]]+\]\]|\{\{[^\}]+\}\}|([='$\-\*#])\\1*|\n|\r|[^='$\-\*#\[\]\{\}]+";
    return array(
      'main' => true,
      'func'  => array(),
      'word'  => array(),
      'default' => true
    );
  }

  function onStatement($w) {
    $obj = &$this->PARENT;
    switch($w) {
      case "\n": case "\r":
          $this->cr = true;
          $obj->cy_nextTok();
          if ($this->icl != 0) echo $this->_tag('li');
          else echo "<br>\n";
          return $w;
      case "'''": echo $this->_tag('b'); break;
      case "''": echo $this->_tag('i'); break;
      case "'''''": echo $this->_tag('ib', '<b><i>', '</i></b>'); break;
      case '=': echo $this->_tag('h1'); break;
      case '==': echo $this->_tag('h2'); break;
      case '===': echo $this->_tag('h3'); break;
      case '====': echo $this->_tag('h4'); break;
      case '$$': echo $this->_tag('s'); break;
      case '$$$': echo $this->_tag('u'); break;
      case '$$$$$': echo $this->_tag('su', '<s><u>', '</u></s>'); break;
      case '---': echo $this->_ifcr('<hr>', $w); break;
      case '*':case '**': case '***': echo $this->_ul($w); break;
      case '#':case '##': case '###': echo $this->_ul($w, ''); break;
      default:
          switch($w[0].$w[1]) {
            case '""':
              $w = trim($w, '"');
              $obj->cy_nextTok();
              echo $w;
              break;
          case '[[':
              $w = trim($w, '[]');
              $obj->cy_nextTok();
              $s = explode('|', $w, 2);
              if (count($s) > 1)
                  echo "<a href=$s[1]>$s[0]</a>";
              else
                 echo "<a href=$w>$w</a>";
              break;
          default:
              $obj->cy_nextTok();
              echo $w;
              break;
          }
    }
    if ($this->cr) {
        echo str_repeat('</ul>', $this->icl);
        $this->icl = 0;
    }
    $this->cr = false;
    return $w;
  }
  function _ifcr($yes, $no) {
      $obj = &$this->PARENT;
      $obj->cy_nextTok();
      if ($this->cr) return $yes;
      else return $no;
  }
  function _ul($w, $o = '') {
    $obj = &$this->PARENT;
    $obj->cy_nextTok();
    if (!$this->cr) return $w;
    $this->cr = false;
    $l = strlen($w);
    $d = $this->icl - $l;
    $ret = ($d < 0)?"<ul$o>":(($d > 0)?str_repeat('</ul>', $d):'');
    $this->icl = $l;
	return $ret.$this->_tag('li');
  }
  function _tag($tag, $beg = null, $end = null) {
    $obj = &$this->PARENT;
    $k = array_search($tag, $this->stack);
    $obj->cy_nextTok();
    if ($beg == null) $beg = "<$tag>";
    if ($end == null) $end = "</$tag>";
    if ($k !== false) {
        unset($this->stack[$k]);
        return $end;
    } else {
        $this->stack[] = $tag;
        return $beg;
    }
  }
}
  ?>
