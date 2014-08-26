<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CProfile {

	private static $userDetails = array();
	private static $profiles = null;
	private static $update = array();
	private static $insert = array();
	private static $stringProfileMaxLength;

	public static function init() {
		self::$userDetails = CWebUser::$data;
		self::$profiles = array();

		$profilesTableSchema = DB::getSchema('profiles');
		self::$stringProfileMaxLength = $profilesTableSchema['fields']['value_str']['length'];

		$db_profiles = DBselect(
			'SELECT p.*'.
			' FROM profiles p'.
			' WHERE p.userid='.self::$userDetails['userid'].
			' ORDER BY p.userid,p.profileid'
		);
		while ($profile = DBfetch($db_profiles)) {
			$value_type = self::getFieldByType($profile['type']);

			if (!isset(self::$profiles[$profile['idx']])) {
				self::$profiles[$profile['idx']] = array();
			}
			self::$profiles[$profile['idx']][$profile['idx2']] = $profile[$value_type];
		}
	}

	/**
	 * Check if data needs to be inserted or updated.
	 *
	 * @return bool
	 */
	public static function isModified() {
		return (self::$insert || self::$update);
	}

	public static function flush() {
		$result = false;

		if (self::$profiles !== null && self::$userDetails['userid'] > 0 && self::isModified()) {
			$result = true;

			foreach (self::$insert as $idx => $profile) {
				foreach ($profile as $idx2 => $data) {
					$result &= self::insertDB($idx, $data['value'], $data['type'], $idx2);
				}
			}

			ksort(self::$update);
			foreach (self::$update as $idx => $profile) {
				ksort($profile);
				foreach ($profile as $idx2 => $data) {
					$result &= self::updateDB($idx, $data['value'], $data['type'], $idx2);
				}
			}
		}

		return $result;
	}

	public static function clear() {
		self::$insert = array();
		self::$update = array();
	}

	public static function get($idx, $default_value = null, $idx2 = 0) {
		// no user data available, just return the default value
		if (!CWebUser::$data) {
			return $default_value;
		}

		if (is_null(self::$profiles)) {
			self::init();
		}

		if (isset(self::$profiles[$idx][$idx2])) {
			return self::$profiles[$idx][$idx2];
		}
		else {
			return $default_value;
		}
	}

	/**
	 * Returns the values stored under the given $ids as an array.
	 *
	 * @param string    $idx
	 * @param mixed     $defaultValue
	 *
	 * @return mixed
	 */
	public static function getArray($idx, $defaultValue = null) {
		if (self::get($idx, null, 0) === null) {
			return $defaultValue;
		}

		$i = 0;
		$values = array();
		while (self::get($idx, null, $i) !== null) {
			$values[] = self::get($idx, null, $i);

			$i++;
		}

		return $values;
	}

	/**
	 * Removes profile values from DB and profiles cache.
	 *
	 * @param string 		$idx	first identifier
	 * @param int|array  	$idx2	second identifier, which can be list of identifiers as well
	 */
	public static function delete($idx, $idx2 = 0) {
		if (is_null(self::$profiles)) {
			self::init();
		}

		if (!isset(self::$profiles[$idx])) {
			return;
		}

		// pick existing Idx2
		$deleteIdx2 = array();
		foreach ((array) $idx2 as $checkIdx2) {
			if (isset(self::$profiles[$idx][$checkIdx2])) {
				$deleteIdx2[] = $checkIdx2;
			}
		}

		if (!$deleteIdx2) {
			return;
		}

		// remove from DB
		self::deleteValues($idx, $deleteIdx2);

		// remove from cache
		foreach ($deleteIdx2 as $v) {
			unset(self::$profiles[$idx][$v]);
		}
		if (!self::$profiles[$idx]) {
			unset(self::$profiles[$idx]);
		}
	}

	/**
	 * Removes all values stored under the given idx.
	 *
	 * @param string $idx
	 */
	public static function deleteIdx($idx) {
		if (self::$profiles === null) {
			self::init();
		}

		if (!isset(self::$profiles[$idx])) {
			return;
		}

		self::deleteValues($idx, array_keys(self::$profiles[$idx]));
		unset(self::$profiles[$idx]);
	}

	/**
	 * Deletes the given values from the DB.
	 *
	 * @param string 	$idx
	 * @param array 	$idx2
	 */
	protected static function deleteValues($idx, array $idx2) {
		// remove from DB
		DB::delete('profiles', array('idx' => $idx, 'idx2' => $idx2));
	}

	/**
	 * Update favorite values in DB profiles table.
	 *
	 * @param string	$idx		max length is 96
	 * @param mixed		$value		max length 255 for string
	 * @param int		$type
	 * @param int		$idx2
	 */
	public static function update($idx, $value, $type, $idx2 = 0) {
		if (is_null(self::$profiles)) {
			self::init();
		}

		if (!self::checkValueType($value, $type)) {
			return;
		}

		$profile = array(
			'idx' => $idx,
			'value' => $value,
			'type' => $type,
			'idx2' => $idx2
		);

		$current = self::get($idx, null, $idx2);
		if (is_null($current)) {
			if (!isset(self::$insert[$idx])) {
				self::$insert[$idx] = array();
			}
			self::$insert[$idx][$idx2] = $profile;
		}
		else {
			if ($current != $value) {
				if (!isset(self::$update[$idx])) {
					self::$update[$idx] = array();
				}
				self::$update[$idx][$idx2] = $profile;
			}
		}

		if (!isset(self::$profiles[$idx])) {
			self::$profiles[$idx] = array();
		}

		self::$profiles[$idx][$idx2] = $value;
	}

	/**
	 * Stores an array in the profiles.
	 *
	 * Each value is stored under the given idx and a sequentially generated idx2.
	 *
	 * @param string    $idx
	 * @param array     $values
	 * @param int       $type
	 */
	public static function updateArray($idx, array $values, $type) {
		// save new values
		$i = 0;
		foreach ($values as $value) {
			self::update($idx, $value, $type, $i);

			$i++;
		}

		// delete remaining old values
		$idx2 = array();
		while (self::get($idx, null, $i) !== null) {
			$idx2[] = $i;

			$i++;
		}

		self::delete($idx, $idx2);
	}

	private static function insertDB($idx, $value, $type, $idx2) {
		$value_type = self::getFieldByType($type);

		$values = array(
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => self::$userDetails['userid'],
			'idx' => zbx_dbstr($idx),
			$value_type => zbx_dbstr($value),
			'type' => $type,
			'idx2' => $idx2
		);

		return DBexecute('INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')');
	}

	private static function updateDB($idx, $value, $type, $idx2) {
		$sqlIdx2 = ($idx2 > 0) ? ' AND idx2='.zbx_dbstr($idx2) : '';

		$valueType = self::getFieldByType($type);

		return DBexecute(
			'UPDATE profiles SET '.
			$valueType.'='.zbx_dbstr($value).','.
			' type='.$type.
			' WHERE userid='.self::$userDetails['userid'].
			' AND idx='.zbx_dbstr($idx).
			$sqlIdx2
		);
	}

	private static function getFieldByType($type) {
		switch ($type) {
			case PROFILE_TYPE_INT:
				$field = 'value_int';
				break;
			case PROFILE_TYPE_STR:
				$field = 'value_str';
				break;
			case PROFILE_TYPE_ID:
			default:
				$field = 'value_id';
		}

		return $field;
	}

	private static function checkValueType($value, $type) {
		switch ($type) {
			case PROFILE_TYPE_ID:
				return zbx_ctype_digit($value);
			case PROFILE_TYPE_INT:
				return zbx_is_int($value);
			case PROFILE_TYPE_STR:
				return mb_strlen($value) <= self::$stringProfileMaxLength;
			default:
				return true;
		}
	}
}
