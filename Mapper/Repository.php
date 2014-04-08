<?php

namespace Mapper;

use Nette\Object,
	Nette\Database\Connection,
	Mapper\Utils\ObjectCollection,
	Mapper\Hydration\Hydrator,
	Mapper\Source\SQLSource,
	Nette\Caching\Cache;
use Mapper\Utils\AssocCollection;
use Nette\InvalidStateException;
use Mapper\Mapping\Mapping;


/**
 * Repository of entities
 * 
 * @author Petr Novotny
 */
class Repository extends Object
{
	
	// association types definition
	const TO_MANY = 'many';
	const TO_ONE = 'one';
	
	/**
	 * Metadata mapping
	 * 
	 * @var array
	 */
	protected $mapping;
	
	/**
	 * Entity which repository is for
	 * 
	 * @var string
	 */
	protected $entity;
	
	/**
	 * Database connection
	 * 
	 * @var Nette\Database\Connection
	 */
	protected $dbConnection;
	
	/**
	 * Object hydrator
	 * 
	 * @var Mapper\Hydrator
	 */
	protected $hydrator;
	
	/**
	 * Result cache
	 * 
	 * @var Nette\Caching\Cache
	 */
	protected $resultCache;
	
	/**
	 * Mapper instance
	 * 
	 * @var Mapper
	 */
	protected $mapper;
	
	/**
	 * Construct routines
	 * 
	 * @param string $entity
	 * @param array $mapping
	 */
	public function __construct($entity, Connection $conn, Hydrator $hydrator, Mapper $mapper)
	{
		$this->entity = $entity;
		$this->dbConnection = $conn;
		$this->hydrator = $hydrator;
		$this->mapper = $mapper;
	} 
	
	/**
	 * Mapping setter
	 * 
	 * @param array $mapping
	 */
	public function setMapping(array $mapping)
	{
		$this->mapping = $mapping;
	}
	
	/**
	 * Saves mapped object to database
	 *
	 * @access public
	 * @param mixed $object
	 * @return void
	 */
	public function save($object)
	{
		$primary = $this->mapping['primary'];
		
		if (empty($object->$primary)) {
			$id = $this->create($object);
		} else {
			$id = $this->update($object);
		}

		return $id;
	}
	
	/**
	 * Saves new object
	 *
	 * @access protected
	 * @param Object $object
	 * @return void
	 */
	protected function create($object)
	{

		$values = $this->getObjectValues($object);
	
		// save object to database
		$this->dbConnection->query("INSERT INTO `" . $this->mapping['table'] . "`", $values);
	
		// set primary id
		$idPrimary = $this->dbConnection->lastInsertId();
		
		$primaryAttribute = $this->getPropertyByColumn($this->mapping['primary']);		
		$object->{$primaryAttribute} = $idPrimary;
	
		return $idPrimary;
	}
	
	private function getObjectValues($object)
	{
		$values = array();
		
		$columns = $this->mapping['properties'];
		
		foreach ($columns as $property => $column) {
		
			// skip metadata
			if (strpos($property, Mapping::META_COLUMN) !== FALSE) {
				continue;
			}
				
			// if object attribute is DateTime object
			if ($object->$property instanceof \DateTime) {
				$values[$column] = $object->$property->format('Y-m-d H:i:s');
			} else {
				$values[$column] = $object->$property;
			}
		
			if ($column == $this->mapping['primary']) {
				$primaryAttribute = $property;
			}
		}
		
		// associations
		if ($this->mapping['associations']) {
				
			foreach ($this->mapping['associations'] as $assocName => $assoc) {
				if ($assoc['type'] === self::TO_ONE) {
						
					$assocEntity = $object->{$assocName};
					if ($assocEntity !== NULL) {
						$prop = $this->mapper->getRepository($assoc['entity'])->getPropertyByColumn($assoc['column']);
						$values[$assoc['selfColumn']] = $assocEntity->$prop;
					}
				}
			}
		}
		
		return $values;
	}
	
	/**
	 * Updates existing object in database
	 *
	 * @access protected
	 * @param Object $object
	 * @return void
	 */
	protected function update($object)
	{
		
		$values = $this->getObjectValues($object);
		$primaryAttribute = $this->getPropertyByColumn($this->mapping['primary']);
		$this->dbConnection->query("UPDATE `".$this->table."` SET ", $values, "WHERE `".$this->primary."` = %i", $object->{$primaryAttribute});
		return $object->{$primaryAttribute};
	}
	
	/**
	 * Delete item from database
	 * @param int|Object $object
	 * @return void
	 */
	public function delete($object)
	{
		// find primary attribute
		foreach ($this->mapping as $property => $column) {
			if ($column == $this->primary) {
				$primaryAttribute = $property;
				break;
			}
		}
	
		if (is_numeric($object)) {
			$id = (int) $object;
		} else {
			$id = $object->{$primaryAttribute};
			foreach ($this->mapping as $property => $column) {
				$object->$property = NULL;
			}
		}
	
		// build delete query
		$query = "DELETE FROM ".$this->table." WHERE ".$this->primary." = ".$id;
		$this->dbConnection->query($query);
		$this->onDelete($this);
	}
	
	
	/**
	 * Find object by its id
	 *
	 * @param int $id
	 */
	public function find($id, $with = array(), $cache = FALSE, $forceLoad = FALSE)
	{
		return $this->findById($id, $with, $cache, $forceLoad);
	}
	
	
	/**
	 * Loads object with given id (primary key)
	 *
	 * @throws ArgumentOutOfRangeException
	 * @param int $id - primary key of object to find
	 * @param string $objClassName - if is not passed, return object classname is determined from mappername
	 */
	public function findById($id, $with = array(), $cache = FALSE, $forceLoad = FALSE)
	{
		$id = (int) $id;
		if (!$id) {
			throw new \InvalidArgumentException(sprintf(self::ERROR_INVALID_ID, $id));
		}
	
		// when loading is not forced, get hydrated entity
		if (!$forceLoad) {
			$instance = $this->hydrator->getEntityInstance($this->entity, $id); 
			if ($instance) {
				return $instance;
			}
		}
		
		$sql = $this->buildSQL($id);
		
		// find objects
		$source = $this->findBySQL($sql);
		if ($cache) {
			$source->useCache($cache);
		}		
		
		// association to JOIN
		if ($with) {
			foreach ($with as $assoc) {
				$source->with($assoc);
			}
		}
		
		// if object was not found throw runtime exception
		if (!count($source)) {
			throw new \InvalidArgumentException($this->entity . " with id ".$id." was not found");
		}
	
		return $source->getFirst();
	}
	
	/**
	 * Build SQL to load single object
	 * 
	 * @param string $id
	 * @return string
	 */
	protected function buildSQL($id = NULL)
	{
		// build SQL query
		return $query = $this->getSelectQuery().' WHERE ' . $this->mapping['alias'] . '.' . $this->mapping['primary']. ' = '.$id;
	}
	
	
	/**
	 * Finds all objects
	 *
	 * @param string $className - if class name differs from name determined from Mapper
	 * @return ObjectCollection
	 */
	public function findAll()
	{
		// build source
		$objectsSource = new SQLSource($this);
		$objectsSource->sql = $this->getSelectQuery();
	
		return $objectsSource;
	}


	/**
	 * Finds object by given parameters
	 *
	 * @param array $params
	 * @param string $objClassName
	 * @param string $logicCond
	 * @return ObjectCollection
	 */
	public function loadByParams($params, $objClassName = NULL, $logicCond = 'AND')
	{
	
		// build where condition
		$where = array();
		foreach ($params as $property => $value) {
			if (is_null($value)) {
				$where[] = $this->table.".`".$this->mapping[$property]."` IS NULL";
			} else {
				$where[] = $this->table.".`".$this->mapping[$property]."` = '$value'";
			}
		}
	
		// build source
		$objectsSource = new SQLSource($this);
		$objectsSource->sql = $this->getSelectQuery();
		foreach ($where as $cond) {
			$objectsSource->where($cond, $logicCond);
		}
	
		return $objectsSource;
	}
	
	/**
	 * Get select query for one object
	 * 
	 * @return string
	 */
	public function getSelectQuery()
	{
		// property => column map
		$propertyMapping = $this->mapping['properties'];
		
		$toSelect = array_values($propertyMapping);
		$alias = $this->getAlias();
		foreach ($toSelect as &$column) {
			$column = $alias.'.'.$column;
		}
		
		return "SELECT ".implode(',', $toSelect). " FROM `" . $this->mapping['table'] . '` ' . $alias;
	}
	
	/**
	 * Get mapping alias
	 * 
	 * @return string
	 */
	public function getAlias()
	{
		if (isset($this->mapping['alias'])) {
			return $this->mapping['alias']; 
		} else {
			preg_match_all("/([^_])[^_]*/i", $this->mapping['table'], $array, PREG_PATTERN_ORDER);
			$alias = preg_replace("/([^_])[^_]*/i", "$1", $this->mapping['table']);
			return str_replace("_", "", $alias);
		}
	}
	
	/**
	 * Load associated objects
	 * 
	 * @param AssocCollection $col
	 */
	public function loadAssociation($entity, $assocName)
	{
		// get association info
		if (!isset($this->mapping['associations'][$assocName])) {
			throw new InvalidStateException("Association mapping of " . $this->entity . " ($assocName) not found");
		}
		$assoc = $this->mapping['associations'][$assocName];
		
		// get repository of associated entity
		$assocRepo = $this->mapper->getRepository($assoc['entity']);
		
		$assocValue = $entity->getMeta($assoc['selfColumn']); 
		
		if (!$assocValue) {
			return;
		}
		
		if ($assoc['type'] === self::TO_MANY) {
			$sqlSource = $assocRepo->findAll()->where($assoc['column'] . " = $assocValue");
			return $sqlSource;
		} else if ($assoc['type'] === self::TO_ONE) {
			$sqlSource = $assocRepo->findAll()->where($assoc['column'] . " = $assocValue");
			return $sqlSource->getFirst();
		}
	}
	
	
	/**
	 * Finds record by given SQL query
	 *
	 * @param string $sql
	 * @return SQLSource
	 */
	public function findBySQL($sql)
	{
		$source = new SQLSource($this);
		$source->sql = $sql;
			
		return $source;
	}
	
	/**
	 * 
	 * @param SQLSource $source
	 * @param unknown $assocName
	 */
	public function joinFromAssocName(SQLSource $source, $assocName)
	{
		// get association info
		$assoc = $this->mapping['associations'][$assocName];
		$assocMapping = $this->mapper->getMapping($assoc['entity']);
		
		// add JOIN to SQL source
		$joinCond = $assocMapping['alias'] . '.' . $assoc['column'] . ' = ' . $this->mapping['alias'] . '.' . $assoc['selfColumn'];
		$source->join($assocMapping['table'], $assocMapping['alias'], $joinCond);
		
		// add SELECT to SQL source
		$selectColumns = array();
		foreach ($assocMapping['properties'] as $column) {
			$selectColumns[] = $assocMapping['alias'] . '.' . $column;
		}
		$source->select($selectColumns);
	}
	
	
	/**
	 * Hydate data from SQLSource
	 *
	 * @param SQLSource $source
	 * @return ObjectCollection
	 */
	public function hydrateSource(SQLSource $source)
	{
		// get source SQL query
		$sql = $source->getSql();
		
		// look if collection is already hydrated
		$sqlHash = md5($sql);
		$collIdentity = 'collection_' . $this->entity . '_' . $sqlHash;
		
		if ($this->hydrator->isCollectionHydrated($collIdentity)) {
			return $this->hydrator->getCollection($collIdentity);
		}
		
		// use cache?
		$useCache = $source->getUseCache();

		$rows = NULL;
		if ($useCache) {			
			// generate cache key
			$cacheKey = $this->entity . '_' . $sqlHash;
			$rows = $this->resultCache->load($cacheKey);
		}
			
		// objects are not cached yet			
		if (!$rows) {
			// fetch data from database
			$rows = $this->query($sql)->fetchAll();
			
			// save objects to cache
			if ($useCache) {
				$cond = array(\Nette\Caching\Cache::TAGS => array($this->entity));
				if (is_array($useCache)) {
					$cond = array_merge_recursive($cond, $useCache);
				}
				$this->resultCache->save($cacheKey, $rows, $cond);
			}
		}
		
		// hydrate objects
		$objects = $this->hydrator->hydrateCollection($rows, $this->entity, $source->isAliased(), $collIdentity);
			
		// hydrate joins
		foreach ($source->getWith() as $association) {
				
			$assocEntity = $this->mapping['associations'][$association]['entity'];
			$colKey = 'collection_' . $assocEntity . '_' . $sqlHash;
				
			$this->hydrator->hydrateCollection($rows, $assocEntity, $source->isAliased(), $colKey);
			$assocRepo = $this->mapper->getRepository($assocEntity);

			$assocSource = clone $source;
			$assocSource->setBuildSql(FALSE);
			$assocSource->setHydrateRepository($assocRepo);
			
			foreach ($objects as $obj) {
					// association is collection
				if ($this->mapping['associations'][$association]['type'] === self::TO_MANY) {
					$obj->{$association}->setSQLSource($assocSource);
				} else {
					// association is one object
					$obj->{$association} = $assocSource->getFirst();
				}
			}
		}
			
		return $objects;
	}
	
	
	/**
	 * Finds objects collection by defined association
	 * table
	 *
	 * @access public
	 * @param array $assocParams
	 * @param int $calledFromId
	 * @return ObjectCollection
	 */
	public function loadByAssocTable(array $assocParams, $calledFromId)
	{
	
		$joinColumn = isset($assocParams['joinColumn']) ? $assocParams['joinColumn'] : $this->mapping[$assocParams['entityLink']];
	
		$query = $this->getSelectQuery() .
		" JOIN " . $assocParams['assocTable'].
		" ON ". $this->table.'.'.$this->mapping[$assocParams['entityLink']]." = ".$assocParams['assocTable'].'.'.$joinColumn.
		" WHERE ".$assocParams['assocTable'].".".$assocParams['this']." = ".$calledFromId;
	
	
		$objectsSource = new SQLSource($this);
		$objectsSource->sql = $query;
		$objectsSource->objectClassName = $this->getClassNameByMapper();
	
	
		return $objectsSource;
	}


	/**
	 * Get primary key name
	 * @return string
	 */
	public function getPrimary()
	{
		return $this->primary;
	}
	

	/**
	 * Get cache storage for result of queries
	 * @return MCache
	 */
	public function getResultCache()
	{
		return $this->resultCache;
	}
	
	/**
	 * Update specific field
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param int $id
	 */
	public function updateField($field, $value, $id)
	{
		$sql = "
			UPDATE {$this->table}
			SET {$this->mapping[$field]} = '$value'
			WHERE {$this->primary} = $id
		";
	
		$this->query($sql);
	}	
	
	/**
	 * Adaptor for dbConnection query method
	 *
	 * REPO
	 * @access protected
	 * @param $query - SQL query
	 * @return DibiRow|DibiError|int (when updating records)
	 */
	public function query($query)
	{
		return $this->dbConnection->query($query);
	}
	
	/**
	 * Set cache storage
	 * 
	 * @param unknown $cache
	 */
	public function setResultCache(Cache $cache)
	{
		$this->resultCache = $cache;
	}
	
	/**
	 * Get object property by column name
	 * 
	 * @param string $column
	 * @return string|NULL
	 */
	public function getPropertyByColumn($column)
	{
		$reverseMap = array_flip($this->mapping['properties']);
		if (isset($reverseMap[$column])) {
			return $reverseMap[$column];
		} else {
			return NULL;
		}
	}
	
	public function countSql($sql)
	{
		return $this->query($sql)->fetchColumn();
	}
	
}