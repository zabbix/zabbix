<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class to manage filter tabs.
 */
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
	 * Global time range start.
	 */
	public $from;

	/**
	 * Global time range end.
	 */
	public $to;

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
	 * Array of tabs filter arrays with default data but without user modified input.
	 * Tab filter properties:
	 * @property string []['filter_name']          Tab label.
	 * @property int    []['filter_show_counter']  Show count of results within tab label when tab is collapsed.
	 * @property int    []['filter_custom_time']   Use custom time range.
	 *
	 * @var array
	 */
	public $db_tabfilters;

	/**
	 * Array of filter field default values.
	 *
	 * @var array
	 */
	public $filter_defaults;

	public function __construct($idx, array $filter_defaults) {
		$this->namespace = $idx;
		$this->tabfilters = [];
		$filter_defaults = array_intersect_key([
			'from' => 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT),
			'to' => 'now'
		], $filter_defaults) + $filter_defaults;
		$this->filter_defaults = $filter_defaults + [
			'filter_name' => _('Untitled'),
			'filter_show_counter' => 0,
			'filter_custom_time' => 0
		];
		$this->selected = 0;
		$this->expanded = false;
	}

	/**
	 * Create filter tab from controller input. Set default values.
	 *
	 * @param array $input  Controller input.
	 *
	 * @return array
	 */
	public function createFilterTab(array $input): array {
		$filter = array_intersect_key($input, $this->filter_defaults) + $this->filter_defaults;

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
		$data = array_key_exists($index, $this->tabfilters)
			? $this->tabfilters[$index] + $this->filter_defaults
			: $this->filter_defaults;

		if (!$data['filter_custom_time']) {
			$data['from'] = $this->from;
			$data['to'] = $this->to;
		}

		return $data;
	}

	/**
	 * Get array of all tabfilters with tab filter data enriched with default values.
	 *
	 * @return array
	 */
	public function getTabsWithDefaults(): array {
		$tabfilters = array_map([$this, 'getTabFilter'], range(0, count($this->tabfilters) - 1));

		if (array_key_exists($this->selected, $this->db_tabfilters)) {
			$tabfilters[$this->selected]['filter_src'] = $this->db_tabfilters[$this->selected] + $this->filter_defaults;
		}

		return $tabfilters;
	}

	/**
	 * Update selected tab filter properties from $input array. Active filter selection:
	 * - If no filter_name passed in input select last opened filter.
	 * - If filter_name is empty select home filter (is used for hotlinking from other pages to home filter)
	 * - If filter_name does not exists in stored filters, select home filer
	 * - If filter_name exists and is not unique among stored filters, select last opened filter if it name match else
	 *   select first filter from matched filters list.
	 *
	 * @param array $input  Tab filter properties array.
	 */
	public function setInput(array $input) {
		$index = array_key_exists('filter_name', $input) ? 0 : $this->selected;
		$input += ['filter_name' => $this->tabfilters[$index]['filter_name']];
		$name_indexes = [];

		foreach (array_slice($this->tabfilters, 1, null, true) as $index => $tabfilter) {
			if ($tabfilter['filter_name'] === $input['filter_name']) {
				$name_indexes[] = $index;
			}
		}

		if (!$name_indexes) {
			$name_indexes = [0];
		}

		if (!in_array($this->selected, $name_indexes)) {
			$this->selected = reset($name_indexes);
			$this->update();
		}

		$filter = $this->tabfilters[$this->selected];
		$sorting = array_intersect_key($filter, ['sort' => '', 'sortorder' => '']);
		$input_sorting = array_intersect_key($input, ['sort' => '', 'sortorder' => '']);

		if ($sorting && array_diff_assoc($sorting, $input_sorting)) {
			$this->tabfilters[$this->selected] = array_merge($filter, $input_sorting);
			$this->update();
		}

		$input += array_merge($this->filter_defaults, $this->tabfilters[$this->selected]);
		$input['filter_show_counter'] = (int) $input['filter_show_counter'];
		$input['filter_custom_time'] = (int) $input['filter_custom_time'];
		$this->tabfilters[$this->selected] = $input;
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
		$from = array_key_exists('from', $this->filter_defaults) ? $this->filter_defaults['from'] : null;
		$to = array_key_exists('to', $this->filter_defaults) ? $this->filter_defaults['to'] : null;

		/*
		 * It is impossible to define "from" as is set system wide default value because constant cannot contain
		 * calculated values. Therefore empty string "from" or "to" value will be initialized equal system wide
		 * defined default value.
		 */

		if ($from === '') {
			$from = 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT);
		}

		if ($to === '') {
			$to = 'now';
		}

		$this->from = CProfile::get($this->namespace.'.from', $from);
		$this->to = CProfile::get($this->namespace.'.to', $to);
		$this->selected = (int) CProfile::get($this->namespace.'.selected', 0);
		$this->expanded = (bool) CProfile::get($this->namespace.'.expanded', true);
		// CProfile::updateArray assign new idx2 values do not need to store order in profile
		$this->tabfilters = CProfile::getArray($this->namespace.'.properties', []);

		foreach ($this->tabfilters as &$tabfilter) {
			$tabfilter = json_decode($tabfilter, true);
		}
		unset($tabfilter);

		if (!$this->tabfilters) {
			$this->tabfilters[] = ['filter_name' => ''];
		}

		$this->db_tabfilters = $this->tabfilters;

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

				if (array_key_exists('filter_show_counter', $tabfilter)) {
					$tabfilter['filter_show_counter'] = (int) $tabfilter['filter_show_counter'];
				}

				if (array_key_exists('filter_custom_time', $tabfilter)) {
					$tabfilter['filter_custom_time'] = (int) $tabfilter['filter_custom_time'];
				}
			}
			unset($tabfilter);
		}

		return $tabfilters;
	}
}
