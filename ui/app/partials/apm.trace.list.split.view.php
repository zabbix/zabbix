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

$trace_view = (new CDiv())
	->setId('trace_view')
	->addClass('trace-view')
	->addItem(
		(new CDiv())
			->addClass('trace-view-header')
			->addItem([
				(new CDiv())
					->setAttribute('data-trace-id', '')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
				(new CButtonIcon(ZBX_ICON_CLOSE))
					->addClass('trace-view-close-button')
					->addClass('js-close-button')
			])
	)
	->addItem(
		(new CTag('z-timeline-range-slider', true))
			->addClass('trace-timeline')
			->setAttribute('data-trace-timeline', '')
			->setAttribute('scale', '')
			->addItem(
				(new CSvg())
					->addClass('trace-overview')
					->setAttribute('data-trace-overview', '')
			)
	)
	->addItem(
		(new CSplitView())
			->setMinPosition('10%')
			->setMaxPosition('90%')
			->setFixed(CSplitView::PANE_FIXED_START)
			->setFixedSize('294px')
			->setFixed('start')
			->addClass('trace-scroll')
			->setAttribute('data-trace-scroll', '')
			->addItem(
				(new CSplitViewPane())
					->addClass('trace-tree')
					->addItem([
						(new CDiv('Span tree'))->addClass('trace-tree-header'),
						(new CTag('z-navigation-tree', true))->setAttribute('data-trace-tree', '')
					])
			)
			->addItem(
				(new CSplitViewPane())
					->addClass('trace-body')
					->addItem([
						(new CDiv())
							->addClass('trace-time')
							->addItem([
								(new CDiv())->addClass('trace-time-header')
									->setAttribute('data-trace-time-header', ''),
								(new CDiv())
									->addClass('trace-waterfall')
									->setAttribute('data-trace-waterfall', '')
							]),
						(new CDiv())->addClass('trace-details')->setAttribute('data-trace-details', '')
					])
			)
	);

$trace_view->show();
