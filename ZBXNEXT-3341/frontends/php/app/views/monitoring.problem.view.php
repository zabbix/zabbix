<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$this->addJsFile('js/gtlc.js');
$this->addJsFile('js/flickerfreescreen.js');
$this->addJsFile('js/multiselect.js');
require_once dirname(__FILE__).'/monitoring.problem.view.js.php';

if ($data['uncheck']) {
	uncheckTableRows();
}

$filter_column1 = (new CFormList())
	->addRow(_('Show'),
		(new CRadioButtonList('filter_show', (int) $data['filter']['show']))
			->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM)
			->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM)
			->setModern(true)
			->setFocused(true)
	)
	->addRow(_('Host groups'),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'objectName' => 'hostGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm=zbx_filter&dstfld1=filter_groupids_'.
					'&srcfld1=groupid&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Hosts'),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'objectName' => 'hosts',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm=zbx_filter&dstfld1=filter_hostids_&srcfld1=hostid'.
					'&real_hosts=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);

if ($data['config']['event_ack_enable']) {
	$filter_column1->addRow(_('Show unacknowledged only'),
		(new CCheckBox('filter_unacknowledged'))->setChecked($data['filter']['unacknowledged'] == 1)
	);
}

$filter_column1
	->addRow(_('Minimum trigger severity'),
		new CComboBox('filter_severity', $data['filter']['severity'], null, $data['filter']['severities'])
	);
$filter_age = (new CNumericBox('filter_age', $data['filter']['age'], 3, false, false, false))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
if ($data['filter']['age_state'] == 0) {
	$filter_age->setAttribute('disabled', 'disabled');
}

$filter_column1
	->addRow(_('Age less than'), [
		(new CCheckBox('filter_age_state'))
			->setChecked($data['filter']['age_state'] == 1)
			->onClick('javascript: this.checked ? $("filter_age").enable() : $("filter_age").disable()'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$filter_age,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('days')
	])
	->addRow(_('Problem'),
		(new CTextBox('filter_problem', $data['filter']['problem']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);

$filter_column2 = (new CFormList())
	->addRow(_('Application'), [
		(new CTextBox('filter_application', $data['filter']['application']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('filter_application_select', _('Select')))
			->onClick('return PopUp("'.
				(new CUrl('popup.php'))
					->setArgument('srctbl', 'applications')
					->setArgument('srcfld1', 'name')
					->setArgument('dstfrm', 'zbx_filter')
					->setArgument('dstfld1', 'filter_application')
					->setArgument('with_applications', '1')
					->setArgument('real_hosts', '1')
					->getUrl().
				'");'
			)
			->addClass(ZBX_STYLE_BTN_GREY)
	]);

$filter_inventory = $data['filter']['inventory'];
if (!$filter_inventory) {
	$filter_inventory = [['field' => '', 'value' => '']];
}

$filter_inventory_table = new CTable();
$filter_inventory_table->setId('filter-inventory');
$i = 0;
foreach ($filter_inventory as $field) {
	$filter_inventory_table->addRow([
		new CComboBox('filter_inventory['.$i.'][field]', $field['field'], null, $data['filter']['inventories']),
		(new CTextBox('filter_inventory['.$i.'][value]', $field['value']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('filter_inventory['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_inventory_table->addRow(
	(new CCol(
		(new CButton('filter_inventory_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);
$filter_column2->addRow(_('Host inventory'),
	(new CDiv($filter_inventory_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
);

$filter_tags = $data['filter']['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '']];
}

$filter_tags_table = new CTable();
$filter_tags_table->setId('filter-tags');
$i = 0;
foreach ($filter_tags as $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('filter_tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CTextBox('filter_tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('filter_tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('filter_tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);
$filter_column2
	->addRow(_('Tags'),
		(new CDiv($filter_tags_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	)
	->addRow(_('Show hosts in maintenance'),
		(new CCheckBox('filter_maintenance'))->setChecked($data['filter']['maintenance'] == 1)
	);

(new CWidget())
	->setTitle(_('Problems'))
	->setControls(
		(new CForm('get'))
			->addVar('action', 'problem.view')
			->addVar('fullscreen', $data['fullscreen'])
			->addVar('page', $data['page'])
			->addItem(
				(new CList())
					->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
			)
	)
	->addItem(
		(new CFilter('web.problem.filter.state'))
			->addVar('action', 'problem.view')
			->addVar('fullscreen', $data['fullscreen'])
			->addVar('page', $data['page'])
			->addColumn($filter_column1)
			->addColumn($filter_column2)
	)
	->addItem(
		CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_PROBLEM,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'problem',
			'page' => $data['page'],
			'data' => [
				'fullscreen' => $data['fullscreen'],
				'sort' => $data['sort'],
				'sortorder' => $data['sortorder'],
				'page' => $data['page'],
				'filter' => $data['filter']
			]
		])->get()
	)
	->show();

// activating blinking
$this->addPostJS('jqBlink.blink();');
