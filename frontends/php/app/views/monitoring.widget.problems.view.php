<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
$sort_div = (new CSpan())
	->addClass(($data['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

$backurl = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.view')
	->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);

$url_details = (new CUrl('tr_events.php'))
	->setArgument('triggerid', '')
	->setArgument('eventid', '')
	->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);

$show_timeline = ($data['sortfield'] === 'clock');
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
			($data['sortfield'] === 'priority') ? [_('Severity'), $sort_div] : _('Severity')
		],
		_('Duration'),
		$data['config']['event_ack_enable'] ? _('Ack') : null,
		_('Actions'),
		$data['fields']['show_tags'] ? _('Tags') : null
	]));

$today = strtotime('today');
$last_clock = 0;

if ($data['data']['problems']) {
	$triggers_hosts = makeTriggersHostsList($data['data']['triggers_hosts']);
}
if ($data['config']['event_ack_enable']) {
	$acknowledges = makeEventsAcknowledges($data['data']['problems'], $backurl->getUrl());
}
$actions = makeEventsActions($data['data']['problems'], true);

foreach ($data['data']['problems'] as $eventid => $problem) {
	$trigger = $data['data']['triggers'][$problem['objectid']];

	if ($problem['r_eventid'] != 0) {
		$value = TRIGGER_VALUE_FALSE;
		$value_str = _('RESOLVED');
		$value_clock = $problem['r_clock'];
	}
	else {
		$in_closing = false;

		if ($data['config']['event_ack_enable']) {
			foreach ($problem['acknowledges'] as $acknowledge) {
				if ($acknowledge['action'] == ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM) {
					$in_closing = true;
					break;
				}
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
		addTriggerValueStyle($cell_status, $value, $value_clock,
			$data['config']['event_ack_enable'] && (bool) $problem['acknowledges']
		);
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
					: _('Resolved by user.')
			);
		}
	}

	$description = (new CCol([
		(new CActionLink($problem['name']))
			->setHint(
				make_popup_eventlist($trigger, $eventid, $backurl->getUrl(), $data['config'], $data['fullscreen']), '',
				true
			)
	]));

	$description_style = getSeverityStyle($trigger['priority']);

	if ($value == TRIGGER_VALUE_TRUE) {
		$description->addClass($description_style);
	}

	if (!$show_recovery_data) {
		// blinking
		$duration = time() - $problem['clock'];

		if ($data['config']['blink_period'] != 0 && $duration < $data['config']['blink_period']) {
			$description->addClass('blink');
			$description->setAttribute('data-time-to-blink', $data['config']['blink_period'] - $duration);
			$description->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN);
		}
	}

	if ($show_timeline) {
		if ($last_clock != 0) {
			CScreenProblem::addTimelineBreakpoint($table, $last_clock, $problem['clock'], ZBX_SORT_DOWN);
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

	$table->addRow(array_merge($row, [
		$show_recovery_data ? $cell_r_clock : null,
		$show_recovery_data ? $cell_status : null,
		makeInformationList($info_icons),
		$triggers_hosts[$trigger['triggerid']],
		$description,
		(new CCol(
			($problem['r_eventid'] != 0)
				? zbx_date2age($problem['clock'], $problem['r_clock'])
				: zbx_date2age($problem['clock'])
		))
			->addClass(ZBX_STYLE_NOWRAP),
		$data['config']['event_ack_enable'] ? $acknowledges[$problem['eventid']] : null,
		array_key_exists($eventid, $actions)
			? (new CCol($actions[$eventid]))->addClass(ZBX_STYLE_NOWRAP)
			: '',
		$data['fields']['show_tags'] ? $data['data']['tags'][$problem['eventid']] : null
	]));
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList([$data['info'], _s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
