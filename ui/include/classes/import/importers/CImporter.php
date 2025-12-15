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

	protected const ALLOW_TESTMODE = false;

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

	protected bool $testmode = false;

	protected array $missing_objects = [
		'items' => [],
		'actions' => [],
		'mediatypes' => [],
		'host' => [],
		'hostgroups' => [],
		'graphs' => [],
		'sysmaps' => [],
		'sla' => [],
		'services' => [],
		'users' => []
	];

	/**
	 * @param array						$options					import options "createMissing", "updateExisting" and "deleteMissing"
	 * @param CImportReferencer			$referencer					class containing all importable objects
	 * @param CImportedObjectContainer	$importedObjectContainer	class containing processed host and template IDs
	 * @param bool						$testmode					skip calls to modify records in the database.
	 */
	public function __construct(array $options, CImportReferencer $referencer,
			CImportedObjectContainer $importedObjectContainer, bool $testmode = false) {
		$this->options = $options;
		$this->referencer = $referencer;
		$this->importedObjectContainer = $importedObjectContainer;

		if ($testmode && !static::ALLOW_TESTMODE) {
			throw new \Exception('Test mode for ' . static::class . ' import is not allowed.');
		}

		$this->testmode = $testmode;
	}

	/**
	 * @abstract
	 *
	 * @param array $elements
	 *
	 * @return mixed
	 */
	abstract public function import(array $elements);

	/**
	 * Register missing referred object.
	 *
	 * @param string $object_type
	 * @param array  $uniq_config
	 *
	 * @throws Exception
	 * @return void
	 */
	protected function addMissingObject(string $object_type, array $uniq_config): void {
		if (!array_key_exists($object_type, $this->missing_objects)) {
			throw new Exception('Unknown object type: '.$object_type);
		}

		$key = str_replace(' ', '_', implode('_', array_values($uniq_config)));
		$this->missing_objects[$object_type][$key] = $uniq_config;
	}

	/**
	 * Return array of missing objects that were skipped during import
	 *
	 * @return array|array[]
	 */
	public function getMissingObjects(): array {
		return $this->missing_objects;
	}
}
