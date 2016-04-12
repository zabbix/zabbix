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


class CControllerMapView extends CController {

	private $sysmapid;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sysmapid' =>		'db sysmaps.sysmapid',
			'mapname' =>		'not_empty',
			'severity_min' =>	'in 0,1,2,3,4,5',
			'fullscreen' =>		'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}

		$sysmapid = null;
		$options = ['output' => ['sysmapid']];

		if ($this->hasInput('mapname')) {
			// Get map by name.
			$options['search']['name'] = $this->getInput('mapname');
		}
		elseif ($this->hasInput('sysmapid')) {
			// Get map by sysmapid from request.
			$options['sysmapids'] = [$this->getInput('sysmapid')];
		}
		else {
			// Get map by sysmapid from profile.
			$options['sysmapids'] = [CProfile::get('web.maps.sysmapid', 0)];
		}

		$sysmaps = API::Map()->get($options);

		if ($sysmaps) {
			$sysmap = reset($sysmaps);
			$sysmapid = $sysmap['sysmapid'];
		}

		if ($sysmapid === null) {
			if (!$this->hasInput('mapname') && !$this->hasInput('sysmapid')) {
				// Redirect to map list.
				redirect('sysmaps.php');
			}
			else {
				// No permissions.
				return false;
			}
		}

		$this->sysmapid = $sysmapid;

		return true;
	}

	protected function doAction() {
		CProfile::update('web.maps.sysmapid', $this->sysmapid, PROFILE_TYPE_ID);

		$data = [
			'fullscreen' => $this->getInput('fullscreen', 0)
		];

		$maps = API::Map()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'sysmapids' => [$this->sysmapid],
			'expandUrls' => true
		]);
		$data['map'] = $maps[0];

		$data['map']['editable'] = API::Map()->isWritable([$this->sysmapid]);

		$data['pageFilter'] = new CPageFilter([
			'severitiesMin' => [
				'default' => $data['map']['severity_min'],
				'mapId' => $this->sysmapid
			],
			'severityMin' => $this->hasInput('severity_min') ? $this->getInput('severity_min') : null
		]);

		$data['severity_min'] = $data['pageFilter']->severityMin;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Network maps'));
		$this->setResponse($response);
	}
}
