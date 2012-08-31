<?php 
/* based on http://onlamp.com/pub/a/php/2004/08/05/dataobjects.html?page=1 */
require_once('DoRoBase.php'); 

if (!function_exists('quote_smart'))
{
	function quote_smart($value, $conn = null)
	{
		// Stripslashes if we need to
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
	
		// Quote it if it's not an integer
		if (!is_int($value)) {
			$value = "'" . mysql_real_escape_string($value) . "'";
		}
		
		if ("''" == $value)
		{
			$value = 'DEFAULT';
		}
		return $value;
	}
}

// this class is for read/write
class DoBase extends DoRoBase 
{
	protected static $all;
	
	// make sure to override these variables in classes extended from this class
	protected $tableName = "";			// name of the table in the database
	protected $primaryKeys = array();

	// now onto the methods
	public function __construct() 
	{
		parent::__construct();
	}
	
	/**
	 * Returl all records of a given data object.
	 * @version 3.0.0, there is only one instance of self::$all, and it is 
	 * shared for every class that shares DOBase as a parent. So we need to 
	 * differentiate called classes. Made $all a keyed, nested array, rather 
	 * than a simple array of objects.
	 */
	public static function getAll()
	{
		$className = get_called_class(); // Only in PHP 5.3!
		if (empty(self::$all) && !array_key_exists($className, self::$all) )
		{
			$objects = array();
			$search = new $className();
			$rs = $search->find();
			for ($i = 0; $i < $rs->rowCount(); $i++)
			{
				$objects[] = $rs->getNext(new $className());
			}
			self::$all[$className] = $objects;
		}
		return self::$all[$className];
	}
	
	public function del() 
	{
		$tableName = $this->tableName;
		$connection = $this->getConnection();
		$sWhere = $this->getWhereClause($this->primaryKeys);
		if (strpos(strtolower($sWhere), " where ") !== 0)
		{
			return false;
		}
		$sql = "DELETE FROM " . $tableName;
		$sql .= $sWhere;
		$dc = mysql_query($sql, $connection) 
			or die(/*mysql_error()."||del||".$sql*/ 'PHPDO Error 7974: Could not delete record.');
		return $dc;
	}
	
	public function get($gracefulFailAttemptPk = -1)
	{
		if ($gracefulFailAttemptPk > -1 && count($this->primaryKeys) == 1)
		{
			$pk = $this->primaryKeys[0];
			$this->$pk = $gracefulFailAttemptPk;
		}
		$result = $this->getResultSet($this->primaryKeys);
		$row = mysql_fetch_array($result);
		$properties = $this->getProperties();
		foreach ($properties as $name)
		{
			$this->$name = stripslashes($row[$name]);
		}
		$numRows = mysql_num_rows($result);
		if ($numRows != 1)
		{
			error_log("PHPDO Warning 6677: DoBase, " . 
				get_class($this) .
				" script: " . $_SERVER["SCRIPT_NAME"] .
				" qs: " . $_SERVER["QUERY_STRING"] . 
				" fn get did not have exactly 1 row for id " . 
				$gracefulFailAttemptPk . 
				". Had " . $numRows . " rows instead." .
				"Referer is " . getenv ("HTTP_REFERER") . '.'
			);
		}
		return $this;
	}
	
	public function getIds()
	{
		$ids = array();
		if ($this->primaryKeys)
		{
			foreach ($this->primaryKeys as $pk) 
			{
				$ids[$pk] = $this->$pk;
			}
		}
		return $ids;
	}

	public function addIfDne() 	// DNE = Does Not Exist
	{
		// if an identical entry already exists in the DB
		// make $this object into the one from the DB (presumably we don't know
		// the ID ). Otherwise, insert $this into the DB
		$rs = $this->find();
		if ($rs->rowCount() > 0)
		{
			// make sure we have an exact match
			$arrProps = $this->getProperties();
			for ($i = 0; $i < $rs->rowCount(); $i++) 
			{
				$match = true;
				$thisClass = get_class($this);
				$testObj = $rs->getNext( new $thisClass() );
				foreach ($arrProps as $property)
				{
					// skip the primary key, which we probably don't know
					if (in_array($property, $this->primaryKeys)) 
					{
						continue;
					}
					$match = ($match && ($this->$property == $testObj->$property));
				}
				if ($match)
				{
					foreach ($this->primaryKeys as $pk) 
					{
						$this->$pk = $testObj->$pk;
					}
					return $testObj;
				}
			}
		}
		$this->insert();
		return $this;
	}

	protected function getInsertSql()
	{
		$tableName = $this->tableName;
		$conn = $this->getConnection();
		$fields = array();
		$fieldList = "";
		$values = array();
		$valueList = "";
		
		$sql = "INSERT into " . $tableName . " ";
		
		$properties = $this->getProperties();
		
		foreach ($properties as $name)
		{
			if ($this->$name != '') 
			{
				$fields[] = sprintf("`%s`", $name);
				$values[] = quote_smart($this->$name, $conn);
			}
		}
		$sql .= "(".implode(',', $fields).")";
		$sql .= " VALUES ";
		$sql .= "(".implode(',', $values).")";
		return $sql;
	}
	
	public function insert() 
	{
		$sql = $this->getInsertSql();
		$connection = $this->getConnection();
		$success = mysql_query($sql, $connection) 
			or die(/* mysql_error()."||ins||".$sql*/ 'PHPDO Error 9287: Could not insert data.');
		if (mysql_insert_id() > 0) 
		{
			$pk = $this->primaryKeys[0];
			$this->$pk = mysql_insert_id();
		}
		return $success;
	}
	
	public function isNull()
	{
		$thisClass = get_class($this);
		$blank = new $thisClass();
		if ($this == $blank) { return true; }
		$blank->getProperties();
		
		return ($this == $blank);
	}

	public function insertOrUpdate() // uses update partial
	{
		$sql = $this->getInsertSql();
		$connection = $this->getConnection();
		$success = mysql_query($sql, $connection);	//  or die(mysql_error()."||ins||".$sql);
		if (!$success)
		{
			return $this->updatePartial();
		}
		if (mysql_insert_id() > 0) 
		{
			$pk = $this->primaryKeys[0];
			$this->$pk = mysql_insert_id();
		}
		return $success;
	}

	// if a value is not set in the object, don't set it to null in the DB
	// e.g., updating an updated DATE only should not reset all other fields to ""
	public function updatePartial()
	{
		return $this->_update(true);
	}
	
	public function update() 
	{
		return $this->_update(false);
	}
	
	// to be overridden if certain fields (e.g., DateAdded) should not be updated
	protected function getUpdateProperties()
	{
		return $this->getProperties();
	}

	protected function _update($bPartialUpdate)
	{
		foreach ($this->primaryKeys as $pk) 
		{
			if (!isset($this->$pk) || $this->$pk == "") { return false; }
		}
		$tableName = $this->tableName;
		$connection = $this->getConnection();
		$fields = array();
		$fieldList = "";
		$values = array();
		$valueList = "";
		$separator="";
		$conn = $this->getConnection();
		
		$sql = "UPDATE " . $tableName . " SET ";
		$properties = $this->getUpdateProperties();
		foreach ($properties as $property) 
		{
			if (in_array($property, $this->primaryKeys)) { continue; }
			if ($bPartialUpdate && !isset($this->$property)) { continue; }
			$sql .= $separator . '`' . $property . '`' . "=".quote_smart($this->$property, $conn);
			$separator = ",";
		}
		$sql .= $this->getWhereClause($this->primaryKeys);
		$success = mysql_query($sql, $connection) or die(mysql_error()."||upd||".$sql);
		return $success;
	}
}
