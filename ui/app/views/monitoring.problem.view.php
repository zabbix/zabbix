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
						->setAttribute('onclick', 'view.datatable.export(this, \'zbx_problems_report.csv\')')
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

$html_page
	->addItem($filter)
	->addItem(
		(new CForm('post', 'zabbix.php'))
			->setId('problem_form')
			->setName('problem')
			->addItem([
				(new CDiv())->setId('problems'),
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
	)
	->show();

(new CTemplateTag('time'))
	->addItem([
		new CFormField(
			(new CCheckBox('show_timeline'))
				->setLabel(_('Show timeline'))
				->setLabelPosition(CCheckBox::LABEL_POSITION_RIGHT)
				->setUncheckedValue(1)
				->addClass('form-label')
		)
	])
	->show();

(new CTemplateTag('problem'))
	->addItem([
		new CFormField(
			(new CCheckBox('show_opdata'))
				->setLabel(_('Show operational data'))
				->setLabelPosition(CCheckBox::LABEL_POSITION_RIGHT)
		),
		new CFormField(
			(new CCheckBox('details'))
				->setLabel(_('Show trigger expression'))
				->setLabelPosition(CCheckBox::LABEL_POSITION_RIGHT)
		),
		new CFormField(
			(new CCheckBox('show_suppressed'))
				->setLabel(_('Show suppressed'))
				->setLabelPosition(CCheckBox::LABEL_POSITION_RIGHT)
				->setUncheckedValue(0)
		)
	])
	->show();

(new CTemplateTag('tags'))
	->addItem([
		(new CLabel(_('Number of tags'), 'number_of_tags'))
			->addClass('form-label'),
		new CFormField(
			(new CRadioButtonList('number_of_tags'))
				->setValues([
					['name' => SHOW_TAGS_1, 'value' => SHOW_TAGS_1],
					['name' => SHOW_TAGS_2, 'value' => SHOW_TAGS_2],
					['name' => SHOW_TAGS_3, 'value' => SHOW_TAGS_3]
				])
				->setModern()
		),
		(new CLabel(_('Tag name display'), 'tag_name_display'))
			->addClass('form-label'),
		new CFormField(
			(new CRadioButtonList('tag_name_display'))
				->setValues([
					['name' => _('Full'), 'value' => TAG_NAME_FULL],
					['name' => _('Shortened'), 'value' => TAG_NAME_SHORTENED],
					['name' => _('None'), 'value' => TAG_NAME_NONE]
				])
				->setModern()
		),
		(new CLabel(_('Tag display priority'), 'tag_display_priority'))
			->addClass('form-label'),
		new CFormField(new CTextBox('tag_display_priority'))
	])
	->show();

(new CTemplateTag('tagvalue'))
	->addItem([
		(new CLabel(_('Tag name'), 'tag_name'))
			->addClass('form-label'),
		new CFormField(new CTextBox('tag_name'))
	])
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'layout_mode' => $web_layout_mode,
		'filter_options' => $data['filter_options'],
		'refresh_interval' => $data['refresh_interval'],
		'filter_defaults' => $data['filter_defaults'],
		'page' => $data['page'],
		'filter' => $data['filter'],
		'sort_field' => $data['sort'],
		'sort_order' => $data['sortorder'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs'],
		'severities' => $data['severities']
	]).');
'))
	->setOnDocumentReady()
	->show();
