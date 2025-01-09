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


namespace Widgets\TopTriggers\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'has_custom_time_period' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$data['error'] = _('No data.');
		}
		else {
			$data['triggers'] = $this->getTriggers();
			$data['error'] = null;
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getTriggers(): array {
		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		if ($this->isTemplateDashboard()) {
			$hostids = $this->fields_values['override_hostid'];
		}
		else {
			$hostids = $this->fields_values['hostids'] ?: null;
		}

		$db_problems = API::Event()->get([
			'countOutput' => true,
			'groupBy' => ['objectid'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'time_from' => $this->fields_values['time_period']['from_ts'],
			'time_till' => $this->fields_values['time_period']['to_ts'],
			'search' => [
				'name' => $this->fields_values['problem'] !== '' ? $this->fields_values['problem'] : null
			],
			'trigger_severities' => $this->fields_values['severities'] ?: null,
			'evaltype' => $this->fields_values['evaltype'],
			'tags' => $this->fields_values['tags'] ?: null,
			'sortfield' => ['rowscount'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => ZBX_MAX_WIDGET_LINES
		]);

		if (!$db_problems) {
			return [];
		}

		$db_problems = array_column($db_problems, null, 'objectid');

		$db_triggers = API::Trigger()->get([
			'output' => ['description', 'priority'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'expandDescription' => true,
			'triggerids' => array_keys($db_problems),
			'preservekeys' => true
		]);

		foreach ($db_triggers as $triggerid => &$trigger) {
			$trigger['problem_count'] = $db_problems[$triggerid]['rowscount'];
		}
		unset($trigger);

		CArrayHelper::sort($db_triggers, [
			['field' => 'problem_count', 'order' => ZBX_SORT_DOWN],
			['field' => 'priority', 'order' => ZBX_SORT_DOWN],
			'description'
		]);

		$db_triggers = array_slice($db_triggers, 0, $this->fields_values['show_lines'], true);

		return $db_triggers;
	}

	/**
	 * Make widget specific info to show in widget's header.
	 */
	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}
}
