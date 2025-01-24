<?php
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


$output = [];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if (array_key_exists('problems', $data)) {
	// Show trigger description and URL.
	$div = new CDiv();

	if ($data['trigger']['comments'] !== '') {
		$div->addItem(
			(new CDiv())
				->addItem(zbx_str2links($data['trigger']['comments']))
				->addClass(ZBX_STYLE_OVERLAY_DESCR)
				->addStyle('max-width: 500px')
		);
	}

	if ($data['trigger']['url'] !== '') {
		$trigger_url = CHtmlUrlValidator::validate($data['trigger']['url'], ['allow_user_macro' => false])
			? $data['trigger']['url']
			: 'javascript: alert('.json_encode(_s('Provided URL "%1$s" is invalid.', $data['trigger']['url'])).');';

		$div->addItem(
			(new CDiv())
				->addItem(new CLink($data['trigger']['url'], $trigger_url))
				->addClass(ZBX_STYLE_OVERLAY_DESCR_URL)
				->addStyle('max-width: 500px')
		);
	}

	// sort field indicator
	$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN);

	if ($data['show_timeline']) {
		$header = [
			(new CColHeader([_('Time'), $sort_div]))->addClass(ZBX_STYLE_RIGHT),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
		];
	}
	else {
		$header = [[_('Time'), $sort_div]];
	}

	// Show events.
	$table = (new CTableInfo())
		->setHeader(array_merge($header, [
			_('Recovery time'),
			_('Status'),
			_('Duration'),
			_('Update'),
			($data['show_tags'] != SHOW_TAGS_NONE) ? _('Tags') : null
		]));

	$data += [
		'last_clock' => 0,
		'sortorder' => ZBX_SORT_DOWN,
		'show_three_columns' => false,
		'show_two_columns' => false
	];

	$today = strtotime('today');

	if ($data['problems'] && $data['show_tags'] != SHOW_TAGS_NONE) {
		$tags = makeTags($data['problems'], true, 'eventid', $data['show_tags'], $data['filter_tags'], null,
			$data['tag_name_format'], $data['tag_priority']
		);
	}

	$url_details = $data['allowed_ui_problems']
		? (new CUrl('tr_events.php'))
			->setArgument('triggerid', $data['trigger']['triggerid'])
			->setArgument('eventid', '')
		: null;

	foreach ($data['problems'] as $problem) {
		$can_be_closed = $data['allowed_close'];
		$in_closing = false;

		if ($problem['r_eventid'] != 0) {
			$value = TRIGGER_VALUE_FALSE;
			$value_clock = $problem['r_clock'];
			$can_be_closed = false;
		}
		else {
			if (hasEventCloseAction($problem['acknowledges'])) {
				$in_closing = true;
				$can_be_closed = false;
			}

			$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$value_clock = $in_closing ? time() : $problem['clock'];
		}

		$value_str = getEventStatusString($in_closing, $problem);

		$cell_clock = ($problem['clock'] >= $today)
			? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
			: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);

		if ($url_details !== null) {
			$url_details->setArgument('eventid', $problem['eventid']);
			$cell_clock = new CCol(new CLink($cell_clock, $url_details));
		}
		else {
			$cell_clock = new CCol($cell_clock);
		}

		if ($problem['r_eventid'] != 0) {
			$cell_r_clock = ($problem['r_clock'] >= $today)
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
			$cell_r_clock = (new CCol(($url_details !== null)
				? new CLink($cell_r_clock, $url_details)
				: $cell_r_clock
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_RIGHT);
		}
		else {
			$cell_r_clock = '';
		}

		$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
		$cell_status = new CSpan($value_str);

		if (isEventUpdating($in_closing, $problem)) {
			$cell_status->addClass('js-blink');
		}

		// Add colors and blinking to span depending on configuration and trigger parameters.
		addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

		if ($data['show_timeline']) {
			if ($data['last_clock'] != 0) {
				CScreenProblem::addTimelineBreakpoint($table, $data, $problem, false, false);
			}
			$data['last_clock'] = $problem['clock'];

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

		$problem_update_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'acknowledge.edit')
			->setArgument('eventids[]', $problem['eventid'])
			->getUrl();

		// Create acknowledge link.
		$problem_update_link = ($data['allowed_add_comments'] || $data['allowed_change_severity']
				|| $data['allowed_acknowledge'] || $can_be_closed || $data['allowed_suppress'])
			? (new CLink(_('Update'), $problem_update_url))->addClass(ZBX_STYLE_LINK_ALT)
			: new CSpan(_('Update'));

		$table->addRow(array_merge($row, [
			$cell_r_clock,
			$cell_status,
			(new CCol(
				($problem['r_eventid'] != 0)
					? zbx_date2age($problem['clock'], $problem['r_clock'])
					: zbx_date2age($problem['clock'])
			))
				->addClass(ZBX_STYLE_NOWRAP),
			$problem_update_link,
			($data['show_tags'] != SHOW_TAGS_NONE) ? $tags[$problem['eventid']] : null
		]));
	}

	$div->addItem($table);

	$output['data'] = $div->toString();
}

echo json_encode($output);
