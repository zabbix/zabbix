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
	}

	public function getKnownWidgetTypesWNames() {
		return $this->knownWidgetTypes;
	}

	public function getKnownWidgetTypes() {
		return array_keys($this->knownWidgetTypes);
	}

	public function saveConfig($widgetid, $fields) {
		// TODO VM: replace by call to API (when it will be ready)
		$fields = (new CJson())->encode($fields);
		CProfile::update('web.dashbrd.widget.'.$widgetid.'.fields', $fields, PROFILE_TYPE_STR);
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
}
