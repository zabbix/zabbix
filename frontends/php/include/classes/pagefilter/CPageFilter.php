<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class to operate with minimum severity dropdown.
 */
class CPageFilter {

	/**
	 * Objects present in the filter.
	 *
	 * @var array
	 */
	protected $data = [
		'severitiesMin' => []
	];

	/**
	 * Selected objects IDs.
	 *
	 * @var array
	 */
	protected $ids = [
		'severityMin' => null
	];

	/**
	 * User profile keys to be used when remembering the selected values.
	 *
	 * @var array
	 */
	private $_profileIdx = [
		'severityMin' => 'web.maps.severity_min'
	];

	/**
	 * IDs of specific objects to be selected.
	 *
	 * @var array
	 */
	private $_profileIds = [
		'severityMin' => null
	];

	/**
	 * Get value from $data or $ids arrays.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		elseif (array_key_exists($name, $this->ids)) {
			return $this->ids[$name];
		}
		else {
			trigger_error(_s('Try to read inaccessible property "%s".', get_class($this).'->'.$name), E_USER_WARNING);

			return false;
		}
	}

	/**
	 * Initialize minimum trigger severity filter.
	 *
	 * @param array         $options
	 * @param array         $options['severitiesMin']
	 * @param int           $options['severitiesMin']['default']
	 * @param int           $options['severitiesMin']['mapid']
	 * @param int|nullable  $options['severityMin']
	 */
	public function __construct(array $options = []) {
		// profiles
		$this->_getProfiles($options);

		// severities min
		if (isset($options['severitiesMin'])) {
			$this->_initSeveritiesMin($options['severityMin'], $options['severitiesMin']);
		}
	}

	/**
	 * Retrieve min severity stored in user profile.
	 *
	 * @param array  $options['severitiesMin']
	 * @param int    $options['severitiesMin']['mapid']
	 */
	private function _getProfiles(array $options) {
		$this->_profileIdx['severityMin'] = 'web.maps.severity_min';

		$mapid = (array_key_exists('severitiesMin', $options) && array_key_exists('mapid', $options['severitiesMin']))
			? $options['severitiesMin']['mapid']
			: null;

		$this->_profileIds['severityMin'] = CProfile::get($this->_profileIdx['severityMin'], null, $mapid);
	}

	/**
	 * Initialize minimum trigger severities.
	 *
	 * @param string|nullable  $severity_min		minimum severity
	 * @param array            $options				array of options
	 * @param int              $options['default']	default severity
	 * @param string           $options['mapid']	ID of a map
	 */
	private function _initSeveritiesMin($severity_min, array $options) {
		$default = isset($options['default']) ? $options['default'] : TRIGGER_SEVERITY_NOT_CLASSIFIED;
		$mapid = isset($options['mapid']) ? $options['mapid'] : 0;
		$severity_min_profile = isset($this->_profileIds['severityMin']) ? $this->_profileIds['severityMin'] : null;
		$config = select_config();

		if ($severity_min === null && $severity_min_profile !== null) {
			$severity_min = $severity_min_profile;
		}

		if ($severity_min !== null) {
			if ($severity_min == $default) {
				CProfile::delete($this->_profileIdx['severityMin'], $mapid);
			}
			else {
				CProfile::update($this->_profileIdx['severityMin'], $severity_min, PROFILE_TYPE_INT, $mapid);
			}
		}

		$this->data['severitiesMin'] = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_name = getSeverityName($severity, $config);

			$this->data['severitiesMin'][] = ($severity == $default)
				? $severity_name.' ('._('default').')'
				: $severity_name;
		}

		$this->ids['severityMin'] = ($severity_min === null) ? $default : $severity_min;
	}

	/**
	 * Get minimum trigger severities combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getSeveritiesMinCB() {
		return new CComboBox('severity_min', $this->severityMin, 'javascript: submit();', $this->severitiesMin);
	}
}
