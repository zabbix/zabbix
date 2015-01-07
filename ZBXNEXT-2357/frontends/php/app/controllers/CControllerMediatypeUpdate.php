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

class CControllerMediatypeUpdate extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'mediatypeid' =>		'fatal|db:media_type.mediatypeid|required',
			'type' =>				'fatal|db:media_type.type       |required|in:'.implode(',', array_keys(media_type2str())),
			'description' =>		'      db:media_type.description|required|not_empty',
			'smtp_server' =>		'      db:media_type.smtp_server|required_if:type,'.MEDIA_TYPE_EMAIL.'|not_empty',
			'smtp_helo' =>			'      db:media_type.smtp_helo  |required_if:type,'.MEDIA_TYPE_EMAIL.'|not_empty',
			'smtp_email' =>			'      db:media_type.smtp_email |required_if:type,'.MEDIA_TYPE_EMAIL.'|not_empty',
			'exec_path' =>			'      db:media_type.exec_path  |required_if:type,'.MEDIA_TYPE_EXEC.','.MEDIA_TYPE_EZ_TEXTING.'|not_empty',
			'gsm_modem' =>			'      db:media_type.hsm_modem  |required_if:type,'.MEDIA_TYPE_SMS.'|not_empty',
			'username' =>			'      db:media_type.username   |required_if:type,'.MEDIA_TYPE_JABBER.','.MEDIA_TYPE_EZ_TEXTING.'|not_empty',
			'passwd' =>				'      db:media_type.passwd     |required_if:type,'.MEDIA_TYPE_JABBER.','.MEDIA_TYPE_EZ_TEXTING.'|not_empty',
			'status' =>				'fatal|db:media_type.status     |required|in:'.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED,
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.formedit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update media type'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $result;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$mediatypes = API::Mediatype()->get(array(
			'mediatypeids' => $this->getInput('mediatypeid'),
			'output' => array()
		));

		if (!$mediatypes) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$mediatype = array(
			'host' => $this->getInput('host'),
			'status' => $this->getInput('status'),
			'interface' => $this->getInput('interface'),
			'description' => $this->getInput('description')
		);

		DBstart();

		$result = API::Mediatype()->update($mediatype);

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MEDIA_TYPE, 'Media type ['.$mediatype['description'].']');
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.list&uncheck=1');
			$response->setMessageOk(_('Media type updated'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.formedit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update media type'));
		}
		$this->setResponse($response);
	}
}
