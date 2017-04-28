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
	private $knownWidgetTypes;
	private $rfRates;

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
			WIDGET_URL					=> 0,
		];
	}

	public function getKnownWidgetTypesWNames($user_type) {
		$known_widget_types = $this->knownWidgetTypes;

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

		return $known_widget_types;
	}

	public function getKnownWidgetTypes($user_type) {
		return array_keys($this->getKnownWidgetTypesWNames($user_type));
	}

	/**
	 * Save dashboard
	 * @param array $dashboard array with dashboard to save
	 *
	 * @return bool
	 */
	public function saveConfig($dashboard) {
		$result = (bool) API::Dashboard()->update([$dashboard]);
		return $result;
		// TODO VM: replace by call to API (when it will be ready)
//		$fields = (new CJson())->encode($fields);
//		CProfile::update('web.dashbrd.widget.'.$widgetid.'.fields', $fields, PROFILE_TYPE_STR);
	}

	public function getConfig($widgetid) {
		// TODO VM: replace by call to API (when it will be ready)
		$fields = CProfile::get('web.dashbrd.widget.'.$widgetid.'.fields', '');
		$res = (new CJson())->decode($fields, true);
		if (!is_array($res)) {
			$res = [];
		}
		return $res;
	}

	public function getAllWidgetConfig() {
		// TODO VM: replace by call to API (when it will be ready)
		// TODO VM: done clunky way, becuase API should be able to do it properly in one call
		$res = [];
		for ($i = 1; $i < 20; $i++) {
			$fields = CProfile::get('web.dashbrd.widget.'.$i.'.fields', '');
			if ($fields !== '') {
				$res[] = (new CJson())->decode($fields, true);
			}
		}
		return $res;
	}

	public function getDefaultRfRate($type) {
		return $this->rfRates[$type];
	}

	public function getForm($data, $user_type) {
		$known_widget_types = $this->getKnownWidgetTypesWNames($user_type);
		switch ($data['type']) {
			case WIDGET_CLOCK:
				return (new CClockWidgetForm($data, $known_widget_types));
			case WIDGET_URL:
				return (new CUrlWidgetForm($data, $known_widget_types));

			default:
				// TODO VM: delete this case after all widget forms will be created
				return (new CWidgetForm($data, $known_widget_types));
		}
	}
}
