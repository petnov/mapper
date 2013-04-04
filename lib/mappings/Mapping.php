<?php

namespace Mapper\Mapping;

use Nette\Object,
	Mapper\Mapper;

/**
 * Metadata Mapping of given entity
 * 
 * @author Petr novotny
 */
abstract class Mapping extends Object 
{
	const META_COLUMN = '_META_';
	
	/**
	 * Mapper
	 * 
	 * @var Mapper
	 */
	protected $mapper;
	
	/**
	 * Load mappings
	 * 
	 * @throws \InvalidArgumentException - 
	 * @return array - array of mappings
	 */
	abstract public function loadMappings($entity);

	/**
	 * Construct routines
	 * 
	 * @param Mapper $mapper
	 */
	public function __construct(Mapper $mapper) 
	{
		$this->mapper = $mapper;
	}
}

/**
 * Exception when mapping for certain entity not found
 * 
 * @author Petr Novotny
 */
class MappingNotFoundException extends \RuntimeException {}
