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


namespace Widgets\Problems\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CRoleHelper,
	CScreenProblem,
	CSettingsHelper,
	API;

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
			$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
			$data = CScreenProblem::getData([
				'show' => $this->fields_values['show'],
				'groupids' => !$this->isTemplateDashboard() ? $this->fields_values['groupids'] : null,
				'exclude_groupids' => !$this->isTemplateDashboard() ? $this->fields_values['exclude_groupids'] : null,
				'hostids' => !$this->isTemplateDashboard()
					? $this->fields_values['hostids']
					: $this->fields_values['override_hostid'],
				'name' => $this->fields_values['problem'],
				'severities' => $this->fields_values['severities'],
				'evaltype' => $this->fields_values['evaltype'],
				'tags' => $this->fields_values['tags'],
				'show_symptoms' => $this->fields_values['show_symptoms'],
				'show_suppressed' => $this->fields_values['show_suppressed'],
				'acknowledgement_status' => $this->fields_values['acknowledgement_status'],
				'acknowledged_by_me' => $this->fields_values['acknowledgement_status'] == ZBX_ACK_STATUS_ACK
					? $this->fields_values['acknowledged_by_me']
					: 0,
				'show_opdata' => $this->fields_values['show_opdata']
			], $search_limit);

			[$sortfield, $sortorder] = self::getSorting($this->fields_values['sort_triggers']);
			$data = CScreenProblem::sortData($data, $search_limit, $sortfield, $sortorder);

			if (count($data['problems']) > $this->fields_values['show_lines']) {
				$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
					min($this->fields_values['show_lines'], count($data['problems'])),
					(count($data['problems']) > $search_limit) ? '+' : '',
					min($search_limit, count($data['problems']))
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

			$data += [
				'show_three_columns' => false,
				'show_two_columns' => false
			];

			$cause_eventids_with_symptoms = [];
			$symptom_data = ['problems' => []];

			if ($data['problems']) {
				$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);

				foreach ($data['problems'] as &$problem) {
					$problem['symptom_count'] = 0;
					$problem['symptoms'] = [];

					if ($problem['cause_eventid'] == 0) {
						$options = [
							'output' => ['objectid'],
							'filter' => ['cause_eventid' => $problem['eventid']]
						];

						$symptom_events = $this->fields_values['show'] == TRIGGERS_OPTION_ALL
							? API::Event()->get($options)
							: API::Problem()->get($options + [
								'recent' => $this->fields_values['show'] == TRIGGERS_OPTION_RECENT_PROBLEM
							]);

						if ($symptom_events) {
							$enabled_triggers = API::Trigger()->get([
								'output' => [],
								'triggerids' => array_column($symptom_events, 'objectid'),
								'filter' => ['status' => TRIGGER_STATUS_ENABLED],
								'preservekeys' => true
							]);

							$symptom_events = array_filter($symptom_events,
								static fn($event) => array_key_exists($event['objectid'], $enabled_triggers)
							);
							$problem['symptom_count'] = count($symptom_events);
						}

						if ($problem['symptom_count'] > 0) {
							$data['show_three_columns'] = true;
							$cause_eventids_with_symptoms[] = $problem['eventid'];
						}
					}

					// There is at least one independent symptom event.
					if ($problem['cause_eventid'] != 0) {
						$data['show_two_columns'] = true;
					}
				}
				unset($problem);

				if ($cause_eventids_with_symptoms) {
					foreach ($cause_eventids_with_symptoms as $cause_eventid) {
						// Get all symptoms for given cause event ID.
						$_symptom_data = CScreenProblem::getData([
							'show_symptoms' => true,
							'show_suppressed' => true,
							'cause_eventid' => $cause_eventid,
							'show' => $this->fields_values['show'],
							'show_opdata' => $this->fields_values['show_opdata']
						], ZBX_PROBLEM_SYMPTOM_LIMIT, true);

						if ($_symptom_data['problems']) {
							$_symptom_data = CScreenProblem::sortData($_symptom_data, ZBX_PROBLEM_SYMPTOM_LIMIT,
								$sortfield, $sortorder
							);

							/*
							 * Since getData returns +1 more in order to show the "+" sign for paging or sortData should
							 * not cut off any excess problems, in order to display actual limit of symptoms, one more
							 * slice is necessary.
							 */
							$_symptom_data['problems'] = array_slice($_symptom_data['problems'], 0,
								ZBX_PROBLEM_SYMPTOM_LIMIT, true
							);

							// Filter does not matter.
							$_symptom_data = CScreenProblem::makeData($_symptom_data, [
								'show' => $this->fields_values['show'],
								'show_opdata' => $this->fields_values['show_opdata'],
								'details' => 0
							], true);

							$data['users'] += $_symptom_data['users'];
							$data['correlations'] += $_symptom_data['correlations'];

							foreach ($_symptom_data['actions'] as $key => $actions) {
								$data['actions'][$key] += $actions;
							}

							if ($_symptom_data['triggers']) {
								// Add hosts from symptoms to the list.
								$data['triggers_hosts'] += getTriggersHostsList($_symptom_data['triggers']);

								// Store all known triggers in one place.
								$data['triggers'] += $_symptom_data['triggers'];
							}

							foreach ($data['problems'] as &$problem) {
								foreach ($_symptom_data['problems'] as $symptom) {
									if (bccomp($symptom['cause_eventid'], $problem['eventid']) == 0) {
										$problem['symptoms'][] = $symptom;
									}
								}
							}
							unset($problem);

							// Combine symptom problems, to show tags later at some point.
							$symptom_data['problems'] += $_symptom_data['problems'];
						}
					}
				}
			}

			if ($this->fields_values['show_tags']) {
				$data['tags'] = makeTags($data['problems'] + $symptom_data['problems'], true, 'eventid',
					$this->fields_values['show_tags'], $this->fields_values['tags'], null,
					$this->fields_values['tag_name_format'], $this->fields_values['tag_priority']
				);
			}

			$this->setResponse(new CControllerResponseData($data + [
				'name' => $this->getInput('name', $this->widget->getDefaultName()),
				'error' => null,
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
