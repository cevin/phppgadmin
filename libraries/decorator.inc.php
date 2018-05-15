<?php
// $Id: decorator.inc.php,v 1.8 2007/04/05 11:09:38 mr-russ Exp $

// This group of functions and classes provides support for
// resolving values in a lazy manner (ie, as and when required)
// using the Decorator pattern.

###TODO: Better documentation!!!

// Construction functions:

function field($fieldName, $default = null) {
	return new FieldDecorator($fieldName, $default);
}

function merge(/* ... */) {
	return new ArrayMergeDecorator(func_get_args());
}

function concat(/* ... */) {
	return new ConcatDecorator(func_get_args());
}

function callback($callback, $params = null) {
	return new CallbackDecorator($callback, $params);
}

function ifempty($value, $empty, $full = null) {
	return new IfEmptyDecorator($value, $empty, $full);
}

function url($base, $vars = null /* ... */) {
	// If more than one array of vars is given,
	// use an ArrayMergeDecorator to have them merged
	// at value evaluation time.
	if (func_num_args() > 2) {
		$v = func_get_args();
		array_shift($v);
		return new UrlDecorator($base, new ArrayMergeDecorator($v));
	}
	return new UrlDecorator($base, $vars);
}

function replace($str, $params) {
	return new replaceDecorator($str, $params);
}

// Resolving functions:

function value(&$var, &$fields, $esc = null) {
	if (is_a($var, 'Decorator')) {
		$val = $var->value($fields);
	} else {
		$val =& $var;
	}

	if (is_string($val)) {
		switch($esc) {
			case 'xml':
				return strtr($val, array(
					'&' => '&amp;',
					"'" => '&apos;', '"' => '&quot;',
					'<' => '&lt;', '>' => '&gt;'
				));
			case 'html':
				return htmlentities($val, ENT_COMPAT, 'UTF-8');
			case 'url':
				return urlencode($val);
		}
	}
	return $val;
}

function value_xml(&$var, &$fields) {
	return value($var, $fields, 'xml');
}

function value_xml_attr($attr, &$var, &$fields) {
	$val = value($var, $fields, 'xml');
	if (!empty($val))
		return " {$attr}=\"{$val}\"";
	else
		return '';
}

function value_url(&$var, &$fields) {
	return value($var, $fields, 'url');
}

// Underlying classes:

class Decorator
{
    /**
     * @var mixed
     */
	private  $v;

	function __construct($value) {
		$this->v = $value;
	}
	
	function value($fields) {
		return $this->v;
	}
}

class FieldDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $f;

	function __construct($fieldName, $default = null) {
		$this->f = $fieldName;
		if ($default !== null) $this->d = $default;
	}
	
	function value($fields) {
		return isset($fields[$this->f]) ? value($fields[$this->f], $fields) : (isset($this->d) ? $this->d : null);
	}
}

class ArrayMergeDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $m;

    /**
     * ArrayMergeDecorator constructor.
     * @param $arrays
     */
	function __construct($arrays) {
		$this->m = $arrays;
	}
	
	function value($fields) {
		$accum = array();
		foreach($this->m as $var) {
			$accum = array_merge($accum, value($var, $fields));
		}
		return $accum;
	}
}

class ConcatDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $c;
    /**
     * ConcatDecorator constructor.
     * @param $values
     */
	function __construct($values) {
		$this->c = $values;
	}
	
	function value($fields) {
		$accum = '';
		foreach($this->c as $var) {
			$accum .= value($var, $fields);
		}
		return trim($accum);
	}
}

class CallbackDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $fn;

    /**
     * @var mixed
     */
	private $p;

    /**
     * CallbackDecorator constructor.
     * @param $callback
     * @param null $param
     */
	function __construct($callback, $param = null) {
		$this->fn = $callback;
		$this->p = $param;
	}
	
	function value($fields) {
		return call_user_func($this->fn, $fields, $this->p);
	}
}

class IfEmptyDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $v;
    /**
     * @var mixed
     */
	private $e;
    /**
     * @var mixed
     */
	private $f;

    /**
     * IfEmptyDecorator constructor.
     * @param $value
     * @param $empty
     * @param null $full
     */
	function __construct($value, $empty, $full = null) {
		$this->v = $value;
		$this->e = $empty;
		if ($full !== null) $this->f = $full;
	}
	
	function value($fields) {
		$val = value($this->v, $fields);
		if (empty($val))
			return value($this->e, $fields);
		else
			return isset($this->f) ? value($this->f, $fields) : $val;
	}
}

class UrlDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $b;
    /**
     * @var mixed
     */
	private $q;

    /**
     * UrlDecorator constructor.
     * @param $base
     * @param null $queryVars
     */
	function __construct($base, $queryVars = null) {
		$this->b = $base;
		if ($queryVars !== null)
			$this->q = $queryVars;
	}
	
	function value($fields) {
		$url = value($this->b, $fields);
		
		if ($url === false) return '';
		
		if (!empty($this->q)) {
			$queryVars = value($this->q, $fields);
			
			$sep = '?';
			foreach ($queryVars as $var => $value) {
				$url .= $sep . value_url($var, $fields) . '=' . value_url($value, $fields);
				$sep = '&';
			}
		}
		return $url;
	}
}

class replaceDecorator extends Decorator
{
    /**
     * @var mixed
     */
	private $s;
    /**
     * @var mixed
     */
	private $p;
    /**
     * replaceDecorator constructor.
     * @param $str
     * @param $params
     */
	function __construct($str, $params) {
		$this->s = $str;
		$this->p = $params;
	}

	function value($fields) {
		$str = $this->s;
		foreach ($this->p as $k => $v) {
			$str = str_replace($k, value($v, $fields), $str);
		}
		return $str;
	}
}