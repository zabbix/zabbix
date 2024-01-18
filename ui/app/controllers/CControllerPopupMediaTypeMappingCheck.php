<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerPopupMediaTypeMappingCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' =>		'required|db media_type.mediatypeid',
			'name' =>				'required|string|not_empty',
			'attribute' =>			'required|string|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid media type mapping configuration.'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name'),
			'attribute' => $this->getInput('attribute')
		];

		$media_type = API::MediaType()->get([
			'output' => ['name', 'mediatypeid'],
			'mediatypeids' => $this->getInput('mediatypeid')
		]);

		if ($media_type) {
			$data['mediatype_name'] = $media_type[0]['name'];
			$data['mediatypeid'] = $media_type[0]['mediatypeid'];
		}
		else {
			$data['error'] = [
				'messages' => [_('No permissions to referred object or it does not exist!')]
			];
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
