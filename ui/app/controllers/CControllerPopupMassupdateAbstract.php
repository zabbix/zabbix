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


abstract class CControllerPopupMassupdateAbstract extends CController {

	/**
	 * Apply mass update changes for value maps.
	 *
	 * @throws Exception
	 */
	protected function updateValueMaps(array $hostids) {
		$db_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'name', 'hostid'],
			'hostids' => $hostids,
			'preservekeys' => true
		]);
		$action = $this->getInput('valuemap_massupdate');
		$ins_valuemaps = [];
		$upd_valuemaps = [];
		$del_valuemapids = [];

		switch ($action) {
			case ZBX_ACTION_ADD:
			case ZBX_ACTION_REPLACE:
				$valuemaps = array_column($this->getInput('valuemaps', []), null, 'name');

				if (!$valuemaps) {
					break;
				}

				if ($action == ZBX_ACTION_REPLACE || $this->hasInput('valuemap_update_existing')) {
					foreach ($db_valuemaps as $db_valuemap) {
						if (!array_key_exists($db_valuemap['name'], $valuemaps)) {
							continue;
						}

						$upd_valuemaps [] = [
							'valuemapid' => $db_valuemap['valuemapid'],
							'mappings' => $valuemaps[$db_valuemap['name']]['mappings']
						];
					}
				}

				if ($action == ZBX_ACTION_ADD || $this->hasInput('valuemap_add_missing')) {
					$host_valuemaps = [];

					foreach ($db_valuemaps as $db_valuemap) {
						$host_valuemaps[$db_valuemap['name']][] = $db_valuemap['hostid'];
					}

					$host_valuemaps += array_fill_keys(array_keys($valuemaps), []);

					foreach ($valuemaps as $valuemap) {
						foreach (array_diff($hostids, $host_valuemaps[$valuemap['name']]) as $hostid) {
							$ins_valuemaps[] = [
								'hostid' => $hostid,
								'name' => $valuemap['name'],
								'mappings' => $valuemap['mappings']
							];
						}
					}
				}
				break;

			case ZBX_ACTION_RENAME:
				$valuemap_rename = array_column($this->getInput('valuemap_rename', []), 'to', 'from');
				unset($valuemap_rename['']);

				if (!$valuemap_rename) {
					break;
				}

				foreach ($db_valuemaps as $db_valuemap) {
					if (!array_key_exists($db_valuemap['name'], $valuemap_rename)) {
						continue;
					}

					$upd_valuemaps [] = [
						'valuemapid' => $db_valuemap['valuemapid'],
						'name' => $valuemap_rename[$db_valuemap['name']]
					];
				}
				break;

			case ZBX_ACTION_REMOVE:
				$valuemaps = $this->getInput('valuemap_remove', []);

				if (!$valuemaps) {
					break;
				}

				$remove_except = $this->hasInput('valuemap_remove_except');
				$delete_names = [];

				foreach ($valuemaps as $valuemapid) {
					$delete_names[] = $db_valuemaps[$valuemapid]['name'];
				}

				if ($remove_except) {
					$delete_names = array_diff(array_column($db_valuemaps, 'name', 'name'), $delete_names);
				}

				foreach ($db_valuemaps as $db_valuemap) {
					if (in_array($db_valuemap['name'], $delete_names)) {
						$del_valuemapids [] = $db_valuemap['valuemapid'];
					}
				}
				break;

			case ZBX_ACTION_REMOVE_ALL:
				if ($this->hasInput('valuemap_remove_all')) {
					$del_valuemapids  = array_column($db_valuemaps, 'valuemapid');
				}
				break;
		}

		if ($upd_valuemaps  && !API::ValueMap()->update($upd_valuemaps )) {
			throw new Exception();
		}

		if ($ins_valuemaps && !API::ValueMap()->create($ins_valuemaps)) {
			throw new Exception();
		}

		if ($del_valuemapids  && !API::ValueMap()->delete($del_valuemapids )) {
			throw new Exception();
		}
	}
}
