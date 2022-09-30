<?php declare(strict_types = 0);
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


namespace Widgets\TrigOver\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	public function __construct() {
		parent::__construct();

		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'required|array',
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction(): void {
		$values = $this->getForm()->getFieldsValues();

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'initial_load' => (bool) $this->getInput('initial_load', 0),
			'style' => $values['style'],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$trigger_options = [
			'skipDependent' => ($values['show'] == TRIGGERS_OPTION_ALL) ? null : true,
			'only_true' => ($values['show'] == TRIGGERS_OPTION_RECENT_PROBLEM) ? true : null,
			'filter' => [
				'value' => ($values['show'] == TRIGGERS_OPTION_IN_PROBLEM) ? TRIGGER_VALUE_TRUE : null
			]
		];

		$problem_options = [
			'show_suppressed' => $values['show_suppressed'],
			'show_recent' => ($values['show'] == TRIGGERS_OPTION_RECENT_PROBLEM) ? true : null,
			'tags' => (array_key_exists('tags', $values) && $values['tags']) ? $values['tags'] : null,
			'evaltype' => array_key_exists('evaltype', $values) ? $values['evaltype'] : TAG_EVAL_TYPE_AND_OR
		];

		$host_options = [
			'hostids' => $values['hostids'] ?: null
		];

		[$data['db_hosts'], $data['db_triggers'], $data['dependencies'], $data['triggers_by_name'],
			$data['hosts_by_name'], $data['exceeded_limit']
		] = getTriggersOverviewData(getSubGroups($values['groupids']), $host_options, $trigger_options, // TODO AS: check getTriggersOverviewData(), getSubGroups() call
			$problem_options
		);

		$this->setResponse(new CControllerResponseData($data));
	}
}
