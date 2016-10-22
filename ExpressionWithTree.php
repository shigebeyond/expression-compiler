<?php

/**
 * 语法节点 
 */
abstract class Node
{
	/**
	 * 父节点
	 * @var Node
	 */
	public $parent;
	
	/**
	 * 位置，用于定位是否包含在某个子表达式之内
	 * @var int
	 */
	public $offset;
	
	/**
	 * 运算
	 * @return int|float
	 */
	public abstract function calculate();
	
	/**
	 * 往树中添加单个节点
	 * @param Node $child
	 * @param Node $newChild
	 * @return Node
	 */
	public abstract function add(Node $child);
	
	/**
	 * 往树中添加多个子节点
	 * @param array $children 要添加的子节点
	 * @return Node 返回该子节点挂载的父节点
	 */
	public function addAll(array $children)
	{
		$last = $this;
		foreach ($children as $child)
		{
			$last = $last->add($child);
		}
		return $last;
	}
	
	/**
	 * 转换为数组
	 */
	public abstract function asArray();
	
	/**
	 * 获得根节点
	 * @return Node
	 */
	public function root()
	{
		$last = $this;
		while($last->parent)
		{
			$last = $last->parent;
		}
		return $last;
	}
	
	public function __toString()
	{
		return json_encode($this->asArray());
	}
	
}

/**
 * 运算符的节点
 */
class OperatorNode extends Node
{
	/**
	 * 运算符优先级
	 * @var array
	 */
	public static $priorities = array(
			'+' => 1,
			'-' => 1,
			'*' => 2,
			'/' => 2,
	
	);
	
	/**
	 * 运算符
	 * @var string
	 */
	public $operator;
	
	/**
	 * 左子：值 + 低优先级的运算符
	 * @var Node
	 */
	public $left;
	
	/**
	 * 右子：值 + 高优先级的运算符
	 * @var Node
	 */
	public $right;
	
	public function __construct($operator, $left = NULL, $right = NULL)
	{
		if(is_array($operator)) // 值+位置
		{
			list($operator, $offset) = $operator;
			$this->offset = $offset;
		}
		$this->operator = $operator;
		$this->left = $left;
		$this->right = $right;
	}
	
	/**
	 * 运算： 后序遍历 + 递归
	 * @return int|float
	 */
	public function calculate()
	{
		switch ($this->operator)
		{
			case '+':
				return $this->left->calculate() + $this->right->calculate();
			case '-':
				return $this->left->calculate() - $this->right->calculate();
			case '*':
				return $this->left->calculate() * $this->right->calculate();
			case '/':
				return $this->left->calculate() / $this->right->calculate();
		}
		
		return false;
	}
	
	/**
	 * 往树中添加单个子节点
	 * @param Node $child 要添加的子节点
	 * @return Node 返回该子节点挂载的父节点
	 */
	public function add(Node $child)
	{
		// 加左子
		if($this->left === NULL) 
		{
			$this->left = $child;
		}
		// 加右子
		elseif ($this->right === NULL) 
		{
			$this->right = $child;
		}
		// 加第三子？
		elseif($child instanceof ValueNode) 
		{
			throw new Exception('一个运算符只有两个操作数');
		}
		// 加符号
		// 比较运算符优先级，同级/高级的走右下，低级的走左上
		elseif($this->compare($child) < 0) // $this < $child
		{
			// 同级/高级的走右下
			$child->add($this->right);
			$this->right = $child;
		}
		else
		{
			// 低级的走左上
			if($this->parent)
				return $this->parent->add($child);
			
			$child->add($this);
			return $child;
		}
			
		
		$child->parent = $this;
		return ($child instanceof OperatorNode) ? $child : $this;
	}
	
	/**
	 * 比较运算符
	 * @param OperatorNode $other
	 * @return int
	 */
	public function compare(OperatorNode $other)
	{
		$thisOp = static::$priorities[$this->operator];
		$otherOp = static::$priorities[$other->operator];
		return $thisOp - $otherOp;
	}
	
	/**
	 * 转换为数组
	 */
	public function asArray()
	{
		return array(
				'operator' => $this->operator,
				'left' => $this->left->asArray(),
				'right' => $this->right->asArray(),
		);
	}
}

/**
 * 数值的节点
 */
class ValueNode extends Node
{
	/**
	 * 数值
	 * @var mixin
	 */
	public $value;
	
	public function __construct($value = NULL, $parent = NULL)
	{
		if(is_array($value)) // 值+位置
		{
			list($value, $offset) = $value;
			$this->offset = $offset;
		}
		$this->value = $value;
		$this->parent = $parent;
	}
	
	/**
	 * 运算
	 * @return int|float
	 */
	public function calculate()
	{
		return $this->value;
	}
	
	/**
	 * 往树中添加单个节点
	 * @param Node $child
	 * @return Node
	 */
	public function add(Node $child)
	{
		if($this->parent)
			return $this->parent->add($child);
		
		if($child instanceof ValueNode)
			throw new Exception('不能往数值节点中添加数值节点');
		
		return $child->add($this);
	}
	
	/**
	 * 转换为数组
	 */
	public function asArray()
	{
		return $this->value;
	}
}

/**
 * (子表达式)的节点
 * 
 * 其中value属性是Node，相当于子表达式的当前节点
 * 注意：不是根节点，因为在调用subAdd()需要缓存当前节点，因此直接使用value属性来缓存
 * => 输出/运算时需要获得根节点才运算
 */
class SubNode extends ValueNode
{
	/**
	 * 开始位置
	 * @var int
	 */
	public $start;

	/**
	 * 结束位置
	 * @var int
	 */
	public $end;
	
	/**
	 * 是否包含某个子节点
	 * @param Node $node
	 * @return boolean
	 */
	public function contains($node)
	{
		return $this->start <= $node->offset && $this->end >= $node->offset;
	}
	
	/**
	 * 运算
	 * @return int|float
	 */
	public function calculate()
	{
		return $this->value->root()->calculate();
	}
	
	/**
	 * 在子表达式内部添加单个子节点
	 * @param Node $child
	 * @return Node
	 */
	public function subAdd(Node $child)
	{
		if($this->value)
			return $this->value = $this->value->add($child);
		
		return $this->value = $child; // 第一个
	}
	
	/**
	 * 在子表达式内部添加多个子节点
	 * @param array $children
	 * @return Node
	 */
	public function subAddAll(array $children)
	{
		if(empty($children))
			return NULL;
		
		$first = array_shift($children);
		return $this->value = $first->addAll($children);
	}
	
	/**
	 * 转换为数组
	 */
	public function asArray()
	{
		return array('sub' => $this->value->asArray());
	}
}

/**
 * 语法树
 */
class ExpressionWithTree
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
	 * 编译表达式为语法树 -- 不带(子表达式)
	 * @param string $exp
	 * @return Node
	 */
	/* public static function compile($exp)
	{
		// 1 没有符号
		if(!preg_match_all(static::REGX_OPERATOR, $exp, $matches))
			return new ValueNode($exp);
		
		// 2 有符号
		// 2.1 获得符号与数值
		// 获得符号
		$ops = $matches[0];
		
		// 获得数值
		$values = preg_split(static::REGX_OPERATOR, $exp);
		
		// 2.2 构建树
		$last = new ValueNode($values[0]); // 上一节点
		
		foreach ($ops as $i => $op)
		{
			// 构建运算符节点
			$last = $last->add(new OperatorNode($op));
			
			// 构建数值节点
			$last = $last->add(new ValueNode($values[$i + 1]));
		}
		
		return $last->root();
	}  */
	
	/**
	 * 编译表达式为语法树 -- 带(子表达式)
	 * @param string $exp
	 * @return Node
	 */
	public static function compile($exp)
	{
		// 0 编译子表达式 => 子表达式的有序列表 => 按范围有小到大有序 
		$subOL = static::compileSubNodeOrderList($exp);
		
		// 1 没有符号
		if(!preg_match_all(static::REGX_OPERATOR, $exp, $matches, PREG_OFFSET_CAPTURE)) // 带位置
		{
			$exp = preg_replace(static::REGX_SUB, '', $exp); // 去掉括号
			return new ValueNode($exp);
		}
		
		// 2 有符号
		// 2.1 获得符号与数值
		// 获得符号
		$ops = $matches[0];
		unset($matches);
		
		// 获得数值
		//$values = preg_split(static::REGX_OPERATOR, $exp); //因为支持了()，因此不能仅以符号作为分割来获得数值
		if(!preg_match_all(static::REGX_VALUE, $exp, $matches, PREG_OFFSET_CAPTURE)) // 带位置
		{
			throw new Exception("表达式格式错误：没有数值");
		}
		$values = $matches[0];
		unset($matches);
		
		// 2.2 构建树
		// 2.2.1 获得顶级子节点
		$child = new ValueNode($values[0]);
		$children = array($child); 
		foreach ($ops as $i => $op_ofs)
		{
			// 构建运算符节点
			$children[] = $child = new OperatorNode($op_ofs);
			
			// 构建数值节点
			$children[] = $child = new ValueNode($values[$i+1]);
		}
		
		// 2.2.2 将括号内的内部子节点，直接替换为子表达式节点
		foreach ($subOL as $sub)
		{
			// 收集当前子表达式的内部子节点
			foreach ($children as $i => $child)
			{
				// 检查 子节点 是否在 当前子表达式内部
				if($sub->contains($child)) 
				{
					// 用子表达式来替换内部子节点
					if($sub->value === NULL) // 第一个
						$children[$i] = $sub; // 将子表达式节点放入顶级节点中
					else 
						unset($children[$i]); // 从顶级节点中删除内部子节点
					
					// 将内部子节点 加入到 当前子表达式
					$sub->subAdd($child);
				}
			}
		}
		
		// 2.2.3 处理顶级子节点（此时顶级子节点已不存在（子表达式的内部节点），因为他们已用子表达式替换）
		$last = array_shift($children);
		$last = $last->addAll($children);
		unset($children);
		return $last->root();
	}
	
	/**
	 * 编译子表达式 => 返回子表达式的有序列表 => 按范围有小到大有序
	 * @param exp
	 */
	private static function compileSubNodeOrderList($exp)
	{
		if(!preg_match_all(static::REGX_SUB, $exp, $matches, PREG_OFFSET_CAPTURE)) // 带位置
			return array();
		
		$subs = array();
		$level = 0;
		$stack = array();
		foreach ($matches[0] as $op_ofs)
		{
			list($op, $offset) = $op_ofs;
			if($op == '(') // ( 开始
			{
				$stack[] = $sub = new SubNode();
				$sub->start = $sub->offset = $offset;
			}
			else // ) 结束
			{
				$subs[] = $sub = array_pop($stack); // 弹出才记录 =>子表达式按范围有小到大有序 => 定位符号与数值时也是先从小范围的子表达式开始
				$sub->end = $offset;
			}
		}
	
		return $subs;
	}
	
	/**
	 * 根语法节点
	 * @var Node
	 */
	public $root;
	
	public function __construct($exp)
	{
		$this->root = ExpressionWithTree::compile($exp);
	}
	
	/**
	 * 运算
	 */
	public function calculate()
	{
		return $this->root->calculate();
	}
	
}
