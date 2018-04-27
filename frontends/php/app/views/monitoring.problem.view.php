<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$options = [
	'resourcetype' => SCREEN_RESOURCE_PROBLEM,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'problem',
	'page' => $data['page'],
	'data' => [
		'action' => $data['action'],
		'fullscreen' => $data['fullscreen'],
		'sort' => $data['sort'],
		'sortorder' => $data['sortorder'],
		'page' => $data['page'],
		'filter' => [
			'show' => $data['filter']['show'],
			'groupids' => $data['filter']['groupids'],
			'hostids' => $data['filter']['hostids'],
			'application' => $data['filter']['application'],
			'triggerids' => $data['filter']['triggerids'],
			'name' => $data['filter']['name'],
			'severity' => $data['filter']['severity'],
			'inventory' => $data['filter']['inventory'],
			'evaltype' => $data['filter']['evaltype'],
			'tags' => $data['filter']['tags'],
			'maintenance' => $data['filter']['maintenance'],
			'unacknowledged' => $data['filter']['unacknowledged'],
			'details' => $data['filter']['details']
		]
	]
];

switch ($data['filter']['show']) {
	case TRIGGERS_OPTION_RECENT_PROBLEM:
	case TRIGGERS_OPTION_IN_PROBLEM:
		$options['data']['filter']['age_state'] = $data['filter']['age_state'];
		$options['data']['filter']['age'] = $data['filter']['age'];
		break;

	case TRIGGERS_OPTION_ALL:
		$options['profileIdx'] = $data['profileIdx'];
		$options['profileIdx2'] = $data['profileIdx2'];
		$options['updateProfile'] = $data['updateProfile'];
		$options['from'] = $data['from'];
		$options['to'] = $data['to'];
		break;
}

$screen = CScreenBuilder::getScreen($options);

if ($data['action'] == 'problem.view') {
	if ($data['filter']['show'] == TRIGGERS_OPTION_ALL) {
		$this->addJsFile('class.calendar.js');
	}
	$this->addJsFile('gtlc.js');
	$this->addJsFile('flickerfreescreen.js');
	$this->addJsFile('multiselect.js');
	require_once dirname(__FILE__).'/monitoring.problem.view.js.php';

	if ($data['uncheck']) {
		uncheckTableRows('problem');
	}

	$filter_column1 = (new CFormList())
		->addRow(_('Show'),
			(new CRadioButtonList('filter_show', (int) $data['filter']['show']))
				->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM)
				->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM)
				->addValue(_('History'), TRIGGERS_OPTION_ALL)
				->setModern(true)
		)
		->addRow(_('Host groups'),
			(new CMultiSelect([
				'name' => 'filter_groupids[]',
				'objectName' => 'hostGroup',
				'data' => $data['filter']['groups'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_groupids_',
						'srcfld1' => 'groupid',
						'multiselect' => '1'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
		->addRow(_('Hosts'),
			(new CMultiSelect([
				'name' => 'filter_hostids[]',
				'objectName' => 'hosts',
				'data' => $data['filter']['hosts'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'hosts',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_hostids_',
						'srcfld1' => 'hostid',
						'real_hosts' => '1',
						'multiselect' => '1'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
		->addRow(_('Application'), [
			(new CTextBox('filter_application', $data['filter']['application']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('filter_application_select', _('Select')))
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'applications',
						'srcfld1' => 'name',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_application',
						'with_applications' => '1',
						'real_hosts' => '1'
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_GREY)
		])
		->addRow(_('Triggers'),
			(new CMultiSelect([
				'name' => 'filter_triggerids[]',
				'objectName' => 'triggers',
				'objectOptions' => [
					'monitored' => true
				],
				'data' => $data['filter']['triggers'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'triggers',
						'srcfld1' => 'triggerid',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_triggerids_',
						'monitored_hosts' => '1',
						'with_monitored_triggers' => '1',
						'multiselect' => '1',
						'noempty' => '1'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
		->addRow(_('Problem'),
			(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
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

	$filter_tags = $data['filter']['tags'];
	if (!$filter_tags) {
		$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
	}

	$filter_tags_table = new CTable();
	$filter_tags_table->setId('filter-tags');

	$filter_tags_table->addRow(
		(new CCol(
			(new CRadioButtonList('filter_evaltype', (int) $data['filter']['evaltype']))
				->addValue(_('AND'), TAG_EVAL_TYPE_AND)
				->addValue(_('OR'), TAG_EVAL_TYPE_OR)
				->setModern(true)
		))->setColSpan(4)
	);

	$i = 0;
	foreach ($filter_tags as $tag) {
		$filter_tags_table->addRow([
			(new CTextBox('filter_tags['.$i.'][tag]', $tag['tag']))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('filter_tags['.$i.'][operator]', (int) $tag['operator']))
				->addValue(_('Like'), TAG_OPERATOR_LIKE)
				->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
				->setModern(true),
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
	$filter_column2 = (new CFormList())
		->addRow(_('Host inventory'), $filter_inventory_table)
		->addRow(_('Tags'), $filter_tags_table)
		->addRow(_('Show hosts in maintenance'),
			(new CCheckBox('filter_maintenance'))->setChecked($data['filter']['maintenance'] == 1)
		);

	if ($data['config']['event_ack_enable']) {
		$filter_column2->addRow(_('Show unacknowledged only'),
			(new CCheckBox('filter_unacknowledged'))->setChecked($data['filter']['unacknowledged'] == 1)
		);
	}

	$filter_column2
		->addRow(_('Show details'), (new CCheckBox('filter_details'))->setChecked($data['filter']['details'] == 1));

	$filter = (new CFilter())
		->setProfile('web.problem.filter', 0)
		->addVar('action', 'problem.view')
		->addVar('fullscreen', $data['fullscreen'] ? '1' : null)
		->addVar('page', $data['page']);

	if ($data['filter']['show'] == TRIGGERS_OPTION_ALL) {
		$filter->addTimeSelector($data['from'], $data['to']);
	}

	$filter->addFilterTab(_('Filter'), [$filter_column1, $filter_column2]);

	$this->addPostJS($filter->getJS());

	(new CWidget())
		->setTitle(_('Problems'))
		->setControls(
			(new CForm('get'))
				->cleanItems()
				->addVar('action', 'problem.view')
				->addVar('fullscreen', $data['fullscreen'] ? '1' : null)
				->addVar('page', $data['page'])
				->addItem(
					(new CList())
						->addItem(new CRedirectButton(_('Export to CSV'),
							(new CUrl('zabbix.php'))
								->setArgument('action', 'problem.view.csv')
								->setArgument('page',  $data['page'])
						))
						->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
				)
		)
		->addItem($filter)
		->addItem($screen->get())
		->show();

	// activating blinking
	$this->addPostJS('jqBlink.blink();');

	if ($data['filter']['show'] == TRIGGERS_OPTION_ALL) {
		$objData = [
			'id' => 'timeline_1',
			'loadSBox' => 0,
			'loadImage' => 0,
			'dynamic' => 0,
			'mainObject' => 1,
			'profile' => [
				'idx' => 'web.problem.timeline',
				'idx2' => 0,
			]
		];

		$this->addPostJS('timeControl.useTimeRefresh('.zbx_jsvalue(CWebUser::getRefresh()).');');
		$this->addPostJS('timeControl.addObject("scroll_events_id", '.zbx_jsvalue($screen->timeline).', '.zbx_jsvalue($objData).');');
		$this->addPostJS('timeControl.processObjects();');
	}
}
else {
	echo $screen->get();
}
