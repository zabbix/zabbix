<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	 */
	public function import(array $maps) {
		$maps = zbx_toHash($maps, 'name');

		$this->checkCircularMapReferences($maps);
		$maps = $this->resolveMapElementReferences($maps);

		/*
		 * Get all importable maps with removed elements and links. First import maps and then update maps with
		 * elements and links from import file. This way we make sure we are able to resolve any references
		 * between maps and links that are imported.
		 */
		$mapsWithoutElements = $this->getMapsWithoutElements($maps);

		$mapsToProcess = ['createMissing' => [], 'updateExisting' => []];

		foreach ($mapsWithoutElements as $mapName => $mapWithoutElements) {
			$mapId = $this->referencer->resolveMap($mapWithoutElements['name']);
			if ($mapId) {
				// Update sysmapid in source map too.
				$mapWithoutElements['sysmapid'] = $mapId;
				$maps[$mapName]['sysmapid'] = $mapId;

				$mapsToProcess['updateExisting'][] = $mapWithoutElements;
			}
			else {
				$mapsToProcess['createMissing'][] = $mapWithoutElements;
			}
		}

		if ($this->options['maps']['createMissing'] && $mapsToProcess['createMissing']) {
			$newMapIds = API::Map()->create($mapsToProcess['createMissing']);
			foreach ($mapsToProcess['createMissing'] as $num => $map) {
				$mapId = $newMapIds['sysmapids'][$num];
				$this->referencer->addMapRef($map['name'], $mapId);

				$maps[$map['name']]['sysmapid'] = $mapId;
			}
		}

		if ($this->options['maps']['updateExisting'] && $mapsToProcess['updateExisting']) {
			API::Map()->update($mapsToProcess['updateExisting']);
		}

		// Form an array of maps that need to be updated with elements and links, respecting the create/update options.
		$mapsToUpdate = [];
		foreach ($mapsToProcess as $mapActionKey => $mapArray) {
			if ($this->options['maps'][$mapActionKey] && $mapsToProcess[$mapActionKey]) {
				foreach ($mapArray as $mapItem) {
					$map = [
						'sysmapid' => $maps[$mapItem['name']]['sysmapid'],
						'name' => $mapItem['name'],
						'selements' => $maps[$mapItem['name']]['selements'],
						'links' => $maps[$mapItem['name']]['links']
					];
					$map = $this->resolveMapReferences($map);

					// Remove the map name so API does not make an update query to the database.
					unset($map['name']);
					$mapsToUpdate[] = $map;
				}
			}
		}

		if ($mapsToUpdate) {
			API::Map()->update($mapsToUpdate);
		}
	}

	/**
	 * Check if map elements have circular references.
	 * Circular references can be only in map elements that represent another map.
	 *
	 * @see checkCircularRecursive
	 *
	 * @throws Exception if circular reference found.
	 *
	 * @param array $maps
	 */
	protected function checkCircularMapReferences(array $maps) {
		foreach ($maps as $mapName => $map) {
			if (empty($map['selements'])) {
				continue;
			}

			foreach ($map['selements'] as $selement) {
				$checked = [$mapName];
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
	 * @param array $element	Map element to inspect on current recursive loop.
	 * @param array $maps		All maps where circular references should be searched.
	 * @param array $checked	Map names that already were processed, should contain unique values
	 *							if no circular references exist.
	 *
	 * @return array|bool
	 */
	protected function checkCircularRecursive(array $element, array $maps, array $checked) {
		// If element is not a map element, recursive reference cannot happen.
		if ($element['elementtype'] != SYSMAP_ELEMENT_TYPE_MAP) {
			return false;
		}

		$elementMapName = $element['element']['name'];

		// If current element map name is already in list of checked map names, circular reference exists.
		if (in_array($elementMapName, $checked)) {
			/*
			 * To have a nice result containing only maps that have circular reference,
			 * remove everything that was added before repeated map name.
			 */
			$checked = array_slice($checked, array_search($elementMapName, $checked));

			// Add repeated name to have nice loop like m1->m2->m3->m1.
			$checked[] = $elementMapName;

			return $checked;
		}
		else {
			$checked[] = $elementMapName;
		}

		// Find maps that reference the current element, and if one has selements, check all of them recursively.
		if (!empty($maps[$elementMapName]['selements'])) {
			foreach ($maps[$elementMapName]['selements'] as $selement) {
				return $this->checkCircularRecursive($selement, $maps, $checked);
			}
		}

		return false;
	}

	/**
	 * Return maps without their elements.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function getMapsWithoutElements(array $maps) {
		foreach ($maps as &$map) {
			if (array_key_exists('selements', $map)) {
				unset($map['selements']);
			}
			if (array_key_exists('links', $map)) {
				unset($map['links']);
			}
		}
		unset($map);

		return $maps;
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

				$icons = [
					'icon_off' => 'iconid_off',
					'icon_on' => 'iconid_on',
					'icon_disabled' => 'iconid_disabled',
					'icon_maintenance' => 'iconid_maintenance',
				];
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
				if (!$link['linktriggers']) {
					unset($link['linktriggers']);
					continue;
				}

				foreach ($link['linktriggers'] as &$linkTrigger) {
					$trigger = $linkTrigger['trigger'];
					$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression']);

					if (!$triggerId) {
						throw new Exception(_s(
							'Cannot find trigger "%1$s" used in map "%2$s".',
							$trigger['description'],
							$map['name']
						));
					}

					$linkTrigger['triggerid'] = $triggerId;
				}
				unset($linkTrigger);
			}
			unset($link);
		}

		return $map;
	}

	/**
	 * Resolves the iconmap and background images for the maps.
	 *
	 * @throws Exception if icon map or background image is not found.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function resolveMapElementReferences(array $maps) {
		foreach ($maps as &$map) {
			if (isset($map['iconmap']) && $map['iconmap']) {
				$map['iconmapid'] = $this->referencer->resolveIconMap($map['iconmap']['name']);

				if (!$map['iconmapid']) {
					throw new Exception(_s('Cannot find icon map "%1$s" used in map "%2$s".',
						$map['iconmap']['name'], $map['name']
					));
				}
			}

			if (isset($map['background']) && $map['background']) {
				$image = getImageByIdent($map['background']);

				if (!$image) {
					throw new Exception(_s('Cannot find background image "%1$s" used in map "%2$s".',
						$map['background']['name'], $map['name']
					));
				}
				$map['backgroundid'] = $image['imageid'];
			}
		}
		unset($map);

		return $maps;
	}
}
