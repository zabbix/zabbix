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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

// indicator of sort field
$sort_div = (new CSpan())->addClass(($data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

$url_details = $data['allowed_ui_problems']
	? (new CUrl('tr_events.php'))
		->setArgument('triggerid', '')
		->setArgument('eventid', '')
	: null;

$show_timeline = ($data['sortfield'] === 'clock' && $data['fields']['show_timeline']);
$show_recovery_data = in_array($data['fields']['show'], [TRIGGERS_OPTION_RECENT_PROBLEM, TRIGGERS_OPTION_ALL]);

$header_time = new CColHeader(($data['sortfield'] === 'clock') ? [_('Time'), $sort_div] : _('Time'));

if ($show_timeline) {
	$header = [
		$header_time->addClass(ZBX_STYLE_RIGHT),
		(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
		(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
	];
}
else {
	$header = [$header_time];
}

$show_opdata = $data['fields']['show_opdata'];

$table = (new CTableInfo())
	->setHeader(array_merge($header, [
		$show_recovery_data ? _('Recovery time') : null,
		$show_recovery_data ? _('Status') : null,
		_('Info'),
		($data['sortfield'] === 'host') ? [_('Host'), $sort_div] : _('Host'),
		[
			($data['sortfield'] === 'name') ? [_('Problem'), $sort_div] : _('Problem'),
			' &bullet; ',
			($data['sortfield'] === 'severity') ? [_('Severity'), $sort_div] : _('Severity')
		],
		($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? _('Operational data') : null,
		_('Duration'),
		_('Ack'),
		_('Actions'),
		$data['fields']['show_tags'] ? _('Tags') : null
	]));

$today = strtotime('today');
$last_clock = 0;

if ($data['data']['problems']) {
	$triggers_hosts = makeTriggersHostsList($data['data']['triggers_hosts']);
}

$allowed = [
	'ui_problems' => $data['allowed_ui_problems'],
	'add_comments' => $data['allowed_add_comments'],
	'change_severity' => $data['allowed_change_severity'],
	'acknowledge' => $data['allowed_acknowledge']
];

foreach ($data['data']['problems'] as $eventid => $problem) {
	$trigger = $data['data']['triggers'][$problem['objectid']];

	$allowed['close'] = ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED && $data['allowed_close']);
	$can_be_closed = $allowed['close'];

	if ($problem['r_eventid'] != 0) {
		$value = TRIGGER_VALUE_FALSE;
		$value_str = _('RESOLVED');
		$value_clock = $problem['r_clock'];
		$can_be_closed = false;
	}
	else {
		$in_closing = false;

		foreach ($problem['acknowledges'] as $acknowledge) {
			if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
				$in_closing = true;
				$can_be_closed = false;
				break;
			}
		}

		$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
		$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
		$value_clock = $in_closing ? time() : $problem['clock'];
	}

	$cell_clock = ($problem['clock'] >= $today)
		? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
		: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);

	if ($url_details !== null) {
		$url_details
			->setArgument('triggerid', $problem['objectid'])
			->setArgument('eventid', $problem['eventid']);
		$cell_clock = new CCol(new CLink($cell_clock, $url_details));
	}
	else {
		$cell_clock = new CCol($cell_clock);
	}

	$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);

	if ($show_recovery_data) {
		if ($problem['r_eventid'] != 0) {
			$cell_r_clock = ($problem['r_clock'] >= $today)
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
			$cell_r_clock = (new CCol($url_details !== null ? new CLink($cell_r_clock, $url_details) : $cell_r_clock))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_RIGHT);
		}
		else {
			$cell_r_clock = '';
		}

		$cell_status = new CSpan($value_str);

		// Add colors and blinking to span depending on configuration and trigger parameters.
		addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);
	}

	// Info.
	$info_icons = [];
	if ($problem['r_eventid'] != 0) {
		if ($problem['correlationid'] != 0) {
			$info_icons[] = makeInformationIcon(
				array_key_exists($problem['correlationid'], $data['data']['correlations'])
					? _s('Resolved by correlation rule "%1$s".',
						$data['data']['correlations'][$problem['correlationid']]['name']
					)
					: _('Resolved by correlation rule.')
			);
		}
		elseif ($problem['userid'] != 0) {
			$info_icons[] = makeInformationIcon(
				array_key_exists($problem['userid'], $data['data']['users'])
					? _s('Resolved by user "%1$s".', getUserFullname($data['data']['users'][$problem['userid']]))
					: _('Resolved by inaccessible user.')
			);
		}
	}

	if (array_key_exists('suppression_data', $problem) && $problem['suppression_data']) {
		$info_icons[] = makeSuppressedProblemIcon($problem['suppression_data']);
	}

	$opdata = null;
	if ($show_opdata != OPERATIONAL_DATA_SHOW_NONE) {

		// operational data
		if ($trigger['opdata'] === '') {
			if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
				$opdata = (new CCol(CScreenProblem::getLatestValues($trigger['items'])))->addClass('latest-values');
			}
		} else {
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

			if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
				$opdata = (new CCol($opdata))
					->addClass('opdata')
					->addClass(ZBX_STYLE_WORDWRAP);
			}
		}
	}

	$problem_link = [
		(new CLinkAction($problem['name']))
			->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], $problem['eventid']))
			->setAttribute('aria-label', _xs('%1$s, Severity, %2$s', 'screen reader',
				$problem['name'], CSeverityHelper::getName((int) $problem['severity'])
			))
	];

	if ($show_opdata == OPERATIONAL_DATA_SHOW_WITH_PROBLEM && $opdata) {
		$problem_link = array_merge($problem_link, [' (', $opdata, ')']);
	}

	$description = (new CCol($problem_link));

	$description_style = CSeverityHelper::getStyle((int) $problem['severity']);

	if ($value == TRIGGER_VALUE_TRUE) {
		$description->addClass($description_style);
	}

	if (!$show_recovery_data
			&& (($is_acknowledged && $data['config']['problem_ack_style'])
				|| (!$is_acknowledged && $data['config']['problem_unack_style']))) {
		// blinking
		$duration = time() - $problem['clock'];
		$blink_period = timeUnitToSeconds($data['config']['blink_period']);

		if ($blink_period != 0 && $duration < $blink_period) {
			$description
				->addClass('blink')
				->setAttribute('data-time-to-blink', $blink_period - $duration)
				->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN);
		}
	}

	if ($show_timeline) {
		if ($last_clock != 0) {
			CScreenProblem::addTimelineBreakpoint($table, $last_clock, $problem['clock'], $data['sortorder']);
		}
		$last_clock = $problem['clock'];

		$row = [
			$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
			(new CCol())
				->addClass(ZBX_STYLE_TIMELINE_AXIS)
				->addClass(ZBX_STYLE_TIMELINE_DOT),
			(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
		];
	}
	else {
		$row = [
			$cell_clock
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_RIGHT)
		];
	}

	// Create acknowledge link.
	$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
			|| $can_be_closed)
		? (new CLink($is_acknowledged ? _('Yes') : _('No')))
			->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
			->addClass(ZBX_STYLE_LINK_ALT)
			->onClick('acknowledgePopUp('.json_encode(['eventids' => [$problem['eventid']]]).', this);')
		: (new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
			$is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
		);

	$table->addRow(array_merge($row, [
		$show_recovery_data ? $cell_r_clock : null,
		$show_recovery_data ? $cell_status : null,
		makeInformationList($info_icons),
		$triggers_hosts[$trigger['triggerid']],
		$description,
		($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY ) ? $opdata : null,
		(new CCol(
			(new CLinkAction(zbx_date2age($problem['clock'], ($problem['r_eventid'] != 0) ? $problem['r_clock'] : 0)))
				->setAjaxHint(CHintBoxHelper::getEventList($trigger['triggerid'], $eventid, $show_timeline,
					$data['fields']['show_tags'], $data['fields']['tags'], $data['fields']['tag_name_format'],
					$data['fields']['tag_priority']
				))
		))->addClass(ZBX_STYLE_NOWRAP),
		$problem_update_link,
		makeEventActionsIcons($problem['eventid'], $data['data']['actions'], $data['data']['users']),
		$data['fields']['show_tags'] ? $data['data']['tags'][$problem['eventid']] : null
	]));
}

if ($data['info'] !== '') {
	$table->setFooter([
		(new CCol($data['info']))
			->setColSpan($table->getNumCols())
			->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
	]);
}

$output = [
	'name' => $data['name'],
	'body' => $table->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
