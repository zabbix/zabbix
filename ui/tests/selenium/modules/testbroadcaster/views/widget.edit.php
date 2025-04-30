<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Test Broadcaster widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$service_field_view = new CWidgetFieldMultiSelectServiceView($data['fields']['serviceids']);

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	)
	->addField(
		new CWidgetFieldMultiSelectHostView($data['fields']['hostids'])
	)
	->addField(
		new CWidgetFieldMultiSelectItemView($data['fields']['itemids'])
	)
	->addField(
		new CWidgetFieldMultiSelectItemPrototypeView($data['fields']['prototype_itemids'])
	)
	->addField(
		new CWidgetFieldMultiSelectGraphView($data['fields']['graphids'])
	)
	->addField(
		new CWidgetFieldMultiSelectGraphPrototypeView($data['fields']['prototype_graphids'])
	)
	->addField(
		new CWidgetFieldMultiSelectMapView($data['fields']['sysmapids'])
	)
	->addField($service_field_view)
	->addField(
		new CWidgetFieldMultiSelectSlaView($data['fields']['slaids'])
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('
		widget_testbroadcaster_form.init('.json_encode([
			'serviceid_field_id' => $service_field_view->getId()
		]).');
	')
	->show();
