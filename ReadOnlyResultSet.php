<?php 
if (!class_exists('ReadOnlyResultSet'))
{
	class ReadOnlyResultSet 
	{
		// This member variable will hold the native result set
		var $rs;
		var $CurrRow = 1;
		
		// Assign the native result set to an instance variable
		public function __construct($rs)
		{
			$this->rs = $rs;
		}
		
		// Receives an instance of the DataObject we're working on
		public function getNext($dataobject)
		{
			$row    = mysql_fetch_array($this->rs);
			
			// Fetch the DO's field names
			$properties = $dataobject->getProperties();
			
			// Loop through the properties to set them from the current row
			foreach ($properties as $prop_name)
			//for ($i = 0; $i < count($properties); $i++) 
			{
				//$prop_name        = $properties[$i];
				$dataobject->$prop_name = $row[$prop_name];
			}
			
			return $dataobject;
		}
		
		public function getSame($dataobject)
		{
			$this->CurrRow--;
			return $this->getNext($dataobject);
		}
		
		// Move the pointer back to the beginning of the result set
		public function reset()
		{
			mysql_data_seek($this->rs, 0);
		}
		
		// Return the number of rows in the result set
		public function rowCount()
		{
			return mysql_num_rows($this->rs);
		}
	}
}
?>