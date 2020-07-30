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
	 * @property string []['filter_name']          Tab label.
	 * @property int    []['filter_show_counter']  Show count of results within tab label when tab is collapsed.
	 * @property int    []['filter_custom_time']   Use custom time range.
	 *
	 * @var array
	 */
	public $tabfilters;

	/**
	 * Array of filter field default values.
	 *
	 * @var array
	 */
	public $filter_defaults;

	public function __construct($idx, array $filter_defaults) {
		$this->namespace = $idx;
		$this->tabfilters = [];
		$this->filter_defaults = $filter_defaults + [
			'filter_name' => _('Untitled'),
			'filter_show_counter' => 0,
			'filter_custom_time' => 0,
		];
		$this->selected = 0;
		$this->expanded = false;
	}

	/**
	 * Create filter tab from controller input. Set default values.
	 *
	 * @param array $input  Controller input.
	 */
	public function createFilterTab(array $input): array {
		$filter = array_intersect_key($input, $this->filter_defaults) + $this->filter_defaults;
		$filter['filter_show_counter'] = (int) $filter['filter_show_counter'];
		$filter['filter_custom_time'] = (int) $filter['filter_custom_time'];

		return $filter;
	}

	/**
	 * Set filter fields values of specific tab, filter out values equal to default value.
	 *
	 * @param int   $index  Tab index to modify.
	 * @param array $input  Fields values.
	 */
	public function setTabFilter($index, array $input) {
		$input = array_intersect_key($input, $this->filter_defaults);
		$this->tabfilters[$index] = $input;
		$this->selected = $index;

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
			? $this->tabfilters[$index] + $this->filter_defaults
			: $this->filter_defaults;
	}

	/**
	 * Get array of all tabfilters with tab filter data enriched with default values.
	 *
	 * @return array
	 */
	public function getTabsWithDefaults(): array {
		$tabfilters = [];

		if (!$this->tabfilters) {
			$tabfilters[] = $this->createFilterTab([]);
		}

		foreach ($this->tabfilters as $tabfilter) {
			$tabfilters[] = $tabfilter + $this->filter_defaults;
		}

		return $tabfilters;
	}

	/**
	 * Update selected tab filter properties from $input array. If $input contains filter name will set tab filter
	 * having this name or create new if tab with such name does not exists.
	 *
	 * @param array $input  Tab filter properties array.
	 */
	public function setInput(array $input) {
		if (array_key_exists('filter_name', $input)) {
			$name_index = array_search($input['filter_name'], array_column($this->tabfilters, 'filter_name'));

			if ($name_index === false) {
				$this->selected = count($this->tabfilters);
				$this->tabfilters[] = $this->createFilterTab($input);
			}
			elseif ($this->selected != $name_index) {
				$this->selected = $name_index;
				$this->update();
			}
		}

		return $this;
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
	 * Delete tab filter by index, do not allow to delete home tab (index equal zero). If deleted tab was selected
	 * previous tab will be set as selected instead.
	 *
	 * @param int $index  Index of deleted tab filter, cannot be zero.
	 */
	public function deleteTab(int $index) {
		if ($index > 0) {
			unset($this->tabfilters[$index]);

			if ($this->selected == $index) {
				$this->selected = $index - 1;
			}
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

		if (!$this->tabfilters) {
			$this->tabfilters[] = $this->createFilterTab([
				'filter_name' => _('Home')
			]);
		}

		return $this;
	}

	/**
	 * Update profile in database.
	 */
	public function update() {
		$tabfilters = $this->getTabFiltersProperties();

		CProfile::updateArray($this->namespace.'.properties', array_map('json_encode', $tabfilters), PROFILE_TYPE_STR);
		CProfile::update($this->namespace.'.selected', $this->selected, PROFILE_TYPE_INT);
		CProfile::update($this->namespace.'.expanded', (int) $this->expanded, PROFILE_TYPE_INT);

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
				$tabfilter = CArrayHelper::unsetEqualValues($tabfilter, $this->filter_defaults) + [
					'filter_name' => $tabfilter['filter_name']
				];
			}
			unset($tabfilter);
		}

		return $tabfilters;
	}
}
