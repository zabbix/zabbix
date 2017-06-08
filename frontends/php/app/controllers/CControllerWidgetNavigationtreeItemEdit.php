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

		if ($ret && getRequest('map_name', '') === '') {
			error(_s('Please specify Item name.'));
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
				'sysmapids' => [$map_mapid],
				'output' => API_OUTPUT_EXTEND
			]);

			if (($map = reset($maps)) === false) {
				$map_mapid = 0;
			}
		}

		if ($map_mapid && $add_submaps == 1) {
			$db_mapselements = DBselect(
				'SELECT DISTINCT se.elementid'.
				' FROM sysmaps_elements se'.
				' WHERE se.elementtype = '.SYSMAP_ELEMENT_TYPE_MAP.' AND se.sysmapid = '.$map_mapid
			);

			$sub_mapsids = [];
			while ($db_mapelement = DBfetch($db_mapselements)) {
				$sub_mapsids[] = $db_mapelement['elementid'];
			}

			$submaps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => $sub_mapsids
			]);
		}

		// prepare output
		$output = [
			'map_name' => $map_item_name,
			'map_mapid' => $map_mapid,
			'map_id' => $mapid,
			'submaps' => $submaps
		];

		echo (new CJson())->encode($output);
	}
}
