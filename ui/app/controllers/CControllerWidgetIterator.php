<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class containing methods for operations with widget iterators.
 */
abstract class CControllerWidgetIterator extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'page' => 'required|ge 1'
		]);
	}

	/**
	 * Get realistic page number for given number of child widgets.
	 */
	protected function getIteratorPage(int $num_widgets): int {
		return max(1, min((int) $this->getInput('page'), $this->getIteratorPageCount($num_widgets)));
	}

	/**
	 * Get number of child widgets to show on a single page.
	 */
	protected function getIteratorPageSize(): int {
		$fields_data = $this->getForm()->getFieldsValues();

		return min($fields_data['columns'] * $fields_data['rows'], DASHBOARD_MAX_COLUMNS * DASHBOARD_MAX_ROWS);
	}

	/**
	 * Get number of pages for given number of child widgets.
	 */
	protected function getIteratorPageCount(int $num_widgets): int {
		return (floor(max(0, $num_widgets - 1) / $this->getIteratorPageSize()) + 1);
	}
}
