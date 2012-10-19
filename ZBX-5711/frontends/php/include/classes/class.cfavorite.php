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
 * Description of CFavorite
 *
 * @author TomTom
 */
class CFavorite {

	// cache for favorite values
	// $cache[idx][]['value']
	// $cache[idx][]['source']
	private static $cache = null;

	public static function get($idx) {

		// return values if cached
		if (isset(CFavorite::$cache[$idx])) {
			return CFavorite::$cache[$idx];
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
		CFavorite::$cache[$idx] = $result;

		return $result;
	}

	public static function add($idx, $favid, $favobj = null) {
		if (CFavorite::in($idx, $favid, $favobj)) {
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
		if (isset(CFavorite::$cache[$idx])) {
			CFavorite::$cache[$idx][] = array(
				'value' => $values['idx'],
				'source' =>( isset($values['source']) ? $values['source'] : null)
			);
		}

		return DBend(DBexecute('INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')'));
	}

	public static function remove($idx, $favid = 0, $favobj = null) {

		// remove from cache
		CFavorite::$cache[$idx] = null;

		return DBexecute(
			'DELETE FROM profiles'.
			' WHERE userid='.CWebUser::$data['userid'].
				' AND idx='.zbx_dbstr($idx).
				($favid > 0 ? ' AND value_id='.$favid : '').
				(is_null($favobj) ? '' : ' AND source='.zbx_dbstr($favobj))
		);
	}

	public static function in($idx, $favid, $favobj = null) {
		$favorites = CFavorite::get($idx);
		foreach ($favorites as $favorite) {
			if (bccomp($favid, $favorite['value']) == 0 && $favorite['source'] == $favobj) {
				return true;
			}
		}
		return false;
	}
}