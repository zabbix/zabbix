<?php declare(strict_types = 1);

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


class CTabFilterProfile {

	/**
	 * Profile Idx value base, additional settings will be used with this string as prefix.
	 *
	 * @var array
	 */
	protected $namespace;

	/**
	 * Index of selected tab, zero when no tabs are selected.
	 *
	 * @var int
	 */
	public $selected;

	/**
	 * Is selected tab expanded.
	 *
	 * @var bool
	 */
	public $expanded;

	/**
	 * Array of tabs filter arrays.
	 * Tab filter properties:
	 * @property string []['name']          Tab label.
	 * @property bool   []['show_counter']  Show count of results within tab label when tab is collapsed.
	 * @property array  []['filter']        Array of filter fields.
	 *
	 * @var array
	 */
	public $tabfilters;

	/**
	 * Array of filter field default values.
	 *
	 * @var array
	 */
	protected $filter_defaults;

	public function __construct($idx) {
		$this->namespace = $idx;
		$this->tabfilters = [];
		$this->filter_defaults = [];
		$this->selected = 0;
		$this->expanded = false;
	}

	/**
	 * Set filter fields default values.
	 *
	 * @param array $defaults  Key value map of field defaults.
	 */
	public function setFilterDefaults(array $defaults) {
		$this->filter_defaults = $defaults;

		return $this;
	}

	/**
	 * Set filter fields values of specific tab, filter out values equal to default value.
	 *
	 * @param int   $index  Tab index to modify.
	 * @param array $input  Fields values.
	 */
	public function setTabFilter($index, array $input) {
		$input = array_intersect_key($input, $this->filter_defaults);
		$this->tabfilters[$index]['filter'] = CArrayHelper::unsetEqualValues($input, $this->filter_defaults);

		return $this;
	}

	/**
	 * Get filter fields values of specific tab. For non existing tab will return defaults.
	 *
	 * @param int $index  Tab index.
	 *
	 * @return array
	 */
	public function getTabFilter($index): array {
		return array_key_exists($index, $this->tabfilters)
			? $this->tabfilters[$index]['filter'] + $this->filter_defaults
			: $this->filter_defaults;
	}

	/**
	 * Get array of all tabfilters with tab filter data enriched with default values.
	 *
	 * @return array
	 */
	public function getTabsWithDefaults(): array {
		$tabfilters = [];

		foreach ($this->tabfilters as $tabfilter) {
			$tabfilter['filter'] += $this->filter_defaults;
			$tabfilters[] = $tabfilter;
		}

		return $tabfilters;
	}

	/**
	 * Order tabfilters value according $taborder string. It should contain comma separated list of $tabfilter indexes.
	 * Tabs not in $taborder will be removed.
	 *
	 * @param string $taborder  Comma separated string of tab indexes.
	 */
	public function sort(string $taborder) {
		$source = $this->tabfilters;
		$this->tabfilters = [];
		$taborder = explode(',', $taborder);

		foreach ($taborder as $index) {
			if (array_key_exists($index, $source)) {
				$this->tabfilters[$index] = $source[$index];
			}
		}

		$selected = array_search($this->selected, $taborder);

		if ($selected !== false) {
			$this->selected = $selected;
		}

		return $this;
	}

	/**
	 * Read profile from database.
	 */
	public function read() {
		$this->selected = (int) CProfile::get($this->namespace.'.selected', 0);
		$this->expanded = (bool) CProfile::get($this->namespace.'.expanded', true);
		// CProfile::updateArray assign new idx2 values do not need to store order in profile
		$this->tabfilters = CProfile::getArray($this->namespace.'.properties', []);

		foreach ($this->tabfilters as &$tabfilter) {
			$tabfilter = json_decode($tabfilter, true);
		}
		unset($tabfilter);

		return $this;
	}

	/**
	 * Update profile in database.
	 */
	public function update() {
		$tabfilters = $this->getTabFiltersProperties();

		CProfile::updateArray($this->namespace.'.properties', array_map('json_encode', $tabfilters), PROFILE_TYPE_STR);
		CProfile::update($this->namespace.'.selected', $this->selected, PROFILE_TYPE_INT);
		CProfile::update($this->namespace.'.expanded', $this->expanded, PROFILE_TYPE_INT);

		return $this;
	}

	/**
	 * Get tab filters data only for fields having non default value.
	 *
	 * @return array
	 */
	protected function getTabFiltersProperties(): array {
		$tabfilters = $this->tabfilters;

		if ($this->filter_defaults) {
			foreach ($tabfilters as &$tabfilter) {
				$tabfilter['filter'] = CArrayHelper::unsetEqualValues($tabfilter['filter'], $this->filter_defaults);
			}
			unset($tabfilter);
		}

		return $tabfilters;
	}
}
