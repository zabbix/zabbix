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
 * Container class for favorite value management.
 * Uses caching.
 */
class CFavorite {

	/**
	 * Cache for favorite values.
	 *
	 * $cache[idx][]['value']
	 * $cache[idx][]['source']
	 */
	private static $cache = null;

	/**
	 * Returns favorite values from db. Uses caching for performance.
	 *
	 * @param string $idx identifier of favorite value group
	 *
	 * @return array list of favorite values corresponding to $idx
	 */
	public static function get($idx) {
		// return values if cached
		if (isset(self::$cache[$idx])) {
			return self::$cache[$idx];
		}

		$result = [];
		$db_profiles = DBselect(
			'SELECT p.value_id,p.source'.
			' FROM profiles p'.
			' WHERE p.userid='.CWebUser::$data['userid'].
				' AND p.idx='.zbx_dbstr($idx).
			' ORDER BY p.profileid'
		);
		while ($profile = DBfetch($db_profiles)) {
			$result[] = ['value' => $profile['value_id'], 'source' => $profile['source']];
		}

		// store db values in cache
		self::$cache[$idx] = $result;

		return $result;
	}

	/**
	 * Adds favorite value to DB.
	 *
	 * @param string $idx    identifier of favorite value group
	 * @param int    $favid  value id
	 * @param string $favobj source object
	 *
	 * @return bool did SQL INSERT succeeded
	 */
	public static function add($idx, $favid, $favobj = null) {
		if (self::exists($idx, $favid, $favobj)) {
			return true;
		}

		// add to cache only if cache is created
		if (isset(self::$cache[$idx])) {
			self::$cache[$idx][] = [
				'value' => $favid,
				'source' => $favobj
			];
		}

		$values = [
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => CWebUser::$data['userid'],
			'idx' => zbx_dbstr($idx),
			'value_id' => zbx_dbstr($favid),
			'value_str' =>zbx_dbstr(''),
			'type' => PROFILE_TYPE_ID
		];
		if (!is_null($favobj)) {
			$values['source'] = zbx_dbstr($favobj);
		}

		return DBexecute(
			'INSERT INTO profiles ('.implode(', ', array_keys($values)).')'.
			' VALUES ('.implode(', ', $values).')'
		);
	}

	/**
	 * Removes favorite from DB. Clears cache by $idx.
	 *
	 * @param string $idx    identifier of favorite value group
	 * @param int    $favid  value id
	 * @param string $favobj source object
	 *
	 * @return boolean did SQL DELETE succeeded
	 */
	public static function remove($idx, $favid = 0, $favobj = null) {
		// empty cache, we know that all $idx values will be removed in DELETE
		if ($favid == 0 && $favobj === null) {
			self::$cache[$idx] = [];
		}
		// remove from cache, cache will be rebuilt upon get()
		else {
			self::$cache[$idx] = null;
		}

		return DBexecute(
			'DELETE FROM profiles'.
			' WHERE userid='.CWebUser::$data['userid'].
				' AND idx='.zbx_dbstr($idx).
				($favid > 0 ? ' AND value_id='.zbx_dbstr($favid) : '').
				(is_null($favobj) ? '' : ' AND source='.zbx_dbstr($favobj))
		);
	}

	/**
	 * Checks whether favorite value exists.
	 *
	 * @param string $idx    identifier of favorite value group
	 * @param int    $favid  value id
	 * @param string $favobj source object
	 *
	 * @return boolean
	 */
	public static function exists($idx, $favid, $favobj = null) {
		$favorites = self::get($idx);
		foreach ($favorites as $favorite) {
			if (idcmp($favid, $favorite['value']) && $favorite['source'] == $favobj) {
				return true;
			}
		}

		return false;
	}
}
