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


class CScreenWebdetails extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$httptest = API::HttpTest()->get(array(
			'httptestids' => $this->profileIdx2,
			'output' => array('httptestid', 'name', 'hostid'),
			'selectSteps' => array('httpstepid', 'name', 'no'),
			'preservekeys' => true
		));
		$httptest = reset($httptest);
		if (!$httptest) {
			access_deny();
		}

		$httptest['lastfailedstep'] = 0;
		$httptest['error'] = '';

		// fetch http test execution data
		$httptest_data = Manager::HttpTest()->getLastData(array($httptest['httptestid']));

		if ($httptest_data) {
			$httptest_data = reset($httptest_data);
		}

		// fetch HTTP step items
		$items = DBfetchArray(DBselect(
			'SELECT i.value_type,i.valuemapid,i.units,i.itemid,hi.type AS httpitem_type,hs.httpstepid'.
			' FROM items i,httpstepitem hi,httpstep hs'.
			' WHERE hi.itemid=i.itemid'.
			' AND hi.httpstepid=hs.httpstepid'.
			' AND hs.httptestid='.zbx_dbstr($httptest['httptestid'])
		));

		$step_items = array();
		foreach ($items as $item) {
			$step_items[$item['httpstepid']][$item['httpitem_type']] = $item;
		}

		// fetch HTTP item history
		$item_history = Manager::History()->getLast($items);

		$httpdetails_table = new CTableInfo();
		$httpdetails_table->setHeader(array(
			_('Step'),
			_('Speed'),
			_('Response time'),
			_('Response code'),
			_('Status')
		));

		$total_time = array(
			'value' => 0,
			'value_type' => null,
			'valuemapid' => null,
			'units' => null
		);

		order_result($httptest['steps'], 'no');
		foreach ($httptest['steps'] as $step_data) {
			$items_by_type = $step_items[$step_data['httpstepid']];

			$status['msg'] = _('OK');
			$status['style'] = 'enabled';
			$status['afterError'] = false;

			if (!array_key_exists('lastfailedstep', $httptest_data)) {
				$status['msg'] = _('Never executed');
				$status['style'] = 'unknown';
			}
			elseif ($httptest_data['lastfailedstep'] != 0) {
				if ($httptest_data['lastfailedstep'] == $step_data['no']) {
					$status['msg'] = ($httptest_data['error'] === null)
					? _('Unknown error')
					: _s('Error: %1$s', $httptest_data['error']);
					$status['style'] = 'disabled';
				}
				elseif ($httptest_data['lastfailedstep'] < $step_data['no']) {
					$status['msg'] = _('Unknown');
					$status['style'] = 'unknown';
					$status['afterError'] = true;
				}
			}

			foreach ($items_by_type as &$item) {
				// Calculate the total time it took to execute the scenario.
				// Skip steps that come after a failed step.
				if (!$status['afterError'] && $item['httpitem_type'] == HTTPSTEP_ITEM_TYPE_TIME) {
					$total_time['value_type'] = $item['value_type'];
					$total_time['valuemapid'] = $item['valuemapid'];
					$total_time['units'] = $item['units'];

					if (array_key_exists($item['itemid'], $item_history)) {
						$history = $item_history[$item['itemid']][0];
						$total_time['value'] += $history['value'];
					}
				}
			}
			unset($item);

			// step speed
			$speed_item = $items_by_type[HTTPSTEP_ITEM_TYPE_IN];
			if (!$status['afterError'] && array_key_exists($speed_item['itemid'], $item_history)
				&& $item_history[$speed_item['itemid']][0]['value'] > 0) {
					$speed = formatHistoryValue($item_history[$speed_item['itemid']][0]['value'], $speed_item);
				}
				else {
					$speed = UNKNOWN_VALUE;
				}

				// step response time
				$resptime_item = $items_by_type[HTTPSTEP_ITEM_TYPE_TIME];
				if (!$status['afterError'] && array_key_exists($resptime_item['itemid'], $item_history)
					&& $item_history[$resptime_item['itemid']][0]['value'] > 0) {
						$resp_time = formatHistoryValue($item_history[$resptime_item['itemid']][0]['value'], $resptime_item);
					}
					else {
						$resp_time = UNKNOWN_VALUE;
					}

					// step response code
					$resp_item = $items_by_type[HTTPSTEP_ITEM_TYPE_RSPCODE];
					if (!$status['afterError'] && array_key_exists($resp_item['itemid'], $item_history)
						&& $item_history[$resp_item['itemid']][0]['value'] > 0) {
							$resp = formatHistoryValue($item_history[$resp_item['itemid']][0]['value'], $resp_item);
						}
						else {
							$resp = UNKNOWN_VALUE;
						}

						$httpdetails_table->addRow(array(
							CMacrosResolverHelper::resolveHttpTestName($httptest['hostid'], $step_data['name']),
							$speed,
							$resp_time,
							$resp,
							new CSpan($status['msg'], $status['style'])
						));
		}

		if (!array_key_exists('lastfailedstep', $httptest_data)) {
			$status['msg'] = _('Never executed');
			$status['style'] = 'unknown';
		}
		elseif ($httptest_data['lastfailedstep'] != 0) {
			$status['msg'] = ($httptest_data['error'] === null)
			? _('Unknown error')
			: _s('Error: %1$s', $httptest_data['error']);
			$status['style'] = 'disabled';
		}
		else {
			$status['msg'] = _('OK');
			$status['style'] = 'enabled';
		}

		$httpdetails_table->addRow(array(
			bold(_('TOTAL')),
			SPACE,
			bold(($total_time['value']) ? formatHistoryValue($total_time['value'], $total_time) : UNKNOWN_VALUE),
			SPACE,
			new CSpan($status['msg'], $status['style'].' bold')
		));

		$caption = _('DETAILS OF SCENARIO').SPACE.
		bold(CMacrosResolverHelper::resolveHttpTestName($httptest['hostid'], $httptest['name'])).
		(array_key_exists('lastcheck', $httptest_data)
			? ' ['.zbx_date2str(_('d M Y H:i:s'), $httptest_data['lastcheck']).']'
			: null
		);

		$script = new CJSScript(get_js("jQuery('#hat_webdetails_header').html('".$caption."')"));
		$this->dataId = 'webdetails';

		return $this->getOutput(array($httpdetails_table, $script), false, array());
	}
}
