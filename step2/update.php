<?php
set_time_limit(60);
ini_set('memory_limit', '256M'); //TODO: Analyze memory usage instead of increasing limit

define('AUTOMATIC_REPLACE_ENABLED', true);


$regexCodeBlock = '\{[^{}]*\}';
$regexCodeBlockLevel1 = "\{(?:[^{}]|(?:{$regexCodeBlock}))*\}";
$regexCodeBlockLevel2 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel1}))*\}";
$regexCodeBlockLevel3 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel2}))*\}";
$regexCodeBlockLevel4 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel3}))*\}";
$regexCodeBlockLevel5 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel4}))*\}";
$regexCodeBlockLevel6 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel5}))*\}";
$regexCodeBlockLevel7 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel6}))*\}";
$regexCodeBlockLevel8 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel7}))*\}";
$regexCodeBlockLevel9 = "\{(?:[^{}]|(?:{$regexCodeBlockLevel8}))*\}";
$regexCodeBlock = "\s*{$regexCodeBlockLevel9}\s*";
$regexAnonymousFunction = "function\s*\([^()]*\){$regexCodeBlock}";
$regexVariable = '[\$A-Za-z_0-9]+';
$regexString = '(?:\"[^\"]*\"|\'[^\']*\')';
$regexNumber = '(?:-?[0-9]+(?:\.[0-9]+)?(?:E-?[0-9]+(?:\.[0-9]+)?)?)';
$regexRegex = '\/.+\/[ig]*'; // WARNING: Creates a huge amount of false positives! Don't rely on this by itself.


$variable = [
  "HbC7FRYovGY",
  "ACT90oEkumd7ZZHZBfs7AsobzkUGs6LJNA"
]; // These seem to change constantly.

require 'lib/definitions.php';

if (file_exists('res/temp-script-original.js')) {
  $content = file_get_contents('res/temp-script-original.js');
} else {
  $base = 'https://www.google.com';
  $scriptFilename = "$base/maps/_/js/k=maps.m.en.{$variable[0]}.O/m=b/d=1/rs={$variable[1]}";
  $content = file_get_contents($scriptFilename);
  file_put_contents('res/temp-script-original.js', $content);
}


// Simple replacments
$replacements = [
  ["\r\n", "\n"], // Removes other line endings
  ["\r",   "\n"], // Removes other line endings

  ["content:\"\";",   ""],
  ["content:'';",   ""],

  ["\ntry{\n",                          "\n"], // Removes top-level try blocks
  ["\n}catch(e){_._DumpException(e)}\n", "\n"], // Removes top-level catch blocks

  ["this._=this._||{};(function(_){var window=this;\n", "this._=this._||{};var window=this;"], // Removes first function definition
  ["\n})(this._);\n", "\n"], // Removes last function definition
  ["'use strict';", "\n"], // Removes strict calls

  ["this._ = this._ || {};\nvar window = this;", ""],
];
$content = replaceManyByBasic($replacements, $content);


// CSS Replacements
$properties = [
  'animation(-[a-z]+)*',
  'background(-[a-z]+)*',
  'border(-(top|left|right|bottom))?(-(color|radius|style|width))?',
  'box(-(shadow|sizing))',
  'clear',
  'color',
  'cursor',
  'display',
  'flex',
  'float',
  'font(-[a-z]+)*',
  '((max|min|line)-)height',
  'margin(-(top|left|right|bottom))?',
  'opacity',
  'outline(-[a-z]+)?',
  'overflow(-(x|y))?',
  'pointer-events',
  'padding(-(top|left|right|bottom))?',
  'position',
  'text-[a-z]+',
  'transform',
  'translate',
  'transition(-[a-z]+)*',
  'user-select',
  'vertical-align',
  'visibility',
  'white-space',
  'z-index',
  '-moz(-[A-z]+)+',
  '-ms(-[A-z]+)+',
  '-webkit(-[A-z]+)+',
];

$regex = '/(min|max)?(' . implode('|', $properties) . "){$c(R_CSS_COLON)}[^;\"\{\}]+;/";
$content = preg_replace($regex, '', $content);

$regex = '/(min|max)?(' . implode('|', $properties) . "){$c(R_CSS_COLON)}[^;\"\{\}]+\}/";
$content = preg_replace($regex, '}', $content);


// Regex replacments (before beautify)
$replacements = [
  ["(background|display|border(-(top|left|right|bottom))?)(-[a-z]+)?{$c(R_CSS_COLON)}[^;\"\{\}]+;", ""],
  //['/\n\}\)\(this\._\);\n/', "\n"], // Removes last function definition
  ["((top|left|right|bottom|width|height|z-index|margin|padding):{$c(R_CSS_VALUE)};)", ""],
  ["((top|left|right|bottom|width|height|z-index|margin|padding):{$c(R_CSS_VALUE)}\}\")", "}\""],
  ["(((background|border)-)?color){$c(R_CSS_COLON)}(#[0-9a-f]{3,6}|[a-z]+);?", ""],
  ["((text|vertical)-align){$c(R_CSS_COLON)}(center|left|right|top|bottom);?", ""],
  ["#[A-Fa-f0-9]{6}", ""],

  ["\s*!1", " false"],
  ["\s*!0", " true"],
	//["\"[a-z]+[A-Z][a-z]+\"", "\"\""],

  ["\"{$c(R_CSS_MANY_SELECTORS)}\{\}", "\""],
  ["'{$c(R_CSS_MANY_SELECTORS)}\{\}", "'"],
  ["background(-image)?:url\(\"[^;\"\{\}]+\"\)[^;\"\{\}]*;", ""],
  ["background(-image)?:url\(\"[^;\"\{\}]+\"\)[^;\"\{\}]*\}", "}"],
  ["\"[-.,:+a-z\s()]+\{\}\"", "\"\""], // All empty CSS selectors
  ["'[-.,:+a-z\s()]+\{\}'", "''"], // All empty CSS selectors

  ["_\.[A-z]+\(\"about:blank\"\);", ""],
  ["_\.[A-z]+\(\"<!DOCTYPE html>\", 0\);", ""],
  ["_\.C\s*\([^()]*\);\n?", ""], // Removing all calls to _.C()
  ["_\.A\s*\(\"[^()\"]+\"\);\n?", ""], // Removing all calls to _.A(string)
  ["_\.z\s*\([^()]*\);\n?", ""], // Removing all calls to _.C()

  ["\s*\<\/?([A-Za-z]+)\>\s*", ""], // Any XML tag. For example, <hr> <h1> </h1> </someTHINGcrazy>

  ["\s*=\s*\"(\.|\/|#|@)[^\"]+\";", " = \"\";"], // Clearing all strings that begin with ./#@
  ["\s*=\s*'(\.|\/|#|@)[^']+';", " = '';"], // Clearing all strings that begin with ./#@

  // Replacing _.Xb(something) with the actual function code
  ["_\.Xb\((_\.[A-Za-z]+)\);", "\$1.Bb = function () {\n\tif (!\$1.HI) {\n\t\t\$1.HI = new \$1;\n\t}\n\treturn \$1.HI;\n};"],

];
$content = replaceManyByRegex($replacements, $content);


// Beautification
$content = beautify($content);


// Moving definitions away from function calls
$definitionContent = '';


// Repeatedly remove empty variables (at the beginning)
$definitionContent .= "//Empty variables.\n";
$regex = "/\\n(?:var\s+)({$regexVariable}),\s*/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "var ${definition};\n";
  }
  $content = preg_replace($regex, "\nvar ", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);
$definitionContent .= "\n\n";


// Repeatedly remove empty variables (in the middle)
$definitionContent .= "//Empty variables.\n";
$regex = "/,\n\t({$regexVariable})\s*,\s*/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "var ${definition};\n";
  }
  $content = preg_replace($regex, ",\n\t", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);
$definitionContent .= "\n\n";


// Regex replacments (after beautify)
$replacements = [
  [",\n\t([\$A-Za-z_0-9]+(?:\s*=\s*|;))", ";\nvar \$1"],
];
$content = replaceManyByRegex($replacements, $content);


function separateDefinitionFromContent($regex, $content, $onMatch) {
	global $definitionContent;
	global $content;
	$matches = [];
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
		$addition = $onMatch($definition);
    $definitionContent .= $addition;
  }
  $content = preg_replace($regex, "\n", $content);
  if ($content === null) {
    print("Error! Invalid regex!");
    print(get_last_preg_error());
    throw new Exception("Error!");
    exit;
  }
}

// New functions (that may begin with var)
$definitionContent .= "//New function.\n";
$regex = "/\\n(?:var\s+)?({$regexVariable}\s*=\s*{$regexAnonymousFunction});/";
separateDefinitionFromContent($regex, $content, function($definition) {
  return "var ${definition};\n";
});
$definitionContent .= "\n\n";


// New function in the underscore object
$definitionContent .= "//New function in underscore.\n";
$regex = "/\\n_\.({$regexVariable}\s*=\s*{$regexAnonymousFunction});/";
separateDefinitionFromContent($regex, $content, function($definition) {
  return "_.${definition};\n";
});
$definitionContent .= "\n\n";

// Empty variable declaration
$definitionContent .= "//Empty variable declarations.\n";
$regex = "/\\n(var\s+{$regexVariable});/";
separateDefinitionFromContent($regex, $content, function($definition) {
  return "${definition};\n";
});
$definitionContent .= "\n\n";


// Repeatedly remove constants
$definitionContent .= "//New constants.\n";
$regex = "/\\n(?:var\s+)({$regexVariable}\s*=\s*(?:{$regexString}|false|true|{$regexNumber}|{$regexRegex}|\{\}));/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "var ${definition};\n";
  }
  $content = preg_replace($regex, "", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);
$definitionContent .= "\n\n";


$functionsToClear = [
  "Iba[A-Za-z\.]*",

  "[CH-Ji-joq]zc",
  "nM",

  "[JMRUn]ba",
  "[KJPQTX]da",
	"[moq]Ea",
	"[abe-il-puw-zA-COP]Xa",
  "[fgjkMO]ea",
  "[a-xzA-Z]Ea",
	"[yABF]ka",
	"[nCK]la",
  "wpa",
  "hnb",
  "[\$adhpvF-HZ]0a",
  ".Ob",
  ".[vyz]a",
  "[\$gij-ps-yBCFH-LY]wc",
  "[glrv]xc",
  "[cegptT]zc",

  ".[LW]a",
  "[a-gijlx-z]1a",
];
$functionsToRemove = [
  "_\.z",
  "_\.(Rda|Jd)",

  "oc\.prototype\.(V)",
  "qM\.prototype\.(ka)",
  "(.f)\.prototype\.[\$a-zA-z]+",
  "(?:_\.)?([m]c|[\$flnpwxLV-Z]e|[deghjm-su-wyzA]f|.s|[f-hklA]t|[Q-V]G|nM|gea|[sGJN]da|[foCH]zc|Wyc|.[bf-hMR]a|[o-u]Hb)\.prototype\.([\$a-zA-z]+)",
	"_\.(?:LXa|Sda)",

  "_\.gr",
];
// Regex replacments (after beautify)
$replacements = [
  // Emptying functions
  ["var\s+(" . implode("|", $functionsToClear) . ")\s*=\s*{$regexAnonymousFunction};", "if (!\$1) var \$1 = function () {};"],
  ["\\n(" . implode("|", $functionsToClear) . ")\s*=\s*{$regexAnonymousFunction};", ""],
  ["var\s+(" . implode("|", $functionsToRemove) . ")\s*=\s*{$regexAnonymousFunction};", ""],
  ["\\n(" . implode("|", $functionsToRemove) . ")\s*=\s*{$regexAnonymousFunction};", ""],

	//["\s+\(0,\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\)\(", " \$2("], // TODO: not working

  // Removes the if block at line 51.
  ["if \(\"undefined\" == typeof window.globals \|\| !_\.Tb\(window\.globals\.ErrorHandler\)\)${regexCodeBlock};", ""],

  ["\s*=\s*\"(\.|\/|#|@)[^\"]+\";", " = \"\";"], // Clearing all strings that begin with ./#@
  ["\s*=\s*'(\.|\/|#|@)[^']+';", " = '';"], // Clearing all strings that begin with ./#@
];
$definitionContent = replaceManyByRegex($replacements, $definitionContent);
$content = replaceManyByRegex($replacements, $content);




// Strings ""
$definitionContent .= "//Strings\n";
$regex = "/\\n((?:var\s+|_\.)?${regexVariable}\s*=\s*${regexString});/";
separateDefinitionFromContent($regex, $content, function($definition) {
  return "${definition};\n";
});
$definitionContent .= "\n\n";


$regex = "/\\n{4,}/";
$content = preg_replace($regex, "\n", $content);



// Automatic Constant Replacement (slowest so far)
if (AUTOMATIC_REPLACE_ENABLED) {
  $strings = [];
  $matches = [];
  $count = preg_match_all('/[^A-z_](_\.[_A-z]+)="([^"]{1,2})"/', $content, $matches);
  if ($count === false) {
    print("Error! Invalid regex!");
    exit;
  }
  for ($i = 0; $i < $count; $i++) {
    $variableName = $matches[1][$i];
    $stringValue  = $matches[2][$i];
    $strings[] = [$variableName, $stringValue];
  }
  foreach ($strings as $replacement) {
    $regex = '/([^A-z0-9])(' . preg_quote($replacement[0]) . ')([^=A-z0-9-])/';
    $substitute = '$1"' . preg_quote($replacement[1]) . '"$3';

    $matches = [];
    $content = preg_replace($regex, $substitute, $content);
    if ($content === null) {
			echo "Error!\n";
      print(get_last_preg_error());
      exit;
    }
  }
}




// Extra code for "definition content"
$definitionContent = '
var map_url = ["/maps/_/js/","m","maps.m.en.'
. $variable[0]
. '.O","'
. $variable[1]
. '"]; // Unsure of its real purpose
window.APP_OPTIONS=[,,,[,,,,,,[[""],,[""],,,,,"/maps/vt"],,,,,,,"//kh.google.com/rt/earth"],,,,,,,,,,,,,,,,,,,,,,[1,],,,,,,,,,,,,,,map_url,,,,,,,,,,,,,,,,,,,1];
' . $definitionContent;


// Extra code for "content"
$content .= '';

echo "Finished processing.\n";

file_put_contents('res/temp-script-processed-definitions.js', $definitionContent);
file_put_contents('res/temp-script-processed-content.js', $content);
