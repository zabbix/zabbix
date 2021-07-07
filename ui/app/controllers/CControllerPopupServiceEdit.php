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

class CControllerPopupServiceEdit extends CController {

	private $service;

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>			'db services.serviceid',
			'parent_serviceids' =>	'array_db services.serviceid',
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES)) {
			return false;
		}

		if ($this->hasInput('serviceid')) {
			$this->service = API::Service()->get([
				'output' => ['serviceid', 'name', 'algorithm', 'triggerid', 'showsla', 'goodsla', 'sortorder'],
				'selectParents' => ['serviceid', 'name'],
				'selectChildren' => ['serviceid', 'name', 'triggerid'],
				'selectTags' => ['tag', 'value'],
				'selectTimes' => ['type', 'ts_from', 'ts_to', 'note'],
				'serviceids' => $this->getInput('serviceid')
			]);

			if (!$this->service) {
				return false;
			}

			$this->service = $this->service[0];

			CArrayHelper::sort($this->service['parents'], ['name']);
			CArrayHelper::sort($this->service['parents'], ['name']);
			CArrayHelper::sort($this->service['children'], ['name']);
			CArrayHelper::sort($this->service['times'], ['type', 'ts_from', 'ts_to']);
		}

		return true;
	}

	protected function doAction(): void {
		$trigger_descriptions = [];

		if ($this->service !== null) {
			$triggerids = [];

			if ($this->service['triggerid'] != 0) {
				$triggerids[$this->service['triggerid']] = true;
			}

			foreach ($this->service['children'] as $service) {
				if ($service['triggerid'] != 0) {
					$triggerids[$service['triggerid']] = true;
				}
			}

			if ($triggerids) {
				$triggerids = array_keys($triggerids);

				$triggers = API::Trigger()->get([
					'output' => ['description'],
					'selectHosts' => ['name'],
					'expandDescription' => true,
					'triggerids' => $triggerids,
					'preservekeys' => true
				]);

				foreach ($triggerids as $triggerid) {
					if (array_key_exists($triggerid, $triggers)) {
						$trigger_descriptions[$triggerid] = $triggers[$triggerid]['hosts'][0]['name'].NAME_DELIMITER.
							$triggers[$triggerid]['description'];
					}
					else {
						$trigger_descriptions[$triggerid] = _('Inaccessible trigger');
					}
				}
			}
		}

		if ($this->service !== null) {
			$parents = $this->service['parents'];
		}
		elseif ($this->hasInput('parent_serviceids')) {
			$parents = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $this->getInput('parent_serviceids')
			]);
		}
		else {
			$parents = [];
		}

		$defaults = DB::getDefaults('services');

		$data = [
			'title' => _('Service'),
			'serviceid' => $this->service !== null ? $this->service['serviceid'] : null,
			'form' => [
				'name' => $this->service !== null ? $this->service['name'] : $defaults['name'],
				'parents' => $parents,
				'children' => $this->service !== null ? $this->service['children'] : [],
				'algorithm' => $this->service !== null ? $this->service['algorithm'] : $defaults['algorithm'],
				'triggerid' => $this->service !== null ? $this->service['triggerid'] : 0,
				'sortorder' => $this->service !== null ? $this->service['sortorder'] : $defaults['sortorder'],
				'showsla' => $this->service !== null ? $this->service['showsla'] : $defaults['showsla'],
				'goodsla' => $this->service !== null ? $this->service['goodsla'] : $defaults['goodsla'],
				'times' => $this->service !== null ? $this->service['times'] : [],
				'tags' => ($this->service !== null && $this->service['tags'])
					? $this->service['tags']
					: [['tag' => '', 'value' => '']],
				'trigger_descriptions' => $trigger_descriptions
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
