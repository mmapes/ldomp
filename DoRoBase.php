<?php
// require_once('class-DB_Connector.php'); // do this yourself
// uses a function called quote_smart ... where is that from?
require_once('ReadOnlyResultSet.php');
require_once('Mirror.php');
class DoRoBase extends DbConnector {
	protected $sql = '';
	protected $where = '';
	protected $orderBy = '';
	protected $limit;
	protected $offset;
	
	public function __construct()
	{
		parent::__construct();
		$this->autocompleteProperties = $this->primaryKeys;
	}
	
	public function getPrimaryKeys()
	{
		return $this->primaryKeys;
	}
	public function getProperties ()
	{
		if (!isset($this->publicProperties))
		{
			$Mirror = Mirror::getInstance();
			$this->publicProperties = $Mirror->get_class_vars($this);
		}
		return $this->publicProperties;
	}

	/**
	 * Set the ORDER BY clause for a search
	 * @param orderBy string <p>
	 * The entire clause to be used in the SQL statement, e.g., ' ORDER BY id asc '
	 * </p>
	 * @return void
	 */
	public function setOrderBy($orderBy)
	{
		$this->orderBy = $orderBy;
	}

	protected function getOrderBy()
	{
		return $this->orderBy;
	}

	public function setLimit($num)
	{
		$this->limit = (int) $num;
	}
	
	public function setOffset($num)
	{
		$this->offset = (int) $num;
	}

	protected function getLimit()
	{
		if (isset($this->limit) && is_numeric($this->limit))
		{
			if (isset($this->offset) && is_numeric($this->offset))
			{
				return sprintf(" LIMIT %s, %s ", $this->offset, $this->limit);
			} else {
				return sprintf(" LIMIT %s ", $this->limit);
			}
		}
		return "";
	}
	
	/**
	 * Add a subclause to the WHERE clause for a search
	 * @param whereCondition string <p>
	 * the condition for the WHERE clause, without the conjunction, e.g., ' idMovie=23 '
	 * </p>
	 * @return void
	 */
	public function addWhereCondition($whereCondition)
	{
		$this->whereConditions[] = $whereCondition;
	}

	// This badly-named method is included for historical purposes. You should 
	// use addWhereCondition instead.
	public function setWhereCondition($whereCondition)
	{
		// synonymous with addWhereCondition
		return $this->addWhereCondition($whereCondition);
	}
	
	public function find($properties = "")
	{
		if ($properties == "")
		{
			$properties = $this->getProperties();
		}
		$rs = $this->getResultSet($properties);
		return new ReadOnlyResultSet($rs);
	}

	public function getResultSet($properties)
	{
		$connection = $this->getConnection();
		$sql = $this->getQuery($properties);
		$rs = mysql_query($sql, $connection) 
			or die(/*mysql_error()."||find||".$sql*/ 'PHPDO Error 3810: Could not get result set.');
		return $rs;
	}

	public function setQuery($sql)
	{
		// you're on your own!
		$this->sql = $sql;
	}

	public function resetQuery()
	{
		$this->sql = "";
		$this->whereConditions = array();
		$this->limit = null;
		$this->orderBy = null;
	}

	protected function getQuery($properties)
	{
		if ($this->sql == "")
		{
			$sql = "SELECT * FROM " . $this->tableName;
		} else {
			$sql = $this->sql;
		}
		$sql .= $this->getWhereClause($properties);
		$sql .= " " . $this->getOrderBy();
		$sql .= " " . $this->getLimit();
		return $sql;
	}

	protected function getWhereClause($properties)
	{
		$conjunction = ' WHERE ';
		$where = array();
		$sWhere = '';
		foreach ($properties as $name)
		{
			if ($this->$name != '') {
				$where[] = "`" . $name . "`=" . quote_smart($this->$name);
			}
		}
		if (count($where) > 0)
		{
			$sWhere = " WHERE " . implode(' AND ', $where);
			$conjunction = " AND ";
		}
		if (isset($this->whereConditions))
		{
			foreach ($this->whereConditions as $condition)
			{
				$sWhere .= $conjunction . $condition;
				$conjunction = " AND ";
			}
		}
		return $sWhere;
	}

	public function findJson()
	{
		$myClass = get_class($this);
		$resultset = $this->find();
		$dataObjects = Array();
		for ($i = 0; $i < $resultset->rowCount(); $i++)
		{
			$dataObjects[] = $resultset->getNext(new $myClass());
		}
		return json_encode($dataObjects);
	}

	public function findAutocomplete($field, $q)
	{
		$x = sprintf(
			' %1$s LIKE "%3$s %2$s%3$s" OR %1$s LIKE "%2$s%3$s" ',
			$field,
			$q,
			'%'
		);
		$this->setWhereCondition($x);
		$this->setOrderBy(sprintf(' ORDER BY %s asc ', $field));
		$resultset = $this->find();
		return $this->getAutocompleteResults($field, $resultset);
	}

	protected function getAutocompleteResults($field, $resultset)
	{
		$myClass = get_class($this);
		$words = array();
		for ($i = 0; $i < $resultset->rowCount(); $i++)
		{
			$dataObject = $resultset->getNext(new $myClass());
			$o = array();
			foreach ($this->autocompleteProperties as $pk)
			{
				$o[$pk] = $dataObject->$pk;
			}
				
			$words[] = $dataObject->$field . "|" . json_encode($o);
		}
		return implode("\n", $words);
	}
}

?>