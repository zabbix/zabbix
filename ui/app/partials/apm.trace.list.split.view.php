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


/**
 * @var CView $this
 * @var array $data
 */

$trace_header_info = (new CDiv([
	(new CSpan(_s('Trace ID: %s', substr($data['trace']['traceid'], 0, 13)))),
	(new CButton())
		->setAttribute('data-traceid', $data['trace']['traceid'])
		->addClass(ZBX_STYLE_BTN_ICON)
		->addClass(ZBX_STYLE_BTN_ALT)
		->addClass('btn-medium')
		->addClass(ZBX_ICON_COPY)
		->addClass('js-copy-button')
]))->addClass('trace-header-info');

$trace_header_links = (new CDiv([
	(new CLink(
		_('Logs'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'apm.log.list')
			->setArgument('filter_traceid', $data['trace']['traceid'])
			->setArgument('filter_set', '1')
	)),
	(new CLink(
		_('Metrics'),
		(new CUrl('zabbix.php'))->setArgument('action', 'apm.metric.list')
	))
]))->addClass('trace-header-links');

$close_button = (new CButton())
	->addClass(ZBX_STYLE_BTN_ICON)
	->addClass(ZBX_STYLE_BTN_ALT)
	->addClass('btn-medium')
	->addClass(ZBX_ICON_CLOSE)
	->addClass('js-close-button');

$trace_timescale_header = (new CDiv())->addClass('trace-timescale-header');

$trace_timescale_timeline = (new CDiv())->addClass('trace-timescale-timeline');

$trace_span_tree_content = (new CDiv())->addClass('trace-span-tree-content');

$trace_span_tree = (new CDiv([
	(new CDiv(_('Span tree')))->addClass('trace-span-tree-header'),
	$trace_span_tree_content
]))->addClass('trace-span-tree');

$trace_span_timeline = (new CDiv([
	(new CDiv())->addClass('trace-span-timeline-header'),
	(new CDiv())->addClass('trace-span-timeline-content')
]))->addClass('trace-span-timeline');

(new CDiv([
	(new CDiv([$trace_header_info, $trace_header_links, $close_button]))->addClass('trace-header'),
	(new CDiv([$trace_timescale_header, $trace_timescale_timeline]))->addClass('trace-timescale'),
	(new CDiv([$trace_span_tree, $trace_span_timeline]))->addClass('trace-span')
]))
	->addClass('trace')
	->show();
