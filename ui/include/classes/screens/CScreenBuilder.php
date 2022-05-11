<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CScreenBuilder {

	/**
	 * Get particular screen object.
	 *
	 * @static
	 *
	 * @param array		$options
	 * @param int		$options['resourcetype']
	 * @param int		$options['screenitemid']
	 * @param int		$options['hostid']
	 * @param array		$options['screen']
	 * @param int		$options['screenid']
	 *
	 * @return CScreenBase
	 */
	public static function getScreen(array $options = []) {
		if (!array_key_exists('resourcetype', $options)) {
			$options['resourcetype'] = null;

			if (is_array($options['screenitem']) && array_key_exists('screenitem', $options)
					&& array_key_exists('resourcetype', $options['screenitem'])) {
				$options['resourcetype'] = $options['screenitem']['resourcetype'];
			}
			else {
				return null;
			}
		}

		if ($options['resourcetype'] === null) {
			return null;
		}

		// get screen
		switch ($options['resourcetype']) {
			case SCREEN_RESOURCE_MAP:
				return new CScreenMap($options);

			case SCREEN_RESOURCE_HISTORY:
				return new CScreenHistory($options);

			case SCREEN_RESOURCE_HTTPTEST_DETAILS:
				return new CScreenHttpTestDetails($options);

			case SCREEN_RESOURCE_DISCOVERY:
				return new CScreenDiscovery($options);

			case SCREEN_RESOURCE_HTTPTEST:
				return new CScreenHttpTest($options);

			case SCREEN_RESOURCE_PROBLEM:
				return new CScreenProblem($options);

			default:
				return null;
		}
	}

	/**
	 * Insert javascript to start time control rendering.
	 *
	 * @static
	 */
	public static function insertProcessObjectsJs() {
		zbx_add_post_js('timeControl.processObjects();');
	}

	/**
	 * Insert javascript for standard screens.
	 *
	 * @param array $timeline
	 *
	 * @static
	 */
	public static function insertScreenStandardJs(array $timeline) {
		CScreenBuilder::insertProcessObjectsJs();
	}

	/**
	 * Creates a string for screen table ID attribute.
	 *
	 * @param string $screenId
	 *
	 * @return string
	 */
	protected static function makeScreenTableId($screenId) {
		return 'screentable_'.$screenId;
	}
}
