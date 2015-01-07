<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	protected function checkInput() {
		$fields = array(
			'sort' =>			'fatal|in_str:name,type',
			'sortorder' =>		'fatal|in_str:'.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'fatal|in_int:1'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $result;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$data['uncheck'] = $this->hasInput('uncheck');

		$sortField = $this->getInput('sort', CProfile::get('web.media_types.php.sort', 'description'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.media_types.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.media_type.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.media_types.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$config = select_config();

		$data['sort'] = $sortField;
		$data['sortorder'] = $sortOrder;

		// get media types
		$data['mediatypes'] = API::Mediatype()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'editable' => true,
			'limit' => $config['search_limit'] + 1
		));

		if ($data['mediatypes']) {
			// get media types used in actions
			$actions = API::Action()->get(array(
				'mediatypeids' => zbx_objectValues($data['mediatypes'], 'mediatypeid'),
				'output' => array('actionid', 'name'),
				'selectOperations' => array('operationtype', 'opmessage'),
				'preservekeys' => true
			));

			foreach ($data['mediatypes'] as $key => $mediaType) {
				$data['mediatypes'][$key]['typeid'] = $data['mediatypes'][$key]['type'];
				$data['mediatypes'][$key]['type'] = media_type2str($data['mediatypes'][$key]['type']);
				$data['mediatypes'][$key]['listOfActions'] = array();

				if ($actions) {
					foreach ($actions as $actionId => $action) {
						foreach ($action['operations'] as $operation) {
							if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
									&& $operation['opmessage']['mediatypeid'] == $mediaType['mediatypeid']) {

								$data['mediatypes'][$key]['listOfActions'][$actionId] = array(
									'actionid' => $actionId,
									'name' => $action['name']
								);
							}
						}
					}

					order_result($data['mediatypes'][$key]['listOfActions'], 'name');
				}
			}

			order_result($data['mediatypes'], $sortField, $sortOrder);

			$data['paging'] = getPagingLine($data['mediatypes']);
		}
		else {
			$arr = array();
			$data['paging'] = getPagingLine($arr);
		}


		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
