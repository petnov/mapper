<?php

namespace Mapper\Utils;

use Mapper\BaseEntity,
	Mapper\Repository,
	Mapper\Source\SQLSource;


/**
 * Collection for association objects
 * 
 *  @author Petr Novotny
 */
class AssocCollection implements \IteratorAggregate 
{
	/**
	 * SQL mapper source
	 * 
	 * @var SQLSource
	 */
	private $sqlSource;
	
	/**
	 * Repository to load objects from
	 * 
	 * @var Repository
	 */
	private $repository;
	
	/**
	 * Entity association name
	 * 
	 * @var string
	 */
	private $assocName;
	
	/**
	 * Entity who owns the association
	 * 
	 * @var BaseEntity
	 */
	private $fromEntity;
	
	/**
	 * Items when object was not persisted yet
	 * 
	 * @var ObjectCollection
	 */
	private $items;
	
	/**
	 * Construct routines
	 * 
	 * @param BaseEntity $entity
	 * @param string $assocName
	 */
	public function __construct(BaseEntity $entity, $assocName) 
	{
		$this->fromEntity = $entity;
		$this->assocName = $assocName; 
		$this->items = new ObjectCollection();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() 
	{
		if ($this->fromEntity->getMapper()) {
			if ($this->sqlSource === NULL) {
				$this->sqlSource = $this->getRepository()->loadAssociation($this->fromEntity, $this->assocName);
			}
		
			return $this->sqlSource->getIterator();
		} else {
			return $this->items;
		}
	}
	
	/**
	 * Get repository to load associated objects from
	 * 
	 * @return \Mapper\Repository
	 */
	private function getRepository()
	{
		if (!$this->repository) {
			$this->repository = $this->fromEntity->getMapper()->getRepository(get_class($this->fromEntity));
		}
		
		return $this->repository;
	}
	
	/**
	 * Reset objects source -> forces collection to reload
	 * @return void
	 */
	public function reset()
	{
		$this->sqlSource = NULL;
	}
	
	/**
	 * SQLSource setter
	 * @param SQLSource $source
	 */
	public function setSQLSource(SQLSource $source)
	{
		$this->sqlSource = $source;
	}
	
	/**
	 * Add object to collection
	 * 
	 * @param mixed $obj
	 */
	public function add($obj)
	{
		$this->items[] = $obj;
	}

}

