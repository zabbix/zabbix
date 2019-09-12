<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


// indicator of sort field
$sort_div = (new CSpan())->addClass(($data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

$backurl = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.view')
	->getUrl();

$url_details = (new CUrl('tr_events.php'))
	->setArgument('triggerid', '')
	->setArgument('eventid', '');

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
		$data['fields']['show_opdata'] ? _('Operational data') : null,
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

foreach ($data['data']['problems'] as $eventid => $problem) {
	$trigger = $data['data']['triggers'][$problem['objectid']];

	if ($problem['r_eventid'] != 0) {
		$value = TRIGGER_VALUE_FALSE;
		$value_str = _('RESOLVED');
		$value_clock = $problem['r_clock'];
	}
	else {
		$in_closing = false;

		foreach ($problem['acknowledges'] as $acknowledge) {
			if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
				$in_closing = true;
				break;
			}
		}

		$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
		$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
		$value_clock = $in_closing ? time() : $problem['clock'];
	}

	$url_details
		->setArgument('triggerid', $problem['objectid'])
		->setArgument('eventid', $problem['eventid']);

	$cell_clock = ($problem['clock'] >= $today)
		? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
		: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
	$cell_clock = new CCol(new CLink($cell_clock, $url_details));

	$is_acknowledged = $problem['acknowledged'] == EVENT_ACKNOWLEDGED;

	if ($show_recovery_data) {
		if ($problem['r_eventid'] != 0) {
			$cell_r_clock = ($problem['r_clock'] >= $today)
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
			$cell_r_clock = (new CCol(new CLink($cell_r_clock, $url_details)))
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

	$description = (new CCol([
		(new CLinkAction($problem['name']))
			->setHint(
				make_popup_eventlist(['comments' => $problem['comments']] + $trigger, $eventid, $backurl,
					$show_timeline, $data['fields']['show_tags'], $data['fields']['tags'],
					$data['fields']['tag_name_format'], $data['fields']['tag_priority']
				)
			)
			->setAttribute('aria-label', _xs('%1$s, Severity, %2$s', 'screen reader',
				$problem['name'], getSeverityName($problem['severity'], $data['config'])
			))
	]));

	$description_style = getSeverityStyle($problem['severity']);

	if ($value == TRIGGER_VALUE_TRUE) {
		$description->addClass($description_style);
	}

	if (!$show_recovery_data && $data['config'][$is_acknowledged ? 'problem_ack_style' : 'problem_unack_style']) {
		// blinking
		$duration = time() - $problem['clock'];

		if ($data['config']['blink_period'] != 0 && $duration < $data['config']['blink_period']) {
			$description
				->addClass('blink')
				->setAttribute('data-time-to-blink', $data['config']['blink_period'] - $duration)
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

	// operational data
	$opdata = null;
	if ($data['fields']['show_opdata']) {
		$opdata = ($trigger['opdata'] !== '')
			? (new CCol(CMacrosResolverHelper::resolveTriggerOpdata(
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
			)))
				->addClass('opdata')
				->addClass(ZBX_STYLE_WORDWRAP)
			: (new CCol(CScreenProblem::getLatestValues($trigger['items'])))->addClass('latest-values');
	}

	// Create acknowledge url.
	$problem_update_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'acknowledge.edit')
		->setArgument('eventids', [$problem['eventid']])
		->setArgument('backurl', $backurl)
		->getUrl();

	$table->addRow(array_merge($row, [
		$show_recovery_data ? $cell_r_clock : null,
		$show_recovery_data ? $cell_status : null,
		makeInformationList($info_icons),
		$triggers_hosts[$trigger['triggerid']],
		$description,
		$opdata,
		(new CCol(zbx_date2age($problem['clock'], ($problem['r_eventid'] != 0) ? $problem['r_clock'] : 0)))
			->addClass(ZBX_STYLE_NOWRAP),
		(new CLink($problem['acknowledged'] == EVENT_ACKNOWLEDGED ? _('Yes') : _('No'), $problem_update_url))
			->addClass($problem['acknowledged'] == EVENT_ACKNOWLEDGED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
			->addClass(ZBX_STYLE_LINK_ALT),
		makeEventActionsIcons($problem['eventid'], $data['data']['actions'], $data['data']['mediatypes'],
			$data['data']['users'], $data['config']
		),
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
	'aria_label' => _xs('%1$s widget', 'screen reader', $data['name']).', '.$data['info'],
	'header' => $data['name'],
	'body' => $table->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
