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


/**
 * Class containing operations with user profile edit form.
 */
class CControllerUserProfileEdit extends CControllerUserEditGeneral {

	protected function checkInput() {
		$this->appendValidationRules(['messages' => 'array']);

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->appendValidationRules([
				'user_medias' =>	'array',
				'new_media' =>		'array',
				'enable_media' =>	'int32',
				'disable_media' =>	'int32'
			]);
		}

		return parent::checkInput();
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest() || !CWebUser::isLoggedIn()) {
			return false;
		}

		$this->userid = CWebUser::$data['userid'];

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->options['selectMedias'] = ['mediatypeid', 'period', 'sendto', 'severity', 'active'];
		}

		return parent::checkPermissions();
	}

	/**
	 * Get user medias from DB if user is at least admin.
	 */
	protected function getDBData() {
		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->data['user_medias'] = $this->user['medias'];
		}
	}

	/**
	 * Set user medias if user is at least admin and set messages in data.
	 */
	protected function setFormData() {
		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->setUserMedias();
		}

		$messages = getMessageSettings();
		$this->data['messages'] = array_merge($messages, $this->getInput('messages', []));
	}

	protected function doAction() {
		$this->data = $this->getDataDefaults();

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->data += [
				'user_medias' => [],
				'new_media' => [],
			];
		}

		$this->fields = ['messages'];

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->fields = array_merge($this->fields, ['user_medias', 'new_media']);
		}

		$this->title = _('User profile');

		parent::doAction();
	}
}
