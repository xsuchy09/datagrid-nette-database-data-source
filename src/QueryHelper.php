<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\NetteDatabaseDataSource;

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class QueryHelper
{

	/**
	 * @var array
	 */
	protected $query;


	public function __construct($sql)
	{
		$this->sqlParser = new PHPSQLParser;
		$this->sqlCreator = new PHPSQLCreator;

		$this->query = $this->prepare($this->sqlParser->parse($sql));
	}


	/**
	 * In case query contains a more complicated query, place it within brackets: (<complicated_expr>)
	 * @param  array $query
	 * @return array
	 */
	public function prepare($query)
	{
		if (!empty($query['WHERE']) && sizeof($query['WHERE']) > 1) {
			$where = $query['WHERE'];

			$query['WHERE'] = [[
				'expr_type' => 'bracket_expression',
				'base_expr' => '',
				'sub_tree' => $where
			]];

			foreach ($where as $where_data) {
				$query['WHERE'][0]['base_expr'] .= ' ' . $where_data['base_expr'];
			}

			$query['WHERE'][0]['base_expr'] = '(' . trim($query['WHERE'][0]['base_expr']) . ')';
		}

		return $query;
	}


	public function getCountSelect()
	{
		$query = $this->query;

		$query['SELECT'] = [[
			'expr_type' => 'aggregate_function',
			'alias' => [
				'as'        => TRUE,
				'name'      => 'count',
				'base_expr' => 'AS count',
				'no_quotes' => [
					'delim' => FALSE,
					'parts' => ['count']
				]
			],
			'base_expr' => 'COUNT',
			'sub_tree'  => [[
				'expr_type' => 'colref',
				'base_expr' => '*',
				'sub_tree'  => FALSE
			]]
		]];

		return $this->sqlCreator->create($query);
	}


	public function limit($limit, $offset)
	{
		$this->query['LIMIT'] = [
			'offset'   => $offset,
			'rowcount' => $limit
		];

		return $this->sqlCreator->create($this->query);
	}


	public function orderBy($column, $order)
	{
		$this->query['ORDER'] = [[
			'expr_type' => 'colref',
			'base_expr' => $column,
			'no_quotes' => [
				'delim' => FALSE,
				'parts' => [$column]
			],
			'subtree'   => FALSE,
			'direction' => $order
		]];

		return $this->sqlCreator->create($this->query);
	}


	public function where($column, $value, $operator)
	{
		if (empty($this->query['WHERE'])) {
			$this->query['WHERE'] = [];
		} else {
			$this->query['WHERE'][] = [
				'expr_type' => 'operator',
				'base_expr' => 'AND',
				'sub_tree'  => FALSE
			];
		}

		/**
		 * Column
		 */
		if (strpos($column, '.') !== FALSE) {
			/**
			 * Column prepanded with table/alias
			 */
			list($alias, $column) = explode('.', $column);

			$this->query['WHERE'][] = [
				'expr_type' => 'colref',
				'base_expr' => "{$alias}.{$column}",
				'no_quotes' => [
					'delim' => '.',
					'parts' => [$alias, $column]
				],
				'sub_tree'  => FALSE
			];
		} else {
			/**
			 * Simple column name
			 */
			$this->query['WHERE'][] = [
				'expr_type' => 'colref',
				'base_expr' => $column,
				'no_quotes' => [
					'delim' => FALSE,
					'parts' => [$column]
				],
				'sub_tree'  => FALSE
			];
		}

		/**
		 * =
		 */
		$this->query['WHERE'][] = [
			'expr_type' => 'operator',
			'base_expr' => $operator,
			'sub_tree'  => FALSE
		];

		/**
		 * ?
		 * 	($value == '_?_')
		 */
		$this->query['WHERE'][] = [
			'expr_type' => 'const',
			'base_expr' => $value,
			'sub_tree'  => FALSE
		];

		return $this->sqlCreator->create($this->query);
	}


	public function whereSql($sql)
	{
		if (empty($this->query['WHERE'])) {
			$this->query['WHERE'] = [];
		} else {
			$this->query['WHERE'][] = [
				'expr_type' => 'operator',
				'base_expr' => 'AND',
				'sub_tree'  => FALSE
			];
		}

		$help_sql = 'SELECT * FROM TEMP WHERE' . $sql;
		$help_query = $this->sqlParser->parse($help_sql);

		$this->query['WHERE'][] = $help_query['WHERE'][0];

		return $this->sqlCreator->create($this->query);
	}

}
