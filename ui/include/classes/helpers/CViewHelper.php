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


/**
 * A helper class for CView related tasks.
 */
class CViewHelper {

	/**
	 * Generate </a>&nbsp;<sup>num</sup>" to be used in tables. Null is returned if equal to zero.
	 *
	 * @param int $num
	 *
	 * @return mixed
	 */
	public static function showNum($num) {
		if ($num == 0) {
			return null;
		}

		return [NBSP(), new CSup($num)];
	}

	/**
	 * Save web layout mode into user's profile.
	 *
	 * @param int $layout_mode  ZBX_LAYOUT_NORMAL | ZBX_LAYOUT_KIOSKMODE
	 */
	public static function saveLayoutMode($layout_mode) {
		CProfile::update('web.layout.mode', $layout_mode, PROFILE_TYPE_INT);
	}

	/**
	 * Load web layout mode from user's profile.
	 *
	 * @return int  Stored web layout mode (ZBX_LAYOUT_NORMAL by default).
	 */
	public static function loadLayoutMode() {
		return (int) CProfile::get('web.layout.mode', ZBX_LAYOUT_NORMAL);
	}

	/**
	 * Save sidebar mode into user's profile.
	 *
	 * @param int $sidebar_mode  ZBX_SIDEBAR_VIEW_MODE_FULL | ZBX_SIDEBAR_VIEW_MODE_COMPACT
	 *                           | ZBX_SIDEBAR_VIEW_MODE_HIDDEN
	 */
	public static function saveSidebarMode($sidebar_mode) {
		CProfile::update('web.sidebar.mode', $sidebar_mode, PROFILE_TYPE_INT);
	}

	/**
	 * Return sidebar mode setting.
	 *
	 * @return int  Stored sidebar mode (ZBX_SIDEBAR_VIEW_MODE_FULL by default).
	 */
	public static function loadSidebarMode() {
		return (int) CProfile::get('web.sidebar.mode', ZBX_SIDEBAR_VIEW_MODE_FULL);
	}
}
