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
 * Test Listener widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectGroupView($data['fields']['groupid'])
	)
	->addField(
		new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	)
	->addField(
		new CWidgetFieldMultiSelectHostView($data['fields']['hostid'])
	)
	->addField(
		new CWidgetFieldMultiSelectHostView($data['fields']['hostids'])
	)
	->addField(
		new CWidgetFieldMultiSelectItemView($data['fields']['itemid'])
	)
	->addField(
		new CWidgetFieldMultiSelectItemView($data['fields']['itemids'])
	)
	->addField(
		new CWidgetFieldMultiSelectItemPrototypeView($data['fields']['prototype_itemid'])
	)
	->addField(
		new CWidgetFieldMultiSelectGraphView($data['fields']['graphid'])
	)
	->addField(
		new CWidgetFieldMultiSelectGraphPrototypeView($data['fields']['prototype_graphid'])
	)
	->addField(
		new CWidgetFieldMultiSelectMapView($data['fields']['sysmapid'])
	)
	->addField(
		(new CWidgetFieldMultiSelectServiceView($data['fields']['serviceid']))
	)
	->addField(
		new CWidgetFieldMultiSelectSlaView($data['fields']['slaid'])
	)
	->addField(
		new CWidgetFieldTimePeriodView($data['fields']['time_period'])
	)
	->show();
