<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	 * Graph id.
	 *
	 * @var int
	 */
	public $graphid = 0;

	/**
	 * String containing base URL for pager.
	 *
	 * @var string
	 */
	public $page_file;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param string	$options['filter']
	 * @param int		$options['filterTask']
	 * @param int		$options['markColor']
	 * @param boolean	$options['plaintext']
	 * @param array		$options['itemids']
	 * @param array     $options['graphid']     When set defines graph id where item.
	 * @param string    $options['pageFile']    Current page file, is used for pagination links.
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
		$this->page_file = array_key_exists('pageFile', $options) ? $options['pageFile'] : null;

		if (!$this->itemids && array_key_exists('graphid', $options)) {
			$itemids = API::Item()->get([
				'output' => ['itemid'],
				'graphids' => [$options['graphid']]
			]);
			$this->itemids = zbx_objectValues($itemids, 'itemid');
			$this->graphid = $options['graphid'];
		}
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$output = [];

		$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
			'output' => ['itemid', 'name_resolved', 'key_', 'value_type'],
			'selectHosts' => ['name'],
			'selectValueMap' => ['mappings'],
			'itemids' => $this->itemids,
			'webitems' => true,
			'preservekeys' => true
		]), ['name_resolved' => 'name']);

		if (!$items) {
			show_error_message(_('No permissions to referred object or it does not exist!'));

			return;
		}

		$iv_string = [
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		if ($this->action == HISTORY_VALUES || $this->action == HISTORY_LATEST) {
			$options = [
				'output' => API_OUTPUT_EXTEND,
				'sortfield' => ['clock'],
				'sortorder' => ZBX_SORT_DOWN
			];

			if ($this->action == HISTORY_LATEST) {
				$options['limit'] = 500;
			}
			else {
				$options += [
					'time_from' => $this->timeline['from_ts'],
					'time_till' => $this->timeline['to_ts'],
					'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
				];
			}

			$is_many_items = (count($items) > 1);
			$numeric_items = true;

			foreach ($items as $item) {
				$numeric_items = ($numeric_items && !array_key_exists($item['value_type'], $iv_string));
				if (!$numeric_items) {
					break;
				}
			}

			/**
			 * View type: As plain text.
			 * Item type: numeric (unsigned, char), float, text, log.
			 */
			if ($this->plaintext) {
				if (!$numeric_items && $this->filter !== ''
						&& in_array($this->filterTask, [FILTER_TASK_SHOW, FILTER_TASK_HIDE])) {
					$options['search'] = ['value' => $this->filter];

					if ($this->filterTask == FILTER_TASK_HIDE) {
						$options['excludeSearch'] = true;
					}
				}

				$history_data = [];
				$items_by_type = [];

				foreach ($items as $item) {
					$items_by_type[$item['value_type']][] = $item['itemid'];
				}

				foreach ($items_by_type as $value_type => $itemids) {
					$options['history'] = $value_type;
					$options['itemids'] = $itemids;

					$item_data = API::History()->get($options);

					if ($item_data) {
						$history_data = array_merge($history_data, $item_data);
					}
				}

				CArrayHelper::sort($history_data, [
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				]);

				$history_data = array_slice($history_data, 0, $options['limit']);

				foreach ($history_data as $history_row) {
					$value = $history_row['value'];

					if (in_array($items[$history_row['itemid']]['value_type'],
							[ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])) {
						$value = '"'.$value.'"';
					}
					elseif ($items[$history_row['itemid']]['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
						$value = formatFloat($value, ['decimals' => ZBX_UNITS_ROUNDOFF_UNSUFFIXED]);
					}

					$row = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_row['clock']).' '.$history_row['clock'].
						' '.$value;

					if ($is_many_items) {
						$row .= ' "'.$items[$history_row['itemid']]['hosts'][0]['name'].NAME_DELIMITER.
							$items[$history_row['itemid']]['name'].'"';
					}
					$output[] = $row;
				}

				// Return values as array of formatted strings.
				return $output;
			}
			/**
			 * View type: Values, 500 latest values
			 * Item type: text, log
			 */
			elseif (!$numeric_items) {
				$use_log_item = false;
				$use_eventlog_item = false;
				$items_by_type = [];
				$history_data = [];

				foreach ($items as $item) {
					$items_by_type[$item['value_type']][] = $item['itemid'];

					if ($item['value_type'] == ITEM_VALUE_TYPE_LOG) {
						$use_log_item = true;
					}

					if (strpos($item['key_'], 'eventlog[') === 0) {
						$use_eventlog_item = true;
					}
				}

				$history_table = (new CTableInfo())
					->setHeader([
						(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH),
						$is_many_items ? _('Item') : null,
						$use_log_item ? (new CColHeader(_('Local time')))->addClass(ZBX_STYLE_CELL_WIDTH) : null,
						($use_eventlog_item && $use_log_item)
							? (new CColHeader(_('Source')))->addClass(ZBX_STYLE_CELL_WIDTH)
							: null,
						($use_eventlog_item && $use_log_item)
							? (new CColHeader(_('Severity')))->addClass(ZBX_STYLE_CELL_WIDTH)
							: null,
						($use_eventlog_item && $use_log_item)
							? (new CColHeader(_('Event ID')))->addClass(ZBX_STYLE_CELL_WIDTH)
							: null,
						_('Value')
					]);

				if ($this->filter !== '' && in_array($this->filterTask, [FILTER_TASK_SHOW, FILTER_TASK_HIDE])) {
					$options['search'] = ['value' => $this->filter];
					if ($this->filterTask == FILTER_TASK_HIDE) {
						$options['excludeSearch'] = true;
					}
				}

				foreach ($items_by_type as $value_type => $itemids) {
					$options['history'] = $value_type;
					$options['itemids'] = $itemids;
					$item_data = API::History()->get($options);

					if ($item_data) {
						$history_data = array_merge($history_data, $item_data);
					}
				}

				CArrayHelper::sort($history_data, [
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				]);

				// Array $history_data will be modified according page and rows on page.
				$history_table->setPageNavigation(
					CPagerHelper::paginate($this->page, $history_data, ZBX_SORT_UP, new CUrl($this->page_file))
				);

				foreach ($history_data as $data) {
					$item = $items[$data['itemid']];
					$host = reset($item['hosts']);
					$color = null;

					if ($value_type == ITEM_VALUE_TYPE_BINARY) {
						$value = italic(_('binary value'))->addClass(ZBX_STYLE_GREY);
					}
					else {
						$data['value'] = rtrim($data['value'], " \t\r\n");
						$value = zbx_nl2br($data['value']);

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
					}

					$row = [];

					$row[] = (new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock'])))
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass($color);

					if ($is_many_items) {
						$row[] = (new CCol($host['name'].NAME_DELIMITER.$item['name']))
							->addClass($color);
					}

					if ($use_log_item) {
						$row[] = (array_key_exists('timestamp', $data) && $data['timestamp'] != 0)
							? (new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['timestamp'])))
								->addClass(ZBX_STYLE_NOWRAP)
								->addClass($color)
							: '';

						// If this is a eventLog item, showing additional info.
						if ($use_eventlog_item) {
							$row[] = array_key_exists('source', $data)
								? (new CCol($data['source']))
									->addClass(ZBX_STYLE_NOWRAP)
									->addClass($color)
								: '';
							$row[] = (array_key_exists('severity', $data) && $data['severity'] != 0)
								? (new CCol(get_item_logtype_description($data['severity'])))
									->addClass(ZBX_STYLE_NOWRAP)
									->addClass(get_item_logtype_style($data['severity']))
								: '';
							$row[] = array_key_exists('severity', $data)
								? (new CCol($data['logeventid']))
									->addClass(ZBX_STYLE_NOWRAP)
									->addClass($color)
								: '';
						}
					}

					$row[] = (new CCol(new CPre($value)))->addClass($color);

					$history_table->addRow($row);
				}

				$output[] = $history_table;
			}
			/**
			 * View type: 500 latest values.
			 * Item type: numeric (unsigned, char), float.
			 */
			elseif ($this->action === HISTORY_LATEST) {
				$history_table = (new CTableInfo())
					->setHeader([(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH), _('Value')]);

				$items_by_type = [];
				$history_data = [];

				foreach ($items as $item) {
					$items_by_type[$item['value_type']][] = $item['itemid'];
				}

				foreach ($items_by_type as $value_type => $itemids) {
					$options['history'] = $value_type;
					$options['itemids'] = $itemids;
					$item_data = API::History()->get($options);

					if ($item_data) {
						$history_data = array_merge($history_data, $item_data);
					}
				}

				CArrayHelper::sort($history_data, [
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				]);

				$history_data = array_slice($history_data, 0, $options['limit']);

				foreach ($history_data as $history_row) {
					$item = $items[$history_row['itemid']];
					$value = $history_row['value'];

					if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
						$value = formatFloat($value, ['decimals' => ZBX_UNITS_ROUNDOFF_UNSUFFIXED]);
					}

					$value = $item['value_type'] == ITEM_VALUE_TYPE_BINARY
						? italic(_('binary value'))->addClass(ZBX_STYLE_GREY)
						: zbx_nl2br(CValueMapHelper::applyValueMap($item['value_type'], $value, $item['valuemap']));

					$history_table->addRow([
						(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_row['clock'])))
							->addClass(ZBX_STYLE_NOWRAP),
						new CPre($value)
					]);
				}

				$output[] = $history_table;
			}
			/**
			 * View type: Values.
			 * Item type: numeric (unsigned, char), float.
			 */
			else {
				CArrayHelper::sort($items, [
					['field' => 'name', 'order' => ZBX_SORT_UP]
				]);
				$table_header = [(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH)];
				$history_data = [];

				foreach ($items as $item) {
					$options['itemids'] = [$item['itemid']];
					$options['history'] = $item['value_type'];
					$item_data = API::History()->get($options);

					CArrayHelper::sort($item_data, [
						['field' => 'clock', 'order' => ZBX_SORT_DOWN],
						['field' => 'ns', 'order' => ZBX_SORT_DOWN]
					]);

					$table_header[] = (new CSpan($item['name']))
						->addClass(ZBX_STYLE_TEXT_VERTICAL)
						->setTitle($item['name']);
					$history_data_index = 0;

					foreach ($item_data as $item_data_row) {
						// Searching for starting 'insert before' index in results array.
						while (array_key_exists($history_data_index, $history_data)) {
							$history_row = $history_data[$history_data_index];

							if ($history_row['clock'] <= $item_data_row['clock']
									&& !array_key_exists($item['itemid'], $history_row['values'])) {
								break;
							}

							++$history_data_index;
						}

						if (array_key_exists($history_data_index, $history_data)
								&& !array_key_exists($item['itemid'], $history_row['values'])
								&& $history_data[$history_data_index]['clock'] === $item_data_row['clock']) {
							$history_data[$history_data_index]['values'][$item['itemid']] = $item_data_row['value'];
						}
						else {
							array_splice($history_data, $history_data_index, 0, [[
								'clock' => $item_data_row['clock'],
								'values' => [$item['itemid'] => $item_data_row['value']]
							]]);
						}
					}
				}

				$history_table = (new CTableInfo())
					->setHeader($table_header)
					->setPageNavigation(
						CPagerHelper::paginate($this->page, $history_data, ZBX_SORT_UP, new CUrl($this->page_file))
					);

				foreach ($history_data as $history_data_row) {
					$row = [(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_data_row['clock'])))
						->addClass(ZBX_STYLE_NOWRAP)
					];
					$values = $history_data_row['values'];

					foreach ($items as $item) {
						$value = array_key_exists($item['itemid'], $values) ? $values[$item['itemid']] : '';

						if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT && $value !== '') {
							$value = formatFloat($value, ['decimals' => ZBX_UNITS_ROUNDOFF_UNSUFFIXED]);
						}

						$value = $item['value_type'] == ITEM_VALUE_TYPE_BINARY
							? italic(_('binary value'))->addClass(ZBX_STYLE_GREY)
							: CValueMapHelper::applyValueMap($item['value_type'], $value, $item['valuemap']);

						$row[] = ($value === '') ? '' : new CPre($value);
					}

					$history_table->addRow($row);
				}

				$output[] = $history_table;
			}
		}

		// time control
		if (str_in_array($this->action, [HISTORY_VALUES, HISTORY_GRAPH, HISTORY_BATCH_GRAPH])) {
			$graphDims = getGraphDims();

			$this->dataId = 'historyGraph';

			$timeControlData = [];

			if ($this->action == HISTORY_GRAPH || $this->action == HISTORY_BATCH_GRAPH) {
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
			}

			if ($this->mode == SCREEN_MODE_JS) {
				$timeControlData['dynamic'] = 0;

				return 'timeControl.addObject("'.$this->getDataId().'", '.json_encode($this->timeline).', '.
					json_encode($timeControlData).');';
			}

			zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.json_encode($this->timeline).', '.
				json_encode($timeControlData).');'
			);
		}

		if ($this->mode != SCREEN_MODE_JS) {
			$flickerfreeData = [
				'itemids' => $this->itemids,
				'action' => ($this->action == HISTORY_BATCH_GRAPH) ? HISTORY_GRAPH : $this->action,
				'filter' => $this->filter,
				'filterTask' => $this->filterTask,
				'markColor' => $this->markColor
			];

			if ($this->action == HISTORY_VALUES) {
				$flickerfreeData['page'] = $this->page;
			}

			if ($this->graphid != 0) {
				unset($flickerfreeData['itemids']);
				$flickerfreeData['graphid'] = $this->graphid;
			}

			return $this->getOutput($output, true, $flickerfreeData);
		}

		return $output;
	}

	/**
	 * Return the URL for the graph.
	 *
	 * @param array $itemIds
	 *
	 * @return string
	 */
	protected function getGraphUrl(array $itemIds) {
		$url = (new CUrl('chart.php'))
			->setArgument('from', $this->timeline['from'])
			->setArgument('to', $this->timeline['to'])
			->setArgument('itemids', $itemIds)
			->setArgument('type', $this->graphType)
			->setArgument('resolve_macros', 1)
			->setArgument('profileIdx', $this->profileIdx)
			->setArgument('profileIdx2', $this->profileIdx2);

		if ($this->action == HISTORY_BATCH_GRAPH) {
			$url->setArgument('batch', 1);
		}

		return $url->getUrl();
	}
}
