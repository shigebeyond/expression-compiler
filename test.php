<?php

// include './ExpressionWithSuffix.php';
set_include_path(dirname(__FILE__));
include 'ExpressionWithSuffix.php';

// $exp = '1+2';
$exp = '1+(2-(3+4)-(5-6))*7';
// $exp = $argv[1];

//后缀表达式的实现
$result = ExpressionWithSuffix::execute($exp);
echo "$exp = $result \n";


//　语法树的实现
$exp = new ExpressionWithTree($exp);
echo $exp->root."\n";
$result = $exp->calculate();
echo "$exp = $result \n";
