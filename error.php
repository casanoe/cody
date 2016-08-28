<?php

define("C_DEBUG_VIEW", 1);
define("C_DEBUG_ERROR", 2);
define("C_DEBUG_WARNING", 4);
define("C_DEBUG_VIEW_OUT", 8);
define("C_DEBUG_LOGFILE", 16);
define("C_DEBUG_BACKTRACE", 32);
define("C_DEBUG_ERROR_DIE", 64);
define("C_ERROR_VIEW_OUT", 128);

if (!isset($DEBUG)) $DEBUG = C_DEBUG_VIEW | C_DEBUG_ERROR | C_DEBUG_WARNING | C_DEBUG_BACKTRACE;

/*-------------------------------------------------------*/

function error($e, $t = 'Error:') {
  global $DEBUG;
  if ($DEBUG & C_DEBUG_ERROR) {
    debug("$e \n", $t, -1);
    if ($DEBUG & C_DEBUG_ERROR_DIE) die();
  }
  return false;
}
function errorif($bool, $e, $t = 'Error:') {
  if ($bool) return error($e, $t);
  return true;
}
function warning($w, $t = 'Warning:') {
  global $DEBUG;
  if ($DEBUG & C_DEBUG_WARNING) debug("Warning: $w \n", $t, -1);
  return true;
}
function warningif($bool, $w, $t = 'Warning:') {
  if ($bool) return warning($w, $t);
  return true;
}

/*-------------------------------------------------------*/

function debug_printr($mix) {
  ob_implicit_flush(0);
  ob_start();
  print_r($mix);
  $out = ob_get_contents();
  ob_end_clean();
  return htmlspecialchars($out);
}

if(!function_exists('debug')) {
  function debug($str, $c = '') {
    echo "<pre><b>Debug <u>$c</u>:</b>\n".debug_printr($str)."<pre>\n";
    //echo "<pre><b>Debug <u>$c</u>:</b>\n";
    //print_r($str);
    echo "<pre>\n";
  }
}
?>
