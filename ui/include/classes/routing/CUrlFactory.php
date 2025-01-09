<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	protected static $contextConfigs = [
		'graphs.php' => [
			'remove' => ['graphid'],
			'add' => ['hostid', 'parent_discoveryid']
		],
		'host_discovery.php' => [
			'remove' => ['itemid'],
			'add' => ['hostid']
		],
		'host_prototypes.php' => [
			'remove' => ['hostid'],
			'add' => ['parent_discoveryid']
		],
		'httpconf.php' => [
			'remove' => ['httptestid']
		],
		'sysmaps.php' => [
			'remove' => ['sysmapid']
		],
		'__default' => [
			'remove' => ['cancel', 'form', 'delete']
		]
	];

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
