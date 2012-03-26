<?php
// Mirror reflects an object, showing how it looks from outside its own
// scope. Useful when you want to see the public properties of an object
// from within itself.
// e.g., 
// $Mirror = Mirror::getInstance();
// print_r($Mirror->get_class_vars($this));$Mirror = Mirror::getInstance(); print_r($Mirror->get_class_vars($this);
Class Mirror
{
	private static $instance = null;
	private function __construct(){}
	 
	public static function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new Mirror();
		}
		return self::$instance;
	}
	
	public function get_class_vars($instance)
	{
		return (array_keys(get_class_vars(get_class($instance))));
	}
}
?>