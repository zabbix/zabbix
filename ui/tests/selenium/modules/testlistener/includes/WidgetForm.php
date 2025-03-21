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


namespace Modules\TestListener\Includes;

use CWidgetsData;

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectGraph,
	CWidgetFieldMultiSelectGraphPrototype,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectItemPrototype,
	CWidgetFieldMultiSelectMap,
	CWidgetFieldMultiSelectService,
	CWidgetFieldMultiSelectSla,
	CWidgetFieldTimePeriod
};

/**
 * Test Listener widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectGroup('groupid', _('Host group')))
					->setInType(CWidgetsData::DATA_TYPE_HOST_GROUP_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectGroup('groupids', _('Host groups')))
					->setInType(CWidgetsData::DATA_TYPE_HOST_GROUP_IDS)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectHost('hostid', _('Host')))
					->setInType(CWidgetsData::DATA_TYPE_HOST_ID)
					->preventDefault()
					->acceptDashboard(false)
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectHost('hostids', _('Hosts')))
					->setInType(CWidgetsData::DATA_TYPE_HOST_IDS)
					->preventDefault()
					->acceptDashboard(false)
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setInType(CWidgetsData::DATA_TYPE_ITEM_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectItem('itemids', _('Items')))
					->setInType(CWidgetsData::DATA_TYPE_ITEM_IDS)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectItemPrototype('prototype_itemid', _('Item prototype')))
					->setInType(CWidgetsData::DATA_TYPE_ITEM_PROTOTYPE_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectGraph('graphid', _('Graph')))
					->setInType(CWidgetsData::DATA_TYPE_GRAPH_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectGraphPrototype('prototype_graphid', _('Graph prototype')))
					->setInType(CWidgetsData::DATA_TYPE_GRAPH_PROTOTYPE_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectMap('sysmapid', _('Map')))
					->setInType(CWidgetsData::DATA_TYPE_MAP_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectService('serviceid', _('Service')))
					->setInType(CWidgetsData::DATA_TYPE_SERVICE_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldMultiSelectSla('slaid', _('SLA')))
					->setInType(CWidgetsData::DATA_TYPE_SLA_ID)
					->preventDefault()
					->acceptWidget()
			)
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
					->acceptWidget()
			);
	}
}
