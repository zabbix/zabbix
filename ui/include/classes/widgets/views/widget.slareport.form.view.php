<?php declare(strict_types = 0);
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
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.slareport.form.view.js.php')];

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

// SLA.
$field_slaid = CWidgetHelper::getSla($fields['slaid'], $data['captions']['ms']['slas']['slaid'],
	$form->getName()
);
$form_grid->addItem([
	CWidgetHelper::getMultiselectLabel($fields['slaid']),
	new CFormField($field_slaid)
]);
$scripts[] = $field_slaid->getPostJS();

// Service.
$field_serviceid = CWidgetHelper::getService($fields['serviceid'], $data['captions']['ms']['services']['serviceid'],
	$form->getName()
);
$form_grid->addItem([
	CWidgetHelper::getMultiselectLabel($fields['serviceid']),
	new CFormField($field_serviceid)
]);
$scripts[] = $field_serviceid->getPostJS();

// Show periods.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show_periods']),
	new CFormField(CWidgetHelper::getIntegerBox($fields['show_periods']))
]);

// From.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['date_from']),
	new CFormField(
		CWidgetHelper::getDatePicker($fields['date_from'])
			->setDateFormat(ZBX_DATE)
			->setPlaceholder(_('YYYY-MM-DD'))
	)
]);

// To.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['date_to']),
	new CFormField(
		CWidgetHelper::getDatePicker($fields['date_to'])
			->setDateFormat(ZBX_DATE)
			->setPlaceholder(_('YYYY-MM-DD'))
	)
]);

$form->addItem($form_grid);

$scripts[] = '
	widget_slareport_form.init('.json_encode([
		'serviceid_field_id' => $fields['serviceid']->getName(),
		'serviceid_multiple' => $fields['serviceid']->isMultiple()
	]).');
';

return [
	'form' => $form,
	'scripts' => $scripts
];
