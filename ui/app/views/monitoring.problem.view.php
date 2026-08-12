<?php
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

$this->addJsFile('gtlc.js');
$this->addJsFile('layout.mode.js');

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
				->addItem(
					(new CLink(_('Export to CSV'), 'javascript:void(0);'))
						->setId('export_csv')
						->addClass(ZBX_STYLE_BTN)
						->setAttribute('onclick', 'view.getDataTable().export(this, \'zbx_problems_report.csv\')')
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CTabFilter())
	->setId('monitoring_problem_filter')
	->setOptions($data['tabfilter_options'])
	->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

if ($web_layout_mode == ZBX_LAYOUT_KIOSKMODE) {
	$filter->setAttribute('hidden', '');
}

foreach ($data['filter_tabs'] as $tab) {
	$tab['tab_view'] = $data['filter_view'];
	$filter->addTemplatedTab($tab['filter_name'], $tab);
}

// Set javascript options for tab filter initialization in monitoring.problem.view.js.php file.
$data['filter_options'] = $filter->options;

$this->includeJsFile('monitoring.problem.view.js.php', $data);

$allowed = [
	'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
	'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
	'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
	'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
	'suppress_problems' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
	'rank_change' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
];

$mass_update_enabled = $allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
	|| $allowed['close'] || $allowed['suppress_problems'] || $allowed['rank_change'];

$csrf_token = CCsrfTokenHelper::get('problem');

$html_page
	->addItem($filter)
	->addItem(
		(new CForm('post', 'zabbix.php'))
			->setId('problem_form')
			->setName('problem')
			->addItem([
				(new CDataTable())->setId('datatable-problems'),
				(new CActionButtonList('action', 'eventids', [
					'acknowledge.edit' => [
						'content' => (new CSimpleButton(_('Mass update')))
							->addClass(ZBX_STYLE_BTN_ALT)
							->addClass('js-massupdate-problem')
							->addClass('js-no-chkbxrange')
							->setEnabled($mass_update_enabled)
					]
				], 'problem'))->setAddSelectedCountElement(false)
			])
	);

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	$html_page->addItem((new CPre())->addClass(ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH));
}

$html_page->show();

(new CScriptTag('
	view.init('.json_encode([
		'csrf_token' => $csrf_token,
		'default_sort_field' => $data['default_sort_field'],
		'default_sort_order' => $data['default_sort_order'],
		'filter' => $data['filter'],
		'filter_defaults' => $data['filter_defaults'],
		'filter_options' => $data['filter_options'],
		'layout_mode' => $web_layout_mode,
		'page' => $data['page'],
		'refresh_interval' => $data['refresh_interval'],
		'severities' => $data['severities'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs']
	]).');
'))
	->setOnDocumentReady()
	->show();
