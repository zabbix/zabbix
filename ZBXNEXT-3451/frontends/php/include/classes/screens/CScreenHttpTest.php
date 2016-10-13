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


/**
 * A class to display Http tests table as a screen element.
 */
class CScreenHttpTest extends CScreenBase {

	/**
	 * Screen data.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param array		$options['data']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);

		$this->data = $options['data'];
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'httptest';
		$sort_field = $this->data['sort'];
		$sort_order = $this->data['sortorder'];

		$httptests = [];
		$paging = [];

		if ($this->data['hosts_selected']) {
			$config = select_config();

			$options = [
				'output' => ['httptestid', 'name', 'hostid'],
				'selectHosts' => ['name', 'status'],
				'selectSteps' => API_OUTPUT_COUNT,
				'templated' => false,
				'preservekeys' => true,
				'filter' => ['status' => HTTPTEST_STATUS_ACTIVE],
				'limit' => $config['search_limit'] + 1
			];

			if ($this->hostid != 0) {
				$options['hostids'] = [$this->hostid];
			}
			elseif ($this->groupid) {
				$options['groupids'] = zbx_toArray($this->groupid);
			}

			$httptests = API::HttpTest()->get($options);

			foreach ($httptests as &$httptest) {
				$httptest['host'] = reset($httptest['hosts']);
				$httptest['hostname'] = $httptest['host']['name'];
				unset($httptest['hosts']);
			}
			unset($httptest);

			order_result($httptests, $sort_field, $sort_order);

			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'web.view')
				->setArgument('groupid', $this->groupid)
				->setArgument('hostid', $this->hostid)
				->setArgument('fullscreen', $this->data['fullscreen']);

			$paging = getPagingLine($httptests, $sort_order, $url);
			$httptests = resolveHttpTestMacros($httptests, true, false);
			order_result($httptests, $sort_field, $sort_order);

			// Fetch the latest results of the web scenario.
			$last_httptest_data = Manager::HttpTest()->getLastData(array_keys($httptests));

			foreach ($httptests as &$httptest) {
				if (array_key_exists($httptest['httptestid'], $last_httptest_data)) {
					$httptest['lastcheck'] = $last_httptest_data[$httptest['httptestid']]['lastcheck'];
					$httptest['lastfailedstep'] = $last_httptest_data[$httptest['httptestid']]['lastfailedstep'];
					$httptest['error'] = $last_httptest_data[$httptest['httptestid']]['error'];
				}
			}
			unset($httptest);
		}

		// Create table.
		$table = (new CTableInfo())
			->setHeader([
				$this->hostid == 0
					? make_sorting_header(_('Host'), 'hostname', $sort_field, $sort_order, 'zabbix.php?action=web.view')
					: null,
				make_sorting_header(_('Name'), 'name', $sort_field, $sort_order, 'zabbix.php?action=web.view'),
				_('Number of steps'),
				_('Last check'),
				_('Status')
			]);

		foreach ($httptests as $httptest) {
			if ($this->hostid == 0) {
				$hostname = $httptest['hostname'];

				if ($httptest['host']['status'] == HOST_STATUS_NOT_MONITORED) {
					$hostname = (new CSpan($hostname))->addClass(ZBX_STYLE_RED);
				}
			}
			else {
				$hostname = null;
			}

			if (array_key_exists('lastfailedstep', $httptest) && $httptest['lastfailedstep'] !== null) {
				$lastcheck = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $httptest['lastcheck']);

				if ($httptest['lastfailedstep'] != 0) {
					$httpstep = get_httpstep_by_no($httptest['httptestid'], $httptest['lastfailedstep']);
					$error = ($httptest['error'] === null) ? _('Unknown error') : $httptest['error'];

					if ($httpstep) {
						$status = new CSpan(_s('Step "%1$s" [%2$s of %3$s] failed: %4$s', $httpstep['name'],
							$httptest['lastfailedstep'], $httptest['steps'], $error
						));
					}
					else {
						$status = new CSpan(_s('Unknown step failed: %1$s', $error));
					}

					$status->addClass(ZBX_STYLE_RED);
				}
				else {
					$status = (new CSpan(_('OK')))->addClass(ZBX_STYLE_GREEN);
				}
			}
			// no history data exists
			else {
				$lastcheck = (new CSpan(_('Never')))->addClass(ZBX_STYLE_RED);
				$status = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_GREY);
			}

			$table->addRow(new CRow([
				$hostname,
				new CLink($httptest['name'], 'httpdetails.php?httptestid='.$httptest['httptestid']),
				$httptest['steps'],
				$lastcheck,
				$status
			]));
		}

		return $this->getOutput([$table, $paging], true, $this->data);
	}
}
