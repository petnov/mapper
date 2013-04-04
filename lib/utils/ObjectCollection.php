<?php

namespace Mapper\Utils;

/**
 * Simple collection of hydrated objects
 * 
 * @author Petr Novotny
 */
class ObjectCollection extends \ArrayObject 
{
	
	/**
	 * Empty value for selection
	 */
	const SELECT_EMPTY_VALUE = 'select_empty';
	
	/**
	 * Collection count wihout applied limit
	 * 
	 * @var int
	 */
	private $totalCount;
	
	/**
	 * Return array with given key => value attributes
	 * of objects in collection
	 * 
	 * @param string $key - object attribute to use as array key
	 * @param string $value - object attribute to use as array value
	 * @return array
	 */
	public function getArraySelect($key, $value, $emptyValue = TRUE) 
	{
		$selectArray = array();
		if ($emptyValue) {
			$selectArray[] = self::SELECT_EMPTY_VALUE;
		}
		foreach($this as $item) {
			$selectArray[$item->{$key}] = $item->$value;
		}
		
		return $selectArray;
	}
	
	/**
	 * Return first object in collection
	 * 
	 * @access public
	 * @return Object
	 */
	public function getFirst()
	{
		if ($this->offsetExists(0)) {    
			return $this->offsetGet(0);
		} else {
			return NULL;
		}
	}
	
	/**
	 * Collection total count without limit
	 * 
	 * @return int
	 */
	public function getTotalCount() 
	{
		return $this->totalCount;
	}
	
	/**
	 * Total count setter
	 * 
	 * @param int $totalCount
	 */
	public function setTotalCount($totalCount) 
	{
		$this->totalCount = (int) $totalCount;
	}
	
}

