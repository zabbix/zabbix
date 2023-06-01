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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * SLA report widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectSlaView($data['fields']['slaid'], $data['captions']['slas']['slaid'])
	)
	->addField(
		new CWidgetFieldMultiSelectServiceView($data['fields']['serviceid'], $data['captions']['services']['serviceid'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_periods'])
	)
	->addField(
		(new CWidgetFieldDatePickerView($data['fields']['date_from']))
			->setDateFormat(ZBX_DATE)
			->setPlaceholder(_('YYYY-MM-DD'))
	)
	->addField(
		(new CWidgetFieldDatePickerView($data['fields']['date_to']))
			->setDateFormat(ZBX_DATE)
			->setPlaceholder(_('YYYY-MM-DD'))
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_slareport_form.init('.json_encode([
		'serviceid_field_id' => $data['fields']['serviceid']->getName()
	], JSON_THROW_ON_ERROR).');')
	->show();
