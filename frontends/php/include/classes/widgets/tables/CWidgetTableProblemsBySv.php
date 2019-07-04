<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Table generation for Problems By Severity widget.
 *
 * Class CWidgetTablesProblemsBySv
 */
class CWidgetTableProblemsBySv extends CWidgetTable {
	/**
	 * Widget filters.
	 *
	 * @var array
	 */
	protected $filter;

	/**
	 * Widget data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Severity names.
	 *
	 * @var array
	 */
	protected $severity_names;

	/**
	 * Url to return from popup.
	 *
	 * @var string
	 */
	protected $backurl;

	/**
	 * CUrl to group problems.
	 *
	 * @var CUrl
	 */
	protected $groupurl;

	/**
	 * Way of showing problems (EXTACK_OPTION_ALL, EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH).
	 *
	 * @var integer
	 */
	protected $ext_ack;

	/**
	 * Hide empty groups filter.
	 *
	 * @var boolean
	 */
	protected $filter_hide_empty_groups;

	/**
	 * Totals mode.
	 *
	 * @var boolean
	 */
	protected $total = false;

	/**
	 * @param array  $filter
	 * @param array  $filter['hostids']            (optional)
	 * @param string $filter['problem']            (optional)
	 * @param array  $filter['severities']         (optional)
	 * @param int    $filter['show_suppressed']    (optional)
	 * @param int    $filter['hide_empty_groups']  (optional)
	 * @param int    $filter['ext_ack']            (optional)
	 * @param int    $filter['show_timeline']      (optional)
	 * @param array  $data
	 * @param array  $data['groups']
	 * @param string $data['groups'][]['groupid']
	 * @param string $data['groups'][]['name']
	 * @param bool   $data['groups'][]['has_problems']
	 * @param array  $data['groups'][]['stats']
	 * @param int    $data['groups'][]['stats']['count']
	 * @param array  $data['groups'][]['stats']['problems']
	 * @param string $data['groups'][]['stats']['problems'][]['eventid']
	 * @param string $data['groups'][]['stats']['problems'][]['objectid']
	 * @param int    $data['groups'][]['stats']['problems'][]['clock']
	 * @param int    $data['groups'][]['stats']['problems'][]['ns']
	 * @param int    $data['groups'][]['stats']['problems'][]['acknowledged']
	 * @param array  $data['groups'][]['stats']['problems'][]['tags']
	 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['tag']
	 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['value']
	 * @param int    $data['groups'][]['stats']['count_unack']
	 * @param array  $data['groups'][]['stats']['problems_unack']
	 * @param array  $data['triggers']
	 * @param string $data['triggers'][<triggerid>]['expression']
	 * @param string $data['triggers'][<triggerid>]['description']
	 * @param array  $data['triggers'][<triggerid>]['hosts']
	 * @param string $data['triggers'][<triggerid>]['hosts'][]['name']
	 * @param array  $config
	 * @param string $config['severity_name_*']
	 * @param string $backurl
	 *
	 */
	public function __construct(array $filter = [], array $data = [], array $config = [], $backurl = '') {
		parent::__construct($data['groups']);

		$this->filter = $filter;
		$this->data = $data;
		$this->severity_names = $config;
		$this->backurl = $backurl;
		$this->ext_ack = array_key_exists('ext_ack', $filter) ? $filter['ext_ack'] : EXTACK_OPTION_ALL;
	}

	/**
	 * Generate Widget Table
	 *
	 * @return CTableInfo
	 */
	public function getTable() {
		if ($this->filter['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS) {
			return $this->getSeverityTotals();
		}
		else {
			return $this->getSeveritiesByGroops();
		}
	}

	private function getSeveritiesByGroops() {
		$filter_severities = (array_key_exists('severities', $this->filter) && $this->filter['severities'])
			? $this->filter['severities']
			: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
		$this->filter_hide_empty_groups = array_key_exists('hide_empty_groups', $this->filter)
			? $this->filter['hide_empty_groups']
			: 0;

		$header = [[_('Host group'), (new CSpan())->addClass(ZBX_STYLE_ARROW_UP)]];

		for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
			if (in_array($severity, $filter_severities)) {
				$header[] = getSeverityName($severity, $this->severity_names);
			}
		}

		$this->setTable(null, ['header' => $header, 'heading_column' => 0]);

		$this->groupurl = (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_set', 1)
			->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
			->setArgument('filter_groupids', null)
			->setArgument('filter_hostids',
				array_key_exists('hostids', $this->filter) ? $this->filter['hostids'] : null
			)
			->setArgument('filter_name', array_key_exists('problem', $this->filter) ? $this->filter['problem'] : null)
			->setArgument('filter_show_suppressed',
				(array_key_exists('show_suppressed', $this->filter) && $this->filter['show_suppressed'] == 1)
					? 1
					: null
			);

		$this->populateNormalTable();

		return $this->table;
	}

	private function getSeverityTotals() {
		$this->total = true;
		$this->setData($this->aggregateData($this->data['groups']));

		$this->setTable([
			ZBX_STYLE_BY_SEVERITY_WIDGET,
			($this->filter['layout'] == STYLE_HORIZONTAL)
				? ZBX_STYLE_BY_SEVERITY_LAYOUT_HORIZONTAL
				: ZBX_STYLE_BY_SEVERITY_LAYOUT_VERTICAL,
		]);

		if ($this->filter['layout'] == STYLE_HORIZONTAL) {
			$this->populateNormalTable();
		}
		else {
			$this->populateVerticalTotalTable();
		}

		return $this->table;
	}

	/**
	 * Row initialization.
	 *
	 * @param array $group  Group data array for initialization
	 * @return array
	 */
	protected function initRow(array $group) {
		if ($this->total) {
			return [];
		}
		else {
			$this->groupurl->setArgument('filter_groupids', [$group['groupid']]);
			return [new CLink($group['name'], $this->groupurl->getUrl())];
		}
	}

	/**
	 * Extract severities list from group.
	 *
	 * @param array $group  Group data array.
	 * @return array
	 */
	protected function extractRowElements(array $group) {
		return $group['stats'];
	}

	/**
	 * Filter groups.
	 *
	 * @param array $group  Group data array.
	 * @return boolean
	 */
	protected function rowFilter(array $group) {
		if ($this->filter_hide_empty_groups && !$group['has_problems']) {
			// Skip row.
			return false;
		}

		return true;
	}

	/**
	 * Filter cells.
	 *
	 * @param int   $severity  Severity code.
	 * @param array $stat      Problem info with this severity.
	 * @return boolean
	 */
	protected function cellFilter($severity, array $stat) {
		if ($this->filter['severities'] && !in_array($severity, $this->filter['severities'])) {
			// Skip cell.
			return false;
		}
		if (!$this->total && $stat['count'] == 0 && $stat['count_unack'] == 0) {
			// Add empty cell.
			$this->row[] = '';
			return false;
		}

		return true;
	}

	/**
	 * Get table cell with this severity.
	 *
	 * @param int   $severity  Severity code.
	 * @param array $stat      Problem info with this severity.
	 * @return CCol
	 */
	protected function getTableCell($severity, array $stat) {

			$severity_name = $this->total ? ' '.getSeverityName($severity, $this->severity_names) : '';

			$allTriggersNum = $stat['count'];
			if ($allTriggersNum) {
				$allTriggersNum = (new CLinkAction($allTriggersNum))
					->setHint(makeProblemsPopup($stat['problems'], $this->data['triggers'], $this->backurl,
						$this->data['actions'], $this->severity_names, $this->filter
					));
			}

			$unackTriggersNum = $stat['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = (new CLinkAction($unackTriggersNum))
					->setHint(makeProblemsPopup($stat['problems_unack'], $this->data['triggers'], $this->backurl,
						$this->data['actions'], $this->severity_names, $this->filter
					));
			}

			switch ($this->ext_ack) {
				case EXTACK_OPTION_ALL:
					$cell = getSeverityCell($severity, null, [new CSpan($allTriggersNum), $severity_name]);
					break;

				case EXTACK_OPTION_UNACK:
					$cell = getSeverityCell($severity, null, [new CSpan($unackTriggersNum), $severity_name]);
					break;

				case EXTACK_OPTION_BOTH:
					if ($stat['count_unack'] != 0 || $this->total) {
						$cell = getSeverityCell($severity, $this->severity_names, [
							new CSpan([$unackTriggersNum, ' '._('of').' ', $allTriggersNum]), $severity_name
						]);
					}
					else {
						$cell = getSeverityCell($severity, $this->severity_names, [$allTriggersNum, $severity_name]);
					}
					break;
			}

		return $cell;
	}

	/**
	 * Aggregate data for totals.
	 *
	 * @return array
	 */
	private function aggregateData() {
		$goups_info = [
			0 => [
				'groupid' => 0,
				'name' => 'Totals',
				'stats' => []
			]
		];

		foreach (array_reverse($this->severity_names) as $key => $value) {
			$i = explode('_', $key)[2];
			$goups_info[0]['stats'][$i] = [
				'count' => 0,
				'problems' => [],
				'count_unack' => 0,
				'problems_unack' => []
			];
		}

		foreach ($this->data['groups'] as $group) {
			foreach ($group['stats'] as $severity => $stat) {
				$goups_info[0]['stats'][$severity]['count'] += $stat['count'];
				foreach ($stat['problems'] as $problem) {
					$goups_info[0]['stats'][$severity]['problems'][] = $problem;
				}
				$goups_info[0]['stats'][$severity]['count_unack'] += $stat['count_unack'];
				foreach ($stat['problems_unack'] as $problem) {
					$goups_info[0]['stats'][$severity]['problems_unack'][] = $problem;
				}
			}
		}

		return $goups_info;
	}
}
