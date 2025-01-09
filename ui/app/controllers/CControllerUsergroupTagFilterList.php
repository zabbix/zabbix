<?php declare(strict_types = 0);
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


class CControllerUsergroupTagFilterList extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'tag_filters' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction(): void {
		$data = [
			'tag_filters' => $this->getInput('tag_filters', [])
		];

		CArrayHelper::sort($data['tag_filters'], ['name']);

		$tag_filters_badges = $data['tag_filters'];

		foreach ($tag_filters_badges as $key => $group) {
			$tags = $group['tags'];

			if (!$tags || (count($tags) == 1 && $tags[key($tags)]['tag'] === '')) {
				unset($tag_filters_badges[$key]);
			}
		}

		$data['tag_filters_badges'] = makeTags($tag_filters_badges, true, 'groupid');

		$this->setResponse(new CControllerResponseData($data));
	}
}
