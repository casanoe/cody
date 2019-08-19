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
// |         Plugin PREPROC    |
// +---------------------------+
/**
  * @subpackage cody
*/

class cl_cody_preproc {
  private $stime;

  function __construct() {;
    $this->stime = microtime(1);
  }

  function onInstall() {
    $obj = &$this->PARENT;
    return array(
      'word'  => array('#capture', '#print', '#log', '#define', '#include', '#codef', '#stop', '#plugin', '#if', '#endif', '#code'),
      'main' => true,
      'op' => array(
        //'regex' => '#[a-z]\w*|\w+|".*"|\{\{\{.*\}\}\}',
        'regex' => "<<<(\w+).*?^\\1$|^#[a-z]\w*|[a-z]\w*|\d+(\.\d+)?|\"([^\"\\\\]*(\\\\.[^\"\\\\]*)*)\"|\S",
        'regexopt' => 'ims',
        'id' => ''
      )
    );
  }

  function onStatement($w) {
    $obj = &$this->PARENT;
    switch($w) {
      case '#capture':
        $obj->cy_getArgs(C_CODY_GETWORD);
        break;
      case '#log':
        echo implode('', $obj->cy_getArgs(C_CODY_GETALLEXPR, '', ',', ''));
        break;
      case '#define':
        $args = $obj->cy_getArgs(C_CODY_GETID, C_CODY_GETEXPR);
        $obj->cy_setVar($args[0], $args[1]);
        break;
      case '#include':
        $obj->cy_getArgs(C_CODY_GETSTR);
        if (file_exists($args[0])) {
          $inccode = $obj->cy_tokenizer(file_get_contents($args[0]));
        }
        break; // TODO
      case '#endif': $obj->cy_nextTok(); break;
      case '#if':
        $args = $obj->cy_getArgs('(', C_CODY_GETBEXPR, ')');
        if (!$args[1]) $obj->blind = '#endif';
        break;
      case '#stop': return C_CODY_CODE_STOP;
      case '#plugin':
        $args = $obj->cy_getArgs(C_CODY_GETEXPR);
        $obj->plugin_install($args[0]);
        break;
      case '#print':
        $args = $obj->cy_getArgs(C_CODY_GETTEXT);
        echo $args[0];
        break;
      case '#code': // #code "plugin1,plugin2" "code"  -> Heredoc supported
        $args = $obj->cy_getArgs(C_CODY_GETEXPR, C_CODY_GETTEXT);
        $code = $args[1];
        if (empty($code)) return $obj->cy_err('Code unexpected');
        $cody = new cl_cody();
        $cody->plugin_install($args[0]);
        $cody->cy_interpret($code);
        break;
      case '#codef': // l extension du fichier indique le plugin a utiliser
        $args = $obj->cy_getArgs(C_CODY_GETEXPR);
        $code = '';
        if (file_exists($args[0])) {
          $code = file_get_contents($args[0]);
          $plugin = end(explode('.', $args[0]));
        }
        if (empty($code)) return $obj->cy_err('Code unexpected');;
        $cody = new cl_cody();
        $cody->plugin_install($plugin);
        $cody->cy_interpret($code);
        break;
    }
    return $w;
  }
}

?>
