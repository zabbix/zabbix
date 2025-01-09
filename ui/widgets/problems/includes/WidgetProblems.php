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


namespace Widgets\Problems\Includes;

use CButtonIcon,
	CCol,
	CColHeader,
	CHintBoxHelper,
	CIcon,
	CLink,
	CLinkAction,
	CMacrosResolverHelper,
	CMenuPopupHelper,
	CRow,
	CScreenProblem,
	CSeverityHelper,
	CSpan,
	CTableInfo,
	CUrl;

class WidgetProblems extends CTableInfo {
	private array $data;

	public function __construct(array $data) {
		$this->data = $data;

		parent::__construct();
	}

	private function build(): void {
		$sort_div = (new CSpan())->addClass(
			($this->data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP
		);

		$show_timeline = ($this->data['sortfield'] === 'clock' && $this->data['fields']['show_timeline']);
		$show_recovery_data = in_array($this->data['fields']['show'],
			[TRIGGERS_OPTION_RECENT_PROBLEM, TRIGGERS_OPTION_ALL]
		);

		$header_time = (new CColHeader(($this->data['sortfield'] === 'clock')
			? [_x('Time', 'compact table header'), $sort_div]
			: _x('Time', 'compact table header')))->addStyle('width: 120px;');

		$header = [];

		if ($this->data['show_three_columns']) {
			$header[] = new CColHeader();
			$header[] = (new CColHeader())->addClass(ZBX_STYLE_CELL_WIDTH);
		}
		elseif ($this->data['show_two_columns']) {
			$header[] = new CColHeader();
		}

		if ($show_timeline) {
			$header[] = $header_time->addClass(ZBX_STYLE_RIGHT);
			$header[] = (new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH);
			$header[] = (new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH);
		}
		else {
			$header[] = $header_time;
		}

		$this->setHeader(array_merge($header, [
			$show_recovery_data
				? _x('Recovery time', 'compact table header')
				: null,
			$show_recovery_data
				? _x('Status', 'compact table header')
				: null,
			_x('Info', 'compact table header'),
			($this->data['sortfield'] === 'host')
				? [_x('Host', 'compact table header'), $sort_div]
				: _x('Host', 'compact table header'),
			[
				($this->data['sortfield'] === 'name')
					? [_x('Problem', 'compact table header'), $sort_div]
					: _x('Problem', 'compact table header'),
				' ', BULLET(), ' ',
				($this->data['sortfield'] === 'severity')
					? [_x('Severity', 'compact table header'), $sort_div]
					: _x('Severity', 'compact table header')
			],
			($this->data['fields']['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY)
				? _x('Operational data', 'compact table header')
				: null,
			_x('Duration', 'compact table header'),
			_('Update'),
			_x('Actions', 'compact table header'),
			$this->data['fields']['show_tags'] ? _x('Tags', 'compact table header') : null
		]));

		$this->data['triggers_hosts'] = $this->data['problems']
			? makeTriggersHostsList($this->data['triggers_hosts'])
			: [];

		$this->data += [
			'today' => strtotime('today'),
			'show_timeline' => $show_timeline,
			'last_clock' => 0,
			'show_recovery_data' => $show_recovery_data
		];

		$this->addProblemsToTable($this->data['problems'], $this->data, false);

		if ($this->data['info'] !== '') {
			$this->setFooter([
				(new CCol($this->data['info']))
					->setColSpan($this->getNumCols())
					->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
			]);
		}
	}

	/**
	 * Add problems and symptoms to table.
	 *
	 * @param array      $problems                              List of problems.
	 * @param array      $data                                  Additional data to build the table.
	 * @param array      $data['triggers']                      List of triggers.
	 * @param int        $data['today']                         Timestamp of today's date.
	 * @param array      $data['tasks']                         List of tasks. Used to determine current problem status.
	 * @param array      $data['users']                         List of users.
	 * @param array      $data['correlations']                  List of event correlations.
	 * @param array      $data['fields']                        Problem widget filter fields.
	 * @param int        $data['fields']['show']                "Show" filter option.
	 * @param int        $data['fields']['show_tags']           "Show tags" filter option.
	 * @param int        $data['fields']['show_opdata']         "Show operational data" filter option.
	 * @param array      $data['fields']['tags']                "Tags" filter.
	 * @param int        $data['fields']['tag_name_format']     "Tag name" filter.
	 * @param string     $data['fields']['tag_priority']        "Tag display priority" filter.
	 * @param bool       $data['show_timeline']                 "Show timeline" filter option.
	 * @param bool       $data['show_three_columns']            True if 3 columns should be displayed.
	 * @param bool       $data['show_two_columns']               True if 2 column should be displayed.
	 * @param int        $data['last_clock']                    Problem time. Used to show timeline breaks.
	 * @param int        $data['sortorder']                     Sort problems in ascending or descending order.
	 * @param array      $data['allowed']                       An array of user role rules.
	 * @param bool       $data['allowed']['ui_problems']        Whether user is allowed to access problem view.
	 * @param bool       $data['allowed']['close']              Whether user is allowed to close problems.
	 * @param bool       $data['allowed']['add_comments']       Whether user is allowed to add problems comments.
	 * @param bool       $data['allowed']['change_severity']    Whether user is allowed to change problems severity.
	 * @param bool       $data['allowed']['acknowledge']        Whether user is allowed to acknowledge problems.
	 * @param bool       $data['allowed']['suppress_problems']  Whether user is allowed to manually suppress/unsuppress
	 *                                                          problems.
	 * @param bool       $data['allowed']['rank_change']        Whether user is allowed to change problem ranking.
	 * @param bool       $data['show_recovery_data']            True if filter "Show" option is "Recent problems"
	 *                                                          or History.
	 * @param array      $data['triggers_hosts']                List of trigger hosts.
	 * @param array      $data['actions']                       List of actions.
	 * @param array      $data['tags']                          List of tags.
	 * @param bool       $nested                                If true, show the symptom rows with indentation.
	 */
	private function addProblemsToTable(array $problems, array $data, $nested): void {
		foreach ($problems as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$cell_clock = ($problem['clock'] >= $data['today'])
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);

			$cell_r_clock = '';

			if ($data['allowed']['ui_problems']) {
				$cell_clock = new CCol(new CLink($cell_clock,
					(new CUrl('tr_events.php'))
						->setArgument('triggerid', $problem['objectid'])
						->setArgument('eventid', $problem['eventid'])
				));
			}
			else {
				$cell_clock = new CCol($cell_clock);
			}

			if ($problem['r_eventid'] != 0) {
				$cell_r_clock = ($problem['r_clock'] >= $data['today'])
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);

				if ($data['allowed']['ui_problems']) {
					$cell_r_clock = new CCol(new CLink($cell_r_clock,
						(new CUrl('tr_events.php'))
							->setArgument('triggerid', $problem['objectid'])
							->setArgument('eventid', $problem['eventid'])
					));
				}
				else {
					$cell_r_clock = new CCol($cell_r_clock);
				}

				$cell_r_clock
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_RIGHT);
			}

			$in_closing = false;

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

			$value_str = getEventStatusString($in_closing, $problem);
			$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
			$cell_status = new CSpan($value_str);

			if (isEventUpdating($in_closing, $problem)) {
				$cell_status->addClass('js-blink');
			}

			// Add colors and blinking to span depending on configuration and trigger parameters.
			addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

			// Info.
			$info_icons = [];

			if ($data['fields']['show'] == TRIGGERS_OPTION_IN_PROBLEM) {
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
			elseif ($problem['suppression_data']) {
				$info_icons[] = makeSuppressedProblemIcon($problem['suppression_data']);
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

			$opdata = null;
			if ($data['fields']['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
				// operational data
				if ($trigger['opdata'] === '') {
					if ($data['fields']['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY) {
						$opdata = (new CCol(
							CScreenProblem::getLatestValues($trigger['items'])
						))->addClass('latest-values');
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
						[
							'events' => true,
							'html' => true
						]
					);

					if ($data['fields']['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY) {
						$opdata = (new CCol($opdata))->addClass('opdata');
					}
				}
			}

			$problem_link = [
				(new CLinkAction($problem['name']))
					->setMenuPopup(CMenuPopupHelper::getTrigger([
						'triggerid' => $trigger['triggerid'],
						'backurl' => (new CUrl('zabbix.php'))
							->setArgument('action', 'dashboard.view')
							->getUrl(),
						'eventid' => $problem['eventid'],
						'show_rank_change_cause' => true
					]))
					->setAttribute('aria-label', _xs('%1$s, Severity, %2$s', 'screen reader',
						$problem['name'], CSeverityHelper::getName((int) $problem['severity'])
					))
			];

			if ($data['fields']['show_opdata'] == OPERATIONAL_DATA_SHOW_WITH_PROBLEM && $opdata) {
				$problem_link = array_merge($problem_link, [' (', $opdata, ')']);
			}

			$description = (new CCol($problem_link))->addClass(ZBX_STYLE_WORDBREAK);
			$description_style = CSeverityHelper::getStyle((int) $problem['severity']);

			if ($value == TRIGGER_VALUE_TRUE) {
				$description->addClass($description_style);
			}

			if (!$data['show_recovery_data']
					&& (($is_acknowledged && $data['config']['problem_ack_style'])
						|| (!$is_acknowledged && $data['config']['problem_unack_style']))) {
				// blinking
				$duration = time() - $problem['clock'];
				$blink_period = timeUnitToSeconds($data['config']['blink_period']);

				if ($blink_period != 0 && $duration < $blink_period) {
					$description
						->addClass('js-blink')
						->setAttribute('data-time-to-blink', $blink_period - $duration)
						->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN);
				}
			}

			$symptom_col = (new CCol(
				new CIcon(ZBX_ICON_ARROW_TOP_RIGHT, _('Symptom'))
			))->addClass(ZBX_STYLE_RIGHT);

			$empty_col = new CCol();

			if ($data['show_timeline']) {
				$symptom_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
				$empty_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
			}

			// Build rows and columns.
			if ($problem['cause_eventid'] == 0) {
				$row = new CRow();

				if ($problem['symptom_count'] > 0) {
					// Show symptom counter and collapse/expand button.
					$symptom_count_col = (new CCol(
						(new CSpan($problem['symptom_count']))->addClass(ZBX_STYLE_ENTITY_COUNT)
					))->addClass(ZBX_STYLE_RIGHT);

					$collapse_expand_col = (new CCol(
						(new CButtonIcon(ZBX_ICON_CHEVRON_DOWN, _('Expand')))
							->addClass(ZBX_STYLE_COLLAPSED)
							->setAttribute('data-eventid', $problem['eventid'])
							->setAttribute('data-action', 'show_symptoms')
					))->addClass(ZBX_STYLE_RIGHT);

					if ($data['show_timeline']) {
						$symptom_count_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
						$collapse_expand_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
					}

					$row->addItem([$symptom_count_col, $collapse_expand_col]);
				}
				else {
					if ($data['show_three_columns']) {
						// Show two empty columns.
						$row->addItem([$empty_col, $empty_col]);
					}
					elseif ($data['show_two_columns']) {
						$row->addItem($empty_col);
					}
				}
			}
			else {
				if ($nested) {
					// First and second column empty for symptom event.
					$row = (new CRow($empty_col))
						->addClass(ZBX_STYLE_PROBLEM_NESTED)
						->addClass(ZBX_STYLE_PROBLEM_NESTED_SMALL)
						->addClass('hidden')
						->setAttribute('data-cause-eventid', $problem['cause_eventid'])
						->addItem($symptom_col);
				}
				else {
					// First column empty stand-alone symptom event.
					$row = new CRow($symptom_col);
				}

				// If there are causes as well, show additional empty column.
				if (!$nested && $data['show_three_columns']) {
					$row->addItem($empty_col);
				}
			}

			if ($data['show_timeline']) {
				if ($data['last_clock'] != 0) {
					CScreenProblem::addTimelineBreakpoint($this, $data, $problem, $nested, false);
				}
				$data['last_clock'] = $problem['clock'];

				$row->addItem([
					$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
					(new CCol())
						->addClass(ZBX_STYLE_TIMELINE_AXIS)
						->addClass(ZBX_STYLE_TIMELINE_DOT),
					(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
				]);
			}
			else {
				$row->addItem($cell_clock
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass(ZBX_STYLE_RIGHT)
				);
			}

			$problem_update_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'acknowledge.edit')
				->setArgument('eventids[]', $problem['eventid'])
				->getUrl();

			// Create acknowledge link.
			$problem_update_link = ($data['allowed']['add_comments'] || $data['allowed']['change_severity']
					|| $data['allowed']['acknowledge'] || $can_be_closed || $data['allowed']['suppress_problems']
					|| $data['allowed']['rank_change'])
				? (new CLink(_('Update'), $problem_update_url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->setAttribute('data-eventids[]', $problem['eventid'])
					->setAttribute('data-action', 'acknowledge.edit')
				: new CSpan(_('Update'));

			$row
				->addItem([
					$data['show_recovery_data'] ? $cell_r_clock : null,
					$data['show_recovery_data'] ? $cell_status : null,
					makeInformationList($info_icons),
					$data['triggers_hosts'][$trigger['triggerid']],
					$description,
					($data['fields']['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY)
						? $opdata->addClass(ZBX_STYLE_WORDBREAK)
						: null,
					(new CCol(
						(new CLinkAction(zbx_date2age($problem['clock'], ($problem['r_eventid'] != 0)
							? $problem['r_clock']
							: 0
						)))
							->setAjaxHint(CHintBoxHelper::getEventList($trigger['triggerid'], $problem['eventid'],
								$data['show_timeline'], $data['fields']['show_tags'], $data['fields']['tags'],
								$data['fields']['tag_name_format'], $data['fields']['tag_priority']
							))
					))->addClass(ZBX_STYLE_NOWRAP),
					$problem_update_link,
					makeEventActionsIcons($problem['eventid'], $data['actions'], $data['users'], $is_acknowledged),
					$data['fields']['show_tags'] ? $data['tags'][$problem['eventid']] : null
				])
				->setAttribute('data-eventid', $problem['eventid']);

			$this->addRow($row);

			if ($problem['cause_eventid'] == 0 && $problem['symptoms']) {
				$this->addProblemsToTable($problem['symptoms'], $data, true);

				CScreenProblem::addSymptomLimitToTable($this, $problem, $data);
			}
		}
	}

	public function toString($destroy = true): string {
		$this->build();

		return parent::toString($destroy);
	}
}
