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
  * Plugin logo utilisable avec CODY
  *
  * Plugin permettant d'interpreter un LOGO simple
  * @package sioux
  * @category plugin
  * @copyright &copy; 2004-2005 by Cyril
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |         Plugin LOGO       |
// +---------------------------+
/**
  * @subpackage cody
*/

define('C_CODY_LOGO_SCRH', 300);
define('C_CODY_LOGO_SCRW', 300);
define('C_CODY_LOGO_TIMEFILE', 600); // seconds
define('C_CODY_LOGO_FILE', uniqid('logo').'.png');

class cl_cody_logo {
  private $x, $y, $direction;
  private $up, $color;
  private $image;
  private $labels, $stack;
  public $show = 'html';   // 'header' or 'file' or 'html'
  public $logo_file = C_CODY_LOGO_FILE;

  function __construct() {
    $this->image = @imagecreatetruecolor (C_CODY_LOGO_SCRW, C_CODY_LOGO_SCRH + 20)
      or die ('Impossible de creer un flux d\'image GD');
    //$trans_colour = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
    //imagefill($this->image, 0, 0, $trans_colour);
    $this->logo_init();
  }

  function onStopInterpret() {
    $this->logo_show();
    $this->labels = $this->stack = null;
    return true;
  }

  function onStartLex() {
    $obj = &$this->PARENT;
    $obj->code = preg_replace('/^\s*\'.*$/im', '', $obj->code);
    $this->labels = $this->stack = array();
    return true;
  }

  function onInstall() {
    $obj = &$this->PARENT;
    return array(
      'main' => true,
      'func' => array(),
      'word' => array('avance', 'recule', 'droite', 'gauche', 'leve', 'baisse', 'couleur', 'repete', 'pour', 'fin', 'dans', 'ecris', 'efface'),
      'op' => array('id' => '')
    );
  }

  function onStatement($w) {
    $obj = &$this->PARENT;
    $s = 1;
    switch($w) {
      case 'efface': $this->logo_init(); break;
      case 'ecris':
        $args = $obj->cy_getArgs(C_CODY_GETEXPR);
        imagestring($this->image, 1, 5, C_CODY_LOGO_SCRH + 5, $args[0], 0);
        break;
      case 'pour':
        $args = $obj->cy_getArgs(C_CODY_GETWORD);
        if (!in_array($args[0], $obj->plugins['logo']['word']))
          $obj->plugins['logo']['word'][] = $args[0];
        $this->labels[$args[0]] = $obj->pc;
        while($obj->look != C_CODY_CODE_FALSE && $obj->look != 'fin') $obj->cy_nextTok();
        $obj->cy_nextTok();
        break;
      case 'couleur':
        $args = $obj->cy_getArgs(C_CODY_GETEXPR, ',', C_CODY_GETEXPR, ',', C_CODY_GETEXPR);
        $this->color = imagecolorallocate($this->image, $args[0], $args[2], $args[4]);
        break;
      case 'droite': $obj->cy_nextTok(); $this->direction -= $obj->cy_getExpr(); break;
      case 'gauche': $obj->cy_nextTok(); $this->direction += $obj->cy_getExpr(); break;
      case 'leve': $this->up = true; break;
      case 'baisse': $this->up = false; break;
      case 'recule': $s = -1;
      case 'avance':
        $obj->cy_nextTok();
        $v = $obj->cy_getExpr();
        $x = $this->x;
        $y = $this->y;
        $this->logo_xyMove($v*$s);
        if (!$this->up) imageline($this->image, $x, $y, $this->x, $this->y, $this->color);
        break;
      case 'repete':
        $args = $obj->cy_getArgs(C_CODY_GETEXPR, '(');
        $r = round($args[0]);
        $pcfor = $obj->pc;
        while ($obj->look && $r) {
          $obj->pc = $pcfor - 1;
          $obj->cy_nextTok();
          while($obj->look && $obj->look != ')') $w = $obj->cy_statement($obj->look);
          $r--;
        }
        $obj->cy_nextTok();
        break;
      case 'dans':
        $args = $obj->cy_getArgs(C_CODY_GETID, '=', C_CODY_GETEXPR);
        $obj->cy_setVar($args[0], $args[2]);
        break;
      case 'fin':
        if (!count($this->stack)) return $w;
        $obj->pc = array_pop($this->stack) - 1;
        $obj->cy_nextTok();
        break;
      default:
        $this->stack[] = $obj->pc + 1;
        $obj->pc = $this->labels[$w] - 1;
        $obj->cy_nextTok();
        break;
    }
    return $w;
  }

  function logo_init() {
    $this->color = imagecolorallocate ($this->image, 255, 255, 255);
    imagefilledrectangle($this->image, 0, C_CODY_LOGO_SCRH, C_CODY_LOGO_SCRW, C_CODY_LOGO_SCRH + 20, $this->color);
    imagerectangle($this->image, 0, C_CODY_LOGO_SCRH, C_CODY_LOGO_SCRW - 1, C_CODY_LOGO_SCRH + 19, 0);
    $this->x = C_CODY_LOGO_SCRW / 2;
    $this->y = C_CODY_LOGO_SCRH / 2;
    $this->up = false;
    $this->direction = 90;
  }

  function logo_xyMove($v) {
    $c = $this->direction * M_PI/180;
	  $cs = round(cos($c)*1e8)/1e8;
		$sn = round(sin($c)*1e8)/1e8;
    $this->x += $v * $cs;
    $this->y += $v * $sn;
    if ($this->x > C_CODY_LOGO_SCRW) $this->x = C_CODY_LOGO_SCRW;
      else if ($this->x < 0) $this->x = 0;
    if ($this->y > C_CODY_LOGO_SCRW) $this->y = C_CODY_LOGO_SCRH;
      else if ($this->y < 0) $this->y = 0;
  }

  function logo_show() {
    $obj = &$this->PARENT;
    imagestring($this->image, 1, 5, 5, 'Plugin LOGO for CODY - PhpSioux Project', 65535);
    $this->logo_showTurtle();
    if ($this->show == 'header') {
      header('Content-type: image/png');
      imagepng($this->image);
    } else if ($this->show == 'file') imagepng($this->image, $this->logo_file);
      else if ($this->show == 'html') {
        imagepng($this->image, $this->logo_file);
        echo '<img src=\'', $this->logo_file, '\'>';
      }
    imagedestroy($this->image);
  }

  function logo_showTurtle() {
    $turtle="R0lGODlhIAAgAPcAAAAAAIAAAACAAICAAAAAgIAAgACAgMDAwMDcwKbK8P///////4SEhMbGxv/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////78KCgpICAgP8AAAD/AP//AAAA//8A/wD//////ywAAAAAIAAgAAAIrQABCBxIkKCAgwUTKlwoUMBAhwwjJoT4UKLFhgUpXmSoEUDHjQopftzoUKNIgx4tlkzZ8CBCjCsjmnzp0aVJlRVHnrwYU2dMkg9t1hwKkiXGlhWLujyaEuJIjkgRnqSJMypNqSBfdlz6E+dSlDCzBjUo8mlIskOdUpW4cypTnkavduXZtuZctnGRpgVK1OlYo1CTqn1rlqlQrT8Lt7xJeK1YwEWhKo5MlLLMyAEBADs=";
    $im = imagecreatefromstring(base64_decode($turtle));
    $im = imagerotate($im, $this->direction, 0);
    imagecopymerge($this->image, $im, $this->x, $this->y, 0, 0, 32, 32, 80);
    imagedestroy($im);
  }

}

?>
