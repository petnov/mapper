<?php

namespace Mapper;

use Nette\Object,
	Mapper\Mapper;
use Mapper\Utils\AssocCollection;

/**
 * Base model entity
 * 
 * @author Petr Novotny
 */
class BaseEntity extends Object
{
	
	/**
	 * Unique object ID
	 * 
	 * @var mixed
	 * @mapped
	 */
	protected $id;
	
	/**
	 * Mapper instance
	 * 
	 * @var Mapper
	 */
	protected $mapper;
	
	/**
	 * Instance metadata
	 * 
	 * @var array
	 */
	protected $metaData = array();
	
	/**
	 * Id getter
	 * 
	 * @return mixed
	 */
	public function getId()
	{
		return $this->id;
	}
	
	/**
	 * Id setter
	 * 
	 * @param unknown $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}
	
	/**
	 * Repository for loading associations
	 * 
	 * @param Mapper $mapper
	 */
	public function setMapper(Mapper $mapper)
	{
		$this->mapper = $mapper;
	}
	
	/**
	 * Mapper getter
	 * 
	 * @return Mapper
	 */
	public function getMapper()
	{
		return $this->mapper;
	}
	
	/**
	 * Association getter
	 * 
	 * @param string $property
	 */
	public function association($property)
	{
		if ($this->$property === NULL) {			
			$this->$property = $this->mapper->getRepository(get_class($this))->loadAssociation($this, $property);
		}
		return $this->$property;
	}
	
	/**
	 * Enity metadata setter
	 * 
	 * @param array $meta
	 */
	public function setMetaData(array $meta)
	{
		$this->metaData = $meta;
	}
	
	/**
	 * Get metadata information
	 * 
	 * @param string $key
	 * @return string
	 */
	public function getMeta($key)
	{
		return isset($this->metaData[$key]) ? $this->metaData[$key] : NULL;
	}
	
	/**
	 * Fill entity from array
	 * @param array $data
	 * @return void
	 */
	public function fromArray(array $data)
	{
		foreach ($data as $attribute => $value) {
			if (property_exists($this, $attribute)) {
				$this->$attribute = $value;
			}
		}
	}
	
	
	/**
	 * Array type conversion routine
	 *
	 * @param bool $flat - return one-dimensional array?
	 * @return array
	 */
	public function toArray($flat = TRUE)
	{
		$r = new \ReflectionClass($this);
		$properties = $r->getProperties();
	
		// filter only data properties (private properties)
		$array = array();
		foreach ($properties as $property) {
			if (strpos($property->getName(), '_') === 0) {
				continue;
			}
	
			if (is_scalar($this->{$property->getName()})) {
				$array[$property->getName()] = $this->{$property->getName()};
			} elseif ($this->{$property->getName()} instanceof BaseEntity || $this->{$property->getName()} instanceof AssocCollection) {
				$associationArray = $this->{$property->getName()}->toArray($flat);
	
				if ($flat) {
					foreach ($associationArray as $key => $value) {
						$array[$property->getName().'_'.$key] = $value;
					}
				} else {
					$array[$property->getName()] = $associationArray;
				}
			}
		}
	
		return $array;
	}	
} 