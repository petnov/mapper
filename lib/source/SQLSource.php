<?php

namespace Mapper\Source;

use Nette\Object,
	IteratorAggregate,
	Countable,
	ArrayAccess,
	Mapper\Repository;

/**
 * Source for objects loaded from database
 * @author Petr Novotny
 */
final class SQLSource extends Object implements IteratorAggregate, Countable, ArrayAccess
{

	/**
	 * Prepared SQL for object source
	 *
	 * @var string
	 */
	private $sql;

	/**
	 * Which column order by
	 *
	 * @var string
	 */
	private $orderBy;
	
	/**
	 * Which column to group by
	 * 
	 * @var string
	 */
	private $groupBy;

	/**
	 * Limit of return objects
	 *
	 * @var int
	 */
	private $limit;

	/**
	 * Offset of returned objects
	 *
	 * @var int
	 */
	private $offset;

	/**
	 * Repository source was created by
	 *
	 * @var Repository
	 */
	private $repository;
	
	/**
	 * Repository used for hydratation
	 * 
	 * @var Repository
	 */
	private $hydrateRepository;

	/**
	 * SQL without additional conditions, limits, etc.
	 * @var string
	 */
	private $originalSql;

	/**
	 * Where conditions
	 *
	 * @var array
	 */
	private $where = array();
	
	/**
	 * Items hydrated from database
	 * @var array
	 */
	private $items;
	
	/**
	 * Use result cache?
	 * @var bool
	 */
	private $useCache;
	
	/**
	 * What to select in count SQL?
	 * 
	 * @var string
	 */
	private $countSelect;
	
	/**
	 * Are columns aliased by table? (when using a JOIN)
	 * 
	 * @var bool
	 */
	private $aliased = FALSE;
	
	/**
	 * What to join with?
	 * 
	 * @var array - join condition
	 */
	private $joins = array();
	
	/**
	 * Associations to join with
	 * 
	 * @var array
	 */
	private $with = array();
	
	/**
	 * What columns to select (additinal)
	 * 
	 * @var array
	 */
	private $select = array();
	
	/**
	 * Build SQL when calling getSql()? 
	 * When FALSE getSql() returns last built query
	 * 
	 * @var bool
	 */
	private $buildSql = TRUE;

	/**
	 * Construct routines
	 * @param Repository $repository
	 */
	public function __construct(Repository $repository)
	{
		$this->repository = $repository;
		$this->hydrateRepository = $repository;
	}

	/**
	 * Repository used for hydratation
	 * 
	 * @param Repository $repository
	 */
	public function setHydrateRepository(Repository $repository)
	{
		$this->hydrateRepository = $repository;
	}

	/**
	 * @return string
	 */
	public function getOrderBy()
	{
		return $this->orderBy;
	}


	/**
	 * Function to delegate calling on collection
	 * object which is created when calling before foreach
	 */
	public function __call($name, $args)
	{
		$collection = $this->getIterator();
		if (method_exists($collection, $name)) {
			return call_user_func_array(array($collection, $name), $args);
		} else {
			throw new \BadMethodCallException('Call to undefined method "'.$name.'"');
		}
	}


	/**
	 * Build SQL string with additional conditions
	 * @return string
	 */
	public function getSql()
	{
		if ($this->buildSql === FALSE) {
			return $this->sql;
		}		
		
		// reset to the very first SQL set
		$this->resetSql();

		// apply all additional JOINs
		$this->applyJoins();
		
		// apply additional conditions
		$this->applyWhere();
		
		// apply select columns
		$this->applySelect();
		
		// when there are JOINs in SQL, alias all columns with table prefix
		if (strpos($this->sql, 'JOIN') !== FALSE) {
			$this->sql = $this->aliasColumns($this->sql);
			$this->aliased = TRUE;
		}
		
		// add group by
		if ($this->groupBy) {
			$this->sql .= ' GROUP BY ' . $this->groupBy;
		}
		
		// apply ordering
		$this->applyOrderBy();

		// build limit and offset
		$this->applyLimit();
		
		return $this->sql;
	}

	/**
	 * Join to table with alias
	 * 
	 * @param string $table
	 * @param string $alias
	 * @param string $cond
	 * @return \Mapper\Source\SQLSource
	 */
	public function join($table, $alias, $cond)
	{
		$join = "\nJOIN `$table` $alias ON $cond";
		$this->joins[] = $join;
		 
		return $this;
	}
	
	/**
	 * Apply JOINs to SQL query
	 * 
	 * @return void
	 */
	private function applyJoins()
	{		
		// modify SQL
		$this->sql = preg_replace("/(.*)(FROM)(.*)(WHERE)(.*)/", "$1 $2 $3 " . implode(' ', $this->joins) . " $4 $5", $this->sql);
	}
	
	/**
	 * Apply SELECT columns
	 * 
	 * @return void
	 */
	private function applySelect()
	{
		// modify SQL
		if ($this->select) {
			$this->sql = preg_replace("/(SELECT)(.*)(FROM)(.*)/", "$1 $2 	, " . implode(', ', $this->select) . " $3 $4", $this->sql);
		}
	}

	/**
	 * Order by closure
	 * @param string $orderBy
	 */
	public function orderBy($orderBy)
	{
		if (strpos($this->sql, 'ORDER BY') !== FALSE) {
			throw new \BadMethodCallException('Could not set order by clause, its already set in given sql');
		}

		$this->orderBy = $orderBy;
		return $this;
	}

	/**
	 * @param unknown $groupBy
	 */
	public function groupBy($groupBy)
	{
		$this->groupBy = $groupBy;
	}
	
	/**
	 * Select count setter
	 * 
	 * @param string $expr
	 */
	public function setCountSelect($expr)
	{
		$this->countSelect = $expr;
	}
	
	/**
	 * Set original SQL to find objects by
	 * 
	 * @param string $sql
	 */
	public function setSql($sql)
	{		
		$this->sql = $sql;
		$this->originalSql = $sql;
	}
	
	/**
	 * Alias SQL columns to know which table data are from
	 * 
	 * @param string $sql
	 * @return string
	 */
	private function aliasColumns($sql)
	{
		$sql = preg_replace_callback("/(SELECT)(.*)(\s*)(FROM.*)/", function($columns) {
			
			// alias all columns
			$aliased = preg_replace_callback("/([a-z0-9]*)\.([a-z0-9\_]*)/", function($column) {
				return $column[0] . ' ' . $column[1] . '_' . $column[2];
			}, trim($columns[2]));
			
			return $columns[1] . ' ' . $aliased . ' ' . $columns[3] . $columns[4];
		}, $sql);

		return $sql;		
	}

	/**
	 * Applies set order by clause to actual SQL
	 *
	 * @return void
	 */
	private function applyOrderBy()
	{
		if ($this->orderBy) {
			$this->sql .= ' ORDER BY '.$this->orderBy;
		}
	}


	/**
	 * Applies where clause by given conditions
	 *
	 * @return void
	 */
	private function applyWhere()
	{
		// determine if where clause is already set in actual query
		$isSetWhere = strpos($this->sql, 'WHERE');

		// build where condition
		$cond = '';
		$i = 0;
		foreach ($this->where as $where) {
			if ($i == 0 && !$isSetWhere) {
				$cond .= $where['cond'];
			} else {
				$cond .= ' '. $where['logic'] . ' ' . $where['cond'];
			}
			$i++;
		}

		if (!$isSetWhere) {
			$whereString = ' WHERE ';
		} else {
			$whereString = ' ';
		}
		// add where clause to actual sql query
		//TODO: append to exist conditions
		return $this->sql .= $cond ? $whereString.$cond : '';
	}

	/**
	 * Apply limit closure
	 * @return void
	 */
	private function applyLimit()
	{
		if ($this->limit) {
			$this->sql .= ' LIMIT ' . $this->limit;
		}

		if ($this->offset) {
			$this->sql .= ' OFFSET ' . $this->offset;
		}
	}

	/**
	 * Returns collection (iterator)
	 *
	 * @return Iterator
	 */
	public function get()
	{
		return $this->getIterator();
	}

	/**
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return $this->hydrateRepository->hydrateSource($this);
	}

	/**
	 * Reset SQL used to get objects
	 * @return void
	 */
	public function resetSql()
	{
		// reset query
		$this->sql = $this->originalSql;
	}
	
	/**
	 * Reset SQL and items
	 * @return void
	 */
	public function reset()
	{
		$this->resetSql();
	}


	/**
	 * @param int $limit
	 */
	public function limit($limit)
	{
		$this->limit = $limit;
		return $this;
	}


	/**
	 * @param int $offset
	 */
	public function offset($offset)
	{
		$this->offset = $offset;
		return $this;
	}


	/**
	 * Add where condition
	 * 
	 * @param string $cond
	 * @param string $logic
	 */
	public function where($cond, $logic = 'AND')
	{
		$this->where[] = array('cond' => $cond, 'logic' => $logic);
		return $this;
	}

	/**
	 * Reset search conditions
	 *
	 * @return void
	 */
	public function clearWhere()
	{
		$this->where = array();
	}

	/**
	 * Add associations to join with
	 * 
	 * @param unknown $association
	 * @return \Mapper\SQLSource
	 */
	public function with($association)
	{
		// add association JOIN
		$this->with[] = $association;
		
		// add JOIN table and condition
		$this->repository->joinFromAssocName($this, $association);
		
		return $this;
	}
	
	/**
	 * Get joined entities
	 * 
	 * @return array
	 */
	public function getJoins()
	{
		return $this->joins;
	}

	/**
	 * Count total objects count
	 * @return int
	 */
	public function count()
	{
		return count($this->hydrateRepository->hydrateSource($this));
	}

	/**
	 * Get total count without limit and offset
	 * @return int
	 */
	public function getTotalCount()
	{
		$sql = $this->getSql();
		$sql = preg_replace("/SELECT(.*)FROM(.*)/", "SELECT COUNT(*) FROM $2", $sql);
		
		if (($limit = strpos($sql, 'LIMIT')) !== FALSE) {
			$sql = substr($sql, 0, $limit);
		}
		
		return $this->repository->countSql($sql);
	}
	
	/**
	 * Get SQL for count
	 */
	protected function getCountSql()
	{
		
		$this->resetSql();
		
		// apply joins and conditions
		$sql = $this->applyDirectJoins();
		
		// build conditions
		$sql = $this->applyWhere($sql);
		
		if ($this->countSelect) {
			
		}
		
		return $sql;
	}
	
	/**
	 * Use cache
	 * 
	 * 
	 * @return SQLSource
	 */	
	public function useCache($conditions = array())
	{
		if ($conditions === FALSE) {
			$this->useCache(FALSE);
		} else {
			$this->useCache = $conditions;
		}
		return $this;
	}
	
	/**
	 * Use cache getter - is object source SQL cached?
	 * 
	 * @return boolean
	 */
	public function getUseCache()
	{
		return $this->useCache;
	}
	
	/**
	 * Add columns to select
	 * 
	 * @param array $columns
	 */
	public function select(array $columns)
	{
		$this->select = array_merge($this->select, $columns);
		return $this;
	}
	
	
	/**
	 * Is SQL aliased?
	 * 
	 * @return boolean
	 */
	public function isAliased()
	{
		return $this->aliased;
	}
	
	/**
	 * Associations source is JOINed with getter
	 * 
	 * @return array
	 */
	public function getWith()
	{
		return $this->with;
	}
	
	/**
	 * Build SQL setter
	 * 
	 * @param bool $bool
	 */
	public function setBuildSql($bool)
	{
		$this->buildSql = (bool) $bool;
	}
	
	// Array Access
	public function offsetExists($offset) {}
	public function offsetSet($offset, $value) {}
	public function offsetUnset($offset) {}
	public function offsetGet($offset)
	{
		$collection = $this->getIterator();
		if ($collection->offsetExists($offset)) {
			return $collection->offsetGet($offset);
		}
	}	

}
