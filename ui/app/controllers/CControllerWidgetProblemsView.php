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


class CControllerWidgetProblemsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_PROBLEMS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$data = CScreenProblem::getData([
			'show' => $fields['show'],
			'groupids' => $fields['groupids'],
			'exclude_groupids' => $fields['exclude_groupids'],
			'hostids' => $fields['hostids'],
			'name' => $fields['problem'],
			'severities' => $fields['severities'],
			'evaltype' => $fields['evaltype'],
			'tags' => $fields['tags'],
			'show_suppressed' => $fields['show_suppressed'],
			'unacknowledged' => $fields['unacknowledged'],
			'show_opdata' => $fields['show_opdata']
		]);
		list($sortfield, $sortorder) = self::getSorting($fields['sort_triggers']);
		$data = CScreenProblem::sortData($data, $sortfield, $sortorder);

		if (count($data['problems']) > $fields['show_lines']) {
			$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
				min($fields['show_lines'], count($data['problems'])),
				(count($data['problems']) > CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)) ? '+' : '',
				min(CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT), count($data['problems']))
			);
		}
		else {
			$info = '';
		}
		$data['problems'] = array_slice($data['problems'], 0, $fields['show_lines'], true);

		$data = CScreenProblem::makeData($data, [
			'show' => $fields['show'],
			'details' => 0,
			'show_opdata' => $fields['show_opdata']
		]);

		if ($fields['show_tags']) {
			$data['tags'] = makeTags($data['problems'], true, 'eventid', $fields['show_tags'], $fields['tags'], null,
				$fields['tag_name_format'], $fields['tag_priority']
			);
		}

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'initial_load' => (bool) $this->getInput('initial_load', 0),
			'fields' => [
				'show' => $fields['show'],
				'show_lines' => $fields['show_lines'],
				'show_tags' => $fields['show_tags'],
				'show_timeline' => $fields['show_timeline'],
				'tags' => $fields['tags'],
				'tag_name_format' => $fields['tag_name_format'],
				'tag_priority' => $fields['tag_priority'],
				'show_opdata' => $fields['show_opdata']
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
			'allowed_close' => $this->checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
		]));
	}

	/**
	 * Get sorting.
	 *
	 * @param int $sort_triggers
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getSorting($sort_triggers)
	{
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
