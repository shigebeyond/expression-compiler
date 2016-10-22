<?php

/**
 * 表达式编译与计算
 */
class ExpressionWithSuffix
{
	/**
	 * 括号正则: ( )
	 * @var string
	 */
	const REGX_SUB = '/[\(\)]/';

	/**
	 * 符号正则: + - * /
	 * @var string
	 */
	const REGX_OPERATOR = '/[\+\-\*\/]/';

	/**
	 * 数值正则: 非括号 非符号
	 * @var string
	 */
	const REGX_VALUE = '/[^\+\-\*\/\(\)]+/';

	/**
	 * 运算符+优先级
	 * @var array
	 */
	public static $operators = array(
			'+' => 1,
			'-' => 1,
			'*' => 2,
			'/' => 2,
	);

	/**
	 * 比较运算符
	 * @param OperatorNode $other
	 * @return int
	*/
	public static function compare($op1, $op2)
	{
		return static::$operators[$op1] - static::$operators[$op2];
	}

	/**
	 * 执行表达式
	 * @param string $expr
	 * @return mixed
	 */
	public static function execute($infix_expr)
	{
		// 先编译
		$suffix_expr = static::compile($infix_expr);
		echo implode(' ', $suffix_expr)."\n";
		// 后计算
		return static::calculate($suffix_expr);
	}

	/**
	 * 将中缀表达式 编译为 后缀表达式
	 *
	 * @param string $infix_expr 中缀表达式
	 * @return array 后缀表达式
	 */
	public static function compile($infix_expr)
	{
		// 1 没有符号
		if(!preg_match_all(static::REGX_OPERATOR, $infix_expr, $matches))
			return $infix_expr;

		// 2 有符号
		// 2.1 获得符号与数值
		// 获得符号
		$ops = $matches[0];

		// 获得数值
		$values = preg_split(static::REGX_OPERATOR, $infix_expr);

		// 2.2 生成后缀表达式
		$suffix_expr = array(); // 后缀元素的数组，用于保存后缀表达式的生成结果
		$op_stack = array(); // 运算符堆栈，用于缓存运算符，高级的在栈顶，先出栈先输出，用以体现运算符的优先级

		$suffix_expr[] = $values[0]; // 第一个数值
		foreach ($ops as $i => $op)
		{
			// 1 输出运算符之前，先检查优先级
			// 策略：高级运算符先输出
			// 实现：遇高入栈，遇低出栈
				
			// 1.1 遇低出栈
			// 如果遇到了低级/同级的运算符（栈顶运算符 >= 当前运算符）：直接输出
			while($op_stack && static::compare(end($op_stack), $op) >= 0)
			{
				// 输出栈顶运算符
				$suffix_expr[] = array_pop($op_stack);
			}
				
			// 1.2 遇高入栈
			// 如果遇到了高级的运算符（栈顶运算符 < 当前运算符）：先入栈存着，以防下一个运算符更高级
			$op_stack[] = $op;
				
			// 2 直接输出数值
			$suffix_expr[] = $values[$i + 1];
		}

		// 输出缓存的运算符： 高级的在栈顶，先输出
		while ($op_stack)
		{
			// 输出栈顶运算符
			$suffix_expr[] = array_pop($op_stack);
		}

		return $suffix_expr;
	}

	/**
	 * 运算
	 * @param array $suffix_expr
	 * @return mixed
	 */
	public static function calculate(array $suffix_expr)
	{
		$value_stack = array(); // 值的堆栈
		foreach ($suffix_expr as $item)
		{
			if(isset(static::$operators[$item])) // 对符号：直接从值的堆栈中取得两个值来运算
			{
				$value2 = array_pop($value_stack);
				$value1 = array_pop($value_stack);
				$value_stack[] = static::calculate_operator($item, $value1, $value2);
			}
			else // 对数值：入栈
			{
				$value_stack[] = $item;
			}
		}

		$result = array_pop($value_stack);
		unset($value_stack);
		return $result;
	}

	/**
	 * 执行运算符
	 * @param string $op
	 * @param string $value1
	 * @param string $value2
	 * @return mixed
	 */
	public static function calculate_operator($op, $value1, $value2)
	{
		switch ($op)
		{
			case '+':
				return $value1 + $value2;
			case '-':
				return $value1 - $value2;
			case '*':
				return $value1 * $value2;
			case '/':
				return $value1 / $value2;
		}
		return false;
	}
}