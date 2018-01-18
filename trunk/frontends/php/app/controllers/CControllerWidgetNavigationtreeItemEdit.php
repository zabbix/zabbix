<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavigationtreeItemEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'depth' => 'ge 0|le '.WIDGET_NAVIGATION_TREE_MAX_DEPTH,
			'map_mapid' => 'db sysmaps.sysmapid',
			'add_submaps' => 'in 0,1',
			'map_name' => 'required|string',
			'mapid' => 'int32'
		];

		$ret = $this->validateInput($fields);

		if ($ret && trim(getRequest('map_name', '')) === '') {
			error(_('Please specify element name.'));
			$ret = false;
		}

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'][] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$add_submaps = $this->getInput('add_submaps', 0);
		$map_item_name = $this->getInput('map_name', '');
		$mapid = $this->getInput('mapid', 0);
		$map_mapid = $this->getInput('map_mapid', 0);
		$submaps = [];

		if ($map_mapid) {
			$maps = API::Map()->get([
				'output' => [],
				'sysmapids' => [$map_mapid]
			]);

			if (!$maps) {
				$map_mapid = 0;
			}
		}

		$maps_relations = [];

		if ($map_mapid && $add_submaps == 1) {
			// Recursively select submaps.
			$maps_found = [$map_mapid];
			$maps_resolved = [];

			while ($diff = array_diff($maps_found, $maps_resolved)) {
				$submaps = API::Map()->get([
					'sysmapids' => $diff,
					'preservekeys' => true,
					'output' => ['sysmapid'],
					'selectSelements' => ['elements', 'elementtype', 'permission']
				]);

				$maps_resolved = array_merge($maps_resolved, $diff);

				foreach ($submaps as $submap) {
					foreach ($submap['selements'] as $selement) {
						if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP
								&& $selement['permission'] >= PERM_READ) {
							if (($element = reset($selement['elements'])) !== false) {
								$maps_relations[$submap['sysmapid']][] = $element['sysmapid'];

								$maps_depth = $this->getInput('depth', 0) + 1;
								$base_mapid = $submap['sysmapid'];

								/*
								 * Looking for parent mapid simply walking back through the hierarchy and counting steps
								 * in $maps_depth.
								 */
								while (array_key_exists($base_mapid, $maps_relations)) {
									foreach ($maps_relations as $next_base_mapid => $list) {
										if (in_array($base_mapid, $list)) {
											$base_mapid = $next_base_mapid;
											$maps_depth++;
											continue 2;
										}
									}

									// not found
									$base_mapid = null;
								}

								if (WIDGET_NAVIGATION_TREE_MAX_DEPTH >= $maps_depth) {
									$maps_found[] = $element['sysmapid'];
								}
							}
						}
					}
				}
			}

			// Get names and ids of all submaps.
			$submaps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => array_keys(array_flip($maps_found)),
				'preservekeys' => true,
			]);

			unset($submap);
		}

		// prepare output
		$output = [
			'map_name' => $map_item_name,
			'map_mapid' => $map_mapid,
			'map_id' => $mapid,
			'hierarchy' => $maps_relations,
			'submaps' => $submaps
		];

		echo (new CJson())->encode($output);
	}
}
