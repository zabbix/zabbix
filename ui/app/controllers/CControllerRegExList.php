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


class CControllerRegExList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>		'string',
			'filter_description' =>	'string',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'sort' => 				'in name',
			'sortorder'	=>			'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>				'ge 1',
			'uncheck' => 			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.regex.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.regex.filter.description',
				$this->getInput('filter_description', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.regex.filter.name');
			CProfile::delete('web.regex.filter.description');
		}

		$filter = [
			'name' => CProfile::get('web.regex.filter.name', ''),
			'description' => CProfile::get('web.regex.filter.description', '')
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.regex.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.regex.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.regex.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.regex.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'filter' => $filter,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'profileIdx' => 'web.regex.filter',
			'active_tab' => CProfile::get('web.regex.filter.active', 1),
			'uncheck' => $this->hasInput('uncheck')
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$data['regexps'] = API::Regexp()->get([
			'output' => ['regexpid', 'name', 'description'],
			'selectExpressions' => ['expression', 'expression_type'],
			'search' => [
				'name' => $filter['name'] !== '' ? $filter['name'] : null,
				'description' => $filter['description'] !== '' ? $filter['description'] : null
			],
			'limit' => $limit,
			'preservekeys' => true
		]);

		order_result($data['regexps'], $sort_field, $sort_order);

		$data['page'] = $this->getInput('page', 1);
		CPagerHelper::savePage('regex.list', $data['page']);
		$data['paging'] = CPagerHelper::paginate($data['page'], $data['regexps'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
