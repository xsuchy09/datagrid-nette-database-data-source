<?php

namespace Ublaboo\DataGrid\Tests\Cases;

use Tester\TestCase;
use Tester\Assert;
use Mockery;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Structure;
use Nette\Caching\Storages\DevNullStorage;
use Ublaboo\NetteDatabaseDataSource\NetteDatabaseDataSource;
use Ublaboo\DataGrid\Utils\Sorting;
use Ublaboo\DataGrid\Filter\FilterSelect;
use Ublaboo\DataGrid\Filter\FilterText;
use Ublaboo\DataGrid\Filter\FilterDate;
use Ublaboo\DataGrid\Filter\FilterDateRange;
use Ublaboo\DataGrid\Filter\FilterRange;
use Ublaboo;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../Files/XTestingDataGridFactory.php';
require __DIR__ . '/../Files/XTestingPresenter.php';

final class NetteDatabaseDataSourceTest extends TestCase
{

	/**
	 * @var Context
	 */
	private $db;

	/**
	 * @var Ublaboo\DataGrid\DataGrid
	 */
	private $grid;


	public function setUp()
	{
		$connection = new Connection('.', NULL, NULL, ['lazy' => TRUE]);

		$structure = new Structure($connection, new DevNullStorage);
		$this->db = new Context($connection, $structure);

		$factory = new Ublaboo\DataGrid\Tests\Files\XTestingDataGridFactory;
		$this->grid = $factory->createXTestingDataGrid();
	}


	public function testQuery()
	{
		$s = new NetteDatabaseDataSource($this->db, 'SELECT * FROM user');

		$s->filterOne(['id' => 1]);
		$q = $s->getQuery();

		Assert::same('SELECT * FROM user WHERE id = ?', $q[0]);
		Assert::same([1], $q[1]);
	}


	public function testSort()
	{
		$s = new NetteDatabaseDataSource($this->db, 'SELECT * FROM user');
		$sorting = new Sorting(['user.name' => 'DESC']);

		$s->sort($sorting);
		$q = $s->getQuery();

		Assert::same('SELECT * FROM user ORDER BY user.name DESC', $q[0]);
	}


	public function testApplyFilterSelect()
	{
		$s = new NetteDatabaseDataSource($this->db, 'SELECT * FROM user');
		$filter = new FilterSelect($this->grid, 'status', 'Status', [1 => 'Online', 0 => 'Offline'], 'user.status');
		$filter->setValue(1);

		$s->applyFilterSelect($filter);
		$q = $s->getQuery();

		Assert::same('SELECT * FROM user WHERE user.status = ?', $q[0]);
		Assert::same([1], $q[1]);
	}


	public function testComplexQuery()
	{
		$q =
			'SELECT u.name, u.age, p.name, p.surname, p2.name, p2.surname CASE WHEN p3.age THEN p3.age ELSE 8 END
			FROM user u
			LEFT JOIN parent p
				ON p.id = u.mother_id
			LEFT JOIN parent p2
				ON p2.id = u.father_id
			JOIN (SELECT id, age FROM parent) p3
				ON p3.age = u.age
			WHERE p2.id != 2 OR p2.id NOT IN (?, ?)';

		$s = new NetteDatabaseDataSource($this->db, $q, [3, 4]);

		$filter1 = new FilterSelect($this->grid, 'status', 'Status', [1 => 'Online', 0 => 'Offline'], 'user.status');
		$filter1->setValue(1);

		$filter2 = new FilterText($this->grid, 'name', 'Name or id', ['name', 'id']);
		$filter2->setValue('text');

		$filter3 = new FilterRange($this->grid, 'range', 'Range', 'id', 'To');
		$filter3->setValue(['from' => 2, 'to' => NULL]);

		$filter4 = new FilterDateRange($this->grid, 'date range', 'Date Range', 'created', '-');
		$filter4->setValue(['from' => '1. 2. 2003', 'to' => '3. 12. 2149']);

		$filter5 = new FilterDate($this->grid, 'date', 'Date', 'date');
		$filter5->setValue('12. 12. 2012');

		$s->applyFilterSelect($filter1);
		$s->applyFilterText($filter2);
		$s->applyFilterRange($filter3);
		$s->applyFilterDateRange($filter4);
		$s->applyFilterDate($filter5);

		$q = $s->getQuery();

		$expected_query = 
			'SELECT u.name, u.age, p.name, p.surname, p2.name, p2.surname CASE WHEN p3.age THEN p3.age ELSE 8 END
			FROM user u
			LEFT JOIN parent p
				ON p.id = u.mother_id
			LEFT JOIN parent p2
				ON p2.id = u.father_id
			INNER JOIN (SELECT id, age FROM parent) p3
				ON p3.age = u.age
			WHERE (p2.id != 2 OR p2.id NOT IN (?, ?))
				AND user.status = ?
				AND ((name LIKE ?) OR (id LIKE ?))
				AND id >= ?
				AND DATE(created) >= ?
				AND DATE(created) <= ?
				AND DATE(date) = ?';

		Assert::same(trim(preg_replace('/\s+/', ' ', $expected_query)), $q[0]);

		$expected_params = [
			3,
			4,
			1,
			"%text%",
			"%text%",
			2,
			"2003-02-01",
			"2149-12-03",
			"2012-12-12",
		];

		Assert::same($expected_params, $q[1]);
	}

}


$test_case = new NetteDatabaseDataSourceTest;
$test_case->run();
