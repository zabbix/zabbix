<?php
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
 * A class to display web scenario details as a screen element by given "httptestid".
 */
class CScreenHttpTestDetails extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'httptest_details';

		$httptest = API::HttpTest()->get([
			'output' => ['httptestid', 'name', 'hostid'],
			'selectSteps' => ['httpstepid', 'name', 'no'],
			'httptestids' => $this->profileIdx2,
			'preservekeys' => true
		]);
		$httptest = reset($httptest);

		if (!$httptest) {
			$messages = [[
				'type' => 'error',
				'message' => _('No permissions to referred object or it does not exist!')
			]];

			return $this->getOutput(makeMessageBox(ZBX_STYLE_MSG_BAD, $messages, null, false));
		}

		$httptest['lastfailedstep'] = 0;
		$httptest['error'] = '';

		// fetch http test execution data
		$httptest_data = Manager::HttpTest()->getLastData([$httptest['httptestid']]);

		if ($httptest_data) {
			$httptest_data = reset($httptest_data);
		}

		// fetch HTTP step items
		$items = DBfetchArray(DBselect(
			'SELECT i.value_type,i.units,i.itemid,hi.type,hs.httpstepid'.
			' FROM items i,httpstepitem hi,httpstep hs'.
			' WHERE hi.itemid=i.itemid'.
				' AND hi.httpstepid=hs.httpstepid'.
				' AND hs.httptestid='.zbx_dbstr($httptest['httptestid'])
		));
		$step_items = [];

		foreach ($items as $item) {
			$step_items[$item['httpstepid']][$item['type']] = $item + ['valuemap' => []];
		}

		// fetch HTTP item history
		$item_history = Manager::History()->getLastValues($items);

		$table = (new CTableInfo())
			->setHeader([
				_('Step'),
				_('Speed'),
				_('Response time'),
				_('Response code'),
				_('Status')
		]);

		$total_time = [
			'value' => 0,
			'value_type' => null,
			'valuemap' => [],
			'units' => null
		];

		order_result($httptest['steps'], 'no');

		foreach ($httptest['steps'] as $step_data) {
			$items_by_type = $step_items[$step_data['httpstepid']];

			$status['msg'] = _('OK');
			$status['style'] = ZBX_STYLE_GREEN;
			$status['afterError'] = false;

			if (!array_key_exists('lastfailedstep', $httptest_data)) {
				$status['msg'] = '';
			}
			elseif ($httptest_data['lastfailedstep'] != 0) {
				if ($httptest_data['lastfailedstep'] == $step_data['no']) {
					$status['msg'] = ($httptest_data['error'] === null)
						? _('Unknown error')
						: _s('Error: %1$s', $httptest_data['error']);
					$status['style'] = ZBX_STYLE_RED;
				}
				elseif ($httptest_data['lastfailedstep'] < $step_data['no']) {
					$status['msg'] = _('Unknown');
					$status['style'] = ZBX_STYLE_GREY;
					$status['afterError'] = true;
				}
			}

			foreach ($items_by_type as &$item) {
				// Calculate the total time it took to execute the scenario.
				// Skip steps that come after a failed step.
				if (!$status['afterError'] && $item['type'] == HTTPSTEP_ITEM_TYPE_TIME) {
					$total_time['value_type'] = $item['value_type'];
					$total_time['valuemap'] = $item['valuemap'];
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

			$table->addRow([
				CMacrosResolverHelper::resolveHttpTestName($httptest['hostid'], $step_data['name']),
				$speed,
				$resp_time,
				$resp,
				(new CSpan($status['msg']))->addClass($status['style'])
			]);
		}


		if (!array_key_exists('lastfailedstep', $httptest_data)) {
			$status_info = '';
		}
		elseif ($httptest_data['lastfailedstep'] != 0) {
			$status_info = (new CSpan(
				($httptest_data['error'] === null) ? _('Unknown error') : _s('Error: %1$s', $httptest_data['error'])
			))->addClass(ZBX_STYLE_RED);
		}
		else {
			$status_info = (new CSpan(_('OK')))->addClass(ZBX_STYLE_GREEN);
		}

		$table->addRow([
			bold(_('TOTAL')),
			'',
			bold(($total_time['value']) ? formatHistoryValue($total_time['value'], $total_time) : UNKNOWN_VALUE),
			'',
			$status_info
		]);

		return $this->getOutput($table);
	}
}
