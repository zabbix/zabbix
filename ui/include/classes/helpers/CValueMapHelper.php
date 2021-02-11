<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CValueMapHelper {

	/**
	 * Get host or template parent templates recursively, input host or template will be added to result array.
	 *
	 * @param array $hosts          Hosts or templates array to get parent templates for.
	 * @param array $parent_fields  Fields to select for parent templates.
	 * @return array
	 */
	static public function getParentTemplatesRecursive(array $hosts, array $parent_fields): array {
		$all_templates = $hosts;
		$templates = $hosts;

		while (true) {
			$parent_templates = [];

			foreach ($templates as $template) {
				$parent_templates += array_column($template['parentTemplates'], null, 'templateid');
			}

			$temlpateids = array_diff_key($parent_templates, $all_templates);
			$all_templates += $parent_templates;

			if (!$temlpateids) {
				break;
			}

			$templates = API::Template()->get([
				'output' => [],
				'selectParentTemplates' => $parent_fields,
				'templateids' => array_keys($temlpateids)
			]);
		};

		return $all_templates;
	}

	/**
	 * Apply value mapping to value.
	 * If value map or mapping is not found, unchanged value is returned,
	 * otherwise mapped value returned in format: "<mapped_value> (<initial_value>)".
	 *
	 * @param string $value  Value that mapping should be applied to.
	 * @param array  $item   Item array
	 * @param bool   $trim
	 *
	 * @return string
	 */
	static public function applyValueMap(string $value, array $item, bool $trim = false): string {
		if (!$item['valuemap']) {
			return $value;
		}

		$mappings = array_column($item['valuemap']['mappings'], 'newvalue', 'value');
		$mapping_value = array_key_exists($value, $mappings) ? $mappings[$value] : null;

		if ($mapping_value !== null) {
			$value = ($trim && mb_strlen($value) > 20) ? mb_substr($value, 0, 20).'&hellip' : $value;
			return $mapping_value.' ('.$value.')';
		}

		return $value;
	}
}
