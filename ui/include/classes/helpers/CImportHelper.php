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


class CImportHelper {

	/**
	 * Convert missing referred objects returned by the configuration import API to a multiline string.
	 *
	 * @param array $missing_objects
	 *
	 * @return string
	 */
	public static function missingObjectsToString(array $missing_objects): string {
		$type_labels = self::getMissingObjectTypeLabels();

		$summary = [];
		foreach ($missing_objects as $type => $objects) {
			$list = [];

			foreach ($objects as $object) {
				$list[] = match ($type) {
					'items' => $object['host'].NAME_DELIMITER.$object['key'],
					'graphs' => $object['host'].NAME_DELIMITER.$object['name'],
					'users' => $object['username'],
					default => $object['name']
				};
			}

			$summary[] = '- '.$type_labels[$type].NAME_DELIMITER.implode(', ', $list);
		}

		return implode("\n", $summary);
	}

	public static function getMissingObjectTypeLabels(): array {
		return [
			'hosts' =>  _('Hosts'),
			'hostgroups' =>  _('Host groups'),
			'items' => _('Items'),
			'graphs' =>  _('Graphs'),
			'services' =>  _('Services'),
			'slas' =>  _('SLAs'),
			'actions' => _('Actions'),
			'mediatypes' =>  _('Media types'),
			'sysmaps' =>  _('Maps'),
			'users' =>  _('Users')
		];
	}
}
