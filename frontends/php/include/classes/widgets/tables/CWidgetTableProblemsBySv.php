<?php

/**
 * Table generation for Problems By Severity widget.
 *
 * Class CWidgetTablesProblemsBySv
 */
class CWidgetTableProblemsBySv {
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
	 * Way of showing problems (EXTACK_OPTION_ALL, EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH).
	 *
	 * @var integer
	 */
	protected $ext_ack;

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
		$this->filter = $filter;
		$this->data = $data;
		$this->severity_names = $config;
		$this->backurl = $backurl;
		$this->ext_ack = array_key_exists('ext_ack', $filter) ? $filter['ext_ack'] : EXTACK_OPTION_ALL;

		return $this;
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
		$filter_hide_empty_groups = array_key_exists('hide_empty_groups', $this->filter)
			? $this->filter['hide_empty_groups']
			: 0;

		$header = [[_('Host group'), (new CSpan())->addClass(ZBX_STYLE_ARROW_UP)]];

		for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
			if (in_array($severity, $filter_severities)) {
				$header[] = getSeverityName($severity, $this->severity_names);
			}
		}

		$table = (new CTableInfo())
			->setHeader($header)
			->setHeadingColumn(0);

		$url_group = (new CUrl('zabbix.php'))
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

		foreach ($this->data['groups'] as $group) {
			if ($filter_hide_empty_groups && !$group['has_problems']) {
				continue;
			}

			$url_group->setArgument('filter_groupids', [$group['groupid']]);
			$row = [new CLink($group['name'], $url_group->getUrl())];

			$row = $this->getGroupSeverity($row, $group, false);
			$table->addRow($row);
		}

		return $table;
	}

	private function getSeverityTotals() {
		$goups_info = $this->aggregateData($this->data['groups']);

		$table = (new CTableInfo())
			->addClass(ZBX_STYLE_BY_SEVERITY_WIDGET)
			->addClass(($this->filter['layout'] == STYLE_HORIZONTAL)
				? ZBX_STYLE_BY_SEVERITY_LAYOUT_HORIZONTAL
				: ZBX_STYLE_BY_SEVERITY_LAYOUT_VERTICAL
			);

		foreach ($goups_info as $group) {
			$row = $this->getGroupSeverity([], $group, true, $table);

			if ($this->filter['layout'] == STYLE_HORIZONTAL) {
				$table->addRow($row);
			}
		}

		return $table;
	}

	/**
	 * Generate row or column by one group or total.
	 *
	 * @param array $row          Initial value of the $row
	 * @param array $group        Group or total data
	 * @param boolean $aggregate  True for totals mode
	 * @param CTableInfo $table   Table for column generation in totals mode
	 * @return array
	 */
	private function getGroupSeverity(array $row, array $group, $aggregate, CTableInfo $table = null) {

		foreach ($group['stats'] as $severity => $stat) {

			if ($this->filter['severities'] && !in_array($severity, $this->filter['severities'])) {
				continue;
			}
			if (!$aggregate && $stat['count'] == 0 && $stat['count_unack'] == 0) {
				$row[] = '';
				continue;
			}
			$severity_name = $aggregate ? SPACE.getSeverityName($severity, $this->severity_names) : '';

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
					$row[] = getSeverityCell($severity, null, [new CSpan($allTriggersNum), $severity_name]);
					break;

				case EXTACK_OPTION_UNACK:
					$row[] = getSeverityCell($severity, null, [new CSpan($unackTriggersNum), $severity_name]);
					break;

				case EXTACK_OPTION_BOTH:
					if ($stat['count_unack'] != 0 || $aggregate) {
						$row[] = getSeverityCell($severity, $this->severity_names, [
							new CSpan([$unackTriggersNum, ' '._('of').' ', $allTriggersNum]), $severity_name
						]);
					}
					else {
						$row[] = getSeverityCell($severity, $this->severity_names, [$allTriggersNum, $severity_name]);
					}
					break;
			}
			if ($this->filter['layout'] == STYLE_VERTICAL) {
				$table->addRow($row);
				$row = [];
			}
		}

		return $row;
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
