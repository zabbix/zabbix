<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CWidgetConfig
{
	/**
	 * Return list of all widget types with names.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getKnownWidgetTypes() {
		return [
			WIDGET_SYSTEM_STATUS		=> _('System status'),
			WIDGET_ZABBIX_STATUS		=> _('Status of Zabbix'),
			WIDGET_LAST_ISSUES			=> _('Last issues'),
			WIDGET_WEB_OVERVIEW			=> _('Web monitoring'),
			WIDGET_DISCOVERY_STATUS		=> _('Discovery status'),
			WIDGET_HOST_STATUS			=> _('Host status'),
			WIDGET_FAVOURITE_GRAPHS		=> _('Favourite graphs'),
			WIDGET_FAVOURITE_MAPS		=> _('Favourite maps'),
			WIDGET_FAVOURITE_SCREENS	=> _('Favourite screens'),
			WIDGET_CLOCK				=> _('Clock'),
			WIDGET_SYSMAP				=> _('Map'),
			WIDGET_NAVIGATION_TREE	=> _('Map Navigation Tree'),
			WIDGET_URL					=> _('URL')
		];

		$this->rfRates = [
			WIDGET_SYSTEM_STATUS		=> SEC_PER_MIN,
			WIDGET_ZABBIX_STATUS		=> 15 * SEC_PER_MIN,
			WIDGET_LAST_ISSUES			=> SEC_PER_MIN,
			WIDGET_WEB_OVERVIEW			=> SEC_PER_MIN,
			WIDGET_DISCOVERY_STATUS		=> SEC_PER_MIN,
			WIDGET_HOST_STATUS			=> SEC_PER_MIN,
			WIDGET_FAVOURITE_GRAPHS		=> 15 * SEC_PER_MIN,
			WIDGET_FAVOURITE_MAPS		=> 15 * SEC_PER_MIN,
			WIDGET_FAVOURITE_SCREENS	=> 15 * SEC_PER_MIN,
			WIDGET_CLOCK				=> 15 * SEC_PER_MIN,
			WIDGET_SYSMAP				=> 15 * SEC_PER_MIN,
			WIDGET_NAVIGATION_TREE => 15 * SEC_PER_MIN,
			WIDGET_URL					=> 0,
		];

		$this->apiFieldKeys = [
			ZBX_WIDGET_FIELD_TYPE_INT32				=> 'value_int',
			ZBX_WIDGET_FIELD_TYPE_STR				=> 'value_str',
			ZBX_WIDGET_FIELD_TYPE_GROUP				=> 'value_groupid',
			ZBX_WIDGET_FIELD_TYPE_HOST				=> 'value_hostid',
			ZBX_WIDGET_FIELD_TYPE_ITEM				=> 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE	=> 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH				=> 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE	=> 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_MAP				=> 'value_sysmapid',
			ZBX_WIDGET_FIELD_TYPE_DASHBOARD			=> 'value_dashboardid'
		];
	}

	/**
	 * Return default refresh rate for widget type.
	 *
	 * @static
	 *
	 * @param int $type  WIDGET_ constant
	 *
	 * @return int  default refresh rate, "0" for no refresh
	 */
	public static function getDefaultRfRate($type) {
		switch ($type) {
			case WIDGET_SYSTEM_STATUS:
			case WIDGET_LAST_ISSUES:
			case WIDGET_WEB_OVERVIEW:
			case WIDGET_DISCOVERY_STATUS:
			case WIDGET_HOST_STATUS:
				return SEC_PER_MIN;

			case WIDGET_ZABBIX_STATUS:
			case WIDGET_FAVOURITE_GRAPHS:
			case WIDGET_FAVOURITE_MAPS:
			case WIDGET_FAVOURITE_SCREENS:
			case WIDGET_CLOCK:
			case WIDGET_SYSMAP:
				return 15 * SEC_PER_MIN;

			case WIDGET_URL:
				return 0;
		}
	}

	/**
	 * Return widget triggers
	 * @param int $type - WIDGET_ constant
	 * @return array - list of [onEvent => jsMethodToCall] pairs
	 */
	public static function getTriggers($type) {
		switch ($type) {
			case WIDGET_NAVIGATION_TREE:
				return [
					'onEditStart' => 'zbx_navtree("onEditStart")',
					'beforeDashboardSave' => 'zbx_navtree("beforeDashboardSave")',
					'afterDashboardSave' => 'zbx_navtree("afterDashboardSave")',
					'onEditStop' => 'zbx_navtree("onEditStop")',
					'afterDashboardSave' => 'zbx_navtree("afterDashboardSave")',
					'beforeConfigLoad' => 'zbx_navtree("beforeConfigLoad")'
				];
				break;
			default:
				return [];
		}
	}

	/**
	 * Returns key, where value is stored for given field type
	 * @param int $field_type - ZBX_WIDGET_FIELD_TYPE_ constant
	 * @return string field key, where to save the value
	 */
	public static function getApiFieldKey($field_type){
		switch ($field_type) {
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				return 'value_int';

			case ZBX_WIDGET_FIELD_TYPE_STR:
				return 'value_str';

			case ZBX_WIDGET_FIELD_TYPE_GROUP:
				return 'value_groupid';

			case ZBX_WIDGET_FIELD_TYPE_HOST:
				return 'value_hostid';

			case ZBX_WIDGET_FIELD_TYPE_ITEM:
				return 'value_itemid';

			case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
				return 'value_itemid';

			case ZBX_WIDGET_FIELD_TYPE_GRAPH:
				return 'value_graphid';

			case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
				return 'value_graphid';

			case ZBX_WIDGET_FIELD_TYPE_MAP:
				return 'value_sysmapid';

			case ZBX_WIDGET_FIELD_TYPE_DASHBOARD:
				return 'value_dashboardid';
		}
	}

	/**
	 * Return Form object for widget with provided data.
	 *
	 * @static
	 *
	 * @param array  $data          array with all widget's fields, including widget type
	 * @param string $data['type']
	 * @param string $data[<name>]  (optional)
	 *
	 * @return CWidgetForm
	 */
	public static function getForm($data) {
		switch ($data['type']) {
			case WIDGET_CLOCK:
				return new CClockWidgetForm($data);

			case WIDGET_NAVIGATION_TREE:
				return (new CNavigationWidgetForm($data));
			case WIDGET_SYSMAP:
				return (new CSysmapWidgetForm($data));
			case WIDGET_URL:
				return new CUrlWidgetForm($data);

			default:
				// TODO VM: delete this case after all widget forms will be created
				return new CWidgetForm($data);
		}
	}
}
