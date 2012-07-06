<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CScreenHistory extends CScreenBase {

	public $itemid;
	public $filter;
	public $filter_task;
	public $mark_color;
	public $plaintext;
	public $items;
	public $item;

	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->resourcetype = SCREEN_RESOURCE_HISTORY;

		// mandatory
		$this->itemid = isset($options['itemid']) ? $options['itemid'] : null;
		$this->filter = isset($options['filter']) ? $options['filter'] : null;
		$this->filter_task = isset($options['filter_task']) ? $options['filter_task'] : null;
		$this->mark_color = isset($options['mark_color']) ? $options['mark_color'] : MARK_COLOR_RED;

		// optional
		$this->items = isset($options['items']) ? $options['items'] : null;
		$this->item = isset($options['item']) ? $options['item'] : null;
		$this->plaintext = isset($options['plaintext']) ? $options['plaintext'] : false;

		if (empty($this->items)) {
			$this->items = API::Item()->get(array(
				'nodeids' => get_current_nodeid(),
				'itemids' => $this->itemid,
				'webitems' => true,
				'selectHosts' => array('hostid', 'name'),
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			));

			$this->item = reset($this->items);
		}
	}

	public function get() {
		$output = array();

		$time = zbxDateToTime($this->stime);
		$till = $time + $this->period;

		$iv_string = array(
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		);
		$iv_numeric = array(
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		);

		if ($this->action == 'showvalues' || $this->action == 'showlatest') {
			$options = array(
				'history' => $this->item['value_type'],
				'itemids' => array_keys($this->items),
				'output' => API_OUTPUT_EXTEND,
				'sortorder' => ZBX_SORT_DOWN
			);
			if ($this->action == 'showlatest') {
				$options['limit'] = 500;
			}
			elseif ($this->action == 'showvalues') {
				$config = select_config();

				$options['time_from'] = $time - 10; // some seconds to allow script to execute
				$options['time_till'] = $till;
				$options['limit'] = $config['search_limit'];
			}

			// text log
			if (isset($iv_string[$this->item['value_type']])) {
				$isManyItems = (count($this->items) > 1);
				$useLogItem = ($this->item['value_type'] == ITEM_VALUE_TYPE_LOG);
				$useEventLogItem = (strpos($this->item['key_'], 'eventlog[') === 0);

				if (empty($this->plaintext)) {
					$historyTable = new CTableInfo(_('No history defined.'));
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

				if (!zbx_empty($this->filter) && in_array($this->filter_task, array(FILTER_TASK_SHOW, FILTER_TASK_HIDE))) {
					$options['search'] = array('value' => $this->filter);
					if ($this->filter_task == FILTER_TASK_HIDE) {
						$options['excludeSearch'] = 1;
					}
				}
				$options['sortfield'] = 'id';

				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$data['value'] = encode_log(trim($data['value'], "\r\n"));

					if (empty($this->plaintext)) {
						$item = $this->items[$data['itemid']];
						$host = reset($item['hosts']);
						$color = null;

						if (isset($this->filter) && !zbx_empty($this->filter)) {
							$contain = zbx_stristr($data['value'], $this->filter);

							if ($contain && $this->filter_task == FILTER_TASK_MARK) {
								$color = $this->mark_color;
							}
							if (!$contain && $this->filter_task == FILTER_TASK_INVERT_MARK) {
								$color = $this->mark_color;
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

						$row = array(nbsp(zbx_date2str(_('[Y.M.d H:i:s]'), $data['clock'])));

						if ($isManyItems) {
							$row[] = $host['name'].': '.itemName($item);
						}

						if ($useLogItem) {
							$row[] = ($data['timestamp'] == 0) ? '-' : zbx_date2str(HISTORY_LOG_LOCALTIME_DATE_FORMAT, $data['timestamp']);

							// if this is a eventLog item, showing additional info
							if ($useEventLogItem) {
								$row[] = zbx_empty($data['source']) ? '-' : $data['source'];
								$row[] = ($data['severity'] == 0)
								? '-'
								: new CCol(get_item_logtype_description($data['severity']), get_item_logtype_style($data['severity']));
								$row[] = ($data['logeventid'] == 0) ? '-' : $data['logeventid'];
							}
						}

						$row[] = new CCol($data['value'], 'pre');

						$newRow = new CRow($row);
						if (is_null($color)) {
							$min_color = 0x98;
							$max_color = 0xF8;
							$int_color = ($max_color - $min_color) / count($this->itemid);
							$int_color *= array_search($data['itemid'], $this->itemid);
							$int_color += $min_color;
							$newRow->setAttribute('style', 'background-color: '.sprintf("#%X%X%X", $int_color, $int_color, $int_color));
						}
						elseif (!is_null($color)) {
							$newRow->setAttribute('class', $color);
						}

						$historyTable->addRow($newRow);
					}
					else {
						$output[] = zbx_date2str(HISTORY_LOG_ITEM_PLAINTEXT, $data['clock']);
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
					$historyTable = new CTableInfo(_('No history defined.'));
					$historyTable->setHeader(array(_('Timestamp'), _('Value')));
				}

				$options['sortfield'] = array('itemid', 'clock');
				$historyData = API::History()->get($options);

				foreach ($historyData as $data) {
					$item = $this->items[$data['itemid']];
					$host = reset($item['hosts']);

					if (empty($data['value'])) {
						$data['value'] = '';
					}

					if ($item['valuemapid'] > 0) {
						$value = applyValueMap($data['value'], $item['valuemapid']);
						$value_mapped = true;
					}
					else {
						$value = $data['value'];
						$value_mapped = false;
					}

					if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT && !$value_mapped) {
						sscanf($data['value'], '%f', $value);
					}

					if (empty($this->plaintext)) {
						$historyTable->addRow(array(
							zbx_date2str(HISTORY_ITEM_DATE_FORMAT, $data['clock']),
							zbx_nl2br($value)
						));
					}
					else {
						if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
							sscanf($data['value'], '%f', $value);
						}
						else {
							$value = $data['value'];
						}

						$output[] = zbx_date2str(HISTORY_PLAINTEXT_DATE_FORMAT, $data['clock']);
						$output[] = "\t".$data['clock']."\t".htmlspecialchars($value)."\n";
					}
				}

				if (empty($this->plaintext)) {
					$output[] = $historyTable;
				}
			}
		}

		if ($this->action == 'showgraph' && !isset($iv_string[$this->item['value_type']])) {
			$this->data_id = 'historyGraph';
			$containerid = 'graph_cont1';
			$src = 'chart.php?itemid='.$this->item['itemid'];

			$historyTable = new CTableInfo(_('No charts defined.'), 'chart');
			$graphContainer = new CCol();
			$graphContainer->setAttribute('id', $containerid);
			$historyTable->addRow($graphContainer);
			$output[] = $historyTable;
		}

		// time control
		if (!$this->plaintext && str_in_array($this->action, array('showvalues', 'showgraph'))) {
			$graphDims = getGraphDims();

			$starttime = get_min_itemclock_by_itemid($this->item['itemid']);
			if ($time < $starttime) {
				$starttime = $time;
			}

			$timeline = array(
				'starttime' => date('YmdHis', $starttime),
				'period' => $this->period,
				'usertime' => date('YmdHis', $till)
			);

			$timeControlData = array(
				'periodFixed' => CProfile::get('web.history.timelinefixed', 1),
				'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
			);

			if (!empty($this->data_id)) {
				$timeControlData['id'] = $this->getDataId();
				$timeControlData['domid'] = $this->getDataId();
				$timeControlData['containerid'] = $containerid;
				$timeControlData['src'] = $src;
				$timeControlData['objDims'] = $graphDims;
				$timeControlData['loadSBox'] = 1;
				$timeControlData['loadImage'] = 1;
				$timeControlData['loadScroll'] = 1;
				$timeControlData['scrollWidthByImage'] = 1;
				$timeControlData['dynamic'] = 1;
			}
			else {
				$this->data_id = 'historyGraph';
				$timeControlData['id'] = $this->getDataId();
				$timeControlData['domid'] = $this->getDataId();
				$timeControlData['loadSBox'] = 0;
				$timeControlData['loadImage'] = 0;
				$timeControlData['loadScroll'] = 1;
				$timeControlData['dynamic'] = 0;
				$timeControlData['mainObject'] = 1;
			}

			if ($this->mode == SCREEN_MODE_JS) {
				return 'timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).');';
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).');');
				zbx_add_post_js('timeControl.processObjects();');
			}
		}

		if (!empty($this->plaintext)) {
			return $output;
		}
		else {
			if ($this->mode != SCREEN_MODE_JS) {
				$flickerfreeData = array(
					'itemid' => $this->itemid,
					'action' => $this->action,
					'filter' => $this->filter,
					'filter_task' => $this->filter_task,
					'mark_color' => $this->mark_color
				);

				return $this->getOutput($output, true, $flickerfreeData);
			}
		}
	}
}
