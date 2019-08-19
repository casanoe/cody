<?php
// +--------------------------------------------------------------------------+
// |   cody   |   php class: interpreteur de code simple                      |
// +--------------------------------------------------------------------------+
// | Copyright (c) 2004-2005 by Cyril                                         |
// +--------------------------------------------------------------------------+
// | Ce code est distribué sous license GNU GPL                               |
// | Pour toute information supplémentaire :                                  |
// |   http://www.gnu.org/copyleft/gpl.html                                   |
  // | http://phpsioux.free.fr                                                |
// +--------------------------------------------------------------------------+

/**
  * Class CODY
  *
  * Classe permettant d'interpreter un code simple de type "Basic"
  * @package phpsioux
  * @category library
  * @copyright &copy; 2004-2005 by Cyril
  * @link http://phpsioux.free.fr
  * @version 1.0
  * @license GNU GPL license : http://www.gnu.org/copyleft/gpl.html
*/

// +---------------------------+
// |         CODY              |
// +---------------------------+
/**
  * @subpackage cody
*/
define('C_CODY_ID', 0);

define('C_CODY_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);
if (!defined('C_CODY_DIR_PLUGINS'))
  define('C_CODY_DIR_PLUGINS', dirname(__FILE__).DIRECTORY_SEPARATOR.'plugin'.DIRECTORY_SEPARATOR);
define('C_CODY_LOGFILE', 'cody.log');

define('C_CODY_GETEXPR', 1);
define('C_CODY_GETBEXPR', 2); // test expression
define('C_CODY_GETFLOAT', 3);
define('C_CODY_GETSTR', 4);
define('C_CODY_GETID', 5);
define('C_CODY_GETALLEXPR', 6);
define('C_CODY_GETWORD', 7);
define('C_CODY_GETCST', 8); // number or string
define('C_CODY_GETANY', 9); // all token until one specific token
define('C_CODY_GETONE', 10); //
define('C_CODY_GETHEREDOC', 11);
define('C_CODY_GETTEXT', 12); // heredoc or expr
define('C_CODY_GETOPT', 99);

define('C_CODY_CODE_STOP', false);
define('C_CODY_CODE_FALSE', null);
define('C_CODY_CODE_FREEZE', 'Zzz');

define('C_CODY_PFX_VAR', 'v_cy_var_');
define('C_CODY_PFX_FUNC', 'f_cy_function_');

define('C_CODY_CONF_FILE', 'f_cody_conf.ini.php');

/*=================================================*/

class codyException extends Exception {
  public function __construct($message, $code = 0) {
    if ($code === 0) {
      if (function_exists('error')) {
        error($message, 'Cody');
      } else echo "Cody: $message";
      parent::__construct('Cody Exception detected:'.$message, $code);
    }
  }
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }
}

/*=================================================*/

class cl_cody {
  protected $curw;
  public $plugins = array();
  public $cmd = array(), $vars = array(), $look, $pc;
  public $blind = '';
  public $err;
  protected $op = array(
    'mul' => array('*', '/', '%', '^'),
    'add' => array('+', '-'),
    'test' => array('or', 'and', 'xor'),
    'un' => array('-', 'not'),
    'comp' => array('eq', 'neq', 'lt', 'le', 'gt', 'ge'),
    'id' => '$',
    'func' => array('(', ',', ')'),
    'regex' => '[a-zA-Z]\w*|\d+(\.\d+)?|"([^"\\\\]*(\\\\.[^"\\\\]*)*)"|\S',
    'regexopt' => 'ims'
  );

  /**
    * cl_cody constructor
    * @param string code a interpreter
  */
  function __construct($code = '') {
    if ($code) $this->cy_interpret($code);
  }

  /**
    * Fonction permettant de decouper un code source en tokens
    * @param string code source
    * @return array tokens
  */
  final private function cy_tokenizer($code) {
    preg_match_all('/'.$this->op['regex'].'/'.$this->op['regexopt'], $code, $c);
    return $c[0];
  }
  /**
    * Fonction intermediaire au lancement de l'interpreteur
    * @param string source
    * @see cy_startInterpret
    * @see cy_startLex
  */
  final private function cy_lex($code) {
    $this->code = $code;
    if (!$this->cy_startLex()) return false;
    $this->cmd = $this->cy_tokenizer($this->code);
    $this->cmd[] = null;
    return true;
  }
  /**
    * Procedure de lancement de l'interpreteur de code
    * @param string source
  */
  final public function cy_interpret($code) {
    if (!$this->cy_startInterpret($code)) return false;
    unset($this->code);
    try {
      while ($this->look && $this->cy_statement($this->look)) $this->curw = $this->pc;
    } catch (codyException $e) {}
    return $this->cy_stopInterpret();
  }
  /**
    * Interprete un code source pas a pas
    * @param string code source a transmettre au premier appel
    * @return boolean false pour la fin de traitement
  */
  final public function cy_interpretByStep($code) {
    if ($code && !$this->cy_startInterpret($code)) return false;
    try {
      $bool = $this->look && $this->cy_statement($this->look);
      $this->curw = $this->pc;
    } catch (codyException $e) {$this->cy_stopInterpret(); return false;}
    return $bool;
  }
  /**
    * Premiere etape avant le debut de l'interpretation de code
    * Permet la tokenisation du code et le pre traitement par les plugins ('onStartInterpret')
    * et initialise les variables de travail
    * @param string code source
    * @see cy_lex
    * @see cy_interpret
  */
  final protected function cy_startInterpret($code) {
    $this->pc = -1;
    $this->curw = 0;
    $this->err = 0;
    return ($this->cy_lex($code) && $this->cy_nextTok() && $this->plugin_execAll('onStartInterpret'));
  }
  /**
    * Derniere etape de l'interpretation de code
    * Permet le pre traitement par les plugins ('onStopInterpret')
    * et initialise les variables de travail
    * @see cy_startInterpret
    * @see cy_interpret
  */
  final protected function cy_stopInterpret() {
    $this->cmd = null;
    return $this->plugin_execAll('onStopInterpret');
  }
  /**
    * Etape avant la tokenisation
    * @see cy_lex
  */
  final protected function cy_startLex() {
    $this->cmd = array();
    return $this->plugin_execAll('onStartLex');
  }

  //------------------------------ PLUGINS / FUNCTIONS
  /**
    * Configure un plugin
    * Dans l'ordre: lance la fonction interne 'OnConfigure' avec ses arguments
    * ou modifie les variables internes (de type public)
    * Si le plugin n'est pas installe, il sera charge par defaut.
    * @param string nom du plugin
    * @param mixed arguments de la fonction 'onConfigure', ou tableau tel que (nom_var=>valeur, ...)
    * @return mixed false si rien ne s'est passe, true si les variables ont ete modifiees, retour de 'onConfigure' sinon
  */
  final function plugin_configure() {
    $args = func_get_args();
    $plugin = array_shift($args);
    if (!isset($this->plugins[$plugin]))
      $this->plugin_install($plugin);
    $obj = &$this->plugins[$plugin]['class'];
    if (isset($args[0]) && method_exists($obj, 'onConfigure'))
      return call_user_func_array(array($obj, 'onConfigure'), $args);
      else if (is_array($args[0])) {
        foreach($args[0] as $p=>$v) $obj->$p = $v;
        return true;
      }
    return false;
  }
  /**
    * Installe un ou plusieurs plugins
    * @param string nom du ou des plugin (separes par des virgules)
    * @param string repertoire du ou des plugins (default='plugin')
  */
  final function plugin_install($plugins, $path = C_CODY_DIR_PLUGINS) {
    foreach(explode(',', $plugins) as $plugin) {
      if (isset($this->plugins[$plugin])) continue;
      $class = 'cl_cody_'.$plugin;
      if (!file_exists($path.'/'.$class.'.php'))
        return $this->cy_err('Plugin not found');
      require_once($path.$class.'.php');
      $obj = new $class();
      $obj->PARENT = &$this;
      $this->plugins[$plugin] = $obj->onInstall();
      $this->plugins[$plugin]['class'] = $obj;
      $plug = $this->plugins[$plugin];
      // Verification des regles d enregistrement des plugins
      if (isset($plug['main']) && $plug['main'] === true) {
        if (isset($this->plugins['main'])) return $this->cy_err('Just one main plugin permitted');
        else $this->plugins['main'] = $plugin;
      }
      if (isset($plug['dependof'])) {
        $bool = false;
        foreach($plug['dependof'] as $v)
          $bool = $bool || isset($this->plugins[$v]);
        if (!$bool) $this->cy_err($plugin.' depend of another plugin which is not present.');
      }
      if (isset($plug['require']))
        $this->plugin_install($plug['require']);
      if (isset($plug['op'])) {
        if ($plug['main'] !== true) return $this->cy_err('Only main plugin can change operators');
        $this->op = array_merge($this->op, $plug['op']);
      }
    }
  }
  /**
    * Renvoie une reference vers la classe d'un plugin
    * @param string nom du plugin
    * @return mixed reference vers la classe du plugin ou false
  */
  final function &plugin_getClass($plugin) {
    if (isset($this->plugins[$plugin]))
      return $this->plugins[$plugin]['class'];
    return false;
  }
  /**
    * Desinstalle un ou plusieurs plugin
    * @param string nom des plugins (separates par des virgules)
  */
  final function plugin_unInstall($plugins) {
    foreach(explode(',', $plugins) as $plugin) {
      $obj = &$this->plugins[$plugin]['class'];
      if (method_exists($obj, 'onStopInterpret')) $obj->onStopInterpret();
      unset($this->plugins[$plugin]);
    }
  }
  /**
    * Fonction de recherche d'un mot cle (fonction ou mot reserve)
    * en vue de son interpretation par le plugin concerne
    * @param string mot a rechercher
    * @param string type='word' ou 'func' (contexte de la recherche)
    * @return mixed classe du premier plugin contenant le mot recherche
  */
  final function plugin_getWordClass($call, $type) {
      $c = null;
      foreach($this->plugins as $k=>$v) {
          if (isset($v[$type]) && in_array($call, $v[$type], true))
              return $v['class'];
          if (isset($v['default']) && $v['default']) $c = $k;
      }
    if ($c) return $this->plugins[$c]['class'];
        else return false;
  }
  /**
    * Execute une fonction dans tous les plugins (si elle existe)
    * @param string nom de la methode
    * @param mixed arguments
    * @return boolean retour des fonctions executees ('et' logique sur chaque retour)
  */
  final function plugin_execAll() {
    $args = func_get_args();
    $method = array_shift($args);
    $bool = true;
    foreach($this->plugins as $k=>$v) {
      if (isset($v['class']) && method_exists($v['class'], $method))
        $bool = call_user_func_array(array($v['class'], $method), $args) && $bool;
    }
    return $bool;
  }
  /**
    * Test si un plugin est installe
    * @param string nom du plugin
    * @return boolean
  */
  final function plugin_exists($plugin) {
    return isset($this->plugins[$plugin]);
  }

  //------------------------------

  /**
  * @desc Saute au prochain token, incremente le pointeur 'pc'
  * @return string mot suivant du code
  */
  final function cy_nextTok() {
    $this->look = $this->cmd[++$this->pc];
    return $this->look;
  }
  /**
  * @desc Saute jusqu'au token indique
  * @return string mot ou false
  */
  final function cy_gotoTok($tok, $b = true) {
      if ($this->cy_needAny($tok, $b))
          return $tok;
      else
        return false;
  }
  /**
  * @desc Decale le pointeur
  * @return string mot
  */
  final function cy_walkTok($j) {
    $this->pc = abs($this->pc + $j);
    return $this->look = $this->cmd[$this->pc];
  }

  final function cy_err($w, $err = 1) {
    $c = implode(' ', array_slice($this->cmd, $this->curw, $this->pc - $this->curw));
    $this->err = $err;
    echo htmlspecialchars("Error:: $w, near statement $c (pc=".$this->pc.')');
    throw new codyException("Error:: $w, near statement $c (pc=".$this->pc.')', $err);
    return false;
  }
  /**
  * @desc Teste la presence d'un nombre flottant et renvoie sa valeur
  * @param boolean s'il ne s'agit pas d'un nombre, provoque une erreur (true) ou renvoie C_CODY_CODE_FALSE sinon
  * @return
  */
  final function cy_needFloat($b = true) {
    if (ctype_digit($this->look{0})) {
      $float = (float)$this->look;
      $this->cy_nextTok();
      return $float;
    }
    return $b?$this->cy_err('number expected'):C_CODY_CODE_FALSE;
  }

  final function cy_needTok($tok, $b = true) {
    if ($this->look == $tok || (is_array($tok) && in_array($this->look, $tok))) {
      $this->cy_nextTok();
      return $tok;
    }
    return $b?$this->cy_err('token \''.implode(' | ', array($tok)).'\' expected'):C_CODY_CODE_FALSE;
  }

  final function cy_needWord($b = true) {
    if (ctype_alpha($this->look{0})) {
      $w = $this->look;
      $this->cy_nextTok();
      return $w;
    }
    return $b?$this->cy_err('word expected'):C_CODY_CODE_FALSE;
  }

  final function cy_needAny($e = '', $b = true) {
    $w = $this->look;
    while ($this->cy_nextTok() && $this->look != $e) {
      $w .= $this->look;
    }
    if ($this->look == $e) {
        $this->cy_nextTok();
        return $w;
    }
    return $b?$this->cy_err('word expected'):C_CODY_CODE_FALSE;
  }

  final function cy_needId($b = true) {
    $s = $this->op['id'];
    if ( ($s && ($this->look != $s || !ctype_alpha($this->cmd[$this->pc + 1]{0}))) ||
    //     (!$s && (!ctype_alpha($this->look{0}) || $this->cmd[$this->pc + 1] == '(')) )
      ($s == '' && (!ctype_alpha($this->look{0}) )) )
      return $b?$this->cy_err('identifier expected'):C_CODY_CODE_FALSE;
    if ($s) $this->cy_nextTok();
    $id = $this->cy_needWord();
    while($this->look == '[' || $this->look ==  '.') {
      $c = $this->look;
      $this->cy_nextTok();
      if ($c == '[') {$arr[] = $this->cy_getExpr(); $this->cy_needTok(']'); }
        else $arr[] = $this->cy_needWord();
    }
    if (isset($arr)) {array_unshift($arr, $id); return $arr;}
    return $id;
  }

  final function cy_needStr($b = true) {
    if ($this->look{0} == '"') {
      $str = substr($this->look, 1, -1);
      $this->cy_nextTok();
      return $str;
    }
    return $b?$this->cy_err('string expected'):C_CODY_CODE_FALSE;
  }

  final function cy_needHereDoc($b = true) {
    if (preg_match_all("/<<<(\w+)(.*)^\\1$/ism", $this->look, $out) > 0) {
      $this->cy_nextTok();
      return $out[2][0];
    }
    return $b?$this->cy_err('HereDoc expected'):C_CODY_CODE_FALSE;
  }

  //-----------------------------

  final function &cy_getVar($var) {
    if (is_array($var)) {
      $val = &$this->cy_getUserVar(array_shift($var));
      foreach($var as $v)
        if (is_array($val)) $val = &$val[$v];
          else if (is_object($val)) $val = &$val->$v;
      return $val;
    }
    return $this->cy_getUserVar($var);
  }

  final function cy_setVar($var, $val) {
    $v = &$this->cy_getVar($var);
    $v = $val;
  }

  function &cy_getUserVar($var) {
    //return $this->vars[$var];
    return $GLOBALS[C_CODY_PFX_VAR.$var];
  }

  //------------------------------

  final function cy_getBoolExpr($b = true) {
    if ($this->look == $this->op['un'][1]) {$this->cy_nextTok(); $e1 = !$this->cy_getBoolExpr($b);}
      else if ($this->look == '(') {$this->cy_nextTok(); $e1 = $this->cy_getBoolExpr($b); $this->cy_needTok(')');}
        else $e1 = $this->cy_getExpr($b);
    if ($this->look == $this->op['comp'][0]) {$this->cy_nextTok(); $b = ($e1 == $this->cy_getExpr());}
      else if ($this->look == $this->op['comp'][1]) {$this->cy_nextTok(); $b = ($e1 != $this->cy_getExpr());}
        else if ($this->look == $this->op['comp'][2]) {$this->cy_nextTok(); $b = ($e1 < $this->cy_getExpr());}
          else if ($this->look == $this->op['comp'][3]) {$this->cy_nextTok(); $b = ($e1 <= $this->cy_getExpr());}
            else if ($this->look == $this->op['comp'][4]) {$this->cy_nextTok(); $b = ($e1 > $this->cy_getExpr());}
              else if ($this->look == $this->op['comp'][5]) {$this->cy_nextTok(); $b = ($e1 >= $this->cy_getExpr());}
                else $b = $e1?true:false;
    if ($this->look == $this->op['test'][0]) {$this->cy_nextTok(); $b = ($this->cy_getBoolExpr() || $b);}
      else if ($this->look == $this->op['test'][1]) {$this->cy_nextTok(); $b = ($this->cy_getBoolExpr() && $b);}
        else if ($this->look == $this->op['test'][2]) {$this->cy_nextTok(); $b = ($this->cy_getBoolExpr() xor $b);}
    return $b;
  }

  final private function cy_getTerm($b = true) {
    $f = $this->cy_getFactor($b);
    if ($this->look == $this->op['mul'][0]) {$this->cy_nextTok(); $f *= $this->cy_getTerm();}
      else if ($this->look == $this->op['mul'][1]) {$this->cy_nextTok(); $f /= $this->cy_getTerm();}
        else if ($this->look == $this->op['mul'][2]) {$this->cy_nextTok(); $f %= $this->cy_getTerm();}
          else if ($this->look == $this->op['mul'][3]) {$this->cy_nextTok(); $f = pow($f, $this->cy_getTerm());}
    return $f;
  }

  final function cy_getExpr($b = true) {
    $t = $this->cy_getTerm($b);
    if ($this->look == $this->op['add'][0]) {
      $this->cy_nextTok();
      $e = $this->cy_getExpr();
      if (!is_string($e)) $t += $e; else $t .= $e.'';
    } else if ($this->look == $this->op['add'][1]) {$this->cy_nextTok(); $t -= $this->cy_getExpr();}
    return $t;
  }

  final private function cy_getFactor($b = true) {
    $w = $this->look;
    if ($w == '(') {
      $this->cy_nextTok();
      $e = $this->cy_getExpr();
      $this->cy_needTok(')');
      return $e;
    } else if (ctype_digit($w{0})) return $this->cy_needFloat();
        else if ($w{0} == '"' ) return $this->cy_needStr();
          else if ($need = $this->cy_needId(false)) return $this->cy_getVar($need);
            else if (ctype_alpha($w{0})) return $this->cy_getFunc();
              else return $b?$this->cy_err("[$w ?] variable, constant or function expected"):C_CODY_CODE_FALSE;
  }

  final private function cy_getFunc() {
    $f0 = $this->look;
    $f = C_CODY_PFX_FUNC.$f0;
    $args = $this->cy_getArgs(C_CODY_GETALLEXPR, $this->op['func'][0], $this->op['func'][1], $this->op['func'][2]);
    if ($func = $this->plugin_getWordClass($f0, 'func')) return $func->onFunction($args, $f0);
      else if (function_exists($f)) return $f($args, $f0);
        else return $this->cy_execFunc($args, $f0);
  }

  protected function cy_execFunc($args, $f) {
    return $this->cy_err("[$f ?] unknown function");
  }

  //------------------------------

  final function cy_getArgs() {
    $args = func_get_args();
    //if (is_array($args[0])) $args = $args[0];
    $n = count($args);
    $uargs = array();
    $b = true;
    $this->cy_nextTok();
    for($i = 0; $i < $n; $i++) {
      $a = $args[$i];
      switch($a) {
        case C_CODY_GETONE: $uargs[] = $this->look; $this->cy_nextTok(); break;
        case C_CODY_GETOPT: $b = !$b; continue 2;
        case C_CODY_GETANY: $this->cy_needAny($args[++$i], $b); break;
        case C_CODY_GETEXPR: $uargs[] = $this->cy_getExpr($b); break;
        case C_CODY_GETCST:
          if (ctype_digit($this->look{0})) $uargs[] = $this->cy_needFloat($b);
            else $uargs[] = $this->cy_needStr($b);
          break;
        case C_CODY_GETBEXPR: $uargs[] = $this->cy_getBoolExpr($b); break;
        case C_CODY_GETFLOAT: $uargs[] = $this->cy_needFloat($b); break;
        case C_CODY_GETSTR: $uargs[] = $this->cy_needStr($b); break;
        case C_CODY_GETHEREDOC: $uargs[] = $this->cy_needHereDoc($b); break;
        case C_CODY_GETTEXT:
          if ($this->look{0} == '<') $uargs[] = $this->cy_needHereDoc($b);
            else $uargs[] = $this->cy_getExpr($b);
          break;
        case C_CODY_GETID: $uargs[] = $this->cy_needId($b); break;
        case C_CODY_GETWORD: $uargs[] = $this->cy_needWord($b); break;
        case C_CODY_GETALLEXPR:
          if ($n < ($i + 3)) return false;
          $s = $args[++$i]; $p = $args[++$i]; $e = $args[++$i];
          if ($s) $this->cy_needTok($s);
          while ($this->look != $e) {
            $uargs[] = $this->cy_getExpr();
            if ($this->look != $e)
              if ($this->look != $p) {
                if ($e != '') $this->cy_needTok($p); else break;
              } else $this->cy_needTok($p);
          }
          if ($e) $this->cy_needTok($e);
          break;
        default:
          $uargs[] = $this->cy_needTok($a, $b);
      }
      //if (!$bb && !$b) $bb = ($uargs[count($uargs)-1] !== null);
      if ($uargs[count($uargs)-1] === null && !$b)  break;
      $b = true;
    }
    return $uargs;
  }

  final private function cy_blind() {
    $b = true;
    if ($this->look == $this->blind) {
      $this->blind = '';
      $b = false;
    }
    $this->cy_nextTok();
    return $b;
  }

  function cy_statement($w) {
    if ($this->blind && $this->cy_blind()) return $w;
    $o = $this->plugin_getWordClass($w, 'word'); // Retrait ref &
    if ($o)
      return $o->onStatement($w);
            else return $this->cy_err("[$w ?] statement expected");
  }
}

/*
class cl_cody_extension_example extends cl_cody {

  function cl_cody_extension_example($code = '') {
  	parent::cl_cody($code);
  }

  function &cy_getUserVar($var) {
  	return $GLOBALS[$var];
  }

  function cy_startInterpret() {
    parent::cy_startInterpret();
    $this->vars = array("mavariable"=>123);
  }

  function cy_execFunc($args, $f) {
    $n = count($args);
    switch($f) {
      case 'test': return "Ceci est une fonction test, avec $n parametres.";
      default: return parent::cy_execFunc($args, $f);
    }
  }

  function cy_statement($w) {
    switch($w) {
      case 'printsum':
        $args = $this->cy_getArgs(C_CODY_GETALLEXPR, '', ',', '');
        $s = 0;
        foreach($args as $v) $s += $v;
        echo $s;
        break;
      case 'test':
        $args = $this->cy_getArgs(C_CODY_GETFLOAT, C_CODY_GETOPT, C_CODY_GETSTR, C_CODY_GETSTR, C_CODY_GETOPT, C_CODY_GETID);
        echo implode(',', $args);
        break;
      default:
        return parent::cy_statement($w);
    }
    return $w;
  }
}
*/

?>
