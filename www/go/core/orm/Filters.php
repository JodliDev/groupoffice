<?php
namespace go\core\orm;

use DateInterval;
use Exception;
use go\core\db\Criteria;
use go\core\jmap\exception\UnsupportedFilter;
use go\core\util\DateTime;

/**
 * Filters
 * 
 * Holds all filters for an entity
 */
class Filters {
	
	private $filters = [];

	private $usedFilters = [];

	const NO_DEFAULT = '__NO_DEFAULT__';

	
	/**
	 * Add generic filter function
	 * 
	 * See also addText(), addNumber() and addDate() for different types
	 * 
	 * @param string $name The name of the filter.
	 * @param Callable $fn The filter function will be called with Criteria $criteria, $value, Query $query, array $filter 
	 * @param mixed $default The default value for the filter. When not set the filter is not applied if no value is given.
	 * 
	 * @return $this
	 */
	public function add(string $name, callable $fn, $default = self::NO_DEFAULT): Filters
	{
		$this->filters[strtolower($name)] = ['type' => 'generic', 'fn' => $fn, 'default' => $default, 'name' => $name];
		
		return $this;
	}


	/**
	 * Add a filter on a column name
	 *
	 * Shortcut for:
	 *
	 * ```
	 * ->add('businessId', function(Criteria  $c, $value) {
	 *  $c->andWhere('businessId', $value);
	 * });
	 * ```
	 * @param string $name
	 * @return $this
	 */
	public function addColumn(string $name): Filters
	{
		return $this->add($name, function(Criteria  $c, $value) use ($name) {
			$c->andWhere($name, '=', $value);
		});
	}

	// private function validate(Query $query, array $filter) {
	// 	$invalidFilters = array_diff(array_map('strtolower',array_keys($filter)), array_keys($this->filters));
	// 	if(!empty($invalidFilters)) {
	// 		throw new Exception("Invalid filters supplied for '".$query->getModel()."': '". implode("', '", $invalidFilters) ."'");
	// 	}
	// }

	/**
	 * @throws Exception
	 */
	private function applyDefaults(Query $query, Criteria $criteria) {

		$f = [];

		foreach($this->filters as $name => $value) {

			if(in_array($name, $this->usedFilters)) {
				continue;
			}

			if($value['default'] === self::NO_DEFAULT) {
				continue;
			}

			if(!array_key_exists($value['name'], $f)) {
				$f[$value['name']] = $value['default'];
			}
		}

		$this->internalApply($query, $f, $criteria);

	}

	/**
	 * Check if filter was used by last apply() call
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function isUsed(string $name): bool
	{
		return in_array(strtolower($name), $this->usedFilters);
	}

	/**
	 * Apply given filters to query object
	 *
	 * @param Query $query
	 * @param array $filter
	 * @return Filters
	 * @throws Exception
	 */
	public function apply(Query $query, array $filter): Filters
	{
		//keep track of used filters because they can be nested in sub conditions
		$this->usedFilters = [];
		$criteria = new Criteria();
		$this->internalApply($query, $filter, $criteria);

		//apply defaults of unused filters
		$this->applyDefaults($query, $criteria);

		if($criteria->hasConditions()) {
			$query->andWhere($criteria);
		}

		return $this;
	}

	/**
	 * @throws Exception
	 */
	private function internalApply(Query $query, array $filter, Criteria $criteria): void
	{
		if(isset($filter['conditions']) && isset($filter['operator'])) { // is FilterOperator

			foreach($filter['conditions'] as $condition) {
				$subCriteria = new Criteria();
				$this->internalApply($query, $condition, $subCriteria);

				if(!$subCriteria->hasConditions()) {
					continue;
				}

				switch(strtoupper($filter['operator'])) {
					case 'AND':
						$criteria->where($subCriteria);
						break;

					case 'OR':
						$criteria->orWhere($subCriteria);
						break;

					case 'NOT':
						$criteria->andWhereNotOrNull($subCriteria);
						break;
				}
			}

		} else {
			// is FilterCondition
			$subCriteria = new Criteria();

			$this->applyCondition($query, $subCriteria, $filter);

			if($subCriteria->hasConditions()) {
				$criteria->andWhere($subCriteria);
			}
		}

	}

  /**
   * Applies all filters to the query object
   *
   * @param Query $query
   * @param Criteria $criteria
   * @param array $filter
   * @throws Exception
   */
	private function applyCondition(Query $query, Criteria $criteria, array $filter) {
		//$this->validate($query, $filter);		
		foreach($filter as $name => $value) {
			$name = strtolower($name);

			if(!isset($this->filters[$name])) {
				throw new UnsupportedFilter($query->getModel()::entityType()->getName(), $name);
			}

			$this->usedFilters[] = $name;

			$filterConfig = $this->filters[$name];
			
			switch($filterConfig['type']) {
				
				case 'number':					
					$range = $this->checkRange($value);
					if($range) {
						call_user_func($filterConfig['fn'], $criteria, '>=', (int) $range[0], $query, $filter);
						call_user_func($filterConfig['fn'], $criteria, '<=', (int) $range[1], $query, $filter);
					} else
					{
						$v = self::parseNumericValue($value);
						call_user_func($filterConfig['fn'], $criteria, $v['comparator'], (int) $v['query'], $query, $filter);
					}
					break;
					
				case 'date':					
					$range = $this->checkDateRange($value);
					if($range) {
						call_user_func($filterConfig['fn'], $criteria, '>=', $range[0], $query, $filter);
						call_user_func($filterConfig['fn'], $criteria, $range['endHasTime'] ? '<=' : '<', $range[1], $query, $filter);
					} else
					{
						if($value == null) {
							$v = [
								'comparator' => '=',
								'query' => null
							];
						} else {
							$v = self::parseNumericValue($value);
							$v["query"] = new DateTime($v["query"]);
						}
						call_user_func($filterConfig['fn'], $criteria, $v['comparator'], $v["query"], $query, $filter);
					}
					break;
					
				case 'text':
					if(!is_array($value)){
						$value = [$value];
					}
					call_user_func($filterConfig['fn'], $criteria, "LIKE", $value, $query, $filter);
					break;
				
				case 'generic':
					call_user_func($filterConfig['fn'], $criteria, $value, $query, $filter);
					break;
			}
			
		}
	}
	
	/**
	 * Add number filter.
	 * 
	 * Supports ranges 1..4 between 1 and 4 and >=, <> != = operators
	 * 
	 * @param string $name
	 * @param Callable $fn Called with: Criteria $criteria, $comparator, $value, Query $query, array $filters
	 * @param mixed $default The default value for the filter. When not set the filter is not applied if no value is given.
	 * 
	 * @return $this
	 */
	public function addNumber(string $name, Callable $fn, $default = self::NO_DEFAULT): Filters
	{
		$this->filters[strtolower($name)] = ['type' => 'number', 'fn' => $fn, 'default' => $default, 'name' => $name];
		
		return $this;
	}	
	
	/**
	 * Add date filter.
	 * 
	 * Supports ranges. For example last week..now,  >last year, >2019-01-01
	 * 
	 * Values are converted to DateTime objects. Supports all strtotime formats as input.
	 *
	 * @param string $name
	 * @param Callable $fn Called with: Criteria $criteria, $comparator, DateTime $value, Query $query, array $filters
	 * @param mixed $default The default value for the filter. When not set the filter is not applied if no value is given.
	 *
	 * @return $this
	 *@example
	 *
	 * ->addDate('date',function(Criteria $criteria, $comparator, $value){
	 * 	$criteria->where('date', $comparator, $value);
	 * })
	 *
	 * @return $this
	 */
	public function addDate(string $name, Callable $fn, $default = self::NO_DEFAULT): Filters
	{
		$this->filters[strtolower($name)] = ['type' => 'date', 'fn' => $fn, 'default' => $default, 'name' => $name];
		
		return $this;
	}	
	
	/**
	 * Add text filter.
	 * 
	 * Comparator will be LIKE
	 * 
	 * @param string $name
	 * @param Callable $fn Called with: Criteria $criteria, $comparator, $value, Query $query, array $filters
	 * @param mixed $default The default value for the filter. When not set the filter is not applied if no value is given.
	 * 
	 * @return $this
	 */
	public function addText(string $name, Callable $fn, $default = self::NO_DEFAULT): Filters
	{
		$this->filters[strtolower($name)] = ['type' => 'text', 'fn' => $fn, 'default' => $default, 'name' => $name];
		
		return $this;
	}

	/**
	 * Check if a filter is already defined.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasFilter(string $name): bool
	{
		return isset($this->filters[strtolower($name)]);
	}
	
	public static function parseNumericValue($value): array
	{
		$regex = '/\s*(>=|<=|>|<|!=|<>|=)\s*(.*)/';
		if(preg_match($regex, $value, $matches)) {
			list(,$comparator, $v) = $matches;
		} else
		{
			$comparator = '=';
			$v = $value;
		}
		
		return ['comparator' => $comparator, 'query' => $v];
	}
	
	// public static function parseStringValue($value) {
	// 	if(!is_array($value)) {
	// 		$value = [$value];
	// 	}
		
	// 	$regex = '/\s*(!=|=)?\s*(.*)/';
	// 	if(preg_match($regex, $value, $matches)) {
	// 		list(,$comparator, $v) = $matches;
	// 	} else
	// 	{
	// 		$comparator = '=';
	// 		$v = '%'.$value.'%';
	// 	}
		
	// 	return [
	// 			['comparator' => $comparator == '=' ? 'LIKE' : 'NOT LIKE', 'query' => $v]
	// 	];
	// }

	/**
	 * @throws Exception
	 */
	private function checkRange($value) {
		//Operators >, <, =, !=,
		//Range ..
		
		$parts = array_map('trim', explode('..', $value));
		if(count($parts) > 2) {
			throw new Exception("Invalid range. Only one .. allowed");
		}
		
		if(count($parts) == 1) {
			//no range given
			return false;
		}

		return $parts;
	}

	/**
	 * @throws Exception
	 */
	private function checkDateRange($value) {
		//Operators >, <, =, !=,
		//Range ..

		$parts = array_map('trim', explode('..', $value));
		if(count($parts) > 2) {
			throw new Exception("Invalid range. Only one .. allowed");
		}

		if(count($parts) == 1) {
			//no range given
			return false;
		}

		$endHasTime = strpos($parts[1], ':') !== false;

		$tz = go()->getAuthState()->getUser()->timezone;

		$parts[0] = new DateTime($parts[0],new \DateTimeZone($tz));
		$parts[0]->setTimezone(new \DateTimeZone('UTC'));

		$parts[1] = new DateTime($parts[1], new \DateTimeZone($tz));
		$parts[1]->setTimezone(new \DateTimeZone('UTC'));

		$parts['endHasTime'] = $endHasTime;
		if(!$endHasTime) {
			$parts[1]->add(new DateInterval("P1D"));
		}

		return $parts;
	}
}
