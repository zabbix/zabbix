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
	CWidgetFieldMultiSelectSla,
	CWidgetFieldTimePeriod
};

class CWidgetsData {

	public const DATA_TYPE_HOST_GROUP_ID =		'_hostgroupid';
	public const DATA_TYPE_HOST_GROUP_IDS =		'_hostgroupids';
	public const DATA_TYPE_HOST_ID =			'_hostid';
	public const DATA_TYPE_HOST_IDS =			'_hostids';
	public const DATA_TYPE_ITEM_ID =			'_itemid';
	public const DATA_TYPE_ITEM_PROTOTYPE_ID =	'_itemprototypeid';
	public const DATA_TYPE_GRAPH_ID =			'_graphid';
	public const DATA_TYPE_GRAPH_PROTOTYPE_ID =	'_graphprototypeid';
	public const DATA_TYPE_MAP_ID =				'_mapid';
	public const DATA_TYPE_SERVICE_ID =			'_serviceid';
	public const DATA_TYPE_SLA_ID =				'_slaid';
	public const DATA_TYPE_TIME_PERIOD =		'_timeperiod';

	/**
	 * Get data types supported by widget communication framework out of the box.
	 *
	 * @return array[]
	 */
	public static function getDataTypes(): array {
		static $data_types;

		if ($data_types === null) {
			$data_types = [
				self::DATA_TYPE_HOST_GROUP_ID => [
					'field_class' => CWidgetFieldMultiSelectGroup::class,
					'label' => _('Host group'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_HOST_GROUP_IDS => [
					'field_class' => CWidgetFieldMultiSelectGroup::class,
					'label' => _('Host groups'),
					'is_multiple' => true,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_HOST_ID => [
					'field_class' => CWidgetFieldMultiSelectHost::class,
					'label' => _('Host'),
					'is_multiple' => false,
					'accepts_dashboard' => true
				],
				self::DATA_TYPE_HOST_IDS => [
					'field_class' => CWidgetFieldMultiSelectHost::class,
					'label' => _('Hosts'),
					'is_multiple' => true,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_ITEM_ID => [
					'field_class' => CWidgetFieldMultiSelectItem::class,
					'label' => _('Item'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_ITEM_PROTOTYPE_ID => [
					'field_class' => CWidgetFieldMultiSelectItemPrototype::class,
					'label' => _('Item prototype'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_GRAPH_ID => [
					'field_class' => CWidgetFieldMultiSelectGraph::class,
					'label' => _('Graph'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_GRAPH_PROTOTYPE_ID => [
					'field_class' => CWidgetFieldMultiSelectGraphPrototype::class,
					'label' => _('Graph prototype'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_MAP_ID => [
					'field_class' => CWidgetFieldMultiSelectMap::class,
					'label' => _('Map'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_SERVICE_ID => [
					'field_class' => CWidgetFieldMultiSelectService::class,
					'label' => _('Service'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_SLA_ID => [
					'field_class' => CWidgetFieldMultiSelectSla::class,
					'label' => _('SLA'),
					'is_multiple' => false,
					'accepts_dashboard' => false
				],
				self::DATA_TYPE_TIME_PERIOD => [
					'field_class' => CWidgetFieldTimePeriod::class,
					'label' => _('Time period'),
					'is_multiple' => false,
					'accepts_dashboard' => true
				]
			];
		}

		return $data_types;
	}
}
