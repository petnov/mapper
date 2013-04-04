<?php

namespace Mapper\Mapping;

use Nette\Reflection\ClassType,
	Nette\Caching\Cache,
	Mapper\Mapper;

/**
 * Get entity metadata mapping from entity annotations
 * 
 * @author Petr Novotny
 */
class AnnotationMapping extends Mapping 
{
	
	/**
	 * Cache to store mapping in
	 * 
	 * @var Nette\Caching\Cache
	 */
	private $cache;
	
	/**
	 * Construct routines
	 * 
	 * @param Mapper $mapper
	 * @param unknown $cache
	 */
	public function __construct(Mapper $mapper, $cache) 
	{
		$this->cache = $cache;
	}
	
	/**
	 * Load mappings from annotations
	 * 
	 * @param string $entity
	 * @return array
	 */
	public function loadMappings($entity) 
	{	
		$mapping = $this->cache->load($entity);
		if (!$mapping) {

			$mapping = array();
			
			$rc = ClassType::from($entity);
			$annotations = $rc->getAnnotations();
			
			// class mapping
			if (isset($annotations['mapping'])) {
				$mapping = array_merge($mapping, current($annotations['mapping'])->getArrayCopy());
			}		 
			$mapping += array(
				'primary' => 'id',
				'table' => strtolower(preg_replace('#(.)(?=[A-Z])#', '$1_', $entity)),
			);
			$alias = str_replace('_', '', preg_replace("/([^_])[^_]*/i", "$1", $mapping['table']));
			$mapping += array('alias' => $alias);
			
			// properties and association mapping
			$mapping['properties'] = array();
			$mapping['associations'] = array();
			foreach ($rc->getProperties() as $property) {
				
				// property
				if ($property->getAnnotation('mapped') !== NULL) {
					$propertyName = $property->getName();
					$column = strtolower(preg_replace('#(.)(?=[A-Z])#', '$1_', $propertyName));
					$mapping['properties'][$property->getName()] = $column;
				}
				
				// association
				if (($assoc = $property->getAnnotation('association')) !== NULL) {
					$propertyName = $property->getName();
					if (count($assoc) < 2) {
						throw new \Nette\InvalidStateException("Association " . $entity . "::" . $propertyName . " invalid");
					}
					
					if (isset($assoc[3])) {
						$mapping['properties'][self::META_COLUMN . $assoc[3]] = $assoc[3];
						$assocColumn = $assoc[3];
					} else {
						$assocColumn = $mapping['primary'];
					}
					
					
					$assoc = array(
						'entity' => $assoc[0],
						'type' => $assoc[1],
						'column' => $assoc[2],
						'selfColumn' => $assocColumn
					);
					$mapping['associations'][$property->getName()] = $assoc;
				}
			}
						
			// store mapping in cache
			$this->cache->save($entity, $mapping, array(Cache::FILES => $rc->getFileName()));
		}

		return $mapping;				
	}
	
}
