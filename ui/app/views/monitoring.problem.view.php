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


/**
 * @var CView $this
 * @var array $data
 */

if ($data['action'] === 'problem.view') {
	$this->addJsFile('class.calendar.js');
	$this->addJsFile('gtlc.js');
	$this->addJsFile('layout.mode.js');
	$this->addJsFile('class.tabfilter.js');
	$this->addJsFile('class.tabfilteritem.js');
	$this->addJsFile('class.tagfilteritem.js');

	$this->enableLayoutModes();
	$web_layout_mode = $this->getLayoutMode();

	if ($data['uncheck']) {
		uncheckTableRows('problem');
	}

	$widget = (new CWidget())
		->setTitle(_('Problems'))
		->setWebLayoutMode($web_layout_mode)
		->setControls(
			(new CTag('nav', true,
				(new CList())
					->addItem((new CRedirectButton(_('Export to CSV'),
						(new CUrl())->setArgument('action', 'problem.view.csv')
					))->setId('export_csv'))
					->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			))->setAttribute('aria-label', _('Content controls'))
		);

	if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
		$filter = (new CTabFilter())
			->setId('monitoring_problem_filter')
			->setOptions($data['tabfilter_options'])
			->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

		foreach ($data['filter_tabs'] as $tab) {
			$tab['tab_view'] = $data['filter_view'];
			$filter->addTemplatedTab($tab['filter_name'], $tab);
		}

		// Set javascript options for tab filter initialization in monitoring.problem.view.js.php file.
		$data['filter_options'] = $filter->options;
		$widget->addItem($filter);
	}
	else {
		$data['filter_options'] = null;
	}

	$this->includeJsFile('monitoring.problem.view.js.php', $data);
	$widget
		->addItem(new CPartial('monitoring.problem.view.html', array_intersect_key($data,
			array_flip(['page', 'action', 'sort', 'sortorder', 'filter', 'tabfilter_idx'])
		)))
		->show();

	(new CScriptTag('
		view.init('.json_encode([
			'filter_options' => $data['filter_options'],
			'refresh_url' => $data['refresh_url'],
			'refresh_interval' => $data['refresh_interval'],
			'filter_defaults' => $data['filter_defaults']
		]).');
	'))
		->setOnDocumentReady()
		->show();
}
else {
	echo (new CPartial('monitoring.problem.view.html', array_intersect_key($data,
		array_flip(['page', 'action', 'sort', 'sortorder', 'filter', 'tabfilter_idx'])
	)))->getOutput();
}
