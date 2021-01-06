<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */

// hint table
$help_hint = (new CList())
	->addClass(ZBX_STYLE_NOTIF_BODY)
	->addStyle('min-width: '.ZBX_OVERVIEW_HELP_MIN_WIDTH.'px');
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$help_hint->addItem([
		(new CDiv())
			->addClass(ZBX_STYLE_NOTIF_INDIC)
			->addClass(getSeverityStyle($severity)),
		new CTag('h4', true, getSeverityName($severity, $data['config'])),
		(new CTag('p', true, _('PROBLEM')))->addClass(ZBX_STYLE_GREY)
	]);
}

// header right
$web_layout_mode = CViewHelper::loadLayoutMode();

$submenu_source = [
	SHOW_TRIGGERS => _('Trigger overview'),
	SHOW_DATA => _('Data overview')
];

$submenu = [];
foreach ($submenu_source as $value => $label) {
	$url = (new CUrl('overview.php'))
		->setArgument('type', $value)
		->getUrl();

	$submenu[$url] = $label;
}

$widget = (new CWidget())
	->setTitle(array_key_exists($this->data['type'], $submenu_source) ? $submenu_source[$this->data['type']] : null)
	->setTitleSubmenu([
		'main_section' => [
			'items' => $submenu
		]
	])
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())
			->addItem((new CForm('get'))
				->cleanItems()
				->setName('main_filter')
				->setAttribute('aria-label', _('Main filter'))
				->addItem(new CInput('hidden', 'type', $data['type']))
				->addItem((new CList())
					->addItem([
						new CLabel(_('Hosts location'), 'label-view-style'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CSelect('view_style'))
							->setId('hosts-location')
							->setValue($data['view_style'])
							->setFocusableElementId('label-view-style')
							->addOption(new CSelectOption(STYLE_TOP, _('Top')))
							->addOption(new CSelectOption(STYLE_LEFT, _('Left')))
					])
				)
			)
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			->addItem(get_icon('overviewhelp')->setHint($help_hint))
		))
			->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	// filter
	$widget->addItem((new CFilter((new CUrl('overview.php'))->setArgument('type', 1)))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
					(new CMultiSelect([
						'multiple' => true,
						'name' => 'filter_groupids[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groupids_',
								'with_monitored_items' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
					(new CMultiSelect([
						'multiple' => true,
						'name' => 'filter_hostids[]',
						'object_name' => 'hosts',
						'data' => $data['filter']['hosts'],
						'popup' => [
							'filter_preselect_fields' => [
								'hostgroups' => 'filter_groupids_'
							],
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_',
								'monitored_hosts' => true,
								'with_monitored_items' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('Application'), [
					(new CTextBox('application', $data['filter']['application']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('application_name', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('return PopUp("popup.generic", jQuery.extend('.
							json_encode([
								'srctbl' => 'applications',
								'srcfld1' => 'name',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'application',
								'real_hosts' => '1',
								'with_applications' => '1'
							]).', getFirstMultiselectValue("filter_hostids_", "filter_groupids_")), null, this);'
						)
				])
				->addRow(_('Show suppressed problems'),
					(new CCheckBox('show_suppressed'))->setChecked(
						$data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE
					)
				)
		])
	);
}

$partial = ($data['view_style'] == STYLE_TOP) ? 'dataoverview.table.top' : 'dataoverview.table.left';
$table = new CPartial($partial, [
	'items' => $data['items'],
	'hosts' => $data['hosts'],
	'has_hidden_data' => $data['has_hidden_data']
]);

$widget->addItem($table);

$widget->show();
