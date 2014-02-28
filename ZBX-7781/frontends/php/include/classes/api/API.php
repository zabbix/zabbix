<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class API {

	/**
	 * API wrapper that all of the calls will go through.
	 *
	 * @var CApiWrapper
	 */
	private static $wrapper;

	/**
	 * A map of classes that should handle the corresponding API objects requests.
	 *
	 * @var array
	 */
	protected static $classMap = array(
		'action' => 'CAction',
		'alert' => 'CAlert',
		'apiinfo' => 'CAPIInfo',
		'application' => 'CApplication',
		'configuration' => 'CConfiguration',
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
		'image' => 'CImage',
		'iconmap' => 'CIconMap',
		'item' => 'CItem',
		'itemprototype' => 'CItemPrototype',
		'maintenance' => 'CMaintenance',
		'map' => 'CMap',
		'mediatype' => 'CMediatype',
		'proxy' => 'CProxy',
		'service' => 'CService',
		'screen' => 'CScreen',
		'screenitem' => 'CScreenItem',
		'script' => 'CScript',
		'template' => 'CTemplate',
		'templatescreen' => 'CTemplateScreen',
		'templatescreenitem' => 'CTemplateScreenItem',
		'trigger' => 'CTrigger',
		'triggerprototype' => 'CTriggerPrototype',
		'user' => 'CUser',
		'usergroup' => 'CUserGroup',
		'usermacro' => 'CUserMacro',
		'usermedia' => 'CUserMedia',
		'httptest' => 'CHttpTest'
	);

	/**
	 * Array of created CApiService objects.
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Sets the API wrapper.
	 *
	 * @param CApiWrapper $wrapper
	 */
	public static function setWrapper(CApiWrapper $wrapper = null) {
		self::$wrapper = $wrapper;
	}

	/**
	 * Returns the API wrapper.
	 *
	 * @return CApiWrapper
	 */
	public static function getWrapper() {
		return self::$wrapper;
	}

	/**
	 * Returns an object that can be used for making API calls.  If a wrapper is used, returns a CApiWrapper,
	 * otherwise - returns a CApiService object.
	 *
	 * @param $name
	 *
	 * @return CApiWrapper|CApiService
	 */
	public static function getApi($name) {
		if (self::$wrapper) {
			self::$wrapper->getClient()->api = $name;

			return self::$wrapper;
		}
		else {
			return self::getApiService($name);
		}
	}

	/**
	 * Returns the CApiInstance object for the requested API.
	 *
	 * NOTE: This method must only be called from other CApiService objects.
	 *
	 * @param string $name
	 *
	 * @return CApiService
	 */
	public static function getApiService($name = null) {
		if ($name) {
			$className = self::getServiceClassName($name);
			if (!isset(self::$instances[$name])) {
				self::$instances[$name] = new $className;
			}

			return self::$instances[$name];
		}
		else {
			if (!isset(self::$instances[0])) {
				self::$instances[0] = new CApiService();
			}

			return self::$instances[0];
		}
	}

	/**
	 * Returns the class name for the given API. The name of the object should not be case sensitive.
	 *
	 * @static
	 *
	 * @param string $object
	 *
	 * @return string
	 */
	protected static function getServiceClassName($object) {
		return self::$classMap[strtolower($object)];
	}

	/**
	 * @return CAction
	 */
	public static function Action() {
		return self::getApi('action');
	}

	/**
	 * @return CAlert
	 */
	public static function Alert() {
		return self::getApi('alert');
	}

	/**
	 * @return CAPIInfo
	 */
	public static function APIInfo() {
		return self::getApi('apiinfo');
	}

	/**
	 * @return CApplication
	 */
	public static function Application() {
		return self::getApi('application');
	}

	/**
	 * @return CConfiguration
	 */
	public static function Configuration() {
		return self::getApi('configuration');
	}

	/**
	 * @return CDCheck
	 */
	public static function DCheck() {
		return self::getApi('dcheck');
	}

	/**
	 * @return CDHost
	 */
	public static function DHost() {
		return self::getApi('dhost');
	}

	/**
	 * @return CDiscoveryRule
	 */
	public static function DiscoveryRule() {
		return self::getApi('discoveryrule');
	}

	/**
	 * @return CDRule
	 */
	public static function DRule() {
		return self::getApi('drule');
	}

	/**
	 * @return CDService
	 */
	public static function DService() {
		return self::getApi('dservice');
	}

	/**
	 * @return CEvent
	 */
	public static function Event() {
		return self::getApi('event');
	}

	/**
	 * @return CGraph
	 */
	public static function Graph() {
		return self::getApi('graph');
	}

	/**
	 * @return CGraphItem
	 */
	public static function GraphItem() {
		return self::getApi('graphitem');
	}

	/**
	 * @return CGraphPrototype
	 */
	public static function GraphPrototype() {
		return self::getApi('graphprototype');
	}

	/**
	 * @return CHistory
	 */
	public static function History() {
		return self::getApi('history');
	}

	/**
	 * @return CHost
	 */
	public static function Host() {
		return self::getApi('host');
	}

	/**
	 * @return CHostPrototype
	 */
	public static function HostPrototype() {
		return self::getApi('hostprototype');
	}

	/**
	 * @return CHostGroup
	 */
	public static function HostGroup() {
		return self::getApi('hostgroup');
	}

	/**
	 * @return CHostInterface
	 */
	public static function HostInterface() {
		return self::getApi('hostinterface');
	}

	/**
	 * @return CImage
	 */
	public static function Image() {
		return self::getApi('image');
	}

	/**
	 * @return CIconMap
	 */
	public static function IconMap() {
		return self::getApi('iconmap');
	}

	/**
	 * @return CItem
	 */
	public static function Item() {
		return self::getApi('item');
	}

	/**
	 * @return CItemPrototype
	 */
	public static function ItemPrototype() {
		return self::getApi('itemprototype');
	}

	/**
	 * @return CMaintenance
	 */
	public static function Maintenance() {
		return self::getApi('maintenance');
	}

	/**
	 * @return CMap
	 */
	public static function Map() {
		return self::getApi('map');
	}

	/**
	 * @return CMediaType
	 */
	public static function MediaType() {
		return self::getApi('mediatype');
	}

	/**
	 * @return CProxy
	 */
	public static function Proxy() {
		return self::getApi('proxy');
	}

	/**
	 * @return CService
	 */
	public static function Service() {
		return self::getApi('service');
	}

	/**
	 * @return CScreen
	 */
	public static function Screen() {
		return self::getApi('screen');
	}

	/**
	 * @return CScreenItem
	 */
	public static function ScreenItem() {
		return self::getApi('screenitem');
	}

	/**
	 * @return CScript
	 */
	public static function Script() {
		return self::getApi('script');
	}

	/**
	 * @return CTemplate
	 */
	public static function Template() {
		return self::getApi('template');
	}

	/**
	 * @return CTemplateScreen
	 */
	public static function TemplateScreen() {
		return self::getApi('templatescreen');
	}

	/**
	 * @return CTemplateScreenItem
	 */
	public static function TemplateScreenItem() {
		return self::getApi('templatescreenitem');
	}

	/**
	 * @return CTrigger
	 */
	public static function Trigger() {
		return self::getApi('trigger');
	}

	/**
	 * @return CTriggerPrototype
	 */
	public static function TriggerPrototype() {
		return self::getApi('triggerprototype');
	}

	/**
	 * @return CUser
	 */
	public static function User() {
		return self::getApi('user');
	}

	/**
	 * @return CUserGroup
	 */
	public static function UserGroup() {
		return self::getApi('usergroup');
	}

	/**
	 * @return CUserMacro
	 */
	public static function UserMacro() {
		return self::getApi('usermacro');
	}

	/**
	 * @return CUserMedia
	 */
	public static function UserMedia() {
		return self::getApi('usermedia');
	}

	/**
	 * @return CHttpTest
	 */
	public static function HttpTest() {
		return self::getApi('httptest');
	}
}
