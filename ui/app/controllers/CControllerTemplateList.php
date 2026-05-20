<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerTemplateList extends CController {

	public const DEFAULT_SORT = 'name';
	public const DEFAULT_SORTORDER = ZBX_SORT_UP;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>			'string',
			'filter_vendor_name' =>		'string',
			'filter_vendor_version' =>	'string',
			'filter_templates' =>		'array_db hosts.hostid',
			'filter_groups' =>			'array_db hosts_groups.groupid',
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'sort' =>					'in name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1',
			'uncheck' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
		}

		$filter_tags = [];

		foreach (CProfile::getArray('web.templates.filter.tags.tag', []) as $i => $tag) {
			$filter_tags[] = [
				'tag' => $tag,
				'value' => CProfile::get('web.templates.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.templates.filter.tags.operator', null, $i)
			];
		}

		$filter = [
			'name' => CProfile::get('web.templates.filter_name', ''),
			'vendor_name' => CProfile::get('web.templates.filter_vendor_name', ''),
			'vendor_version' => CProfile::get('web.templates.filter_vendor_version', ''),
			'templates' => CProfile::getArray('web.templates.filter_templates', []),
			'groups' => CProfile::getArray('web.templates.filter_groups', []),
			'evaltype' => CProfile::get('web.templates.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => $filter_tags
		];

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$sort_field = $this->getInput('sort',
			CProfile::get('web.templates.sort', self::DEFAULT_SORT));
		$sort_order = $this->getInput('sortorder',
			CProfile::get('web.templates.sortorder', self::DEFAULT_SORTORDER));

		CProfile::update('web.templates.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.templates.sortorder', $sort_order, PROFILE_TYPE_STR);

		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::TemplateGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groups'],
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		if (!$filter['tags']) {
			$filter['tags'] = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
		}

		$storage_idx = 'web.templates.datatable';

		$data = [
			'action' => $this->getAction(),
			'active_tab' => CProfile::get('web.templates.filter.active', 1),
			'default_sort_field' => $sort_field,
			'default_sort_order' => $sort_order,
			'filter' => $filter,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'page' => $this->getInput('page', 1),
			'profileIdx' => 'web.templates.filter',
			'storage_idx' => $storage_idx,
			'uncheck' => $this->getInput('uncheck', 0) == 1,
			'user_configs' => array_map(static fn (string $user_config) => json_decode($user_config, true) ?? [],
				CProfile::getArray($storage_idx, [])
			)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of templates'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		$filter_tags = [];

		foreach ($this->getInput('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] !== '' || $filter_tag['value'] !== '') {
				$filter_tags[] = $filter_tag;
			}
		}

		CProfile::update('web.templates.filter_name', $this->getInput('filter_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.templates.filter_vendor_name', $this->getInput('filter_vendor_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.templates.filter_vendor_version', $this->getInput('filter_vendor_version', ''),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.templates.filter_templates', $this->getInput('filter_templates', []),
			PROFILE_TYPE_ID
		);
		CProfile::updateArray('web.templates.filter_groups', $this->getInput('filter_groups', []),
			PROFILE_TYPE_ID
		);
		CProfile::update('web.templates.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.templates.filter.tags.tag', array_column($filter_tags, 'tag'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.templates.filter.tags.value', array_column($filter_tags, 'value'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.templates.filter.tags.operator', array_column($filter_tags, 'operator'),
			PROFILE_TYPE_INT
		);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.templates.filter_name');
		CProfile::delete('web.templates.filter_vendor_name');
		CProfile::delete('web.templates.filter_vendor_version');
		CProfile::deleteIdx('web.templates.filter_templates');
		CProfile::deleteIdx('web.templates.filter_groups');
		CProfile::delete('web.templates.filter.evaltype');
		CProfile::deleteIdx('web.templates.filter.tags.tag');
		CProfile::deleteIdx('web.templates.filter.tags.value');
		CProfile::deleteIdx('web.templates.filter.tags.operator');
	}
}
