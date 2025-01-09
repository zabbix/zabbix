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


class CControllerMapView extends CController {

	private $sysmapid;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'sysmapid' =>		'db sysmaps.sysmapid',
			'mapname' =>		'not_empty',
			'severity_min' =>	'in '.implode(',', [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_MAPS)) {
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
		$maps = API::Map()->get([
			'output' => ['name', 'severity_min'],
			'sysmapids' => [$this->sysmapid]
		]);

		$map = reset($maps);

		if ($this->hasInput('severity_min')) {
			$severity_min = $this->getInput('severity_min');
			if ($severity_min == $map['severity_min']) {
				CProfile::delete('web.maps.severity_min', $this->sysmapid);
			}
			else {
				CProfile::update('web.maps.severity_min', $severity_min, PROFILE_TYPE_INT, $this->sysmapid);
			}
		}
		else {
			$severity_min = CProfile::get('web.maps.severity_min', $map['severity_min'], $this->sysmapid);
		}

		$map['editable'] = (bool) API::Map()->get([
			'output' => [],
			'sysmapids' => [$this->sysmapid],
			'editable' => true
		]);

		$severities_dropdown = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_name = CSeverityHelper::getName($severity);

			$severities_dropdown[$severity] = ($severity == $map['severity_min'])
				? $severity_name.' ('._('default').')'
				: $severity_name;
		}

		$response = new CControllerResponseData([
			'map' => $map,
			'severity_min' => $severity_min,
			'severities' => $severities_dropdown,
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS)
		]);

		$response->setTitle(_('Network maps'));
		$this->setResponse($response);
	}
}
