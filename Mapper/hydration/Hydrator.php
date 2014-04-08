<?php

namespace Mapper\Hydration;

use Nette\Database\Row;

/**
 * Hydrates data from database row
 * 
 * @author Petr Novotny
 */
interface Hydrator
{
	/**
	 * Hydrate data from row
	 * 
	 * @param Row $row
	 * @param string $entity
	 * @return mixed
	 */
	public function hydrate(Row $row, $entity);

}