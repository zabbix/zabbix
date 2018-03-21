<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CApiServiceFactory extends CRegistryFactory {

	public function __construct(array $objects = []) {
		parent::__construct(array_merge([
			// a generic API class
			'api' => 'CApiService',

			// specific API classes
			'action' => 'CAction',
			'alert' => 'CAlert',
			'apiinfo' => 'CAPIInfo',
			'application' => 'CApplication',
			'configuration' => 'CConfiguration',
			'correlation' => 'CCorrelation',
			'dashboard' => 'CDashboard',
			'dcheck' => 'CDCheck',
			'dhost' => 'CDHost',
			'discoveryrule' => 'CDiscoveryRule',
			'drule' => 'CDRule',
			'dservice' => 'CDService',
			'event' => 'CEvent',
			'graph' => 'CGraph',
			'graphitem' => 'CGraphItem',
			'graphprototype' => 'CGraphPrototype',
			'host' => 'CHost',
			'hostgroup' => 'CHostGroup',
			'hostprototype' => 'CHostPrototype',
			'history' => 'CHistory',
			'hostinterface' => 'CHostInterface',
			'httptest' => 'CHttpTest',
			'image' => 'CImage',
			'iconmap' => 'CIconMap',
			'item' => 'CItem',
			'itemprototype' => 'CItemPrototype',
			'maintenance' => 'CMaintenance',
			'map' => 'CMap',
			'mediatype' => 'CMediatype',
			'problem' => 'CProblem',
			'proxy' => 'CProxy',
			'service' => 'CService',
			'screen' => 'CScreen',
			'screenitem' => 'CScreenItem',
			'script' => 'CScript',
			'task' => 'CTask',
			'template' => 'CTemplate',
			'templatescreen' => 'CTemplateScreen',
			'templatescreenitem' => 'CTemplateScreenItem',
			'trend' => 'CTrend',
			'trigger' => 'CTrigger',
			'triggerprototype' => 'CTriggerPrototype',
			'user' => 'CUser',
			'usergroup' => 'CUserGroup',
			'usermacro' => 'CUserMacro',
			'valuemap' => 'CValueMap'
		], $objects));
	}
}
