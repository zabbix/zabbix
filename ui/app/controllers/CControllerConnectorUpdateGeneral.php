<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


abstract class CControllerConnectorUpdateGeneral extends CController {

	final protected static function processConnectorInput(array &$connector): void {
		if ($connector['max_records_mode'] == 0) {
			$connector['max_records'] = DB::getDefault('connector', 'max_records');
		}

		unset($connector['max_records_mode']);

		if (array_key_exists('item_value_types', $connector)) {
			$connector['item_value_type'] = array_sum($connector['item_value_types']);;
			unset($connector['item_value_types']);
		}

		$connector['tags'] = self::processTags($connector['tags']);
	}

	private static function processTags(array $tags): array {
		foreach ($tags as $key => $tag) {
			if ($tag['tag'] === '' && (!array_key_exists('value', $tag) || $tag['value'] === '')) {
				unset($tags[$key]);
			}
		}

		return array_values($tags);
	}
}
