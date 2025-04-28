<?php declare(strict_types = 0);
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


class CHostTemplateCacheHelper {

	public static function getLinksToCreate(array $links, array $ancestors, array $descendants): array {
		self::mergeAncestorsIntoLinks($links, $ancestors);
		self::expandLinks($links);
		self::excludeAncestorsFromLinks($links, $ancestors);
		self::mergeDescendantsIntoLinks($links, $descendants);

		return $links;
	}

	public static function getLinksToDelete(array $links, array $ancestors, array $descendants): array {
		self::extendLinksWithAncestorValues($links, $ancestors);
		self::mergeDescendantsIntoLinks($links, $descendants);

		return $links;
	}

	private static function mergeAncestorsIntoLinks(array &$links, array $ancestors): void {
		foreach ($ancestors as $key => $values) {
			$links[$key] = array_key_exists($key, $links)
				? array_merge($links[$key], $values)
				: $values;
		}
	}

	private static function extendLinksWithAncestorValues(array &$links, array $ancestors): void {
		foreach ($links as $key => $values) {
			foreach ($values as $value) {
				if (array_key_exists($value, $ancestors)) {
					$links[$key] = array_merge($values, $ancestors[$value]);
				}
			}
		}
	}

	private static function expandLinks(array &$links): void {
		$result = [];

		foreach ($links as $key => $values) {
			$result[$key] = [];
			$stack = $values;

			while ($stack) {
				$current = array_pop($stack);

				if (!in_array($current, $result[$key])) {
					$result[$key][] = $current;
				}

				if (array_key_exists($current, $links)) {
					foreach ($links[$current] as $ancestor) {
						$stack[] = $ancestor;
					}
				}
			}
		}

		$links = $result;
	}

	private static function excludeAncestorsFromLinks(array &$links, array $ancestors): void {
		foreach ($ancestors as $key => $values) {
			if (array_key_exists($key, $links)) {
				$links[$key] = array_values(array_diff($links[$key], $values));

				if (!$links[$key]) {
					unset($links[$key]);
				}
			}
		}
	}

	private static function mergeDescendantsIntoLinks(array &$links, array $descendants): void {
		foreach ($descendants as $key => $values) {
			foreach ($values as $value) {
				if (!array_key_exists($value, $links)) {
					$links[$value] = $links[$key];
				}
				else {
					foreach ($links[$key] as $id) {
						if (!in_array($id, $links[$value])) {
							$links[$value][] = $id;
						}
					}
				}
			}
		}
	}
}
