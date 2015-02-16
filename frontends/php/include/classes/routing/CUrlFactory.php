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

class CUrlFactory {

	/**
	 * Configuration of context configurations. Top-level key is file name from $page['file'], below goes:
	 * 'remove' - to remove arguments
	 * 'add' - name of $_REQUEST keys to be kept as arguments
	 * 'callable' - callable to apply to argument list
	 *
	 * @var array
	 */
	protected static $contextConfigs = array(
		'actionconf.php' => array(
			'remove' => array('actionid')
		),
		'applications.php' => array(
			'remove' => array('applicationid')
		),
		'disc_prototypes.php' => array(
			'remove' => array('itemid'),
			'add' => array('hostid', 'parent_discoveryid')
		),
		'discoveryconf.php' => array(
			'remove' => array('druleid')
		),
		'graphs.php' => array(
			'remove' => array('graphid'),
			'add' => array('hostid', 'parent_discoveryid')
		),
		'host_discovery.php' => array(
			'remove' => array('itemid'),
			'add' => array('hostid')
		),
		'host_prototypes.php' => array(
			'remove' => array('hostid'),
			'add' => array('parent_discoveryid')
		),
		'hostgroups.php' => array(
			'remove' => array('groupid')
		),
		'hosts.php' => array(
			'remove' => array('hostid')
		),
		'httpconf.php' => array(
			'remove' => array('httptestid')
		),
		'items.php' => array(
			'remove' => array('itemid')
		),
		'maintenance.php' => array(
			'remove' => array('maintenanceid')
		),
		'screenconf.php' => array(
			'remove' => array('screenid'),
			'add' => array('templateid')
		),
		'slideconf.php' => array(
			'remove' => array('slideshowid')
		),
		'sysmaps.php' => array(
			'remove' => array('sysmapid')
		),
		'templates.php' => array(
			'remove' => array('templateid')
		),
		'trigger_prototypes.php' => array(
			'remove' =>  array('triggerid'),
			'add' => array('parent_discoveryid', 'hostid')
		),
		'triggers.php' => array(
			'remove' => array('triggerid'),
			'add' => array('hostid')
		),
		'usergrps.php' => array(
			'remove' => array('usrgrpid')
		),
		'users.php' => array(
			'remove' => array('userid')
		),
		'__default' => array(
			'remove' => array('cancel', 'form', 'delete')
		)
	);

	/**
	 * Creates new CUrl object based on giver URL (or $_REQUEST if null is given),
	 * and adds/removes parameters based on current page context.
	 *
	 * @param string $sourceUrl
	 *
	 * @return CUrl
	 */
	public static function getContextUrl($sourceUrl = null) {
		$config = self::resolveConfig();

		$url = new CUrl($sourceUrl);

		if (isset($config['remove'])) {
			foreach ($config['remove'] as $key) {
				$url->removeArgument($key);
			}
		}

		if (isset($config['add'])) {
			foreach ($config['add'] as $key) {
				$url->setArgument($key, getRequest($key));
			}
		}

		return $url;
	}

	/**
	 * Resolves context configuration for current file (based on $page['file'] global variable)
	 *
	 * @return array
	 */
	protected static function resolveConfig() {
		global $page;

		if (isset($page['file']) && isset(self::$contextConfigs[$page['file']])) {
			return array_merge_recursive(self::$contextConfigs['__default'], self::$contextConfigs[$page['file']]);
		}

		return self::$contextConfigs['__default'];
	}
}
