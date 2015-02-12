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


class CControllerDiscoveryView extends CController {

	private $sysmapid;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = array(
			'druleid' =>	'db drules.druleid',
			'sort' =>		'in ip',
			'sortorder' =>	'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'fullscreen' =>	'in 0,1'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->hasInput('druleid') && $this->getInput('druleid') != 0) {
			$drules = API::DRule()->get(array(
				'output' => array(),
				'druleids' => array($this->getInput('druleid')),
				'filter' => array('status' => DRULE_STATUS_ACTIVE)
			));
			if (!$drules) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.discovery.php.sort', 'ip'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.discovery.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.discovery.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.discovery.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		/*
		 * Display
		 */
		$data = array(
			'fullscreen' => $this->getInput('fullscreen', 0),
			'druleid' => $this->getInput('druleid', 0),
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'services' => array(),
			'drules' => array()
		);

		$data['pageFilter'] = new CPageFilter(array(
			'drules' => array('filter' => array('status' => DRULE_STATUS_ACTIVE)),
			'druleid' => $data['druleid']
		));

		if ($data['pageFilter']->drulesSelected) {

			// discovery rules
			$options = array(
				'output' => API_OUTPUT_EXTEND,
				'selectDHosts' => API_OUTPUT_EXTEND,
				'filter' => array('status' => DRULE_STATUS_ACTIVE)
			);

			if ($data['pageFilter']->druleid > 0) {
				$options['druleids'] = $data['pageFilter']->druleid; // set selected discovery rule id
			}

			$data['drules'] = API::DRule()->get($options);
			if (!empty($data['drules'])) {
				order_result($data['drules'], 'name');
			}

			// discovery services
			$options = array(
				'selectHosts' => array('hostid', 'name', 'status'),
				'output' => API_OUTPUT_EXTEND,
				'sortfield' => $sortField,
				'sortorder' => $sortOrder,
				'limitSelects' => 1
			);
			if (!empty($data['druleid'])) {
				$options['druleids'] = $data['druleid'];
			}
			else {
				$options['druleids'] = zbx_objectValues($data['drules'], 'druleid');
			}
			$dservices = API::DService()->get($options);

			// user macros
			$data['macros'] = API::UserMacro()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'globalmacro' => true
			));
			$data['macros'] = zbx_toHash($data['macros'], 'macro');

			// services
			$data['services'] = array();
			foreach ($dservices as $dservice) {
				$key_ = $dservice['key_'];
				if (!zbx_empty($key_)) {
					if (isset($data['macros'][$key_])) {
						$key_ = $data['macros'][$key_]['value'];
					}
					$key_ = ': '.$key_;
				}
				$serviceName = discovery_check_type2str($dservice['type']).
					discovery_port2str($dservice['type'], $dservice['port']).$key_;
				$data['services'][$serviceName] = 1;
			}
			ksort($data['services']);

			// discovery services to hash
			$data['dservices'] = zbx_toHash($dservices, 'dserviceid');

			// discovery hosts
			$data['dhosts'] = API::DHost()->get(array(
				'druleids' => zbx_objectValues($data['drules'], 'druleid'),
				'selectDServices' => array('dserviceid', 'ip', 'dns', 'type', 'status', 'key_'),
				'output' => array('dhostid', 'lastdown', 'lastup', 'druleid')
			));
			$data['dhosts'] = zbx_toHash($data['dhosts'], 'dhostid');
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Status of discovery'));
		$this->setResponse($response);
	}
}
