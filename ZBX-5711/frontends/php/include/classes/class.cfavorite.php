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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Container class for favorite value management.
 * Uses caching.
 */
class CFavorite {

	// cache for favorite values
	// $cache[idx][]['value']
	// $cache[idx][]['source']
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

		$result = array();
		$db_profiles = DBselect(
			'SELECT p.value_id,p.source'.
			' FROM profiles p'.
			' WHERE p.userid='.CWebUser::$data['userid'].
				' AND p.idx='.zbx_dbstr($idx).
			' ORDER BY p.profileid'
		);
		while ($profile = DBfetch($db_profiles)) {
			$result[] = array('value' => $profile['value_id'], 'source' => $profile['source']);
		}

		// store db values in cache
		self::$cache[$idx] = $result;

		return $result;
	}

	/**
	 * Adds favorite value to DB.
	 *
	 * @param string $idx identifier of favorite value group
	 * @param int $favid value id
	 * @param string $favobj source object
	 *
	 * @return bool did SQL INSERT succeeded
	 */
	public static function add($idx, $favid, $favobj = null) {
		if (self::in($idx, $favid, $favobj)) {
			return true;
		}

		DBstart();
		$values = array(
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => CWebUser::$data['userid'],
			'idx' => zbx_dbstr($idx),
			'value_id' => $favid,
			'type' => PROFILE_TYPE_ID
		);
		if (!is_null($favobj)) {
			$values['source'] = zbx_dbstr($favobj);
		}

		// add to cache only if cache is created
		if (isset(self::$cache[$idx])) {
			self::$cache[$idx][] = array(
				'value' => $values['idx'],
				'source' =>( isset($values['source']) ? $values['source'] : null)
			);
		}

		return DBend(DBexecute('INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')'));
	}

	/**
	 * Removes favorite from DB. Clears cache by $idx.
	 *
	 * @param string $idx identifier of favorite value group
	 * @param int $favid value id
	 * @param string $favobj source object
	 *
	 * @return boolean did SQL DELETE succeeded
	 */
	public static function remove($idx, $favid = 0, $favobj = null) {

		// remove from cache
		if ($favid == 0 && $favobj === null) {
			self::$cache[$idx] = array();
		}
		else {
			self::$cache[$idx] = null;
		}

		return DBexecute(
			'DELETE FROM profiles'.
			' WHERE userid='.CWebUser::$data['userid'].
				' AND idx='.zbx_dbstr($idx).
				($favid > 0 ? ' AND value_id='.$favid : '').
				(is_null($favobj) ? '' : ' AND source='.zbx_dbstr($favobj))
		);
	}

	/**
	 * Cheks wether exists favorite value.
	 *
	 * @param string $idx identifier of favorite value group
	 * @param int $favid value id
	 * @param string $favobj source object
	 *
	 * @return boolean
	 */
	public static function in($idx, $favid, $favobj = null) {
		$favorites = self::get($idx);
		foreach ($favorites as $favorite) {
			if (bccomp($favid, $favorite['value']) == 0 && $favorite['source'] == $favobj) {
				return true;
			}
		}
		return false;
	}
}