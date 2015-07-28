<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	const RETURN_TYPE_API = 'api';
	const RETURN_TYPE_RPC = 'rpc';

	private static $APIobjects = array();
	private static $RPCobjects = array();
	private static $return = self::RETURN_TYPE_RPC;

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
		'httptest' => 'CHttpTest',
		'webcheck' => 'CHttpTest'
	);

	/**
	 * Returns the class name for the given API object. The name of the object should not be case sensitive.
	 *
	 * @static
	 *
	 * @param string $object
	 *
	 * @return string
	 */
	public static function getObjectClassName($object) {
		return self::$classMap[strtolower($object)];
	}

	public static function setReturnAPI() {
		self::$return = self::RETURN_TYPE_API;
	}

	public static function setReturnRPC() {
		self::$return = self::RETURN_TYPE_RPC;
	}

	/**
	 * Returns an instance of the CZBXAPI class, that's responsible for handling the given
	 * API objects requests.
	 *
	 * @static
	 *
	 * @param $object
	 *
	 * @return CZBXAPI
	 */
	public static function getApi($object = null) {
		if ($object) {
			$className = self::getObjectClassName($object);
			if (!isset(self::$APIobjects[$object])) {
				self::$APIobjects[$object] = new $className;
			}

			return self::$APIobjects[$object];
		}
		else {
			if (!isset(self::$APIobjects[0])) {
				self::$APIobjects[0] = new CZBXAPI();
			}

			return self::$APIobjects[0];
		}
	}

	private static function getRpc($className) {
		if (!isset(self::$RPCobjects[$className])) {
			self::$RPCobjects[$className] = new CAPIObject($className);
		}

		return self::$RPCobjects[$className];
	}

	public static function getObject($className) {
		return (self::$return == self::RETURN_TYPE_API) ? self::getApi($className) : self::getRpc($className);
	}

	/**
	 * @return CAction
	 */
	public static function Action() {
		return self::getObject('action');
	}

	/**
	 * @return CAlert
	 */
	public static function Alert() {
		return self::getObject('alert');
	}

	/**
	 * @return CAPIInfo
	 */
	public static function APIInfo() {
		return self::getObject('apiinfo');
	}

	/**
	 * @return CApplication
	 */
	public static function Application() {
		return self::getObject('application');
	}

	/**
	 * @return CConfiguration
	 */
	public static function Configuration() {
		return self::getObject('configuration');
	}

	/**
	 * @return CDCheck
	 */
	public static function DCheck() {
		return self::getObject('dcheck');
	}

	/**
	 * @return CDHost
	 */
	public static function DHost() {
		return self::getObject('dhost');
	}

	/**
	 * @return CDiscoveryRule
	 */
	public static function DiscoveryRule() {
		return self::getObject('discoveryrule');
	}

	/**
	 * @return CDRule
	 */
	public static function DRule() {
		return self::getObject('drule');
	}

	/**
	 * @return CDService
	 */
	public static function DService() {
		return self::getObject('dservice');
	}

	/**
	 * @return CEvent
	 */
	public static function Event() {
		return self::getObject('event');
	}

	/**
	 * @return CGraph
	 */
	public static function Graph() {
		return self::getObject('graph');
	}

	/**
	 * @return CGraphItem
	 */
	public static function GraphItem() {
		return self::getObject('graphitem');
	}

	/**
	 * @return CGraphPrototype
	 */
	public static function GraphPrototype() {
		return self::getObject('graphprototype');
	}

	/**
	 * @return CHistory
	 */
	public static function History() {
		return self::getObject('history');
	}

	/**
	 * @return CHost
	 */
	public static function Host() {
		return self::getObject('host');
	}

	/**
	 * @return CHostPrototype
	 */
	public static function HostPrototype() {
		return self::getObject('hostprototype');
	}

	/**
	 * @return CHostGroup
	 */
	public static function HostGroup() {
		return self::getObject('hostgroup');
	}

	/**
	 * @return CHostInterface
	 */
	public static function HostInterface() {
		return self::getObject('hostinterface');
	}

	/**
	 * @return CImage
	 */
	public static function Image() {
		return self::getObject('image');
	}

	/**
	 * @return CIconMap
	 */
	public static function IconMap() {
		return self::getObject('iconmap');
	}

	/**
	 * @return CItem
	 */
	public static function Item() {
		return self::getObject('item');
	}

	/**
	 * @return CItemPrototype
	 */
	public static function ItemPrototype() {
		return self::getObject('itemprototype');
	}

	/**
	 * @return CMaintenance
	 */
	public static function Maintenance() {
		return self::getObject('maintenance');
	}

	/**
	 * @return CMap
	 */
	public static function Map() {
		return self::getObject('map');
	}

	/**
	 * @return CMediaType
	 */
	public static function MediaType() {
		return self::getObject('mediatype');
	}

	/**
	 * @return CProxy
	 */
	public static function Proxy() {
		return self::getObject('proxy');
	}

	/**
	 * @return CService
	 */
	public static function Service() {
		return self::getObject('service');
	}

	/**
	 * @return CScreen
	 */
	public static function Screen() {
		return self::getObject('screen');
	}

	/**
	 * @return CScreenItem
	 */
	public static function ScreenItem() {
		return self::getObject('screenitem');
	}

	/**
	 * @return CScript
	 */
	public static function Script() {
		return self::getObject('script');
	}

	/**
	 * @return CTemplate
	 */
	public static function Template() {
		return self::getObject('template');
	}

	/**
	 * @return CTemplateScreen
	 */
	public static function TemplateScreen() {
		return self::getObject('templatescreen');
	}

	/**
	 * @return CTemplateScreenItem
	 */
	public static function TemplateScreenItem() {
		return self::getObject('templatescreenitem');
	}

	/**
	 * @return CTrigger
	 */
	public static function Trigger() {
		return self::getObject('trigger');
	}

	/**
	 * @return CTriggerPrototype
	 */
	public static function TriggerPrototype() {
		return self::getObject('triggerprototype');
	}

	/**
	 * @return CUser
	 */
	public static function User() {
		return self::getObject('user');
	}

	/**
	 * @return CUserGroup
	 */
	public static function UserGroup() {
		return self::getObject('usergroup');
	}

	/**
	 * @return CUserMacro
	 */
	public static function UserMacro() {
		return self::getObject('usermacro');
	}

	/**
	 * @return CUserMedia
	 */
	public static function UserMedia() {
		return self::getObject('usermedia');
	}

	/**
	 * @return CHttpTest
	 */
	public static function HttpTest() {
		return self::getObject('httptest');
	}
}
