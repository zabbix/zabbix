<?php
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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavTreeItemUpdate extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' => 'required|string|not_empty',
			'sysmapid' => 'db sysmaps.sysmapid',
			'add_submaps' => 'in 0,1',
			'depth' => 'ge 1|le '.WIDGET_NAVIGATION_TREE_MAX_DEPTH
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sysmapid = $this->getInput('sysmapid', 0);
		$add_submaps = (int) $this->getInput('add_submaps', 0);
		$depth = (int) $this->getInput('depth', 1);

		if ($sysmapid != 0) {
			$sysmaps = API::Map()->get([
				'output' => [],
				'sysmapids' => $sysmapid
			]);

			if (!$sysmaps) {
				$sysmapid = 0;
			}
		}

		$all_sysmapids = [];
		$hierarchy = [];

		if ($sysmapid != 0 && $add_submaps == 1) {
			// Recursively select submaps.
			$sysmapids = [];
			$sysmapids[$sysmapid] = true;

			do {
				if ($depth++ > WIDGET_NAVIGATION_TREE_MAX_DEPTH) {
					break;
				}

				$sysmaps = API::Map()->get([
					'output' => ['sysmapid'],
					'selectSelements' => ['elements', 'elementtype', 'permission'],
					'sysmapids' => array_keys($sysmapids),
					'preservekeys' => true
				]);

				$all_sysmapids += $sysmapids;
				$sysmapids = [];

				foreach ($sysmaps as $sysmap) {
					foreach ($sysmap['selements'] as $selement) {
						if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP
								&& $selement['permission'] >= PERM_READ) {
							$element = $selement['elements'][0];
							$hierarchy[$sysmap['sysmapid']][] = $element['sysmapid'];

							if (!array_key_exists($element['sysmapid'], $all_sysmapids)) {
								$sysmapids[$element['sysmapid']] = true;
							}
						}
					}
				}
			}
			while ($sysmapids);
		}

		// Prepare output.
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([
			'name' => $this->getInput('name'),
			'sysmapid' => $sysmapid,
			'hierarchy' => $hierarchy,
			'submaps' => $all_sysmapids
				? API::Map()->get([
					'output' => ['sysmapid', 'name'],
					'sysmapids' => array_keys($all_sysmapids),
					'preservekeys' => true
				])
				: []
		])]));
	}
}
