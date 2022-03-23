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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * SLA report widget form view.
 *
 * @var $data array
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.slareport.form.view.js.php')];

// SLA.
$field_slaid = CWidgetHelper::getSla($fields['slaid'], $data['captions']['ms']['slas']['slaid'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['slaid']), $field_slaid);
$scripts[] = $field_slaid->getPostJS();

// Services.
$field_serviceid = CWidgetHelper::getService($fields['serviceid'], $data['captions']['ms']['services']['serviceid'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['serviceid']), $field_serviceid);
$scripts[] = $field_serviceid->getPostJS();

// Show periods.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['show_periods']),
	CWidgetHelper::getIntegerBox($fields['show_periods'])
);

// Date from.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['date_from']),
	CWidgetHelper::getDatePicker($fields['date_from'])
		->setDateFormat(ZBX_DATE)
		->setPlaceholder(_('YYYY-MM-DD'))
);

// Date to.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['date_to']),
	CWidgetHelper::getDatePicker($fields['date_to'])
		->setDateFormat(ZBX_DATE)
		->setPlaceholder(_('YYYY-MM-DD'))
);

$form->addItem($form_list);

$form->addItem(
	(new CScriptTag('
		widget_slareport.init('.json_encode([
			'serviceid_field_id' => $fields['serviceid']->getName(),
			'serviceid_multiple' => $fields['serviceid']->isMultiple()
		]).');
	'))->setOnDocumentReady()
);

return [
	'form' => $form,
	'scripts' => $scripts
];
