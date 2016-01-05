<?php

class SJMSQLayer {
	public $db;
	public $log = false; // set to array() for logging
	
	public function __construct($db) {
		$this->db = $db;
	}
	
	public function query() {
		// %@ -> quoted value/list
		// %K -> unquoted value/list
		// %W -> where (WHERE %W), connected with AND (see _SQLeqexpr)
		// %S -> assign (for UPDATE SET %A)
		// %I -> insert (INSERT INTO %K %I)
		$items = func_get_args();
		if (count($items)==1) return new SJMSQLayerStatement($this,$items[0]);
		$callback = function($matches,$itemsSet=false) {
			static $items;
			if ($itemsSet) $items = $itemsSet;
			$item = array_shift($items);
			if (!$matches) return $item;
			if ($matches[0]=='%@') {
				if (is_array($item)) {
					return '('.$this->_SQLlist($item).')';
				} else {
					return $this->_SQLquote($item);
				}
			}
			if ($matches[0]=='%K') {
				if (is_array($item)) {
					return implode(',',$item);
				} else {
					return $item;
				}
			}
			if ($matches[0]=='%W') {
				return $this->_SQLeqexpr($item);
			}
			if ($matches[0]=='%S') {
				return $this->_SQLassign($item);
			}
			if ($matches[0]=='%I') {
				$keys = implode(',',array_keys($item));
				$values = $this->_SQLlist($item);
				return "($keys) VALUES($values)";
			}
			return '';
		};
		$format = $callback(false,$items);
		$query = preg_replace_callback('#%[@KWSI]#',$callback,$format);
		return new SJMSQLayerStatement($this,$query);
	}
	
	// internal quote functions
	
	public function _SQLlist($values) {
		$terms = array();
		foreach ($values as $v) $terms []= $this->_SQLquote($v);
		return implode(',',$terms);
	}
	public function _SQLassign($pairs) {
		$terms = array();
		foreach ($pairs as $k=>$v) $terms []= $k.'='.$this->_SQLquote($v);
		return implode(',',$terms);
	}
	public function _SQLeqexpr($pairs) {
		$terms = array();
		foreach ($pairs as $k=>$v) {
			if (is_integer($k)) $terms []= $v; // allow using custom strings
			elseif (is_null($v)) $terms []= $k.' ISNULL';
			elseif (is_array($v)) $terms []= $k.' IN ('.$this->_SQLlist($v).')';
			else $terms []= $k.'='.$this->_SQLquote($v);
		}
		return implode(' AND ',$terms);
	}
	public function _SQLquote($v) {
		if (is_integer($v)||is_float($v)) return $v;
		elseif (is_null($v)) return 'NULL';
		else return $this->db->quote($v);
	}
}

class SJMSQLayerStatement {
	public $sjmSQLLayer;
	public $sql = null;
	public $statement = null;
	public $executed = false;
	
	public function __construct($dbl,$sql) {
		$this->sjmSQLLayer = $dbl;
		$this->sql = $sql;
	}
	
	public function exec() {
		$prepareError = $execError = false;
		if ($this->statement===null) {
			$this->statement = $this->sjmSQLLayer->db->prepare($this->sql);
		}
		if ($this->statement===false) $prepareError = true;
		if ($prepareError===false) {
			$r = $this->statement->execute();
			if ($r===false) $execError = true;
		}
		if ($this->sjmSQLLayer->log!==false) {
			$entry = array();
			$entry['query'] = $this->sql;
			if ($prepareError) $entry['error'] = $this->sjmSQLLayer->db->errorInfo();
			elseif ($execError) $entry['error'] = $this->statement->errorInfo();
			$this->sjmSQLLayer->log []= $entry;
		}
		$this->executed = true;
		return $this;
	}
	
	public function get($key=false) {
		if (!$this->executed) $this->exec();
		if (!$this->statement) return false;
		$data = $this->statement->fetch(PDO::FETCH_ASSOC);
		$this->statement->closeCursor();
		if ($key===false) return $data;
		else return $data ? $data[$key] : false;
	}
	public function getAll($key=false) {
		if (!$this->executed) $this->exec();
		if (!$this->statement) return false;
		if ($key===false) {
			return $this->statement->fetchAll(PDO::FETCH_ASSOC);
		} else {
			$result = array();
			while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
				$result []= $row[$key];
			}
			return $result;
		}
	}
	public function getDict($dictKey,$valueKey=false) {
		if (!$this->executed) $this->exec();
		if (!$this->statement) return false;
		$result = array();
		while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
			if ($valueKey===false) {
				$result[$row[$dictKey]] = $row;
			} else {
				$result[$row[$dictKey]] = $row[$valueKey];
			}
		}
		return $result;
	}
	public function getGroup($groupKey,$valueKey=false) {
		if (!$this->executed) $this->exec();
		if (!$this->statement) return false;
		$result = array();
		while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
			$group = $row[$groupKey];
			if (!array_key_exists($group,$result)) $result[$group] = array();
			if ($valueKey===false) {
				$result[$group] []= $row;
			} else {
				$result[$group] []= $row[$valueKey];
			}
		}
		return $result;
	}
	public function lastInsertId() {
		if (!$this->executed) $this->exec();
		if (!$this->statement) return false;
		return $this->sjmSQLLayer->db->lastInsertId();
	}
}
