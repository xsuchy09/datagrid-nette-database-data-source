<?php

namespace Ublaboo\DataGrid\Nette\Database\Tests\Files;

use Nette;
use Ublaboo\DataGrid\DataGrid;

final class ExportTestingPresenter extends Nette\Application\UI\Presenter
{

	protected function createComponentGrid($name)
	{
		$grid = new DataGrid(NULL, $name);
		$grid->addExportCsv('export', 'export.csv');

		return $grid;
	}

}
