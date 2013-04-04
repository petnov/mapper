<?php

namespace Mapper\Hydration;

use Nette\Object,
	Nette\Database\Row,
	Mapper\Utils\ObjectCollection,
	Mapper\Mapping\Mapping,
	Mapper\Mapper;


/**
 * Hydrates objects from data, using Metadata Mapping
 * 
 * @link http://martinfowler.com/eaaCatalog/metadataMapping.html
 * @author Petr Novotny
 */
class ObjectHydrator extends Object implements Hydrator
{
	
	/**
	 * Metadata mapping object
	 * 
	 * @var Mapping
	 */
	private $mapping;
	
	/**
	 * Identity map for storing objects
	 * 
	 * @var IdentityMap
	 */
	private $identityMap;
	
	/**
	 * Mapper
	 * 
	 * @var Mapper
	 */
	private $mapper;
	
	/**
	 * Construct routines
	 * 
	 * @param Mapping $mapping
	 */
	public function __construct(Mapping $mapping, IdentityMap $map, Mapper $mapper)
	{
		$this->mapping = $mapping;
		$this->identityMap = $map;
		$this->mapper = $mapper;
	}
	

	/**
	 * Method finds attribute of object which corresponds to primary key
	 * in database model
	 *
	 * @access protected
	 * @return string
	 */
	public function getPrimaryAttribute()
	{
		foreach ($this->mapping as $property => $column) {
			if ($column == $this->primary) {
				return $property;
			}
		}
	}
	
	/**
	 * Object hydration
	 * 
	 * @param Row $obj - Object to be filled with data
	 * @param dibiRow $row - row with data
	 * @return mixed $obj - hydrated object
	 */
	public function hydrate(Row $row, $entity, $mappings = array(), $aliased = FALSE)
	{
		// get entity mappings
		if (!$mappings) {
			$mappings = $this->mapping->loadMappings($entity);
		}
				
		// primary key
		$primary = $mappings['primary'];
		if ($aliased) {
			$primary = $mappings['alias'] . '_' . $primary;
		}
		
		if (!$row->{$primary}) {
			return NULL;		
		}
		 
		$instance = $this->identityMap->getEntityInstance($entity, $row->{$primary});
		if ($instance === NULL) {		
			// get values				
			$instance = new $entity;
			
			$metaColumnLength = strlen(Mapping::META_COLUMN);
			
			$meta = array($mappings['primary'] => $row->{$primary});
			// set values of object properites
			foreach ($mappings['properties'] as $property => $column) {
				
				if ($aliased) {
					$columnName = $mappings['alias'] . '_' . $column;
				} else {
					$columnName = $column;
				}
				
				if (!isset($row->$columnName)) {
					continue;
				}
				
				if (strpos($property, Mapping::META_COLUMN) !== FALSE) {
					$meta[substr($property, $metaColumnLength)] = $row->{$columnName};
				} else if (property_exists($instance, $property)) {					
					$instance->$property = $row->{$columnName};
				}
			}
			$instance->setMetaData($meta);
			
			if ($mappings['associations']) {
				$instance->setMapper($this->mapper);
			}
			
			$this->identityMap->addEntityInstance($entity, $row->{$primary}, $instance);
		}
		return $instance;
	}
	
	/**
	 * Hydate collection of objects
	 * 
	 * @param array $rows - array of Row objects
	 */
	public function hydrateCollection(array $rows, $entity, $aliased = FALSE, $key)
	{
		$className = $entity;
		$mappings = $this->mapping->loadMappings($entity);
		
		// create object collection
		$collectionClass = $className.'Collection';
		
		$this->identityMap->addCollection($key);
		
		// creates objects
		foreach ($rows as $row) {
			$obj = $this->hydrate($row, $entity, $mappings, $aliased);
			$this->identityMap->addCollection($key, $obj);
		}
		
		if (class_exists($collectionClass)) {
			$objects = new $collectionClass($this->identityMap->getCollection($key));
		} else {
			$objects = new ObjectCollection($this->identityMap->getCollection($key));
		}
		
		return $objects;
	}
	
	/**
	 * Is collection of objects already hydrated?
	 * 
	 * @param string $identityKey
	 * @return boolean
	 */
	public function isCollectionHydrated($identityKey)
	{
		return $this->identityMap->hasCollection($identityKey);
	}
	
	/**
	 * Hydrated collection getter
	 * 
	 * @param unknown $identityKey
	 * @return \Mapper\Utils\ObjectCollection
	 */
	public function getCollection($identityKey)
	{
		return new ObjectCollection($this->identityMap->getCollection($identityKey));
	}
	
	public function getIdentityMap()
	{
		return $this->identityMap;
	}
	
	/**
	 * Get hydrated entity instance
	 * 
	 * @param string $entity
	 * @param int $primary
	 * @return BaseEntity
	 */
	public function getEntityInstance($entity, $primary)
	{
		return $this->identityMap->getEntityInstance($entity, $primary);
	}
	

}