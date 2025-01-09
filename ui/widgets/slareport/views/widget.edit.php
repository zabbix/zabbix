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
 * SLA report widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectSlaView($data['fields']['slaid'])
	)
	->addField(
		new CWidgetFieldMultiSelectServiceView($data['fields']['serviceid'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_periods'])
	)
	->addField(
		(new CWidgetFieldTimePeriodView($data['fields']['date_period']))
			->setDateFormat(ZBX_DATE)
			->setFromPlaceholder(_('YYYY-MM-DD'))
			->setToPlaceholder(_('YYYY-MM-DD'))
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_slareport_form.init('.json_encode([
		'serviceid_field_id' => $data['fields']['serviceid']->getName()
	], JSON_THROW_ON_ERROR).');')
	->show();
