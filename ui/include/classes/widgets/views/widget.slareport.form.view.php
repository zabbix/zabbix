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
$field_slaids = CWidgetHelper::getSla($fields['slaids'], $data['captions']['ms']['slas']['slaids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['slaids']), $field_slaids);
$scripts[] = $field_slaids->getPostJS();

// Services.
$field_serviceids = CWidgetHelper::getService($fields['serviceids'], $data['captions']['ms']['services']['serviceids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['serviceids']), $field_serviceids);
$scripts[] = $field_serviceids->getPostJS();

// Show periods.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['show_periods']),
	CWidgetHelper::getNumericBox($fields['show_periods']),
	'js-show_periods'
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
			'serviceids_field_id' => $fields['serviceids']->getName()
		]).');
	'))->setOnDocumentReady()
);

return [
	'form' => $form,
	'scripts' => $scripts
];
