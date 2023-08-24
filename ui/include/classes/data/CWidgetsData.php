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

class CWidgetsData {

	/**
	 * Get data types supported by widget communication framework out of the box.
	 *
	 * @return array[]
	 */
	public static function getDataTypes() {
		static $data_types;

		if ($data_types === null) {
			$data_types = [
				'_hostgroupid' => [
					'field_class' => CWidgetFieldMultiSelectGroup::class,
					'label' => _('Host group'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_hostgroupids' => [
					'field_class' => CWidgetFieldMultiSelectGroup::class,
					'label' => _('Host groups'),
					'is_multiple' => true,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_hostid' => [
					'field_class' => CWidgetFieldMultiSelectHost::class,
					'label' => _('Host'),
					'is_multiple' => false,
					'accepts_dashboard_host' => true,
					'accepts_dashboard_time_period' => false
				],
				'_hostids' => [
					'field_class' => CWidgetFieldMultiSelectHost::class,
					'label' => _('Hosts'),
					'is_multiple' => true,
					'accepts_dashboard_host' => true,
					'accepts_dashboard_time_period' => false
				],
				'_itemid' => [
					'field_class' => CWidgetFieldMultiSelectItem::class,
					'label' => _('Item'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_itemprototypeid' => [
					'field_class' => CWidgetFieldMultiSelectItemPrototype::class,
					'label' => _('Item prototype'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_graphid' => [
					'field_class' => CWidgetFieldMultiSelectGraph::class,
					'label' => _('Graph'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_graphprototypeid' => [
					'field_class' => CWidgetFieldMultiSelectGraphPrototype::class,
					'label' => _('Graph prototype'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_mapid' => [
					'field_class' => CWidgetFieldMultiSelectMap::class,
					'label' => _('Map'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_serviceid' => [
					'field_class' => CWidgetFieldMultiSelectService::class,
					'label' => _('Service'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				],
				'_slaid' => [
					'field_class' => CWidgetFieldMultiSelectSla::class,
					'label' => _('SLA'),
					'is_multiple' => false,
					'accepts_dashboard_host' => false,
					'accepts_dashboard_time_period' => false
				]
			];
		}

		return $data_types;
	}
}
