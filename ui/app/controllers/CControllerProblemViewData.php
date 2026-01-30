<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerProblemViewData extends CControllerDataTable
{
	protected function checkPermissions(): bool
	{
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	/**
	 * @throws Exception
	 *
	 * @return array
	 */
	protected function getData(): array
	{
		$rows = [];
		$data = $this->prepareData();

		self::addProblemRows($rows, $data, $data['problems'], $data['filter'], $data['options'],
			$data['context_popup_data']);

		order_result($data['problems'], $data['sort_field'], $data['sort_order']);

		return [
			'fields' => $data['fields'],
			'columns' => $data['columns'],
			'page' => $data['page'],
			'allowed' => $data['allowed'],
			'show_three_columns' => $data['show_three_columns'],
			'show_two_columns' => $data['show_two_columns'],
			'rows' => $rows
		];
	}

	private static function addProblemRows(array &$rows, array &$data, array $problems, array $filter,
		array $options, array $context_popup_data, bool $nested = false): void
	{
		foreach ($problems as $problem) {
			if ($data['sort_field'] == 'clock' && $context_popup_data['show_timeline'] && !$options['compact_view']
					&& $data['last_clock'] != 0) {

				$breakpoint = self::createTimelineBreakpoint($data, $problem);

				if ($breakpoint != null) {
					$rows[] = [['renderer' => 'breakpoint', 'raw_data' => true], [$breakpoint]];
				}
			}

			$clock = $problem['clock'];

			$problem['clock'] = zbx_date2str(
				($clock >= $data['today'])
					? TIME_FORMAT_SECONDS
					: DATE_TIME_FORMAT_SECONDS,
				$clock);

			$problem['recovery'] = ($problem['r_eventid'] != 0)
				? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock'])
				: '';

			$in_closing = false;
			$trigger = $data['triggers'][$problem['objectid']];

			if ($problem['r_eventid'] != 0) {
				$value = TRIGGER_VALUE_FALSE;
				$value_clock = $problem['r_clock'];
				$can_be_closed = false;
			}
			else {
				$in_closing = hasEventCloseAction($problem['acknowledges']);
				$can_be_closed = ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
					&& $data['allowed']['close'] && !$in_closing
				);
				$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$value_clock = $in_closing ? time() : $problem['clock'];
			}

			$status = getEventStatusString($in_closing, $problem);

			$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
			$cell_status = new CSpan($status);

			if (isEventUpdating($in_closing, $problem)) {
				$cell_status->addClass('js-blink');
			}

			addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

			$problem['status'] = $cell_status->toString();

			$info_icons = [];

			if ($data['filter']['show'] == TRIGGERS_OPTION_IN_PROBLEM) {
				$info_icons[] = getEventStatusUpdateIcon($problem);
			}

			if ($problem['r_eventid'] != 0) {
				if ($problem['correlationid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['correlationid'], $data['correlations'])
							? _s('Resolved by event correlation rule "%1$s".',
							$data['correlations'][$problem['correlationid']]['name']
						)
							: _('Resolved by event correlation rule.')
					);
				}
				elseif ($problem['userid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['userid'], $data['users'])
							? _s('Resolved by user "%1$s".', getUserFullname($data['users'][$problem['userid']]))
							: _('Resolved by inaccessible user.')
					);
				}
			}

			if (array_key_exists('suppression_data', $problem)) {
				if (count($problem['suppression_data']) == 1
					&& $problem['suppression_data'][0]['maintenanceid'] == 0
					&& isEventRecentlyUnsuppressed($problem['acknowledges'], $unsuppression_action)) {
					// Show blinking button if the last manual suppression was recently revoked.
					$user_unsuppressed = array_key_exists($unsuppression_action['userid'], $data['users'])
						? getUserFullname($data['users'][$unsuppression_action['userid']])
						: _('Inaccessible user');

					$info_icons[] = (new CButtonIcon(ZBX_ICON_EYE))
						->addClass(ZBX_STYLE_COLOR_ICON)
						->addClass('js-blink')
						->setHint(_s('Unsuppressed by: %1$s', $user_unsuppressed));
				}
				elseif ($problem['suppression_data']) {
					$info_icons[] = makeSuppressedProblemIcon($problem['suppression_data'], false);
				}
				elseif (isEventRecentlySuppressed($problem['acknowledges'], $suppression_action)) {
					// Show blinking button if suppression was made but is not yet processed by server.
					$info_icons[] = makeSuppressedProblemIcon([[
						'suppress_until' => $suppression_action['suppress_until'],
						'username' => array_key_exists($suppression_action['userid'], $data['users'])
							? getUserFullname($data['users'][$suppression_action['userid']])
							: _('Inaccessible user')
					]], true);
				}
			}

			if ($options['compact_view'] && $context_popup_data['show_suppressed'] && count($info_icons) > 1) {
				$cell_info = (new CButtonIcon(ZBX_ICON_MORE))->setHint(makeInformationList($info_icons));
			}
			else {
				$cell_info = makeInformationList($info_icons);
			}

			$problem['info'] = (string) $cell_info;
			$problem['host'] = $data['triggers_hosts'][$trigger['triggerid']];

			$opdata = null;
			if ($trigger['opdata'] === '') {
				$opdata = (new CDiv(CScreenProblem::getLatestValues($trigger['items'])))
					->addClass('latest-values');
			}
			else {
				$opdata = (new CSpan(CMacrosResolverHelper::resolveTriggerOpdata(
					[
						'triggerid' => $trigger['triggerid'],
						'expression' => $trigger['expression'],
						'opdata' => $trigger['opdata'],
						'clock' => ($problem['r_eventid'] != 0) ? $problem['r_clock'] : $problem['clock'],
						'ns' => ($problem['r_eventid'] != 0) ? $problem['r_ns'] : $problem['ns']
					],
					[
						'events' => true,
						'html' => true
					]
				)))->addClass('opdata');
			}

			$problem['opdata'] = $opdata->toString(false);

			$description = array_key_exists($trigger['triggerid'], $data['dependencies'])
				? makeTriggerDependencies($data['dependencies'][$trigger['triggerid']])
				: [];
			$description[] = (new CLinkAction($problem['name']))
				->addClass(ZBX_STYLE_WORDBREAK)
				->setMenuPopup(CMenuPopupHelper::getTrigger([
					'triggerid' => $trigger['triggerid'],
					'backurl' => (new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->getUrl(),
					'eventid' => $problem['eventid'],
					'show_rank_change_cause' => true,
					'show_rank_change_symptom' => true
				]));

			if ($trigger['opdata'] != '' && $options['compact_view'] && $context_popup_data['show_opdata'] == 1) {
				$description[] = ' (';
				$description[] = $opdata;
				$description[] = ')';
			}

			$description[] = ($problem['comments'] !== '') ? makeDescriptionIcon($problem['comments']) : null;

			if (!$options['compact_view'] && $context_popup_data['details'] == 1) {
				$description[] = BR();

				if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					$description[] = [_('Problem'), ': ', (new CDiv($trigger['expression_html']))
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS), BR()];
					$description[] = [_('Recovery'), ': ', (new CDiv($trigger['recovery_expression_html']))
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)];
				}
				else {
					$description[] = (new CDiv($trigger['expression_html']))->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
				}
			}

			$problem['description'] = $options['compact_view']
				? (new CDiv($description))->addClass(ZBX_STYLE_ACTION_CONTAINER)->toString()
				: (new CObject($description))->toString();

			$problem['duration'] = ($problem['r_eventid'] != 0)
				? zbx_date2age($clock, $problem['r_clock'])
				: zbx_date2age($clock);

			$problem['can_be_closed'] =  $can_be_closed;

			$problem['actions'] = (string) makeEventActionsIcons($problem['eventid'], $data['actions'], $data['users'],
				$is_acknowledged);

			$problem['nested'] = $nested;

			if ($nested) {
				$rows[] = [['renderer' => 'nested_symptom'], $problem];
			}
			else {
				$rows[] = [[], $problem];
			}

			if ($problem['cause_eventid'] == 0 && $problem['symptoms']) {
				self::addProblemRows($rows, $data, $problem['symptoms'], $filter, $options, $context_popup_data, true);

				if ($problem['symptom_count'] > ZBX_PROBLEM_SYMPTOM_LIMIT) {
					$rows[] = [['renderer' => 'symptom_limit', 'raw_data' => true], [
						$problem['eventid'],
						_s('Displaying %1$s of %2$s found', ZBX_PROBLEM_SYMPTOM_LIMIT, $problem['symptom_count'])
					]];
				}
			}

			if (!$nested) {
				$data['last_clock'] = $clock;
			}
		}
	}

	/**
	 * Create a timeline breakpoint.
	 *
	 * @param array  $data                        Various table data.
	 *        int    $data['last_clock']          Timestamp of the previous record.
	 *        string $data['sort_order']          Order by which column is sorted.
	 * @param array  $problem                     Problem data.
	 *        int    $problem['clock']            Timestamp of the current record.
	 *        int    $problem['symptom_count']    Problem symptom count.
	 *
	 * @return string|null
	 */
	private static function createTimelineBreakpoint(array &$data, array $problem): ?string {
		if ($data['sort_order'] === ZBX_SORT_UP) {
			[$problem['clock'], $data['last_clock']] = [$data['last_clock'], $problem['clock']];
		}

		$breakpoint = null;
		$today = strtotime('today');
		$yesterday = strtotime('yesterday');
		$this_year = strtotime('first day of January '.date('Y', $today));

		if ($data['last_clock'] >= $today) {
			if ($problem['clock'] < $today) {
				$breakpoint = _('Today');
			}
			elseif (date('H', $data['last_clock']) != date('H', $problem['clock'])) {
				$breakpoint = date('H:00', $data['last_clock']);
			}
		}
		elseif ($data['last_clock'] >= $yesterday) {
			if ($problem['clock'] < $yesterday) {
				$breakpoint = _('Yesterday');
			}
		}
		elseif ($data['last_clock'] >= $this_year && $problem['clock'] < $this_year) {
			$breakpoint = date('Y', $data['last_clock']);
		}
		elseif (date('Ym', $data['last_clock']) != date('Ym', $problem['clock'])) {
			$breakpoint = getMonthCaption(date('m', $data['last_clock']));
		}

		return $breakpoint;
	}

	/**
	 * @param string $type
	 *
	 * @throws Exception
	 *
	 * @return string|null
	 */
	protected function export(string $type): ?string {
		if ($type != 'csv') {
			return null;
		}

		$data = $this->prepareData();

		$visible_columns = array_filter($data['columns'],
			static fn (array $column_config) => $column_config['visible'] ?? false);

		$show_opdata_separately = in_array('opdata', array_column($visible_columns, 'id'));

		$csv = [];

		$csv[] = array_filter([
			_('Severity'),
			_('Time'),
			_('Recovery time'),
			_('Status'),
			_('Host'),
			_('Problem'),
			$data['symptom_cause_eventids'] ? _('Cause') : null,
			$show_opdata_separately ? _('Operational data') : null,
			_('Duration'),
			_('Ack'),
			_('Actions'),
			_('Tags')
		]);

		$tags = CTagHelper::getTagsRaw($data['problems'] + $data['symptom_data']['problems']);

		// Get cause event names for symptoms.
		$causes = [];
		if ($data['symptom_cause_eventids']) {
			$event_options = [
				'output' => ['cause_eventid', 'name'],
				'eventids' => $data['symptom_cause_eventids'],
				'preservekeys' => true
			];

			$causes = ($data['filter']['show'] == TRIGGERS_OPTION_ALL)
				? API::Event()->get($event_options)
				: API::Problem()->get($event_options);
		}

		foreach ($data['problems'] as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$in_closing = false;
			if ($problem['r_eventid'] == 0) {
				$in_closing = hasEventCloseAction($problem['acknowledges']);
			}

			$value_str = getEventStatusString($in_closing, $problem);

			$hosts = [];
			foreach ($data['triggers_hosts'][$trigger['triggerid']] as $trigger_host) {
				$hosts[] = $trigger_host['name'];
			}

			// operational data
			$opdata = null;
			if ($data['context_popup_data']['show_opdata'] || $show_opdata_separately) {
				if ($trigger['opdata'] === '') {
					if ($show_opdata_separately) {
						$opdata = CScreenProblem::getLatestValues($trigger['items'], false);
					}
				}
				else {
					$opdata = CMacrosResolverHelper::resolveTriggerOpdata(
						[
							'triggerid' => $trigger['triggerid'],
							'expression' => $trigger['expression'],
							'opdata' => $trigger['opdata'],
							'clock' => ($problem['r_eventid'] != 0) ? $problem['r_clock'] : $problem['clock'],
							'ns' => ($problem['r_eventid'] != 0) ? $problem['r_ns'] : $problem['ns']
						],
						['events' => true]
					);
				}
			}

			$actions_performed = [];
			if ($data['actions']['messages'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Messages').
					' ('.$data['actions']['messages'][$problem['eventid']]['count'].')';
			}
			if ($data['actions']['severities'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Severity changes');
			}

			if (array_column($problem['suppression_data'], 'userid')) {
				$actions_performed[] = _('Suppressed');
			}
			elseif ($data['actions']['suppressions'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Unsuppressed');
			}

			if ($data['actions']['actions'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Actions').' ('.$data['actions']['actions'][$problem['eventid']]['count'].')';
			}

			$row = [];

			$row[] = CSeverityHelper::getName((int) $problem['severity']);
			$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
			$row[] = ($problem['r_eventid'] != 0) ? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']) : '';
			$row[] = $value_str;
			$row[] = implode(', ', $hosts);
			$row[] = ($data['context_popup_data']['show_opdata'] && $trigger['opdata'] !== '')
				? $problem['name'].' ('.$opdata.')'
				: $problem['name'];

			if ($data['symptom_cause_eventids']) {
				$row[] = $problem['cause_eventid'] != 0 ? $causes[$problem['cause_eventid']]['name'] : '';
			}

			if ($show_opdata_separately) {
				$row[] = $opdata;
			}

			$row[] = ($problem['r_eventid'] != 0)
				? zbx_date2age($problem['clock'], $problem['r_clock'])
				: zbx_date2age($problem['clock']);
			$row[] = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED) ? _('Yes') : _('No');
			$row[] = implode(', ', $actions_performed);
			$row[] = implode(', ', $tags[$problem['eventid']]);

			$csv[] = $row;
		}

		return zbx_toCSV($csv);
	}

	/**
	 * @throws Exception
	 *
	 * @return array
	 */
	private function prepareData(): array {
		$columns = $this->getInput('columns');
		$fields = $this->extractFields($columns);
		$options = $this->getInput('options', []);
		$context_popup_data = array_merge(...array_column($columns, 'context_popup_data'));
		$export = $this->getInput('export_file');

		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page');

		$sort_field = $this->getInput('sort_field', CControllerProblem::FILTER_FIELDS_DEFAULT['sort']);
		$sort_order = $this->getInput('sort_order', CControllerProblem::FILTER_FIELDS_DEFAULT['sortorder']);

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

		if ($filter['show'] == TRIGGERS_OPTION_ALL) {
			$timeline = getTimeSelectorPeriod([
				'profileidx' => true,
				'profileidx2' => true
			]);

			$filter['from'] = $timeline['from_ts'];
			$filter['to'] = $timeline['to_ts'];
		}

		$context_popup_data['show_opdata'] = $context_popup_data['show_opdata'] == 1
			?: OPERATIONAL_DATA_SHOW_SEPARATELY;

		$data = CScreenProblem::getData($filter, $context_popup_data, $limit, true);
		$data = CScreenProblem::sortData($data, $limit, $sort_field, $sort_order);

		if ($export == null) {
			$this->paging = $this->paginate($data['problems'], $page, ZBX_SORT_UP);
		}

		$data = CScreenProblem::makeData($data, $filter, $context_popup_data, true);

		if ($data['triggers']) {
			$triggerids = array_keys($data['triggers']);

			$db_triggers = API::Trigger()->get([
				'output' => [],
				'selectDependencies' => ['triggerid'],
				'triggerids' => $triggerids,
				'preservekeys' => true
			]);

			foreach ($data['triggers'] as $triggerid => &$trigger) {
				$trigger['dependencies'] = array_key_exists($triggerid, $db_triggers)
					? $db_triggers[$triggerid]['dependencies']
					: [];
			}
			unset($trigger);
		}

		$triggers_hosts = [];
		$symptom_cause_eventids = [];
		$cause_eventids_with_symptoms = [];
		$do_causes_have_symptoms = false;
		$symptom_data = ['problems' => []];

		if ($data['problems']) {
			$triggers_hosts = getTriggersHostsList($data['triggers']);

			// Get symptom count for each problem.
			foreach ($data['problems'] as &$problem) {
				$problem['symptom_count'] = 0;
				$problem['symptoms'] = [];

				if ($problem['cause_eventid'] == 0) {
					$event_options = [
						'output' => ['objectid'],
						'filter' => ['cause_eventid' => $problem['eventid']]
					];

					$symptom_events = $filter['show'] == TRIGGERS_OPTION_ALL
						? API::Event()->get($event_options)
						: API::Problem()->get($event_options + [
								'recent' => $filter['show'] == TRIGGERS_OPTION_RECENT_PROBLEM
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
						$do_causes_have_symptoms = true;
						$cause_eventids_with_symptoms[] = $problem['eventid'];
					}
				}

				if ($problem['cause_eventid'] != 0) {
					// For CSV get cause names for these symptom events.
					$symptom_cause_eventids[] = $problem['cause_eventid'];
				}
			}
			unset($problem);
		}

		if ($cause_eventids_with_symptoms) {
			foreach ($cause_eventids_with_symptoms as $cause_eventid) {
				// Get all symptoms for given cause event ID.
				$_symptom_data = CScreenProblem::getData([
					'show_symptoms' => true,
					'cause_eventid' => $cause_eventid,
					'show' => $filter['show']
				], $context_popup_data, ZBX_PROBLEM_SYMPTOM_LIMIT, true);

				if ($_symptom_data['problems']) {
					$_symptom_data = CScreenProblem::sortData($_symptom_data, ZBX_PROBLEM_SYMPTOM_LIMIT, $sort_field,
						$sort_order
					);

					/*
					 * Since getData returns +1 more in order to show the "+" sign for paging or sortData should not cut
					 * off any excess problems, in order to display actual limit of symptoms, one more slice is
					 * necessary.
					 */
					$_symptom_data['problems'] = array_slice($_symptom_data['problems'], 0, ZBX_PROBLEM_SYMPTOM_LIMIT,
						true
					);

					// Filter does not matter.
					$_symptom_data = CScreenProblem::makeData($_symptom_data, ['show' => $filter['show']],
						$context_popup_data, true);

					$data['users'] += $_symptom_data['users'];
					$data['correlations'] += $_symptom_data['correlations'];

					foreach ($_symptom_data['actions'] as $key => $actions) {
						$data['actions'][$key] += $actions;
					}

					if ($_symptom_data['triggers']) {
						$triggerids = array_keys($_symptom_data['triggers']);

						$db_triggers = API::Trigger()->get([
							'output' => [],
							'selectDependencies' => ['triggerid'],
							'triggerids' => $triggerids,
							'preservekeys' => true
						]);

						foreach ($_symptom_data['triggers'] as $triggerid => &$trigger) {
							$trigger['dependencies'] = array_key_exists($triggerid, $db_triggers)
								? $db_triggers[$triggerid]['dependencies']
								: [];
						}
						unset($trigger);

						// Add hosts from symptoms to the list.
						$triggers_hosts += getTriggersHostsList($_symptom_data['triggers']);

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

		if ($data['problems']) {
			$db_maintenances = [];

			$hostids = [];
			$maintenanceids = [];

			foreach ($triggers_hosts as $hosts) {
				foreach ($hosts as $host) {
					$hostids[$host['hostid']] = true;
					if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
						$maintenanceids[$host['maintenanceid']] = true;
					}
				}
			}

			if ($hostids) {
				if ($maintenanceids) {
					$db_maintenances = API::Maintenance()->get([
						'output' => ['name', 'description'],
						'maintenanceids' => array_keys($maintenanceids),
						'preservekeys' => true
					]);
				}
			}

			foreach ($triggers_hosts as &$hosts) {
				foreach ($hosts as &$host) {
					if (array_key_exists($host['maintenanceid'], $db_maintenances)) {
						$host['maintenance'] = $db_maintenances[$host['maintenanceid']];
					}
				}
				unset($host);
			}
			unset($hosts);
		}
		else {
			$triggers_hosts = [];
		}

		// Make trigger dependencies.
		$dependencies = $data['triggers'] ? getTriggerDependencies($data['triggers']) : [];

		$data += [
			'today' => strtotime('today'),
			'last_clock' => 0,
			'allowed' => [
				'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
				'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
				'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
				'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
				'suppress_problems' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
				'rank_change' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
			],
			'columns' => $columns,
			'fields' => $fields,
			'filter' => $filter,
			'options' => $options,
			'context_popup_data' => $context_popup_data,
			'triggers_hosts' => $triggers_hosts,
			'symptom_data' => $symptom_data,
			'symptom_cause_eventids' => $symptom_cause_eventids,
			'cause_eventids_with_symptoms' => $cause_eventids_with_symptoms,
			'show_three_columns' => $do_causes_have_symptoms,
			'show_two_columns' => (bool) $symptom_cause_eventids,
			'dependencies' => $dependencies,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order
		];

		return $data;
	}
}
