<?php declare(strict_types = 0);
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
 * @var CPartial $this
 * @var array    $data
 */


$preprocessing = [];

foreach ($data['preprocessing'] as $step) {
	$preprocessing[] = $step + [
		'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
		'error_handler_params' => ''
	];
}

$formgrid = (new CFormGrid())
	->setId('item_preproc_list')
	->addItem([
		new CLabel([
			_('Preprocessing steps'),
			makeHelpIcon([
				_('Preprocessing is a transformation before saving the value to the database. It is possible to define a sequence of preprocessing steps, and those are executed in the order they are set.'),
				BR(), BR(),
				_('However, if "Check for not supported value" steps are configured, they are always placed and executed first (with "any error" being the last of them).')
			])
		]),
		new CFormField(getItemPreprocessing($preprocessing, $data['readonly'], $data['preprocessing_types']))
	])
	->addItem([
		(new CLabel(_('Type of information'), 'label-value-type-steps'))
			->addClass('js-item-preprocessing-type'),
		(new CFormField((new CSelect('value_type_steps'))
			->setFocusableElementId('label-value-type-steps')
			->setValue($data['item']['value_type'])
			->addOptions(CSelect::createOptionsFromArray($data['value_types']))
			->setReadonly($data['readonly'])
		))->addClass('js-item-preprocessing-type')
	]);

$formgrid->show();
