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


namespace Modules\TestBroadcaster\Includes;

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
	CWidgetFieldMultiSelectSla
};

/**
 * Test Broadcaster widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				new CWidgetFieldMultiSelectItem('itemids', _('Items'))
			)
			->addField(
				new CWidgetFieldMultiSelectItemPrototype('prototype_itemids', _('Item prototypes'))
			)
			->addField(
				new CWidgetFieldMultiSelectGraph('graphids', _('Graphs'))
			)
			->addField(
				new CWidgetFieldMultiSelectGraphPrototype('prototype_graphids', _('Graph prototypes'))
			)
			->addField(
				new CWidgetFieldMultiSelectMap('sysmapids', _('Maps'))
			)
			->addField(
				new CWidgetFieldMultiSelectService('serviceids', _('Services'))
			)
			->addField(
				new CWidgetFieldMultiSelectSla('slaids', _('SLAs'))
			);
	}
}
