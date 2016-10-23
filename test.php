<?php

// include './ExpressionWithSuffix.php';
set_include_path(dirname(__FILE__));
include 'ExpressionWithSuffix.php';
include 'ExpressionWithTree.php';

// $exp = '1+2';
$exp = '1+2*3-4';
// $exp = '1+(2-(3+4)-(5-6))*7';
// $exp = $argv[1];

//后缀表达式的实现
// $result = ExpressionWithSuffix::execute($exp);
// echo "$exp = $result \n";


//　语法树的实现
$tree = new ExpressionWithTree($exp);
echo $tree->root."\n";
$result = $tree->calculate();
echo "$exp = $result \n";
