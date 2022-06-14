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
			: 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.',
				json_encode($data['trigger']['url'])).
			'\');';

		$div->addItem(
			(new CDiv())
				->addItem(new CLink(CHtml::encode($data['trigger']['url']), $trigger_url))
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
			_('Ack'),
			($data['show_tags'] != SHOW_TAGS_NONE) ? _('Tags') : null
		]));

	$today = strtotime('today');
	$last_clock = 0;

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

		// Add colors and blinking to span depending on configuration and trigger parameters.
		addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

		if ($data['show_timeline']) {
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

		// Create acknowledge link.
		$problem_update_link = ($data['allowed_add_comments'] || $data['allowed_change_severity']
				|| $data['allowed_acknowledge'] || $can_be_closed || $data['allowed_suppress'])
			? (new CLink($is_acknowledged ? _('Yes') : _('No')))
				->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT)
				->setAttribute('data-eventid', $problem['eventid'])
				->onClick('acknowledgePopUp({eventids: [this.dataset.eventid]}, this);')
			: (new CSpan($is_acknowledged ? _('Yes') : _('No')))
				->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);

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
