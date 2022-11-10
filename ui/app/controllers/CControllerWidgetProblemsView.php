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
		$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

		$data = CScreenProblem::getData([
			'show' => $fields['show'],
			'groupids' => $fields['groupids'],
			'exclude_groupids' => $fields['exclude_groupids'],
			'hostids' => $fields['hostids'],
			'name' => $fields['problem'],
			'severities' => $fields['severities'],
			'evaltype' => $fields['evaltype'],
			'tags' => $fields['tags'],
			'show_symptoms' => $fields['show_symptoms'],
			'show_suppressed' => $fields['show_suppressed'],
			'unacknowledged' => $fields['unacknowledged'],
			'show_opdata' => $fields['show_opdata']
		], $search_limit);

		list($sortfield, $sortorder) = self::getSorting($fields['sort_triggers']);

		$data = CScreenProblem::sortData($data, $search_limit, $sortfield, $sortorder);

		if (count($data['problems']) > $fields['show_lines']) {
			$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
				min($fields['show_lines'], count($data['problems'])),
				(count($data['problems']) > $search_limit) ? '+' : '',
				min($search_limit, count($data['problems']))
			);
		}
		else {
			$info = '';
		}

		$data['problems'] = array_slice($data['problems'], 0, $fields['show_lines'], true);

		$data = CScreenProblem::makeData($data, [
			'show' => $fields['show'],
			'details' => 0,
			'show_opdata' => $fields['show_opdata'],
		]);

		$data += [
			'do_causes_have_symptoms' => false,
			'has_symptoms' => false
		];

		$cause_eventids_with_symptoms = [];
		$symptom_data['problems'] = [];

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);

			foreach ($data['problems'] as &$problem) {
				$problem['symptom_count'] = 0;
				$problem['symptoms'] = [];

				if ($problem['cause_eventid'] == 0) {
					$options = [
						'filter' => ['cause_eventid' => $problem['eventid']],
						'limit' => $search_limit,
						'countOutput' => true
					];

					$problem['symptom_count'] = ($fields['show'] == TRIGGERS_OPTION_ALL)
						? API::Event()->get($options)
						: API::Problem()->get($options);

					if ($problem['symptom_count'] > 0) {
						$data['do_causes_have_symptoms'] = true;
						$cause_eventids_with_symptoms[] = $problem['eventid'];
					}
				}

				// There is at least one independent symptom event.
				if ($problem['cause_eventid'] != 0) {
					$data['has_symptoms'] = true;
				}
			}
			unset($problem);

			if ($cause_eventids_with_symptoms) {
				// Get all symptoms for given cause event IDs.
				$symptom_data = CScreenProblem::getData([
					'show_symptoms' => true,
					'cause_eventid' => $cause_eventids_with_symptoms,
					'show' => $fields['show'],
					'show_opdata' => $fields['show_opdata']
				], ZBX_PROBLEM_SYMPTOM_LIMIT, true);

				$symptom_data = CScreenProblem::sortData($symptom_data, ZBX_PROBLEM_SYMPTOM_LIMIT, $sortfield,
					$sortorder
				);

				// Filter does not matter.
				$symptom_data = CScreenProblem::makeData($symptom_data, [
					'show' => $fields['show'],
					'show_opdata' => $fields['show_opdata'],
					'details' => 0
				], true);

				$data['users'] += $symptom_data['users'];
				$data['correlations'] += $symptom_data['correlations'];

				foreach ($symptom_data['actions'] as $key => $actions) {
					$data['actions'][$key] += $symptom_data['actions'][$key];
				}

				if ($symptom_data['triggers']) {
					// Add hosts from symptoms to the list.
					$data['triggers_hosts'] += getTriggersHostsList($symptom_data['triggers']);

					// Store all known triggers in one place.
					$data['triggers'] += $symptom_data['triggers'];
				}

				if ($symptom_data['problems']) {
					foreach ($data['problems'] as &$problem) {
						foreach ($symptom_data['problems'] as $symptom) {
							if (bccomp($symptom['cause_eventid'], $problem['eventid']) == 0) {
								$problem['symptoms'][] = $symptom;
							}
						}
					}
					unset($problem);
				}
			}
		}

		$data['tasks'] = getActiveTasksByEventAcknowledges($data['problems'] + $symptom_data['problems']);

		if ($fields['show_tags']) {
			$data['tags'] = makeTags($data['problems'] + $symptom_data['problems'], true, 'eventid',
				$fields['show_tags'], $fields['tags'], null, $fields['tag_name_format'], $fields['tag_priority']
			);
		}

		$this->setResponse(new CControllerResponseData($data + [
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
			'allowed' => [
				'ui_problems' => $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
				'add_comments' => $this->checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
				'change_severity' => $this->checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
				'acknowledge' => $this->checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
				'close' => $this->checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
				'suppress_problems' => $this->checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
				'rank_change' => $this->checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
			]
		]));
	}

	/**
	 * Get sorting.
	 *
	 * @param int $sort_triggers
	 *
	 * @return array
	 */
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
