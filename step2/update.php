<?php
set_time_limit(60);
ini_set('memory_limit', '256M'); //TODO: Analyze memory usage instead of increasing limit

define('AUTOMATIC_REPLACE_ENABLED', false);

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


// Regex replacments
$replacements = [
  ["(background|display|border(-(top|left|right|bottom))?)(-[a-z]+)?{$c(R_CSS_COLON)}[^;\"\{\}]+;", ""],
  //['/\n\}\)\(this\._\);\n/', "\n"], // Removes last function definition
  ["((top|left|right|bottom|width|height|z-index|margin|padding):{$c(R_CSS_VALUE)};)", ""],
  ["((top|left|right|bottom|width|height|z-index|margin|padding):{$c(R_CSS_VALUE)}\}\")", "}\""],
  ["(((background|border)-)?color){$c(R_CSS_COLON)}(#[0-9a-f]{3,6}|[a-z]+);?", ""],
  ["((text|vertical)-align){$c(R_CSS_COLON)}(center|left|right|top|bottom);?", ""],
  ["#[A-Fa-f0-9]{6}", ""],

  ["\"{$c(R_CSS_MANY_SELECTORS)}\{\}", "\""],
  ["'{$c(R_CSS_MANY_SELECTORS)}\{\}", "'"],
  ["background(-image)?:url\(\"[^;\"\{\}]+\"\)[^;\"\{\}]*;", ""],
  ["background(-image)?:url\(\"[^;\"\{\}]+\"\)[^;\"\{\}]*\}", "}"],
  ["\"[-.,:+a-z\s()]+\{\}\"", "\"\""], // All empty CSS selectors
  ["'[-.,:+a-z\s()]+\{\}'", "''"], // All empty CSS selectors

  ["_\.C\s*\([^()]*\);\n?", ""], // Removing all calls to _.C()
  ["_\.A\s*\(\"[^()\"]+\"\);\n?", ""], // Removing all calls to _.A(string)
  ["_\.z\s*\([^()]*\);\n?", ""], // Removing all calls to _.C()

  ["\s*\<\/?([A-Za-z]+)\>\s*", ""], // Any XML tag. For example, <hr> <h1> </h1> </someTHINGcrazy>

  ["\s*=\s*\"(\.|\/|#|@)[^\"]+\";", " = \"\";"], // Clearing all strings that begin with ./#@
  ["\s*=\s*'(\.|\/|#|@)[^']+';", " = '';"], // Clearing all strings that begin with ./#@

];
$content = replaceManyByRegex($replacements, $content);


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
      print(get_last_preg_error());
      exit;
    }
  }
}


// Beautification
$content = beautify($content);


// Moving definitions away from function calls
$definitionContent = '';

$regexCodeBlock = '\{[^{}]*\}';
$regexCodeBlockLevel1 = "\{(?:[^{}]|(?:${regexCodeBlock}))*\}";
$regexCodeBlockLevel2 = "\{(?:[^{}]|(?:${regexCodeBlockLevel1}))*\}";
$regexCodeBlockLevel3 = "\{(?:[^{}]|(?:${regexCodeBlockLevel2}))*\}";
$regexCodeBlockLevel4 = "\{(?:[^{}]|(?:${regexCodeBlockLevel3}))*\}";
$regexCodeBlockLevel5 = "\{(?:[^{}]|(?:${regexCodeBlockLevel4}))*\}";
$regexCodeBlockLevel6 = "\{(?:[^{}]|(?:${regexCodeBlockLevel5}))*\}";
$regexCodeBlockLevel7 = "\{(?:[^{}]|(?:${regexCodeBlockLevel6}))*\}";
$regexCodeBlockLevel8 = "\{(?:[^{}]|(?:${regexCodeBlockLevel7}))*\}";
$regexCodeBlockLevel9 = "\{(?:[^{}]|(?:${regexCodeBlockLevel8}))*\}";
$regexVariable = '\$?[A-Za-z_0-9]+';
$regexString = '(?:\"[^\"]*\"|\'[^\']*\')';
$regexAnonymousFunction = "function\s*\([^()]*\)\s*${regexCodeBlockLevel9}";


// Repeatedly remove functions at the end of a multi-variable declaration.
$regex = "/\s*,\s*\\n\\t(${regexVariable}\s*=\s*${regexAnonymousFunction});/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "//New function (found at end of multi-variable declaration).\nvar ${definition};\n\n";
  }
  $content = preg_replace($regex, ";", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);


// Repeatedly remove functions at the beginning of a multi-variable declaration.
$regex = "/\\n(?:var\s+)(${regexVariable}\s*=\s*${regexAnonymousFunction}),(\\n|\\t)*/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "//New function (found at start of multi-variable declaration).\nvar ${definition};\n\n";
  }
  $content = preg_replace($regex, "\nvar ", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);


// Repeatedly remove functions in the middle of a multi-variable declaration.
$regex = "/,\\n\\t(${regexVariable}\s*=\s*${regexAnonymousFunction})/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "//New function (found in middle of multi-variable declaration).\nvar ${definition};\n\n";
  }
  $content = preg_replace($regex, "", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);


// New functions (that may begin with var)
$regex = "/\\n(?:var\s+)?(${regexVariable}\s*=\s*${regexAnonymousFunction});/";
preg_match_all($regex, $content, $matches);
foreach ($matches[0] as $index => $match) {
  $definition = $matches[1][$index];
  $definitionContent .= "//New function.\nvar ${definition};\n\n";
}
$content = preg_replace($regex, "\n", $content);


// New function in the underscore object
$regex = "/\\n_\.(${regexVariable}\s*=\s*${regexAnonymousFunction});/";
preg_match_all($regex, $content, $matches);
foreach ($matches[0] as $index => $match) {
  $definition = $matches[1][$index];
  $definitionContent .= "//New function in underscore.\n_.${definition};\n\n";
}
$content = preg_replace($regex, "\n", $content);


// Empty variable declaration
$definitionContent .= "//Empty variable declarations.\n";
$regex = "/\\n(var\s+${regexVariable});/";
preg_match_all($regex, $content, $matches);
foreach ($matches[0] as $index => $match) {
  $definition = $matches[1][$index];
  $definitionContent .= "${definition};\n";
}
$content = preg_replace($regex, "", $content);


// Empty variable declaration
$definitionContent .= "//Empty variable declarations.\n";
$regex = "/\\n(var\s+${regexVariable}(,\s+${regexVariable})+);/";
preg_match_all($regex, $content, $matches);
foreach ($matches[0] as $index => $match) {
  $definition = $matches[1][$index];
  $definitionContent .= "${definition};\n";
}
$definitionContent .= "\n\n";
$content = preg_replace($regex, "", $content);


// Repeatedly remove strings at the beginning of a multi-variable declaration.
$regex = "/\\n(?:var\s+)(${regexVariable}\s*=\s*${regexString}),(\\n|\\t)*/";
do {
  preg_match_all($regex, $content, $matches);
  foreach ($matches[0] as $index => $match) {
    $definition = $matches[1][$index];
    $definitionContent .= "//New string (found at start of multi-variable declaration).\nvar ${definition};\n\n";
  }
  $content = preg_replace($regex, "\nvar ", $content);
} while (isset($matches[0]) && count($matches[0]) > 0);


// Strings ""
$definitionContent .= "//Strings\n";
$regex = "/\\n((?:var\s+|_\.)?${regexVariable}\s*=\s*${regexString});/";
preg_match_all($regex, $content, $matches);
foreach ($matches[0] as $index => $match) {
  $definition = $matches[1][$index];
  $definitionContent .= "${definition};\n";
}
$definitionContent .= "\n\n";
$content = preg_replace($regex, "", $content);



$regex = "/\\n{4,}/";
$content = preg_replace($regex, "\n", $content);


// Extra code for "definition content"
$definitionContent = 'var lat = -76.7128387, lon = 39.8836807;

var map_url = ["/maps/_/js/","m","maps.m.en.'
. $variable[0]
. '.O","'
. $variable[1]
. '"]; // Unsure of its real purpose
window.APP_OPTIONS=[,,,[,,,,,,[[""],,[""],,,,,"/maps/vt"],,,,,,,"//kh.google.com/rt/earth"],,,,,,,,,,,,,,,,,,,,,,[1,],,,,,,,,,,,,,,map_url,,,,,,,,,,,,,,,,,,,1];
window.APP_INITIALIZATION_STATE=[[[208.0,lat,lon]],[[["m"]]],,,,[,,[3,[,lat,lon,,208.0,,20.0,46.18,55.71]]],,,[59,60,61,63,66,65]];


this._ = this._ || {};
var window = this;
' . $definitionContent;


// Extra code for "content"
$content .= '
for (var i = 0; i <= 15; i++) { _.z("sy" + i); }
for (var i = 17; i <= 20; i++) { _.z("sy" + i); }
for (var i = 25; i <= 67; i++) { _.z("sy" + i); }
for (var i = 69; i <= 71; i++) { _.z("sy" + i); }
for (var i = 74; i <= 75; i++) { _.z("sy" + i); }
for (var i = 78; i <= 79; i++) { _.z("sy" + i); }
_.z("sy83");
for (var i = 85; i <= 89; i++) { _.z("sy" + i); }
for (var i = 93; i <= 96; i++) { _.z("sy" + i); }
for (var i = 101; i <= 104; i++) { _.z("sy" + i); }
_.z("sy110");
_.z("sy113");
for (var i = 115; i <= 117; i++) { _.z("sy" + i); }
_.z("sy120");
for (var i = 127; i <= 131; i++) { _.z("sy" + i); }
_.z("sy141");
_.z("sy147");
_.z("sy148");
_.z("sy156");
for (var i = 164; i <= 170; i++) { _.z("sy" + i); }
for (var i = 172; i <= 188; i++) { _.z("sy" + i); }
_.z("sy190");
_.z("sy192");
_.z("sy196");
_.z("sy197");
_.z("sy200");
_.z("sy202");
_.z("sy205");
_.z("sy207");
for (var i = 210; i <= 213; i++) { _.z("sy" + i); }
_.z("sy215");
for (var i = 220; i <= 224; i++) { _.z("sy" + i); }
for (var i = 229; i <= 232; i++) { _.z("sy" + i); }
_.z("sy239");
_.z("sy244");
_.z("sy255");
for (var i = 257; i <= 262; i++) { _.z("sy" + i); }
for (var i = 274; i <= 276; i++) { _.z("sy" + i); }
_.z("sy279");
_.z("sy280");
_.z("sy342");
_.z("sy358");
_.z("sy359");
_.z("sy362");
_.z("sy374");
_.z("sy379");
_.z("sy389");
_.z("sy391");
_.z("sy393");
_.z("sy394");
for (var i = 396; i <= 400; i++) { _.z("sy" + i); }
for (var i = 402; i <= 424; i++) { _.z("sy" + i); }
_.z("sy426");
_.z(_.Ya);
';


file_put_contents('res/temp-script-processed-definitions.js', $definitionContent);
file_put_contents('res/temp-script-processed-content.js', $content);
