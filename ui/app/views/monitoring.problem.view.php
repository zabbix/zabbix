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


/**
 * @var CView $this
 * @var array $data
 */

if ($data['action'] === 'problem.view') {
	$this->addJsFile('gtlc.js');
	$this->addJsFile('layout.mode.js');
	$this->addJsFile('class.tabfilter.js');
	$this->addJsFile('class.tabfilteritem.js');

	$this->enableLayoutModes();
	$web_layout_mode = $this->getLayoutMode();

	if ($data['uncheck']) {
		uncheckTableRows('problem');
	}

	$html_page = (new CHtmlPage())
		->setTitle(_('Problems'))
		->setWebLayoutMode($web_layout_mode)
		->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_PROBLEMS_VIEW))
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
		$html_page->addItem($filter);
	}
	else {
		$data['filter_options'] = null;
	}

	$this->includeJsFile('monitoring.problem.view.js.php', $data);
	$html_page
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
