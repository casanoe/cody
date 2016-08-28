<?php

define("C_PATH_LIB", dirname(__FILE__)."/lib/");

require_once('error.php');

function __autoload($className) {
  $iterator = new RecursiveIteratorIterator(
              new RecursiveDirectoryIterator(C_PATH_LIB,
                  RecursiveDirectoryIterator::KEY_AS_FILENAME),
                  RecursiveIteratorIterator::SELF_FIRST);
  foreach ($iterator as $entry){
    if($entry->isFile() && strtolower($className) == strtolower(basename($iterator->current(),'.php')))
      require($iterator->getPathname());
  }
}

?>
