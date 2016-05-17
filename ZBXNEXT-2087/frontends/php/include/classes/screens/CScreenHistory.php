<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * Items ids.
	 *
	 * @var array
	 */
	public $itemids;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param string	$options['filter']
	 * @param int		$options['filterTask']
	 * @param int		$options['markColor']
	 * @param boolean	$options['plaintext']
	 * @param array		$options['itemids']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);

		$this->resourcetype = SCREEN_RESOURCE_HISTORY;

		// mandatory
		$this->filter = isset($options['filter']) ? $options['filter'] : '';
		$this->filterTask = isset($options['filter_task']) ? $options['filter_task'] : null;
		$this->markColor = isset($options['mark_color']) ? $options['mark_color'] : MARK_COLOR_RED;
		$this->graphType = isset($options['graphtype']) ? $options['graphtype'] : GRAPH_TYPE_NORMAL;

		// optional
		$this->itemids = array_key_exists('itemids', $options) ?  $options['itemids'] : [];
		$this->plaintext = isset($options['plaintext']) ? $options['plaintext'] : false;
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$output = [];

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid', 'history', 'trends'],
			'selectHosts' => ['name'],
			'itemids' => $this->itemids,
			'webitems' => true,
			'preservekeys' => true
		]);

		$items = CMacrosResolverHelper::resolveItemNames($items);

		$stime = zbxDateToTime($this->timeline['stime']);
		$firstItem = reset($items);

		$iv_string = [
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];
		$iv_numeric = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		if ($this->action == HISTORY_VALUES || $this->action == HISTORY_LATEST) {
			$options = [
				'history' => $firstItem['value_type'],
				'itemids' => $this->itemids,
				'output' => API_OUTPUT_EXTEND,
				'sortorder' => ZBX_SORT_DOWN
			];
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
				$isManyItems = (count($items) > 1);
				$useLogItem = ($firstItem['value_type'] == ITEM_VALUE_TYPE_LOG);
				$useEventLogItem = (strpos($firstItem['key_'], 'eventlog[') === 0);

				if (empty($this->plaintext)) {
					$historyTable = (new CTableInfo())
						->setHeader([
							(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH),
							$isManyItems ? _('Item') : null,
							$useLogItem ? (new CColHeader(_('Local time')))->addClass(ZBX_STYLE_CELL_WIDTH) : null,
							($useEventLogItem && $useLogItem)
								? (new CColHeader(_('Source')))->addClass(ZBX_STYLE_CELL_WIDTH)
								: null,
							($useEventLogItem && $useLogItem)
								? (new CColHeader(_('Severity')))->addClass(ZBX_STYLE_CELL_WIDTH)
								: null,
							($useEventLogItem && $useLogItem)
								? (new CColHeader(_('Event ID')))->addClass(ZBX_STYLE_CELL_WIDTH)
								: null,
							_('Value')
						]);
				}

				if ($this->filter !== '' && in_array($this->filterTask, [FILTER_TASK_SHOW, FILTER_TASK_HIDE])) {
					$options['search'] = ['value' => $this->filter];
					if ($this->filterTask == FILTER_TASK_HIDE) {
						$options['excludeSearch'] = 1;
					}
				}
				$options['sortfield'] = ['itemid', 'clock'];

				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$data['value'] = rtrim($data['value'], " \t\r\n");

					if (empty($this->plaintext)) {
						$item = $items[$data['itemid']];
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
									$color = ZBX_STYLE_RED;
									break;
								case MARK_COLOR_GREEN:
									$color = ZBX_STYLE_GREEN;
									break;
								case MARK_COLOR_BLUE:
									$color = ZBX_STYLE_BLUE;
									break;
							}
						}

						$row = [];

						$row[] = (new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock'])))
							->addClass(ZBX_STYLE_NOWRAP)
							->addClass($color);

						if ($isManyItems) {
							$row[] = (new CCol($host['name'].NAME_DELIMITER.$item['name_expanded']))
								->addClass($color);
						}

						if ($useLogItem) {
							$row[] = ($data['timestamp'] != 0)
								? (new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['timestamp'])))
									->addClass(ZBX_STYLE_NOWRAP)
									->addClass($color)
								: '';

							// if this is a eventLog item, showing additional info
							if ($useEventLogItem) {
								$row[] = (new CCol($data['source']))
									->addClass(ZBX_STYLE_NOWRAP)
									->addClass($color);
								$row[] = ($data['severity'] != 0)
									? (new CCol(get_item_logtype_description($data['severity'])))
										->addClass(ZBX_STYLE_NOWRAP)
										->addClass(get_item_logtype_style($data['severity']))
									: '';
								$row[] = ($data['logeventid'] != 0)
									? (new CCol($data['logeventid']))
										->addClass(ZBX_STYLE_NOWRAP)
										->addClass($color)
									: '';
							}
						}

						$row[] = (new CCol(new CPre(zbx_nl2br($data['value']))))->addClass($color);

						$historyTable->addRow($row);
					}
					else {
						$output[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']).' '. $data['clock'].' '.
							htmlspecialchars($data['value']);
					}
				}

				if (empty($this->plaintext)) {
					$output[] = $historyTable;
				}
			}

			// numeric, float
			else {
				if (empty($this->plaintext)) {
					$historyTable = (new CTableInfo())->setHeader([
						(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH),
						_('Value')
					]);
				}

				$options['sortfield'] = ['itemid', 'clock'];
				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$item = $items[$data['itemid']];
					$value = rtrim($data['value'], " \t\r\n");

					// format the value as float
					if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
						sscanf($data['value'], '%f', $value);
					}

					// html table
					if (empty($this->plaintext)) {
						if ($item['valuemapid']) {
							$value = applyValueMap($value, $item['valuemapid']);
						}

						$historyTable->addRow([
							(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock'])))
								->addClass(ZBX_STYLE_NOWRAP),
							new CPre(zbx_nl2br($value))
						]);
					}
					// plain text
					else {
						$output[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']).' '.$data['clock'].' '.
							htmlspecialchars($value);
					}
				}

				if (empty($this->plaintext)) {
					$output[] = $historyTable;
				}
			}
		}

		// time control
		if (!$this->plaintext && str_in_array($this->action, [HISTORY_VALUES, HISTORY_GRAPH, HISTORY_BATCH_GRAPH])) {
			$graphDims = getGraphDims();

			$this->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid([$firstItem]));

			$this->dataId = 'historyGraph';

			$timeControlData = [
				'periodFixed' => CProfile::get('web.history.timelinefixed', 1),
				'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
			];

			if (($this->action == HISTORY_GRAPH || $this->action == HISTORY_BATCH_GRAPH) && !isset($iv_string[$firstItem['value_type']])) {
				$containerId = 'graph_cont1';
				$output[] = (new CDiv())
					->addClass('center')
					->setId($containerId);

				$timeControlData['id'] = $this->getDataId();
				$timeControlData['containerid'] = $containerId;
				$timeControlData['src'] = $this->getGraphUrl($this->itemids);
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
				$flickerfreeData = [
					'itemids' => $this->itemids,
					'action' => ($this->action == HISTORY_BATCH_GRAPH) ? HISTORY_GRAPH : $this->action,
					'filter' => $this->filter,
					'filterTask' => $this->filterTask,
					'markColor' => $this->markColor
				];

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
