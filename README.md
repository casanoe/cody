# php Cody
Simple interpreter code in PHP

###Summary


##Class features
- Compatible with php 5 and later
- Build your own simple language (words and functions)
- Plugins system
- Begin with a few examples: basic and logo plugins
- More plugins and repository coming soon and based on **Cody** (template system...)

##Licence
This software is distributed under the [GPLv3](http://www.gnu.org/licenses/gpl.html) licence. Please read LICENSE for information on the software availability and distribution.

##Installation
Just copy or sync this repository.
Two ways to load Cody class and his plugins:

1- Load the config.php file 
```php
require_once("config.php");
```
In the config file you can change libray path if you want:
```php
define("C_PATH_LIB", "path_of_cody_class_and_plugins_files");
```
2- Place php files where you want, but put these 2 lines of code:
```php
require_once("mypath/cl_cody.php");
define('C_CODY_DIR_PLUGINS', "path_of_plugins_files"); // optional, directory "plugin" by default
```

##Simple examples

1- Basic plugin
```php
<?php
require_once('config.php');

$code = '
print "Table of Square, Sin<br>"
let $num = rnd(2,10)  
print $num, "values<br>"
for $i=1 to $num gosub "calc"  next
end
label calc
  print $i, "-->", $i*$i, "-->", sin($i)
return
'

$cody = new cl_cody();
$cody->plugin_install('basic,math');
$cody->cy_interpret($code);

?>
```

2- Preproc plugin
```php
<?php
require_once('config.php');

$code = '
#define myvar 45

#print "Print a simple text"

#code "microbasic" "print 100+255"

#code "basic"
<<<BASIC
let $a=4
let $b=5
$myvar = $myvar + 100.5
print "a+b=", $a+$b
BASIC

#print
<<<TXT
Ceci est un texte <b>html</b><br>
TXT

#print "Myvar is now equal to ", myvar'

$cody = new cl_cody();
$cody->plugin_install('preproc');
$cody->cy_interpret($code);

?>
```

3- Logo plugin

```php
<?php
require_once('config.php');

$code = 'pour carre avance 90 droite 90 avance 90 droite 90 avance 90 droite 90 avance 90 droite 90 fin
  pour rectangle avance 90 droite 90 avance 50 droite 90 avance 90 droite 90 avance 50 droite 90 fin
  pour plcarre repete 36 (carre droite 10) fin
  pour cercle dans X=36 dans Y=5 repete X (droite 360/X avance Y) fin
  pour rosace 
    couleur 255, 255, 0   
    dans X = 36
    dans Y = 5
    dans C = 0
    repete X (
      dans C = C + 10
      couleur 255, 255 - C, C 
      droite 360/X
      repete X (
        droite 360/X
        avance Y 
      ))
  fin
  ecris "Test rosace..."
  rosace'
  
$cody = new cl_cody();
$cody->plugin_install('logo');
$cody->cy_interpret($code);
?>
```

##Short documentation
See plugins in the repository. There are a few interesting cases using Cody class.

###How to create a plugin ?
The first difficult thing is to know how to implement the lexical of the language.

Cody is not an advanced tool for that. It use only regex to cut your code into interpretable tokens, and can only interpret classical expression like that: 
```
12+4-(3+var)
```
Luckily Cody comes with a default lexical rule like Basic. 

A plugin should be written like that:
```php
<?php
class cl_cody_myplugin {

  function onInstall() {
    return array(
      'main' => true, // True if this plugin control all others
      'func'  => array('rnd'), // List of words corresponding to your functions
      'word'      => array(';', ':', '$', 'if', 'end', 'print', 'let') // List of words corresponding to your statements
    );
  }

  // Called when interpreter found a word corresponding to an existing function
  function onFunction($args, $f) {
    switch($f) {
      case 'mid': return substr($args[0], $args[1], $args[2]);
    }
  }
  // Called when interpreter found a word corresponding to the beginning of a statement
  function onStatement($w) {
    $obj = &$this->PARENT; // In order to call internal functions or variables of Cody Class (parent)
    switch($w) {
      case ';': case ':': $obj->cy_nextTok(); break; // No effect. Move one token forward.
      case 'if':
        $args = $obj->cy_getArgs('(', C_CODY_GETBEXPR, ')', 'then'); // cy_getArgs is used to control and get the right tokens or expressions
    	  $b = $args[1]; // Result of the boolean expression
    	  while ($obj->look && $obj->look != 'endif') { // Look around util the end of the "if" statement
    	    if ($obj->look == 'else' && $obj->cy_nextTok()) $b = !$b;
    	      else if ($b) {
    	    	  $w = $obj->cy_statement($obj->look); // Interpret the code in the "if" or "else" statement
    	      } else $obj->cy_nextTok(); // This code haven't to be interpret, so move forward
    	  }
        $obj->cy_nextTok();
    		break;
      case 'end': return C_CODY_CODE_STOP; // Stop interpreter
      case 'print':
        echo implode('', $obj->cy_getArgs(C_CODY_GETALLEXPR, '', ',', '')); // Get all expressions results (separated with a coma)
        break;
      case '$': $obj->pc--; // Move back (one token)
      case 'let':
        $args = $obj->cy_getArgs(C_CODY_GETID, '=', C_CODY_GETEXPR);
        $obj->cy_setVar($args[0], $args[2]); // Set a variable
        break;
?>
```

##Contributing
Please submit bug reports, suggestions and pull requests to the [GitHub issue tracker](https://github.com/casanoe/cody/issues).

##Changelog
See [changelog](changelog.md).
