<?php
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


abstract class CImporter {

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var CImportedObjectContainer
	 */
	protected $importedObjectContainer;

	/**
	 * @var array
	 */
	protected $options = [];

	/**
	 * @param array						$options					import options "createMissing", "updateExisting" and "deleteMissing"
	 * @param CImportReferencer			$referencer					class containing all importable objects
	 * @param CImportedObjectContainer	$importedObjectContainer	class containing processed host and template IDs
	 */
	public function __construct(array $options, CImportReferencer $referencer,
			CImportedObjectContainer $importedObjectContainer) {
		$this->options = $options;
		$this->referencer = $referencer;
		$this->importedObjectContainer = $importedObjectContainer;
	}

	/**
	 * @abstract
	 *
	 * @param array $elements
	 *
	 * @return mixed
	 */
	abstract public function import(array $elements);
}
