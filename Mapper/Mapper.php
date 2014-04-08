<?php

namespace Mapper;

use Mapper\Hydration\Hydrator;

use Mapper\Mapping\Mapping;

use Nette\Object,
	Mapper\Hydration\ObjectHydrator,
	Nette\Caching\Cache,
	Mapper\Mapping\AnnotationMapping,
	Mapper\Hydration\IdentityMap,
	Mapper\Mapping\ArrayMapping;

/**
 * Simple ORM using data mapper implementation
 *
 * @copyright Petr Novotny 2013
 * @author Petr Novotny
 */
class Mapper extends Object
{

	/**
	 * Database connection
	 *
	 * @var Nette\Database\Connection $dbConnection
	 */
	protected $dbConnection;

	/**
	 * Hydrator is responsible for hydrating data from source (ex.: hydrate objects from database rows)
	 * 
	 * @var Mapper\Hydration\Hydrator
	 */
	protected $hydrator;
	
	/**
	 * Pool for created repositories
	 *
	 * @var array $mapperPool
	 */
	protected $pool = array();
	
	/**
	 * Implementation of metadata mappings
	 * 
	 * @var Mapper\Mapping\Mapping
	 */
	protected $metadataMapping;
	
	/**
	 * Cache storage for data results
	 * 
	 * @var Nette\Caching\Cache
	 */
	protected $resultCache;
	
	/**
	 * Identity map for storing hydrated objects
	 * 
	 * @var Mapper\Hydration\IdentityMap
	 */
	protected $identityMap;

	/**
	 * Exception messages
	 */
	const REPOSITORY_DOES_NOT_EXIST = 'Repository %s does not exist';
	
	/**
	 * Result cache key
	 */
	const CACHE_KEY_RESULT = 'MapperResult';

	/**
	 * Construct routines
	 * 
	 * @param Nette\Database\Connection $conn
	 */
	public function __construct(\Nette\Database\Connection $conn, Mapping $mapping, Hydrator $hydrator, \Nette\Caching\IStorage $cacheStorage)
	{
		$this->dbConnection = $conn;
		$this->metadataMapping = $mapping;
		$this->hydrator = $hydrator;
		$this->identityMap = new IdentityMap();
		$this->resultCache = new Cache($cacheStorage, self::CACHE_KEY_RESULT);
	}

	/**
	 * Returns repository for certain entity
	 *
	 * @access public
	 * @param string $entity
	 * @return Mapper\Repository 
	 */
	public function getRepository($entity)
	{
		// check if repository exists
		$className = 'Mapper\Repository';
		if (class_exists($entity . 'Repository')) {
			$className = $entity . 'Repository';
		}
		
		// check if repository is already stored in pool
		if (isset($this->pool[$entity])) {
			return $this->pool[$entity];
		}

		$repository = new $className($entity, $this->dbConnection, $this->hydrator, $this);
		$repository->setResultCache($this->resultCache);
		$this->pool[$entity] = $repository;
		
		// create repository instance
		$entityMapping = $this->metadataMapping->loadMappings($entity);
		$repository->setMapping($entityMapping);

		return $repository;
	}


	/**
	 * Database connection getter
	 * 
	 * @return DibiConnection
	 */
	public function getDbConnection()
	{
		return $this->dbConnection;
	}
	
	/**
	 * Result cache getter
	 * 
	 * @return Nette\Caching\Cache
	 */
	public function getResultCache()
	{
		return $this->resultCache;
	}
	
	/**
	 * Identity map getter
	 * 
	 * @return Mapper\Hydration\IdentityMap
	 */
	public function getIdentityMap()
	{
		return $this->identityMap;
	}
	
	/**
	 * Get metadata mapping of entity
	 * 
	 * @param string $entity
	 * @return array
	 */
	public function getMapping($entity)
	{
		return $this->metadataMapping->loadMappings($entity);
	}

}
