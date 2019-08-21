<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerUsergroupAddTagFilter extends CController {

	protected function checkInput() {

		$fields = [
			'new_tag_filter' => 'array',
			'tag_filters'    => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse((new CControllerResponseData([
				'main_block' => CJs::encodeJson(['errors' => getMessages()->toString()])
			]))->disableView());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$new_tag_filter = $this->getInput('new_tag_filter') + [
			'groupids' => [],
			'tag' => '',
			'value' => '',
			'include_subgroups' => false
		];

		if ($this->validateNewTagFilter($new_tag_filter)) {
			$view = (new CView('administration.usergroup.table.tagfilter', $this->getViewData($new_tag_filter)));
			$this->setResponse((new CControllerResponseData([
				'main_block' => CJs::encodeJson(['body' => $view->render()->toString()])
			])));
		}
		else {
			$this->setResponse((new CControllerResponseData([
				'main_block' => CJs::encodeJson(['errors' => getMessages()->toString()])
			])));
		}
	}

	/**
	 * @param array $new_tag_filter
	 *
	 * @return bool
	 */
	protected function validateNewTagFilter($new_tag_filter) {
		if (!$new_tag_filter['groupids']) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));

			return false;
		}
		elseif ($new_tag_filter['tag'] === '' && $new_tag_filter['value'] !== '') {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));

			return false;
		}

		return true;
	}

	/**
	 * @param array $new_tag_filter
	 *
	 * @return array
	 */
	protected function getViewData(array $new_tag_filter) {
		$groupids = $new_tag_filter['include_subgroups']
			? getSubGroups($new_tag_filter['groupids'])
			: $new_tag_filter['groupids'];

		$view_data = ['tag_filters' => $this->getInput('tag_filters', [])];
		foreach ($groupids as $groupid) {
			$view_data['tag_filters'][] = [
				'groupid' => $groupid,
				'tag' => $new_tag_filter['tag'],
				'value' => $new_tag_filter['value'],
			];
		}

		$view_data['tag_filters'] = collapseTagFilters($view_data['tag_filters']);

		return $view_data;
	}
}
