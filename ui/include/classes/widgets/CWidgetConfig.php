<?php declare(strict_types = 0);
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


namespace Zabbix\Widgets;

class CWidgetConfig {

	/**
	 * Get reference field name for widgets of the given type.
	 *
	 * @static
	 *
	 * @return string|null
	 */
	/*public static function getReferenceField(string $type): ?string {
		switch ($type) {
			case WIDGET_MAP: // TODO AS: need to check
			case WIDGET_NAV_TREE: // TODO AS: need to check
				return 'reference';

			default:
				return null;
		}
	}*/

	/**
	 * Get foreign reference field names for widgets of the given type.
	 *
	 * @static
	 *
	 * @return array
	 */
	/*public static function getForeignReferenceFields(string $type): array {
		switch ($type) {
			case WIDGET_MAP: // TODO AS: need to check
				return ['filter_widget_reference'];

			default:
				return [];
		}
	}*/

}
