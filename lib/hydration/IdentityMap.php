<?php

namespace Mapper\Hydration;

/**
 * Identity map for storing all hydrated objects
 * 
 * @author Petr Novotny
 */
class IdentityMap
{
	/**
	 * Map of entities to their primary attribute
	 * 
	 * @var array
	 */
	private $idMap = array();
	
	/**
	 * Storage for hydrated collections
	 * 
	 * @var array
	 */
	private $collectionMap = array();
	
	/**
	 * Get entity instance (by its primary)
	 * 
	 * @param string $entity
	 * @param mixed $primary
	 * @return mixed
	 */
	public function getEntityInstance($entity, $primary)
	{
		if (isset($this->idMap[$entity]) && isset($this->idMap[$entity][$primary])) {
			return $this->idMap[$entity][$primary];
		}
	}
	
	/**
	 * Add entity instance under ID
	 * 
	 * @param string $entity
	 * @param mixed $primary
	 * @param mixed $instance
	 */
	public function addEntityInstance($entity, $primary, $instance)
	{
		isset($this->idMap[$entity]) || $this->idMap[$entity] = array();
		$this->idMap[$entity][$primary] = $instance;
	}
	
	/**
	 * Checks key presence in keyMap data
	 * 
	 * @param string $key
	 * @return bool
	 */
	public function hasCollection($key)
	{
		return isset($this->collectionMap[$key]);
	}
	
	/**
	 * Add collection
	 * 
	 * @param mixed $key
	 * @param string $item
	 */
	public function addCollection($key, $item = NULL)
	{
		isset($this->collectionMap[$key]) || $this->collectionMap[$key] = array();
		$item && $this->collectionMap[$key][] = $item;
	}
	
	/**
	 * Get collection with given key
	 * 
	 * @return array
	 */
	public function getCollection($key)
	{
		return $this->collectionMap[$key];
	}
}