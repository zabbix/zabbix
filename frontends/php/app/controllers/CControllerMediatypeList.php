<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerMediatypeList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>			'in description,type',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'in 1',
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in -1,'.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED,
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.media_types.php.sort', 'description'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.media_types.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.media_type.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.media_types.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		// filter
		if (hasRequest('filter_set')) {
			CProfile::update('web.media_types.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.media_types.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif (hasRequest('filter_rst')) {
			CProfile::delete('web.media_types.filter_name');
			CProfile::delete('web.media_types.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.media_types.filter_name', ''),
			'status' => CProfile::get('web.media_types.filter_status', -1)
		];

		$config = select_config();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'filter' => $filter
		];

		// get media types
		$data['mediatypes'] = API::Mediatype()->get([
			'output' => ['mediatypeid', 'description', 'type', 'smtp_server', 'smtp_helo', 'smtp_email',
				'exec_path', 'gsm_modem', 'username', 'status'
			],
			'search' => [
				'description' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'limit' => $config['search_limit'] + 1,
			'editable' => true,
			'preservekeys' => true
		]);

		if ($data['mediatypes']) {
			// get media types used in actions
			$actions = API::Action()->get([
				'output' => ['actionid', 'name'],
				'selectOperations' => ['operationtype', 'opmessage'],
				'mediatypeids' => array_keys($data['mediatypes'])
			]);

			foreach ($data['mediatypes'] as &$mediaType) {
				$mediaType['typeid'] = $mediaType['type'];
				$mediaType['type'] = media_type2str($mediaType['type']);
				$mediaType['listOfActions'] = [];

				foreach ($actions as $action) {
					foreach ($action['operations'] as $operation) {
						if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
								&& $operation['opmessage']['mediatypeid'] == $mediaType['mediatypeid']) {

							$mediaType['listOfActions'][$action['actionid']] = [
								'actionid' => $action['actionid'],
								'name' => $action['name']
							];
						}
					}
				}

				order_result($mediaType['listOfActions'], 'name');
			}
			unset($mediaType);

			order_result($data['mediatypes'], $sortField, $sortOrder);
		}

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'mediatype.list');

		$data['paging'] = getPagingLine($data['mediatypes'], $sortOrder, $url);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
