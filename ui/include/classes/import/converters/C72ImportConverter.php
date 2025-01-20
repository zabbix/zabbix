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
		foreach ($maps as &$map) {
			$map['show_element_label'] = DB::getDefault('sysmaps', 'show_element_label');
			$map['show_link_label'] = DB::getDefault('sysmaps', 'show_link_label');
			$map['background_scale'] = DB::getDefault('sysmaps', 'background_scale');

			foreach ($map['selements'] as &$selement) {
				$selement['show_label'] = DB::getDefault('sysmaps_elements', 'show_label');
			}
			unset($selement);

			foreach ($map['links'] as &$link) {
				switch ($link['drawtype']) {
					case MAP_LINK_DRAWTYPE_LINE:
						$link['drawtype'] = CXmlConstantName::SINGLE_LINE;
						break;

					case MAP_LINK_DRAWTYPE_BOLD_LINE:
						$link['drawtype'] = CXmlConstantName::BOLD_LINE;
						break;

					case MAP_LINK_DRAWTYPE_DOT:
						$link['drawtype'] = CXmlConstantName::DOTTED_LINE;
						break;

					case MAP_LINK_DRAWTYPE_DASHED_LINE:
						$link['drawtype'] = CXmlConstantName::DASHED_LINE;
				}

				if ($link['linktriggers']) {
					$link['indicator_type'] = CXmlConstantName::INDICATOR_TYPE_TRIGGER;

					foreach ($link['linktriggers'] as &$link_trigger) {
						switch ($link_trigger['drawtype']) {
							case MAP_LINK_DRAWTYPE_LINE:
								$link_trigger['drawtype'] = CXmlConstantName::SINGLE_LINE;
								break;

							case MAP_LINK_DRAWTYPE_BOLD_LINE:
								$link_trigger['drawtype'] = CXmlConstantName::BOLD_LINE;
								break;

							case MAP_LINK_DRAWTYPE_DOT:
								$link_trigger['drawtype'] = CXmlConstantName::DOTTED_LINE;
								break;

							case MAP_LINK_DRAWTYPE_DASHED_LINE:
								$link_trigger['drawtype'] = CXmlConstantName::DASHED_LINE;
								break;
						}
					}
					unset($link_trigger);
				}
			}
			unset($link);
		}
		unset($map);

		return $maps;
	}
}
