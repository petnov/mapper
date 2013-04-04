<?php

namespace Mapper\Mapping;

/**
 * Get entity metadata mapping from definition in entity repository
 * 
 * @author Petr Novotny
 */
class ArrayMapping extends Mapping 
{
	
	/**
	 * Load mappings from repository data
	 */
	public function loadMappings($entity) 
	{
		
		$repository = $this->mapper->getRepository($entity);
		$mapping = array();
		
		// table name
		if (property_exists($repository, 'table')) {
			$mapping['table'] = $repository->table;
		}

		// primary key
		if (property_exists($repository, 'primary')) {
			$mapping['primary'] = $repository->primary;
		}
		
		// property mappings
		if (property_exists($repository, 'map')) {
			$mapping['properties'] = $repository->map;
		}
		
		// associations mappings
		if (property_exists($repository, 'associations')) {
			$mapping['associations'] = $repository->associations;
		}
		
		return $mapping;
	}
	
}
