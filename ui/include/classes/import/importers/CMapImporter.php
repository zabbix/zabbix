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


class CMapImporter extends CImporter {

	/**
	 * Import maps.
	 *
	 * @param array $maps
	 *
	 * @throws Exception
	 */
	public function import(array $maps): void {
		$maps = zbx_toHash($maps, 'name');
		$maps = $this->resolveMapElementReferences($maps);

		$maps_to_create = [];
		$maps_to_update = [];

		/*
		 * Get all importable maps with removed elements and links. First import maps and then update maps with
		 * elements and links from import file. This way we make sure we are able to resolve any references
		 * between maps and links that are imported.
		 */
		foreach ($this->getMapsWithoutElements($maps) as $map_name => $map_without_elements) {
			$mapid = $this->referencer->findMapidByName($map_without_elements['name']);

			if ($mapid !== null) {
				// Update sysmapid in source map too.
				$map_without_elements['sysmapid'] = $mapid;
				$maps[$map_name]['sysmapid'] = $mapid;
				$maps_to_update[] = $map_without_elements;
			}
			else {
				$maps_to_create[] = $map_without_elements;
			}
		}

		if ($this->options['maps']['updateExisting'] && $maps_to_update) {
			API::Map()->update($maps_to_update);
		}

		if ($this->options['maps']['createMissing'] && $maps_to_create) {
			$created_maps = API::Map()->create($maps_to_create);

			foreach ($maps_to_create as $index => $map) {
				$mapid = $created_maps['sysmapids'][$index];

				$this->referencer->setDbMap($mapid, $map);

				$maps[$map['name']]['sysmapid'] = $mapid;
			}
		}

		// Form an array of maps that need to be updated with elements and links, respecting the create/update options.
		$maps_to_process = [];

		foreach (['createMissing' => $maps_to_create, 'updateExisting' => $maps_to_update]
				as $action => $maps_without_elements) {
			if ($this->options['maps'][$action]) {
				foreach ($maps_without_elements as $map_without_element) {
					$map = $maps[$map_without_element['name']];

					$map = $this->resolveMapReferences([
						'sysmapid' => $map['sysmapid'],
						'name' => $map_without_element['name'],
						'shapes' => $map['shapes'],
						'lines' => $map['lines'],
						'selements' => $map['selements'],
						'links' => $map['links']
					]);

					// Remove the map name so API does not make an update query to the database.
					unset($map['name']);
					$maps_to_process[] = $map;
				}
			}
		}

		if ($maps_to_process) {
			API::Map()->update($maps_to_process);
		}
	}

	/**
	 * Return maps without their elements.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function getMapsWithoutElements(array $maps): array {
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
	 * @param array $map
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function resolveMapReferences(array $map): array {
		foreach ($map['selements'] as &$selement) {
			switch ($selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_MAP:
					$mapid = $this->referencer->findMapidByName($selement['elements'][0]['name']);

					if ($mapid === null) {
						throw new Exception(_s('Cannot find map "%1$s" used in map "%2$s".',
							$selement['elements'][0]['name'], $map['name']));
					}

					$selement['elements'][0]['sysmapid'] = $mapid;
					unset($selement['elements'][0]['name']);
					break;

				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$groupid = $this->referencer->findHostGroupidByName($selement['elements'][0]['name']);

					if ($groupid === null) {
						throw new Exception(_s('Cannot find group "%1$s" used in map "%2$s".',
							$selement['elements'][0]['name'], $map['name']));
					}

					$selement['elements'][0]['groupid'] = $groupid;
					unset($selement['elements'][0]['name']);
					break;

				case SYSMAP_ELEMENT_TYPE_HOST:
					$hostid = $this->referencer->findHostidByHost($selement['elements'][0]['host']);

					if ($hostid === null) {
						throw new Exception(_s('Cannot find host "%1$s" used in map "%2$s".',
							$selement['elements'][0]['host'], $map['name']));
					}

					$selement['elements'][0]['hostid'] = $hostid;
					unset($selement['elements'][0]['host']);
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					foreach ($selement['elements'] as &$element) {
						$triggerid = $this->referencer->findTriggeridByName($element['description'],
							$element['expression'], $element['recovery_expression']
						);

						if ($triggerid === null) {
							throw new Exception(_s('Cannot find trigger "%1$s" used in map "%2$s".',
								$element['description'], $map['name']));
						}

						$element['triggerid'] = $triggerid;
						unset($element['description'], $element['expression'], $element['recovery_expression']);
					}
					unset($element);
					break;

				case SYSMAP_ELEMENT_TYPE_IMAGE:
					unset($selement['elements']);
					break;
			}

			$icons = [
				'icon_off' => 'iconid_off',
				'icon_on' => 'iconid_on',
				'icon_disabled' => 'iconid_disabled',
				'icon_maintenance' => 'iconid_maintenance'
			];

			foreach ($icons as $name_field => $id_field) {
				if (array_key_exists($name_field, $selement) && array_key_exists('name', $selement[$name_field])
						&& $selement[$name_field]['name'] !== '') {
					$imageid = $this->referencer->findImageidByName(trim($selement[$name_field]['name']));

					if ($imageid === null) {
						throw new Exception(_s('Cannot find icon "%1$s" used in map "%2$s".',
							$selement[$name_field]['name'], $map['name']));
					}
					$selement[$id_field] = $imageid;
				}
			}
		}
		unset($selement);

		foreach ($map['links'] as &$link) {
			if (!$link['linktriggers']) {
				unset($link['linktriggers']);
				continue;
			}

			foreach ($link['linktriggers'] as &$linktrigger) {
				$trigger = $linktrigger['trigger'];
				$triggerid = $this->referencer->findTriggeridByName($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression']
				);

				if ($triggerid === null) {
					throw new Exception(_s('Cannot find trigger "%1$s" used in map "%2$s".',
						$trigger['description'], $map['name']));
				}

				$linktrigger['triggerid'] = $triggerid;
			}
			unset($linktrigger);
		}
		unset($link);

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
	protected function resolveMapElementReferences(array $maps): array {
		foreach ($maps as &$map) {
			if (array_key_exists('iconmap', $map) && array_key_exists('name', $map['iconmap'])
					&& $map['iconmap']['name'] !== '') {
				$iconmapid = $this->referencer->findIconmapidByName($map['iconmap']['name']);

				if ($iconmapid === null) {
					throw new Exception(_s('Cannot find icon map "%1$s" used in map "%2$s".',
						$map['iconmap']['name'], $map['name']
					));
				}
				$map['iconmapid'] = $iconmapid;
			}

			if (array_key_exists('background', $map) && array_key_exists('name', $map['background'])
					&& $map['background']['name'] !== '') {
				$imageid = $this->referencer->findImageidByName(trim($map['background']['name']));

				if ($imageid === null) {
					throw new Exception(_s('Cannot find background image "%1$s" used in map "%2$s".',
						$map['background']['name'], $map['name']
					));
				}
				$map['backgroundid'] = $imageid;
			}
		}
		unset($map);

		return $maps;
	}
}
