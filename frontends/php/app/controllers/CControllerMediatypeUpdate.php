<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
			'mediatypeid' =>		'fatal|required|db media_type.mediatypeid',
			'type' =>				'      required|db media_type.type       |in '.implode(',', array_keys(media_type2str())),
			'description' =>		'               db media_type.description|not_empty',
			'smtp_server' =>		'               db media_type.smtp_server',
			'smtp_helo' =>			'               db media_type.smtp_helo',
			'smtp_email' =>			'               db media_type.smtp_email',
			'exec_path' =>			'               db media_type.exec_path',
			'gsm_modem' =>			'               db media_type.gsm_modem',
			'jabber_username' =>	'               db media_type.username',
			'eztext_username' =>	'               db media_type.username',
			'passwd' =>				'               db media_type.passwd',
			'status' =>				'               db media_type.status     |in '.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED,
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update media type'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		return (bool) API::Mediatype()->get(array(
			'output' => array(),
			'mediatypeids' => $this->getInput('mediatypeid'),
			'editable' => true
		));
	}

	protected function doAction() {
		$mediatype = array();

		$this->getInputs($mediatype, array('mediatypeid', 'type', 'description', 'status'));

		switch ($mediatype['type']) {
			case MEDIA_TYPE_EMAIL:
				$this->getInputs($mediatype, array('smtp_server', 'smtp_helo', 'smtp_email'));
				break;
			case MEDIA_TYPE_EXEC:
				$this->getInputs($mediatype, array('exec_path'));
				break;
			case MEDIA_TYPE_SMS:
				$this->getInputs($mediatype, array('gsm_modem'));
				break;
			case MEDIA_TYPE_JABBER:
				$this->getInputs($mediatype, array('passwd'));
				if ($this->hasInput('jabber_username')) {
					$mediatype['username'] = $this->getInput('jabber_username');
				}
				break;
			case MEDIA_TYPE_EZ_TEXTING:
				$this->getInputs($mediatype, array('passwd', 'exec_path'));
				if ($this->hasInput('eztext_username')) {
					$mediatype['username'] = $this->getInput('eztext_username');
				}
				break;
		}

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
			$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update media type'));
		}
		$this->setResponse($response);
	}
}
