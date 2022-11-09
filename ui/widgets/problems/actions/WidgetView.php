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


namespace Widgets\Problems\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CRoleHelper,
	CScreenProblem,
	CSettingsHelper;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction(): void {
		$data = CScreenProblem::getData([
			'show' => $this->fields_values['show'],
			'groupids' => $this->fields_values['groupids'],
			'exclude_groupids' => $this->fields_values['exclude_groupids'],
			'hostids' => $this->fields_values['hostids'],
			'name' => $this->fields_values['problem'],
			'severities' => $this->fields_values['severities'],
			'evaltype' => $this->fields_values['evaltype'],
			'tags' => $this->fields_values['tags'],
			'show_suppressed' => $this->fields_values['show_suppressed'],
			'unacknowledged' => $this->fields_values['unacknowledged'],
			'show_opdata' => $this->fields_values['show_opdata']
		]);

		[$sortfield, $sortorder] = self::getSorting($this->fields_values['sort_triggers']);
		$data = CScreenProblem::sortData($data, $sortfield, $sortorder);

		if (count($data['problems']) > $this->fields_values['show_lines']) {
			$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
				min($this->fields_values['show_lines'], count($data['problems'])),
				(count($data['problems']) > CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)) ? '+' : '',
				min(CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT), count($data['problems']))
			);
		}
		else {
			$info = '';
		}
		$data['problems'] = array_slice($data['problems'], 0, $this->fields_values['show_lines'], true);

		$data = CScreenProblem::makeData($data, [
			'show' => $this->fields_values['show'],
			'details' => 0,
			'show_opdata' => $this->fields_values['show_opdata']
		]);

		if ($this->fields_values['show_tags']) {
			$data['tags'] = makeTags($data['problems'], true, 'eventid', $this->fields_values['show_tags'],
				$this->fields_values['tags'], null, $this->fields_values['tag_name_format'],
				$this->fields_values['tag_priority']
			);
		}

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'initial_load' => (bool) $this->getInput('initial_load', 0),
			'fields' => [
				'show' => $this->fields_values['show'],
				'show_lines' => $this->fields_values['show_lines'],
				'show_tags' => $this->fields_values['show_tags'],
				'show_timeline' => $this->fields_values['show_timeline'],
				'tags' => $this->fields_values['tags'],
				'tag_name_format' => $this->fields_values['tag_name_format'],
				'tag_priority' => $this->fields_values['tag_priority'],
				'show_opdata' => $this->fields_values['show_opdata']
			],
			'data' => $data,
			'info' => $info,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'config' => [
				'problem_ack_style' => CSettingsHelper::get(CSettingsHelper::PROBLEM_ACK_STYLE),
				'problem_unack_style' => CSettingsHelper::get(CSettingsHelper::PROBLEM_UNACK_STYLE),
				'blink_period' => CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD)
			],
			'allowed_ui_problems' => $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'allowed_add_comments' => $this->checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
			'allowed_change_severity' => $this->checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
			'allowed_acknowledge' => $this->checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
			'allowed_close' => $this->checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
			'allowed_suppress' => $this->checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)
		]));
	}

	private static function getSorting(int $sort_triggers): array {
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				return ['clock', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
				return ['clock', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_SEVERITY_ASC:
				return ['severity', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
				return ['severity', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
				return ['host', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_HOST_NAME_DESC:
				return ['host', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_NAME_ASC:
				return ['name', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_NAME_DESC:
				return ['name', ZBX_SORT_DOWN];
		}
	}
}
