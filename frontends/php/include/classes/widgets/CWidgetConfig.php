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
	// TODO VM: (?) maybe better to convert all functions to static ones
	private $knownWidgetTypes;
	private $rfRates;
	private $apiFieldKeys;

	public function __construct() {
		$this->knownWidgetTypes = [
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
			WIDGET_URL					=> _('URL'),
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
	 * Return list of all widget types with names
	 * @param int $user_type - USER_TYPE_ZABBIX_, if not passed, all widget types will be returned
	 * @return array
	 */
	public function getKnownWidgetTypesWNames($user_type = null) {
		$known_widget_types = $this->knownWidgetTypes;

		// Remove widget types, user can't create
		if ($user_type !== null) {
			$show_discovery_widget = ($user_type >= USER_TYPE_ZABBIX_ADMIN && (bool) API::DRule()->get([
				'output' => [],
				'filter' => ['status' => DRULE_STATUS_ACTIVE],
				'limit' => 1
			]));
			if (!$show_discovery_widget) {
				unset($known_widget_types[WIDGET_DISCOVERY_STATUS]);
			}

			$show_status_widget = ($user_type == USER_TYPE_SUPER_ADMIN);
			if (!$show_status_widget) {
				unset($known_widget_types[WIDGET_ZABBIX_STATUS]);
			}
		}

		return $known_widget_types;
	}

	/**
	 * Return list of all widget types
	 * @param int $user_type - USER_TYPE_ZABBIX_, if not passed, all widget types will be returned
	 * @return array
	 */
	public function getKnownWidgetTypes($user_type = null) {
		return array_keys($this->getKnownWidgetTypesWNames($user_type));
	}

	/**
	 * Return default refresh rate for widget type
	 * @param int $type - WIDGET_ constant
	 * @return int default refresh rate, "0" for no refresh
	 */
	public function getDefaultRfRate($type) {
		return $this->rfRates[$type];
	}

	/**
	 * Returns key, where value is stored for given field type
	 * @param int $field_type - ZBX_WIDGET_FIELD_TYPE_ constant
	 * @return string field key, where to save the value
	 */
	public function getApiFieldKey($field_type){
		return $this->apiFieldKeys[$field_type];
	}

	/**
	 * Return Form object for widget with provided data
	 * @param array $data - array with all widget's fields, including widget type and position
	 * @param int $user_type - USER_TYPE_ZABBIX_ constant, if null or not passed, will accept all widget types
	 * @return CWidgetForm
	 */
	public function getForm($data, $user_type = null) {
		$known_widget_types = $this->getKnownWidgetTypesWNames($user_type);
		switch ($data['type']) {
			case WIDGET_CLOCK:
				return (new CClockWidgetForm($data, $known_widget_types));
			case WIDGET_SYSMAP:
				return (new CSysmapWidgetForm($data, $known_widget_types));
			case WIDGET_URL:
				return (new CUrlWidgetForm($data, $known_widget_types));

			default:
				// TODO VM: delete this case after all widget forms will be created
				return (new CWidgetForm($data, $known_widget_types));
		}
	}
}
