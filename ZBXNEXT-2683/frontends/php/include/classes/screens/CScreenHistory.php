<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CScreenHistory extends CScreenBase {

	/**
	 * Type of graph to display.
	 *
	 * Supported values:
	 * - GRAPH_TYPE_NORMAL
	 * - GRAPH_TYPE_STACKED
	 *
	 * @var int
	 */
	protected $graphType;

	/**
	 * Search string
	 *
	 * @var string
	 */
	public $filter;

	/**
	 * Filter show/hide
	 *
	 * @var int
	 */
	public $filterTask;

	/**
	 * Filter highlight color
	 *
	 * @var string
	 */
	public $markColor;

	/**
	 * Is plain text displayed
	 *
	 * @var boolean
	 */
	public $plaintext;

	/**
	 * Items data
	 *
	 * @var array
	 */
	public $items;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param string	$options['filter']
	 * @param int		$options['filterTask']
	 * @param int		$options['markColor']
	 * @param boolean	$options['plaintext']
	 * @param array		$options['items']
	 */
	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->resourcetype = SCREEN_RESOURCE_HISTORY;

		// mandatory
		$this->filter = isset($options['filter']) ? $options['filter'] : '';
		$this->filterTask = isset($options['filter_task']) ? $options['filter_task'] : null;
		$this->markColor = isset($options['mark_color']) ? $options['mark_color'] : MARK_COLOR_RED;
		$this->graphType = isset($options['graphtype']) ? $options['graphtype'] : GRAPH_TYPE_NORMAL;

		// optional
		$this->items = isset($options['items']) ? $options['items'] : null;
		$this->plaintext = isset($options['plaintext']) ? $options['plaintext'] : false;
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$output = array();

		$stime = zbxDateToTime($this->timeline['stime']);
		$itemIds = zbx_objectValues($this->items, 'itemid');
		$firstItem = reset($this->items);

		$iv_string = array(
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		);
		$iv_numeric = array(
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		);

		if ($this->action == HISTORY_VALUES || $this->action == HISTORY_LATEST) {
			$options = array(
				'history' => $firstItem['value_type'],
				'itemids' => $itemIds,
				'output' => API_OUTPUT_EXTEND,
				'sortorder' => ZBX_SORT_DOWN
			);
			if ($this->action == HISTORY_LATEST) {
				$options['limit'] = 500;
			}
			elseif ($this->action == HISTORY_VALUES) {
				$config = select_config();

				// interval start value is non-inclusive, hence the + 1 second
				$options['time_from'] = $stime + 1;
				$options['time_till'] = $stime + $this->timeline['period'];
				$options['limit'] = $config['search_limit'];
			}

			// text log
			if (isset($iv_string[$firstItem['value_type']])) {
				$isManyItems = (count($this->items) > 1);
				$useLogItem = ($firstItem['value_type'] == ITEM_VALUE_TYPE_LOG);
				$useEventLogItem = (strpos($firstItem['key_'], 'eventlog[') === 0);

				if (empty($this->plaintext)) {
					$historyTable = new CTableInfo(_('No values found.'));
					$historyTable->setHeader(
						array(
							_('Timestamp'),
							$isManyItems ? _('Item') : null,
							$useLogItem ? _('Local time') : null,
							($useEventLogItem && $useLogItem) ? _('Source') : null,
							($useEventLogItem && $useLogItem) ? _('Severity') : null,
							($useEventLogItem && $useLogItem) ? _('Event ID') : null,
							_('Value')
						),
						'header'
					);
				}

				if ($this->filter !== '' && in_array($this->filterTask, array(FILTER_TASK_SHOW, FILTER_TASK_HIDE))) {
					$options['search'] = array('value' => $this->filter);
					if ($this->filterTask == FILTER_TASK_HIDE) {
						$options['excludeSearch'] = 1;
					}
				}
				$options['sortfield'] = 'id';

				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$data['value'] = trim($data['value'], "\r\n");

					if (empty($this->plaintext)) {
						$item = $this->items[$data['itemid']];
						$host = reset($item['hosts']);
						$color = null;

						if ($this->filter !== '') {
							$haystack = mb_strtolower($data['value']);
							$needle = mb_strtolower($this->filter);
							$pos = mb_strpos($haystack, $needle);

							if ($pos !== false && $this->filterTask == FILTER_TASK_MARK) {
								$color = $this->markColor;
							}
							elseif ($pos === false && $this->filterTask == FILTER_TASK_INVERT_MARK) {
								$color = $this->markColor;
							}

							switch ($color) {
								case MARK_COLOR_RED:
									$color = 'red';
									break;
								case MARK_COLOR_GREEN:
									$color = 'green';
									break;
								case MARK_COLOR_BLUE:
									$color = 'blue';
									break;
							}
						}

						$row = array(nbsp(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock'])));

						if ($isManyItems) {
							$row[] = $host['name'].NAME_DELIMITER.$item['name_expanded'];
						}

						if ($useLogItem) {
							$row[] = ($data['timestamp'] == 0)
								? '-'
								: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['timestamp']);

							// if this is a eventLog item, showing additional info
							if ($useEventLogItem) {
								$row[] = ($data['source'] === '') ? '-' : $data['source'];
								$row[] = ($data['severity'] == 0)
								? '-'
								: new CCol(get_item_logtype_description($data['severity']), get_item_logtype_style($data['severity']));
								$row[] = ($data['logeventid'] == 0) ? '-' : $data['logeventid'];
							}
						}

						$row[] = new CCol($data['value'], 'pre');

						$newRow = new CRow($row);
						if (!is_null($color)) {
							$newRow->setAttribute('class', $color);
						}

						$historyTable->addRow($newRow);
					}
					else {
						$output[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']);
						$output[] = "\t".$data['clock']."\t".htmlspecialchars($data['value'])."\n";
					}
				}

				if (empty($this->plaintext)) {
					$output[] = $historyTable;
				}
			}

			// numeric, float
			else {
				if (empty($this->plaintext)) {
					$historyTable = new CTableInfo(_('No values found.'));
					$historyTable->setHeader(array(_('Timestamp'), _('Value')));
				}

				$options['sortfield'] = array('itemid', 'clock');
				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$item = $this->items[$data['itemid']];
					$value = $data['value'];

					// format the value as float
					if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
						sscanf($data['value'], '%f', $value);
					}

					// html table
					if (empty($this->plaintext)) {
						if ($item['valuemapid']) {
							$value = applyValueMap($value, $item['valuemapid']);
						}

						$historyTable->addRow(array(
							zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']),
							zbx_nl2br($value)
						));
					}
					// plain text
					else {
						$output[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']);
						$output[] = "\t".$data['clock']."\t".htmlspecialchars($value)."\n";
					}
				}

				if (empty($this->plaintext)) {
					$output[] = $historyTable;
				}
			}
		}

		// time control
		if (!$this->plaintext && str_in_array($this->action, array(HISTORY_VALUES, HISTORY_GRAPH, HISTORY_BATCH_GRAPH))) {
			$graphDims = getGraphDims();

			$this->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($firstItem['itemid']));

			$this->dataId = 'historyGraph';

			$timeControlData = array(
				'periodFixed' => CProfile::get('web.history.timelinefixed', 1),
				'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
			);

			if (($this->action == HISTORY_GRAPH || $this->action == HISTORY_BATCH_GRAPH) && !isset($iv_string[$firstItem['value_type']])) {
				$containerId = 'graph_cont1';
				$output[] = new CDiv(null, 'center', $containerId);

				$timeControlData['id'] = $this->getDataId();
				$timeControlData['containerid'] = $containerId;
				$timeControlData['src'] = $this->getGraphUrl($itemIds);
				$timeControlData['objDims'] = $graphDims;
				$timeControlData['loadSBox'] = 1;
				$timeControlData['loadImage'] = 1;
				$timeControlData['dynamic'] = 1;
			}
			else {
				$timeControlData['id'] = $this->getDataId();
				$timeControlData['mainObject'] = 1;
			}

			if ($this->mode == SCREEN_MODE_JS) {
				$timeControlData['dynamic'] = 0;

				return 'timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.CJs::encodeJson($timeControlData).');';
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.CJs::encodeJson($timeControlData).');');
			}
		}

		if (!empty($this->plaintext)) {
			return $output;
		}
		else {
			if ($this->mode != SCREEN_MODE_JS) {
				$flickerfreeData = array(
					'itemids' => $itemIds,
					'action' => ($this->action == HISTORY_BATCH_GRAPH) ? HISTORY_GRAPH : $this->action,
					'filter' => $this->filter,
					'filterTask' => $this->filterTask,
					'markColor' => $this->markColor
				);

				return $this->getOutput($output, true, $flickerfreeData);
			}
		}
	}

	/**
	 * Return the URL for the graph.
	 *
	 * @param array $itemIds
	 *
	 * @return string
	 */
	protected function getGraphUrl(array $itemIds) {
		$url = new CUrl('chart.php');
		$url->setArgument('period', $this->timeline['period']);
		$url->setArgument('stime', $this->timeline['stime']);
		$url->setArgument('itemids', $itemIds);
		$url->setArgument('type', $this->graphType);

		if ($this->action == HISTORY_BATCH_GRAPH) {
			$url->setArgument('batch', 1);
		}

		return $url->getUrl().$this->getProfileUrlParams();
	}
}
