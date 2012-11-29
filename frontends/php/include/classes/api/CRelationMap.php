<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * A helper class for storing relations between objects, and mapping related objects.
 */
class CRelationMap {

	/**
	 * A hash with IDs of related objects as keys and arrays of base objects IDs as values.
	 *
	 * @var array
	 */
	protected $map = array();

	/**
	 * Adds a new relation.
	 *
	 * @param string $baseObjectId
	 * @param string $relatedObjectId
	 */
	public function addRelation($baseObjectId, $relatedObjectId) {
		$this->map[$relatedObjectId][$baseObjectId] = $baseObjectId;
	}

	/**
	 * Returns the IDs of all related objects.
	 *
	 * @return array
	 */
	public function getRelatedIds() {
		return array_keys($this->map);
	}

	public function mapMany($baseObjects, $relatedObjects, $name, $limit = null) {
		// create an empty array for every base object
		foreach ($baseObjects as &$baseObject) {
			$baseObject[$name] = array();
		}
		unset($baseObject);

		// add related objects
		if ($this->map) {
			$count = array();
			foreach ($relatedObjects as $relatedObjectId => $relatedObject) {
				if (isset($this->map[$relatedObjectId])) {
					foreach ($this->map[$relatedObjectId] as $baseObjectId) {
						if (isset($baseObjects[$baseObjectId])) {
							// limit the number of results for each object
							if ($limit) {
								if (!isset($count[$baseObjectId])) {
									$count[$baseObjectId] = 0;
								}

								$count[$baseObjectId]++;

								if ($count[$baseObjectId] > $limit) {
									continue;
								}
							}

							$baseObjects[$baseObjectId][$name][] = $relatedObjects[$relatedObjectId];
						}
					}
				}
			}
		}

		return $baseObjects;
	}

	public function mapOne($baseObjects, $relatedObjects, $name) {
		// create an empty array for every base object
		foreach ($baseObjects as &$baseObject) {
			$baseObject[$name] = array();
		}
		unset($baseObject);

		// add related object
		if ($this->map) {
			foreach ($relatedObjects as $relatedObjectId => $relatedObject) {
				if (isset($this->map[$relatedObjectId])) {
					foreach ($this->map[$relatedObjectId] as $baseObjectId) {
						if (isset($baseObjects[$baseObjectId])) {
							$baseObjects[$baseObjectId][$name] = $relatedObjects[$relatedObjectId];
						}
					}
				}
			}
		}

		return $baseObjects;
	}
}
