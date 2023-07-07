<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$formgrid = (new CFormGrid())
	->setId('item_preproc_list')
	->addItem([
		new CLabel(_('Preprocessing steps'), 'preprocessing'),
		new CFormField(
			getItemPreprocessing($data['preprocessing'], $data['readonly'], $data['preprocessing_types'])
		)
	])
	->addItem([
		(new CLabel(_('Type of information'), 'label-value-type-steps'))
			->addClass('js-item-preprocessing-type'),
		(new CFormField((new CSelect('value_type_steps'))
			->setFocusableElementId('label-value-type-steps')
			->setValue($data['form']['value_type'])
			->addOptions(CSelect::createOptionsFromArray($data['value_types']))
			->setReadonly($data['readonly'])
		))->addClass('js-item-preprocessing-type')
	]);

$formgrid->show();
