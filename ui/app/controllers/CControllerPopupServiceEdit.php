<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupServiceEdit extends CControllerPopup {

	protected $service = [];

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>			'db services.serviceid',
			'name' =>				'db services.name',
			'parent_serviceids' =>	'array_db services.serviceid',
			'algorithm' =>			'db services.algorithm|in '.implode(',', [SERVICE_ALGORITHM_NONE, SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN]),
			'triggerid' =>			'db services.triggerid',
			'sortorder' =>			'db services.sortorder|ge 0|le 999',
			'showsla' =>			'db services.showsla|in '.SERVICE_SHOW_SLA_OFF.','.SERVICE_SHOW_SLA_ON,
			'goodsla' =>			'db services.goodsla',
			'times' =>				'array',
			'tags' =>				'array',
			'child_serviceids' =>	'array_db services.serviceid',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES)) {
			return false;
		}

		if ($this->hasInput('serviceid') && !$this->hasInput('form_refresh')) {
			$services = API::Service()->get([
				'output' => ['serviceid', 'name', 'algorithm', 'triggerid', 'showsla', 'goodsla', 'sortorder'],
				//'selectParents' => ['serviceid', 'name'],
				//'selectChildren' => ['serviceid', 'name', 'triggerid'],
				//'selectTags' => ['tag', 'value'],
				'selectTimes' => ['type', 'ts_from', 'ts_to', 'note'],
				'serviceids' => $this->getInput('serviceid')
			]);

			if (!$services) {
				return false;
			}

			$service = reset($services);
			$service['trigger'] = [];

			if ($service['triggerid'] != 0) {
				$trigger = API::Trigger()->get([
					'output' => ['description'],
					'selectHosts' => ['name'],
					'expandDescription' => true,
					'triggerids' => $service['triggerid']
				]);
				$trigger = reset($trigger);
				$host = reset($trigger['hosts']);
				$service['trigger'] = [
					'triggerid' => $service['triggerid'],
					'description' => $host['name'].NAME_DELIMITER.$trigger['description']
				];
			}

			$this->service = $service;
		}

		return true;
	}

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('services');

		$data = [
			'serviceid' => 0,
			'name' => '',
			'ms_parent_services' => [],
			'algorithm' => SERVICE_ALGORITHM_MAX,
			'ms_trigger' => [],
			'sortorder' => $db_defaults['sortorder'],
			'showsla' => $db_defaults['showsla'],
			'goodsla' => $db_defaults['goodsla'],
			'times' => [],
			'tags' => [],
			'children' => [],
			'form_refresh' => 0
		];

		if ($this->hasInput('serviceid') && !$this->hasInput('form_refresh')) {
			$data['serviceid'] = $this->service['serviceid'];
			$data['name'] = $this->service['name'];
			$data['algorithm'] = $this->service['algorithm'];
			$data['sortorder'] = $this->service['sortorder'];
			$data['showsla'] = $this->service['showsla'];
			$data['goodsla'] = $this->service['goodsla'];
			$data['times'] = $this->service['times'];
			//$data['tags'] = $this->service['tags'];
			//$data['children'] = $this->service['children'];

//			if ($this->service['parents']) {
//				CArrayHelper::sort($this->service['parents'], ['name']);
//				$data['ms_parent_services'] = CArrayHelper::renameObjectsKeys($this->service['parents'], [
//					'serviceid' => 'id'
//				]);
//			}

			if ($this->service['trigger']) {
				$data['ms_trigger'] = CArrayHelper::renameObjectsKeys([$this->service['trigger']], [
					'triggerid' => 'id',
					'description' => 'name'
				]);
			}
		}

		$this->getInputs($data, ['name', 'algorithm', 'sortorder', 'showsla', 'goodsla', 'form_refresh']);

		if ($data['form_refresh'] != 0) {
			$data['times'] = $this->getInput('times', []);
//			$data['tags'] = $this->getInput('tags', []);
		}

		if ($data['tags']) {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
		}
		else {
			$data['tags'][] = ['tag' => '', 'value' => ''];
		}

		$data += [
			'title' => _('Service'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
