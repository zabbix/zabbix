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


class CMapImporter extends CImporter {

	/**
	 * Import maps.
	 *
	 * @param array $maps
	 *
	 * @return void
	 */
	public function import(array $maps) {
		$maps = zbx_toHash($maps, 'name');

		$this->checkCircularMapReferences($maps);

		do {
			$im = $this->getIndependentMaps($maps);

			$mapsToCreate = array();
			$mapsToUpdate = array();
			foreach ($im as $name) {
				$map = $maps[$name];
				unset($maps[$name]);

				$map = $this->resolveMapReferences($map);

				if ($mapId = $this->referencer->resolveMap($map['name'])) {
					$map['sysmapid'] = $mapId;
					$mapsToUpdate[] = $map;
				}
				else {
					$mapsToCreate[] = $map;
				}
			}

			if ($this->options['maps']['createMissing'] && $mapsToCreate) {
				$newMapIds = API::Map()->create($mapsToCreate);
				foreach ($mapsToCreate as $num => $map) {
					$mapId = $newMapIds['sysmapids'][$num];
					$this->referencer->addMapRef($map['name'], $mapId);
				}
			}
			if ($this->options['maps']['updateExisting'] && $mapsToUpdate) {
				API::Map()->update($mapsToUpdate);
			}
		} while (!empty($im));
	}

	/**
	 * Check if map elements have circular references.
	 * Circular references can be only in map elements that represent another map.
	 *
	 * @throws Exception
	 * @see checkCircularRecursive
	 *
	 * @param array $maps
	 *
	 * @return void
	 */
	protected function checkCircularMapReferences(array $maps) {
		foreach ($maps as $mapName => $map) {
			if (empty($map['selements'])) {
				continue;
			}

			foreach ($map['selements'] as $selement) {
				$checked = array($mapName);
				if ($circMaps = $this->checkCircularRecursive($selement, $maps, $checked)) {
					throw new Exception(_s('Circular reference in maps: %1$s.', implode(' - ', $circMaps)));
				}
			}
		}
	}

	/**
	 * Recursive function for searching for circular map references.
	 * If circular reference exist it return array with map elements with circular reference.
	 *
	 * @param array $element map element to inspect on current recursive loop
	 * @param array $maps    all maps where circular references should be searched
	 * @param array $checked map names that already were processed,
	 *                       should contain unique values if no circular references exist
	 *
	 * @return array|bool
	 */
	protected function checkCircularRecursive(array $element, array $maps, array $checked) {
		// if element is not map element, recursive reference cannot happen
		if ($element['elementtype'] != SYSMAP_ELEMENT_TYPE_MAP) {
			return false;
		}

		$elementMapName = $element['element']['name'];

		// if current element map name is already in list of checked map names,
		// circular reference exists
		if (in_array($elementMapName, $checked)) {
			// to have nice result containing only maps that have circular reference,
			// remove everything that was added before repeated map name
			$checked = array_slice($checked, array_search($elementMapName, $checked));
			// add repeated name to have nice loop like m1->m2->m3->m1
			$checked[] = $elementMapName;
			return $checked;
		}
		else {
			$checked[] = $elementMapName;
		}

		// we need to find map that current element reference to
		// and if it has selements check all them recursively
		if (!empty($maps[$elementMapName]['selements'])) {
			foreach ($maps[$elementMapName]['selements'] as $selement) {
				return $this->checkCircularRecursive($selement, $maps, $checked);
			}
		}

		return false;
	}

	/**
	 * Get maps that don't have map elements that reference not existing map i.e. map elements references can be resolved.
	 * Returns array with map names.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function getIndependentMaps(array $maps) {
		foreach ($maps as $num => $map) {
			if (empty($map['selements'])) {
				continue;
			}

			foreach ($map['selements'] as $selement) {
				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
					if (!$this->referencer->resolveMap($selement['element']['name'])) {
						unset($maps[$num]);
						continue 2;
					}
				}
			}
		}

		return zbx_objectValues($maps, 'name');
	}

	/**
	 * Change all references in map to database ids.
	 *
	 * @throws Exception
	 *
	 * @param array $map
	 *
	 * @return array
	 */
	protected function resolveMapReferences(array $map) {
		// resolve icon map
		if (!empty($map['iconmap'])) {
			$map['iconmapid'] = $this->referencer->resolveIconMap($map['iconmap']['name']);
			if (!$map['iconmapid']) {
				throw new Exception(_s('Cannot find icon map "%1$s" used in map "%2$s".', $map['iconmap']['name'], $map['name']));
			}
		}

		if (!empty($map['background'])) {
			$image = getImageByIdent($map['background']);

			if (!$image) {
				throw new Exception(_s('Cannot find background image "%1$s" used in map "%2$s".',
					$map['background']['name'], $map['name']
				));
			}
			$map['backgroundid'] = $image['imageid'];
		}

		if (isset($map['selements'])) {
			foreach ($map['selements'] as &$selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$selement['elementid'] = $this->referencer->resolveMap($selement['element']['name']);
						if (!$selement['elementid']) {
							throw new Exception(_s('Cannot find map "%1$s" used in map "%2$s".',
								$selement['element']['name'], $map['name']));
						}
						break;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$selement['elementid'] = $this->referencer->resolveGroup($selement['element']['name']);
						if (!$selement['elementid']) {
							throw new Exception(_s('Cannot find group "%1$s" used in map "%2$s".',
								$selement['element']['name'], $map['name']));
						}
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$selement['elementid'] = $this->referencer->resolveHost($selement['element']['host']);
						if (!$selement['elementid']) {
							throw new Exception(_s('Cannot find host "%1$s" used in map "%2$s".',
								$selement['element']['host'], $map['name']));
						}
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$el = $selement['element'];
						$selement['elementid'] = $this->referencer->resolveTrigger($el['description'], $el['expression']);

						if (!$selement['elementid']) {
							throw new Exception(_s(
								'Cannot find trigger "%1$s" used in map "%2$s".',
								$selement['element']['description'],
								$map['name']
							));
						}
						break;

					case SYSMAP_ELEMENT_TYPE_IMAGE:
						$selement['elementid'] = 0;
						break;
				}

				$icons = array(
					'icon_off' => 'iconid_off',
					'icon_on' => 'iconid_on',
					'icon_disabled' => 'iconid_disabled',
					'icon_maintenance' => 'iconid_maintenance',
				);
				foreach ($icons as $element => $field) {
					if (!empty($selement[$element])) {
						$image = getImageByIdent($selement[$element]);
						if (!$image) {
							throw new Exception(_s('Cannot find icon "%1$s" used in map "%2$s".',
								$selement[$element]['name'], $map['name']));
						}
						$selement[$field] = $image['imageid'];
					}
				}
			}
			unset($selement);
		}

		if (isset($map['links'])) {
			foreach ($map['links'] as &$link) {
				if (empty($link['linktriggers'])) {
					unset($link['linktriggers']);
					continue;
				}

				foreach ($link['linktriggers'] as &$linktrigger) {
					$dbTriggers = API::Trigger()->getObjects($linktrigger['trigger']);
					if (empty($dbTriggers)) {
						throw new Exception(_s(
							'Cannot find trigger "%1$s" used in map "%2$s".',
							$linktrigger['trigger']['description'],
							$map['name']
						));
					}

					$tmp = reset($dbTriggers);
					$linktrigger['triggerid'] = $tmp['triggerid'];
				}
				unset($linktrigger);
			}
			unset($link);
		}

		return $map;
	}
}
