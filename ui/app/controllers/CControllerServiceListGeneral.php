<?php
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


abstract class CControllerServiceListGeneral extends CController {

	protected $validation_fields = [
		'serviceid' =>			'db services.serviceid',
		'filter_name' =>		'string',
		'filter_status' =>		'in '.SERVICE_STATUS_ANY.','.SERVICE_STATUS_OK.','.SERVICE_STATUS_PROBLEM,
		'filter_evaltype' =>	'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
		'filter_tags' =>		'array',
		'page' =>				'ge 1',
	];

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->validation_fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function doAction() {
		// TODO: Implement doAction() method.
	}

	protected function updateFilter(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.service.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);

			CProfile::update('web.service.filter_status', $this->getInput('filter_status', SERVICE_STATUS_ANY), PROFILE_TYPE_INT);

			$evaltype = $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
			CProfile::update('web.service.filter.evaltype', $evaltype, PROFILE_TYPE_INT);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}
				$filter_tags['tags'][] = $tag['tag'];
				$filter_tags['values'][] = $tag['value'];
				$filter_tags['operators'][] = $tag['operator'];
			}
			CProfile::updateArray('web.service.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.service.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.service.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.service.filter_name');
			CProfile::delete('web.service.filter_status');
			CProfile::deleteIdx('web.service.filter.evaltype');
			CProfile::deleteIdx('web.service.filter.tags.tag');
			CProfile::deleteIdx('web.service.filter.tags.value');
			CProfile::deleteIdx('web.service.filter.tags.operator');
		}
	}

	protected function getFilter(): array {
		$filter = [
			'name' => CProfile::get('web.service.filter_name', ''),
			'status' => CProfile::get('web.service.filter_status', SERVICE_STATUS_ANY),
			'evaltype' => CProfile::get('web.service.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		foreach (CProfile::getArray('web.service.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.service.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.service.filter.tags.operator', null, $i)
			];
		}

		return $filter;
	}
}
