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


/**
 * A helper class for storing relations between objects, and mapping related objects.
 */
class CRelationMap {

	/**
	 * A hash with IDs of base objects as keys and hashes of related object IDs as values.
	 *
	 * @var array
	 */
	protected $map = [];

	/**
	 * A hash of related object IDs.
	 *
	 * @var array
	 */
	protected $relatedIds = [];

	/**
	 * Adds a new relation.
	 *
	 * @param string $baseObjectId
	 * @param string $relatedObjectId
	 */
	public function addRelation($baseObjectId, $relatedObjectId) {
		$this->map[$baseObjectId][$relatedObjectId] = $relatedObjectId;
		$this->relatedIds[$relatedObjectId] = $relatedObjectId;
	}

	/**
	 * Returns the IDs of all related objects.
	 *
	 * @return array
	 */
	public function getRelatedIds() {
		return array_values($this->relatedIds);
	}

	/**
	 * Maps multiple related objects to the base objects and adds them under the $name property. Each base object will
	 * have an array of related objects.
	 *
	 * @param array  $baseObjects		a hash of base objects with IDs as keys
	 * @param array  $relatedObjects	a hash of related objects with IDs as keys
	 * @param string $name				the name of the property under which the related objects will be added
	 * @param int    $limit				maximum number of related objects for each base objects
	 *
	 * @return array
	 */
	public function mapMany(array $baseObjects, array $relatedObjects, $name, $limit = null) {
		foreach ($baseObjects as $baseObjectId => &$baseObject) {
			$baseObject[$name] = [];

			if (isset($this->map[$baseObjectId]) && $this->map[$baseObjectId]) {
				$matchingRelatedObjects = array_values(array_intersect_key($relatedObjects, $this->map[$baseObjectId]));

				if ($matchingRelatedObjects) {
					if ($limit) {
						$matchingRelatedObjects = array_slice($matchingRelatedObjects, 0, $limit);
					}

					$baseObject[$name] = $matchingRelatedObjects;
				}
			}
		}
		unset($baseObject);

		return $baseObjects;
	}

	/**
	 * Maps multiple related objects to the base objects and adds them under the $name property.
	 * Each base object will have only one related object.
	 *
	 * @param array  $baseObjects		a hash of base objects with IDs as keys
	 * @param array  $relatedObjects	a hash of related objects with IDs as keys
	 * @param string $name				the name of the property under which the related object will be added
	 *
	 * @return array
	 */
	public function mapOne(array $baseObjects, array $relatedObjects, $name) {
		foreach ($baseObjects as $baseObjectId => &$baseObject) {
			$matchingRelatedObject = [];

			if (isset($this->map[$baseObjectId])) {
				$matchingRelatedId = reset($this->map[$baseObjectId]);

				if (isset($relatedObjects[$matchingRelatedId])) {
					$matchingRelatedObject = $relatedObjects[$matchingRelatedId];
				}
			}

			$baseObject[$name] = $matchingRelatedObject;
		}
		unset($baseObject);

		return $baseObjects;
	}
}
