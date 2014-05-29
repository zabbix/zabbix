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

class CUrlFilter {

	/**
	 * Configuration of urlFilters. Top-level key is file name from $page['file'], below goes:
	 * 'remove' - to remove arguments
	 * 'add' - key => value array to add arguments
	 * 'callable' - callable to apply to argument list
	 *
	 * @var array
	 */
	protected static $urlConfig = array(
		'__default' => array(
			'remove' => array('go', 'cancel', 'form', 'delete')
		)
	);

	public static function filter($arguments) {
		$config = self::resolveConfig();

		if (isset($config['remove'])) {
			foreach ($config['remove'] as $key) {
				unset($arguments[$key]);
			}
		}

		if (isset($config['add'])) {
			$arguments = array_merge($arguments, $config['add']);
		}

		if (isset($config['callable']) && is_callable($config['callable'])) {
			$arguments = call_user_func($config['callable'], $arguments);
		}

		return $arguments;
	}

	protected static function resolveConfig() {
		global $page;

		if (isset($page['file']) && isset(self::$urlConfig[$page['file']])) {
			return self::$urlConfig[$page['file']];
		}

		return self::$urlConfig['__default'];
	}
}
