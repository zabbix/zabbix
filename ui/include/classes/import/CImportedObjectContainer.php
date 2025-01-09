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
 * Class that holds processed (created and updated) host and template IDs during the current import.
 */
class CImportedObjectContainer {

	/**
	 * @var array with created and updated hosts.
	 */
	protected $hostids = [];

	/**
	 * @var array with created and updated templates.
	 */
	protected $templateids = [];

	/**
	 * Add host IDs that have been created and updated.
	 *
	 * @param array $hostids
	 */
	public function addHostIds(array $hostids): void {
		foreach ($hostids as $hostid) {
			$this->hostids[$hostid] = $hostid;
		}
	}

	/**
	 * Add template IDs that have been created and updated.
	 *
	 * @param array $templateids
	 */
	public function addTemplateIds(array $templateids): void {
		foreach ($templateids as $templateid) {
			$this->templateids[$templateid] = $templateid;
		}
	}

	/**
	 * Checks if host has been created and updated during the current import.
	 *
	 * @param string $hostids
	 *
	 * @return bool
	 */
	public function isHostProcessed(string $hostids): bool {
		return array_key_exists($hostids, $this->hostids);
	}

	/**
	 * Checks if template has been created and updated during the current import.
	 *
	 * @param string $templateid
	 *
	 * @return bool
	 */
	public function isTemplateProcessed(string $templateid): bool {
		return array_key_exists($templateid, $this->templateids);
	}

	/**
	 * Get array of created and updated hosts IDs.
	 *
	 * @return array
	 */
	public function getHostids(): array {
		return array_keys($this->hostids);
	}

	/**
	 * Get array of created and updated template IDs.
	 *
	 * @return array
	 */
	public function getTemplateids(): array {
		return array_keys($this->templateids);
	}
}
