<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\TopTriggers\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CRangeTimeParser;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'from' => 'string',
			'to' => 'string',
			'dynamic_hostid' => 'db hosts.hostid'
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
		if ($this->isTemplateDashboard() && !$this->hasInput('dynamic_hostid')) {
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
			$hostids = [$this->getInput('dynamic_hostid')];
		}
		else {
			$hostids = $this->fields_values['hostids'] ?: null;
		}

		$range_time_parser = new CRangeTimeParser();

		$range_time_parser->parse($this->getInput('from'));
		$time_from = $range_time_parser->getDateTime(true)->getTimestamp();

		$range_time_parser->parse($this->getInput('to'));
		$time_to = $range_time_parser->getDateTime(false)->getTimestamp();

		$db_problems = API::Event()->get([
			'countOutput' => true,
			'groupBy' => ['objectid'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'time_from' => $time_from,
			'time_till' => $time_to,
			'search' => [
				'name' => $this->fields_values['problem'] !== '' ? $this->fields_values['problem'] : null
			],
			'trigger_severities' => $this->fields_values['severities'] ?: null,
			'evaltype' => $this->fields_values['evaltype'],
			'tags' => $this->fields_values['tags'] ?: null,
			'sortfield' => ['rowscount'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $this->fields_values['show_lines']
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

		return $db_triggers;
	}
}
