<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../selenium/traits/TableTrait.php';
require_once dirname(__FILE__).'/../include/helpers/CAPIHelper.php';

class testPageReportsAuditValues extends CWebTest {
	use TableTrait;

	public $created;
	public $updated;
	public $deleted;
	public $config_refresh;
	public $login;

	public $resource_name;

	public function checkAuditValues($resourceid, $action) {
		$this->filterAuditLog($this->resource_name, $resourceid, $action);
		$this->assertAuditDetails($action);
	}

	/**
	 * Filter Audit log table.
	 *
	 * @param string $resource_name		can be anything, that is displayed in audit.
	 * @param string $resourceid		resource id number.
	 * @param string $action			add, update, delete.
	 */
	private function filterAuditLog($resource_name, $resourceid, $action){
		$this->page->login()->open('zabbix.php?action=auditlog.list')->waitUntilReady();

		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		// Find filter form and fill with correct resource values.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill(['Resource' => $resource_name, 'Resource ID' => $resourceid]);
		$form->query('xpath://label[text()="'.$action.'"]/../input[contains(@id, "filter_actions")]')->asCheckbox()->one()->check();
		$form->submit()->waitUntilReloaded();
	}

	/**
	 * Check Details column in audit table.
	 *
	 * @param string $audit			value that should be displayed in Details.
	 * @param string $action		add, update, delete.
	 */
	private function assertAuditDetails($action) {
		switch ($action) {
			case 'Update':
				$audit = $this->updated;
				break;

			case 'Add':
				$audit = $this->created;
				break;

			case 'Delete':
				$audit = $this->deleted;
				break;

			case 'Configuration refresh':
				$audit = $this->config_refresh;
				break;

			case 'Login':
				$audit = $this->login;
				break;
		}

		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals($action, $table->getRow(0)->getColumn('Action')->getText());

		// Check that overlay with details can be opened.
		if ($table->getRow(0)->getColumn('Details')->query('link:Details')->exists()) {
			$table->getRow(0)->getColumn('Details')->query('link:Details')->one()->click();
			$details = COverlayDialogElement::find()->waitUntilReady()->one()->getContent()->getText();
		}
		else {
			$details = $table->getRow(0)->getColumn('Details')->getText();
		}

		$this->assertEquals($details, $audit);

		if (COverlayDialogElement::find()->exists()) {
			COverlayDialogElement::find()->one()->close();
		}
		$this->query('name:zbx_filter')->asForm()->one()->query('button:Reset')->one()->click();
	}
}
