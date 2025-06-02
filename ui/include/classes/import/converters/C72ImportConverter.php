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


/**
 * Converter for converting import data from 7.2 to 7.4.
 */
class C72ImportConverter extends CConverter {

	/**
	 * Convert import data from 7.2 to 7.4 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '7.4';

		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = self::convertMaps($data['zabbix_export']['maps']);
		}

		return $data;
	}

	private static function convertMaps(array $maps): array {
		$drawtypes = [
			CXmlConstantValue::SINGLE_LINE => CXmlConstantName::SINGLE_LINE,
			CXmlConstantValue::BOLD_LINE => CXmlConstantName::BOLD_LINE,
			CXmlConstantValue::DOTTED_LINE => CXmlConstantName::DOTTED_LINE,
			CXmlConstantValue::DASHED_LINE => CXmlConstantName::DASHED_LINE
		];

		foreach ($maps as &$map) {
			$map['background_scale'] = CXmlConstantName::MAP_BACKGROUND_SCALE_NONE;

			foreach ($map['links'] as &$link) {
				$link['drawtype'] = $drawtypes[$link['drawtype']];

				if ($link['linktriggers']) {
					$link['indicator_type'] = CXmlConstantName::INDICATOR_TYPE_TRIGGER;

					foreach ($link['linktriggers'] as &$link_trigger) {
						$link_trigger['drawtype'] = $drawtypes[$link_trigger['drawtype']];
					}
					unset($link_trigger);
				}
			}
			unset($link);

			$map['selements'] = self::convertMapElements($map['selements']);
		}
		unset($map);

		return $maps;
	}

	private static function convertMapElements(array $selements): array {
		$i = 0;
		foreach ($selements as &$selement) {
			$selement['zindex'] = (string) $i++;
		}
		unset($selement);

		return $selements;
	}
}
