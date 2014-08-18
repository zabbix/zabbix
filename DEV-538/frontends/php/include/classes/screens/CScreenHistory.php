<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * Item ids
	 *
	 * @var array
	 */
	public $itemids;

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
	 * Item data
	 *
	 * @var array
	 */
	public $item;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param array		$options['itemids']
	 * @param string	$options['filter']
	 * @param int		$options['filterTask']
	 * @param int		$options['markColor']
	 * @param boolean	$options['plaintext']
	 * @param array		$options['items']
	 * @param array		$options['item']
	 */
	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->resourcetype = SCREEN_RESOURCE_HISTORY;

		// mandatory
		$this->itemids = isset($options['itemids']) ? $options['itemids'] : null;
		$this->filter = isset($options['filter']) ? $options['filter'] : null;
		$this->filterTask = isset($options['filter_task']) ? $options['filter_task'] : null;
		$this->markColor = isset($options['mark_color']) ? $options['mark_color'] : MARK_COLOR_RED;

		// optional
		$this->items = isset($options['items']) ? $options['items'] : null;
		$this->item = isset($options['item']) ? $options['item'] : null;
		$this->plaintext = isset($options['plaintext']) ? $options['plaintext'] : false;

		if (empty($this->items)) {
			$this->items = API::Item()->get(array(
				'itemids' => $this->itemids,
				'webitems' => true,
				'selectHosts' => array('name'),
				'output' => array('itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid'),
				'preservekeys' => true
			));

			$this->items = CMacrosResolverHelper::resolveItemNames($this->items);

			$this->item = reset($this->items);
		}
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$output = array();

		$stime = zbxDateToTime($this->timeline['stime']);

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

				$options['time_from'] = $stime - 10; // some seconds to allow script to execute
				$options['time_till'] = $stime + $this->timeline['period'];
				$options['limit'] = $config['search_limit'];
			}

			// text log
			if (isset($iv_string[$this->item['value_type']])) {
				$isManyItems = (count($this->items) > 1);
				$useLogItem = ($this->item['value_type'] == ITEM_VALUE_TYPE_LOG);
				$useEventLogItem = (strpos($this->item['key_'], 'eventlog[') === 0);

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

				if (!zbx_empty($this->filter) && in_array($this->filterTask, array(FILTER_TASK_SHOW, FILTER_TASK_HIDE))) {
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

						if (isset($this->filter) && !zbx_empty($this->filter)) {
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
							$int_color = ($max_color - $min_color) / count($this->itemids);
							$int_color *= array_search($data['itemid'], $this->itemids);
							$int_color += $min_color;
							$newRow->setAttribute('style', 'background-color: '.sprintf("#%X%X%X", $int_color, $int_color, $int_color));
						}
						elseif (!is_null($color)) {
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

		if ($this->action == 'showgraph' && !isset($iv_string[$this->item['value_type']])) {
			$this->dataId = 'historyGraph';
			$containerId = 'graph_cont1';
			$src = 'chart.php?itemid='.$this->item['itemid'].'&period='.$this->timeline['period'].'&stime='.$this->timeline['stime'].$this->getProfileUrlParams();

			$output[] = new CDiv(null, 'center', $containerId);
		}

		// time control
		if (!$this->plaintext && str_in_array($this->action, array('showvalues', 'showgraph'))) {
			$graphDims = getGraphDims();

			$this->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($this->item['itemid']));

			$timeControlData = array(
				'periodFixed' => CProfile::get('web.history.timelinefixed', 1),
				'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
			);

			if (!empty($this->dataId)) {
				$timeControlData['id'] = $this->getDataId();
				$timeControlData['containerid'] = $containerId;
				$timeControlData['src'] = $src;
				$timeControlData['objDims'] = $graphDims;
				$timeControlData['loadSBox'] = 1;
				$timeControlData['loadImage'] = 1;
				$timeControlData['dynamic'] = 1;
			}
			else {
				$this->dataId = 'historyGraph';
				$timeControlData['id'] = $this->getDataId();
				$timeControlData['mainObject'] = 1;
			}

			if ($this->mode == SCREEN_MODE_JS) {
				$timeControlData['dynamic'] = 0;

				return 'timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');';
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');');
			}
		}

		if (!empty($this->plaintext)) {
			return $output;
		}
		else {
			if ($this->mode != SCREEN_MODE_JS) {
				$flickerfreeData = array(
					'itemids' => $this->itemids,
					'action' => $this->action,
					'filter' => $this->filter,
					'filterTask' => $this->filterTask,
					'markColor' => $this->markColor
				);

				return $this->getOutput($output, true, $flickerfreeData);
			}
		}
	}
}
