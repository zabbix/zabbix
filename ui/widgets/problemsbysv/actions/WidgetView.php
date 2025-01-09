<?php declare(strict_types = 0);
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


namespace Widgets\ProblemsBySv\Actions;

use APP,
	CControllerDashboardWidgetView,
	CControllerResponseData;

use Widgets\ProblemsBySv\Widget;

require_once APP::getRootDir().'/include/blocks.inc.php';

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction(): void {
		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$this->setResponse(new CControllerResponseData([
				'name' => $this->getInput('name', $this->widget->getDefaultName()),
				'error' => _('No data.'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
		else {
			$filter = [
				'groupids' => !$this->isTemplateDashboard() ? getSubGroups($this->fields_values['groupids']) : null,
				'exclude_groupids' => !$this->isTemplateDashboard()
					? getSubGroups($this->fields_values['exclude_groupids'])
					: null,
				'hostids' => !$this->isTemplateDashboard()
					? $this->fields_values['hostids']
					: $this->fields_values['override_hostid'],
				'problem' => $this->fields_values['problem'],
				'severities' => $this->fields_values['severities'],
				'show_type' => !$this->isTemplateDashboard() ? $this->fields_values['show_type'] : Widget::SHOW_TOTALS,
				'layout' => $this->fields_values['layout'],
				'show_suppressed' => $this->fields_values['show_suppressed'],
				'hide_empty_groups' => !$this->isTemplateDashboard() ? $this->fields_values['hide_empty_groups'] : null,
				'show_opdata' => $this->fields_values['show_opdata'],
				'ext_ack' => $this->fields_values['ext_ack'],
				'show_timeline' => $this->fields_values['show_timeline'],
				'evaltype' => $this->fields_values['evaltype'],
				'tags' => $this->fields_values['tags']
			];

			$data = getSystemStatusData($filter);

			if ($filter['show_type'] == Widget::SHOW_TOTALS) {
				$data['groups'] = getSystemStatusTotals($data);
			}

			$this->setResponse(new CControllerResponseData([
				'name' => $this->getInput('name', $this->widget->getDefaultName()),
				'error' => null,
				'initial_load' => (bool) $this->getInput('initial_load', 0),
				'data' => $data,
				'filter' => $filter,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				],
				'allowed' => $data['allowed']
			]));
		}
	}
}
