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


/**
 * Class that holds processed (created and updated) host and template IDs during the current import.
 */
class CImportedObjectContainer {
	/**
	 * @var array with created and updated hosts.
	 */
	protected $hostIds = [];

	/**
	 * @var array with created and updated templates.
	 */
	protected $templateIds = [];

	/**
	 * Add host IDs that have been created and updated.
	 *
	 * @param array $hostIds
	 */
	public function addHostIds(array $hostIds) {
		foreach ($hostIds as $hostId) {
			$this->hostIds[$hostId] = $hostId;
		}
	}

	/**
	 * Add template IDs that have been created and updated.
	 *
	 * @param array $templateIds
	 */
	public function addTemplateIds(array $templateIds) {
		foreach ($templateIds as $templateId) {
			$this->templateIds[$templateId] = $templateId;
		}
	}

	/**
	 * Checks if host has been created and updated during the current import.
	 *
	 * @param string $hostId
	 *
	 * @return bool
	 */
	public function isHostProcessed($hostId) {
		return isset($this->hostIds[$hostId]);
	}

	/**
	 * Checks if template has been created and updated during the current import.
	 *
	 * @param string $templateId
	 *
	 * @return bool
	 */
	public function isTemplateProcessed($templateId) {
		return isset($this->templateIds[$templateId]);
	}

	/**
	 * Get array of created and updated hosts IDs.
	 *
	 * @return array
	 */
	public function getHostIds() {
		return array_values($this->hostIds);
	}

	/**
	 * Get array of created and updated template IDs.
	 *
	 * @return array
	 */
	public function getTemplateIds() {
		return array_values($this->templateIds);
	}
}
