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
 */

require_once dirname(__FILE__).'/js/configuration.maintenance.edit.js.php';

$widget = (new CWidget())->setTitle(_('Maintenance periods'));

$maintenance_form = (new CForm())
	->setId('maintenance-form')
	->setName('maintenanceForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);

if (array_key_exists('maintenanceid', $data) && $data['maintenanceid']) {
	$maintenance_form->addVar('maintenanceid', $data['maintenanceid']);
}

/*
 * Maintenance period.
 */
$maintenance_period_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Period type'), _('Schedule'), _('Period'), _('Action')])
	->setAriaRequired();

foreach (array_values($data['timeperiods']) as $index => $timeperiod) {
	$period_data = [];

	if ($timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME) {
		unset($timeperiod['start_date']);
	}

	foreach ($timeperiod as $field => $value) {
		$period_data[] = (new CVar(sprintf('timeperiods[%s][%s]', $index, $field), $value))->removeId();
	}

	$maintenance_period_table->addRow([
		(new CCol(timeperiod_type2str($timeperiod['timeperiod_type'])))->addClass(ZBX_STYLE_NOWRAP),
		($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME)
			? $timeperiod['start_date']
			: schedule2str($timeperiod),
		(new CCol(zbx_date2age(0, $timeperiod['period'])))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol([
			$period_data,
			new CHorList([
				(new CSimpleButton(_('Edit')))
					->setAttribute('data-action', 'edit')
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled($data['allowed_edit']),
				(new CSimpleButton(_('Remove')))
					->setAttribute('data-action', 'remove')
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled($data['allowed_edit'])
			])
		]))->addClass(ZBX_STYLE_NOWRAP)
	]);
}

$periods_container = (new CDiv($maintenance_period_table))
	->setId('maintenance_periods')
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->addItem(
		(new CSimpleButton(_('Add')))
			->setAttribute('data-action', 'add')
			->addClass(ZBX_STYLE_BTN_LINK)
			->setEnabled($data['allowed_edit'])
	);

$table = (new CFormList('maintenanceFormList'))
	->addRow(
		(new CLabel(_('Name'), 'mname'))->setAsteriskMark(),
		(new CTextBox('mname', $data['mname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setReadonly(!$data['allowed_edit'])
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('maintenances', 'name'))
	)
	->addRow((new CLabel(_('Maintenance type'), 'maintenance_type')),
		(new CRadioButtonList('maintenance_type', (int) $data['maintenance_type']))
			->addValue(_('With data collection'), MAINTENANCE_TYPE_NORMAL)
			->addValue(_('No data collection'), MAINTENANCE_TYPE_NODATA)
			->setModern(true)
			->setReadonly(!$data['allowed_edit'])
	)
	// Show date and time in shorter format without seconds.
	->addRow((new CLabel(_('Active since'), 'active_since'))->setAsteriskMark(),
		(new CDateSelector('active_since', $data['active_since']))
			->setDateFormat(ZBX_DATE_TIME)
			->setPlaceholder(_('YYYY-MM-DD hh:mm'))
			->setAriaRequired()
			->setReadonly(!$data['allowed_edit'])
	)
	->addRow((new CLabel(_('Active till'), 'active_till'))->setAsteriskMark(),
		(new CDateSelector('active_till', $data['active_till']))
			->setDateFormat(ZBX_DATE_TIME)
			->setPlaceholder(_('YYYY-MM-DD hh:mm'))
			->setAriaRequired()
			->setReadonly(!$data['allowed_edit'])
	)
	->addRow(
		(new CLabel(_('Periods'), $periods_container->getId()))->setAsteriskMark(), $periods_container
	);

/*
 * Hosts and groups tab.
 */
$tags = $data['tags'];
if (!$tags) {
	$tags = [['tag' => '', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => '']];
}

$tag_table = (new CTable())
	->setId('tags')
	->addRow(
		(new CCol(
			(new CRadioButtonList('tags_evaltype', (int) $data['tags_evaltype']))
				->addValue(_('And/Or'), MAINTENANCE_TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), MAINTENANCE_TAG_EVAL_TYPE_OR)
				->setModern(true)
				->setEnabled($data['maintenance_type'] == MAINTENANCE_TYPE_NODATA ? false : $data['allowed_edit'])
		))->setColSpan(4)
	);

$i = 0;
foreach ($tags as $tag) {
	if ($data['maintenance_type'] == MAINTENANCE_TYPE_NODATA) {
		$tag_table->addRow([
			(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setEnabled(false),
			(new CRadioButtonList('tags['.$i.'][operator]', (int) $tag['operator']))
				->addValue(_('Contains'), MAINTENANCE_TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), MAINTENANCE_TAG_OPERATOR_EQUAL)
				->setModern(true)
				->setEnabled(false),
			(new CTextBox('tags['.$i.'][value]', $tag['value']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setEnabled(false),
			(new CCol(
				(new CButton('tags['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->setEnabled(false)
			))->addClass(ZBX_STYLE_NOWRAP)
		], 'form_row');
	}
	else {
		$tag_table->addRow([
			(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setReadonly(!$data['allowed_edit']),
			(new CRadioButtonList('tags['.$i.'][operator]', (int) $tag['operator']))
				->addValue(_('Contains'), MAINTENANCE_TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), MAINTENANCE_TAG_OPERATOR_EQUAL)
				->setModern(true)
				->setEnabled($data['allowed_edit']),
			(new CTextBox('tags['.$i.'][value]', $tag['value']))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setReadonly(!$data['allowed_edit']),
			(new CCol(
				(new CButton('tags['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->setEnabled($data['allowed_edit'])
			))->addClass(ZBX_STYLE_NOWRAP)
		], 'form_row');
	}

	$i++;
}

$tag_table->addRow(
	(new CCol(
		(new CButton('tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled($data['maintenance_type'] == MAINTENANCE_TYPE_NODATA ? false : $data['allowed_edit'])
	))->setColSpan(3)
);

$table
	->addRow(new CLabel(_('Host groups'), 'groupids__ms'),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['groups_ms'],
			'disabled' => !$data['allowed_edit'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $maintenance_form->getName(),
					'dstfld1' => 'groupids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Hosts'), 'hostids__ms'),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $data['hosts_ms'],
			'disabled' => !$data['allowed_edit'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $maintenance_form->getName(),
					'dstfld1' => 'hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$table
	->addRow('',
		(new CLabel(_('At least one host group or host must be selected.')))->setAsteriskMark()
	)
	->addRow(_('Tags'),
		(new CDiv($tag_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setReadonly(!$data['allowed_edit'])
	);

// Append tabs to form.
$maintenance_tab = (new CTabView())->addTab('maintenanceTab', _('Maintenance'), $table);

// append buttons to form
if (array_key_exists('maintenanceid', $data) && $data['maintenanceid']) {
	$maintenance_tab->setFooter(makeFormFooter(
		(new CSubmit('update', _('Update')))->setEnabled($data['allowed_edit']),
		[
			(new CSubmit('clone', _('Clone')))->setEnabled($data['allowed_edit']),
			(new CButtonDelete(_('Delete maintenance period?'), url_param('form').url_param('maintenanceid')))
				->setEnabled($data['allowed_edit']),
			new CButtonCancel()
		]
	));
}
else {
	$maintenance_tab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$maintenance_form->addItem($maintenance_tab);

$widget->addItem($maintenance_form);

$widget->show();
