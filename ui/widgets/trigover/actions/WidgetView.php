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


namespace Widgets\TrigOver\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$data['error'] = _('No data.');
		}
		else {
			$data += [
				'error' => null,
				'initial_load' => (bool) $this->getInput('initial_load', 0),
				'layout' => $this->fields_values['layout'],
				'is_template_dashboard' => $this->isTemplateDashboard()
			];

			$trigger_options = [
				'skipDependent' => ($this->fields_values['show'] == TRIGGERS_OPTION_ALL) ? null : true,
				'only_true' => $this->fields_values['show'] == TRIGGERS_OPTION_RECENT_PROBLEM ? true : null,
				'filter' => [
					'value' => $this->fields_values['show'] == TRIGGERS_OPTION_IN_PROBLEM ? TRIGGER_VALUE_TRUE : null
				]
			];

			$problem_options = [
				'show_suppressed' => $this->fields_values['show_suppressed'],
				'show_recent' => $this->fields_values['show'] == TRIGGERS_OPTION_RECENT_PROBLEM ? true : null,
				'tags' => array_key_exists('tags', $this->fields_values) && $this->fields_values['tags']
					? $this->fields_values['tags']
					: null,
				'evaltype' => array_key_exists('evaltype', $this->fields_values)
					? $this->fields_values['evaltype']
					: TAG_EVAL_TYPE_AND_OR
			];

			if ($this->isTemplateDashboard()) {
				$groupids = [];
				$host_options['hostids'] = $this->fields_values['override_hostid'];
			}
			else {
				$groupids = $this->fields_values['groupids'];
				$host_options['hostids'] = $this->fields_values['hostids'] ?: null;
			}

			[$data['db_hosts'], $data['db_triggers'], $data['dependencies'], $data['triggers_by_name'],
				$data['hosts_by_name'], $data['exceeded_limit']
			] = getTriggersOverviewData(getSubGroups($groupids), $host_options, $trigger_options, $problem_options);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
