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
	 * A hash of relation maps. Each key contains a hash of relations between specific objects types. A relation hash
	 * consists of an ID of the related object as the key, and an array of corresponding base objects as the value. That
	 * is array(relatedObjectId => array(baseObjectId2, baseObjectId2))
	 *
	 * @var array
	 */
	protected $map = array();

	/**
	 * Adds a new relation.
	 *
	 * @param string $baseObjectId
	 * @param string $name             name of the relation
	 * @param string $relatedObjectId
	 */
	public function addRelation($baseObjectId, $name, $relatedObjectId) {
		$this->map[$name][$relatedObjectId][] = $baseObjectId;
	}

	/**
	 * Returns the IDs of all related objects for the given relation.
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public function getRelatedIds($name) {
		return (isset($this->map[$name])) ? array_keys($this->map[$name]) : array();
	}

	public function mapMany($baseObjects, $relatedObjects, $name, $limit = null) {
		// create an empty array for every base object
		foreach ($baseObjects as &$baseObject) {
			$baseObject[$name] = array();
		}
		unset($baseObject);

		// add related objects
		if (isset($this->map[$name])) {
			$map = $this->map[$name];
			$count = array();
			foreach ($relatedObjects as $relatedObjectId => $relatedObject) {
				if (isset($map[$relatedObjectId])) {
					foreach ($map[$relatedObjectId] as $baseObjectId) {
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

		return $baseObjects;
	}

	public function mapOne($baseObjects, $relatedObjects, $name) {
		// create an empty array for every base object
		foreach ($baseObjects as &$baseObject) {
			$baseObject[$name] = array();
		}
		unset($baseObject);

		// add related object
		if (isset($this->map[$name])) {
			$map = $this->map[$name];
			foreach ($relatedObjects as $relatedObjectId => $relatedObject) {
				if (isset($map[$relatedObjectId])) {
					foreach ($map[$relatedObjectId] as $baseObjectId) {
						$baseObjects[$baseObjectId][$name] = $relatedObjects[$relatedObjectId];
					}
				}
			}
		}

		return $baseObjects;
	}
}
