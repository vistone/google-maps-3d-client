/// Node JS file

// http://stackoverflow.com/questions/281264/remove-empty-elements-from-an-array-in-javascript
Array.prototype.clean = function(deleteValue) {
  for (var i = 0; i < this.length; i++) {
    if (this[i] == deleteValue) {         
      this.splice(i, 1);
      i--;
    }
  }
  return this;
};

var esprima = require('esprima');
var escodegen = require('escodegen');
var fs = require('fs');

/**
 * Takes a MemberExpression object as provided by the esprima module, and an array of regexes.
 * 
 * Determines if the regexes match each part of a member expression from left to right.
 */
var isMembersMatching = function(member, regexes) {
  if (regexes.length === 1) {
    if (member.type != 'Identifier') return false;
    if (!regexes[0].test(member.name)) return false;

    return true;
  }

  if (regexes.length === 0) {
    throw "Invalid length! The array provided must contain at least one regex.";
  }

  if (member.type != 'MemberExpression') return false;
  var regex = regexes[regexes.length - 1];
  if (!regex.test(member.property.name)) return false;

  var new_regexes = regexes.slice(0, regexes.length - 1); // Cuts off the last element.
  return isMembersMatching(member.object, new_regexes);
};

var original_script = fs.readFileSync('res/original.js');

var variable_declarations = [];
var literals = [];
var misc_50 = [];
var misc_52 = [];
var misc_54 = [];
var misc_56 = [];
var misc_58 = [];
var other_nodes = [];

var syntax = esprima.parse(original_script);

for (var index = 0; index < syntax.body.length; index++) {
  var node = syntax.body[index];
  if (node.type === 'VariableDeclaration') {
    for (var varIndex = node.declarations.length - 1; varIndex >= 0; varIndex--) {
      var variableDeclarator = node.declarations[varIndex];
      var init = variableDeclarator.init;
      if (isFullyLiteralExpression(init)) {
        variable_declarations.push({
          type: "VariableDeclaration",
          kind: "var",
          declarations: [variableDeclarator]
        });
        node.declarations.splice(varIndex, 1);
        continue;
      }
    }
    //node.declarations.clean(null);
    if (node.declarations.length === 0) {
      continue;
    }
  }

  if (node.type == 'ExpressionStatement') {
    var expression = node.expression;
    if (expression.type == 'AssignmentExpression' && expression.operator === '=') {

      if (expression.left.type == 'Identifier') {
        if (isFullyLiteralExpression(expression.right)) {
          literals.push(node);
          continue;
        }
      }

      if (expression.left.type == 'MemberExpression') {
        var member = expression.left;

        if (member.object.type == 'Identifier' && member.property.type == 'Identifier') {
          if (isFullyLiteralExpression(expression.right)) {
            literals.push(node);
            continue;
          }
        }

        if (isMembersMatching(member, [
          /^([A-Z].*|[a-z]{3,}|[a-cf-jl-pr-z].+)$/,
          /^prototype$/,
          /^/,
        ])) {
          misc_50.push(node);
          continue;
        }

        if (isMembersMatching(member, [
          /^_$/,
          /^([A-Z]+|[a-z][A-Z]+)$/,
          /^prototype$/,
          /^([A-Z]+)/,
        ])) {
          misc_50.push(node);
          continue;
        }

        if (isMembersMatching(member, [
          /^_$/,
          /^/,
        ])) {
          if (isFullyLiteralExpression(expression.right)) {
            misc_52.push(node);
            continue;
          }
        }

        if (isMembersMatching(member, [
          /^_$/,
          /^[A-Z]+/,
          /^/,
        ])) {
          if (isFullyLiteralExpression(expression.right)) {
            misc_52.push(node);
            continue;
          }
        }
      }

    }
  }

  other_nodes.push(node);
}

function isFullyLiteralExpression(expression) {
  if (expression === null) {
    return true;
  }
  if (expression === undefined) {
    return true;
  }
  if (expression.type == 'FunctionExpression') {
    return true;
  }
  if (expression.type == 'Literal') {
    return true;
  }
  if (expression.type == 'ArrayExpression') {
    if (expression.elements.length === 0) {
      return true;
    }
    var isLiteral = true;
    for (var i = 0; i < expression.elements.length; i++) {
      if (!isFullyLiteralExpression(expression.elements[i])) {
        isLiteral = false;
        break;
      }
    }
    if (isLiteral) {
      return true;
    }
  }
  if (expression.type == 'ObjectExpression') {
    if (expression.properties.length === 0) {
      return true;
    }
    var isLiteral = true;
    for (var i = 0; i < expression.properties.length; i++) {
      var property = expression.properties[i];
      if (property.key.type != 'Identifier' && property.key.type != 'Literal') {
        isLiteral = false;
        break;
      }
      if (!isFullyLiteralExpression(property.value)) {
        isLiteral = false;
        break;
      }
    }
    if (isLiteral) {
      return true;
    }
  }
  if (expression.type == 'UnaryExpression') {
    if (isFullyLiteralExpression(expression.argument)) {
      return true;
    }
  }
  if (expression.type == 'ConditionalExpression') {
    if (isFullyLiteralExpression(expression.consequent)) {
      if (isFullyLiteralExpression(expression.alternate)) {
        return true;
      }
    }
  }
  return false;
}

function generateCodeFromArray(array) {
  var code = '';
  for (var i = 0; i < array.length; i++) {
    var node = array[i];
    var node_code = escodegen.generate(node);
    code += node_code + "\n";
  }
  return code;
}

var variable_declarations_code = generateCodeFromArray(variable_declarations);
var literals_code = generateCodeFromArray(literals);
var misc_50_code = generateCodeFromArray(misc_50);
var misc_52_code = generateCodeFromArray(misc_52);
var misc_54_code = generateCodeFromArray(misc_54);
var misc_56_code = generateCodeFromArray(misc_56);
var misc_58_code = generateCodeFromArray(misc_58);
var other_nodes_code = generateCodeFromArray(other_nodes);

fs.writeFileSync('js/10-processed-var.js', variable_declarations_code);
fs.writeFileSync('js/20-literals.js', literals_code);
fs.writeFileSync('js/50-misc.js', misc_50_code);
fs.writeFileSync('js/52-misc.js', misc_52_code);
fs.writeFileSync('js/54-misc.js', misc_54_code);
fs.writeFileSync('js/56-misc.js', misc_56_code);
fs.writeFileSync('js/58-misc.js', misc_58_code);
fs.writeFileSync('js/90-other-nodes.js', other_nodes_code);
