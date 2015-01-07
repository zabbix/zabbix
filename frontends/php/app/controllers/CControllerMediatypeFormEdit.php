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

class CControllerMediatypeFormEdit extends CController {

	private $mediatype;

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'mediatypeid' =>		'fatal|db:media_type.mediatypeid|required',
			'type' =>				'fatal|db:media_type.type       |required_if:form,1|in:'.implode(',', array_keys(media_type2str())),
			'description' =>		'fatal|db:media_type.description|required_if:form,1',
			'smtp_server' =>		'fatal|db:media_type.smtp_server|required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'smtp_helo' =>			'fatal|db:media_type.smtp_helo  |required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'smtp_email' =>			'fatal|db:media_type.smtp_email |required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'exec_path' =>			'fatal|db:media_type.exec_path  |required_if:form,1|required_if:type,'.MEDIA_TYPE_EXEC.','.MEDIA_TYPE_EZ_TEXTING,
			'gsm_modem' =>			'fatal|db:media_type.hsm_modem  |required_if:form,1|required_if:type,'.MEDIA_TYPE_SMS,
			'username' =>			'fatal|db:media_type.username   |required_if:form,1|required_if:type,'.MEDIA_TYPE_JABBER.','.MEDIA_TYPE_EZ_TEXTING,
			'passwd' =>				'fatal|db:media_type.passwd     |required_if:form,1|required_if:type,'.MEDIA_TYPE_JABBER.','.MEDIA_TYPE_EZ_TEXTING,
			'status' =>				'fatal|db:media_type.status     |required_if:form,1|in:'.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$mediatypes = API::Mediatype()->get(array(
			'mediatypeids' => $this->getInput('mediatypeid'),
			'output' => array('mediatypeid','type','description','smtp_server','smtp_helo','smtp_email','exec_path','gsm_modem','username','passwd','status')
		));

		if (!$mediatypes) {
			return false;
		}

		$this->mediatype = $mediatypes[0];

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$data = array(
				'mediatypeid' => $this->getInput('mediatypeid'),
				'type' => $this->getInput('type'),
				'description' => $this->getInput('description'),
				'smtp_server' => $this->getInput('smtp_server'),
				'smtp_helo' => $this->getInput('smtp_helo'),
				'smtp_email' => $this->getInput('smtp_email'),
				'exec_path' => $this->getInput('exec_path'),
				'gsm_modem' => $this->getInput('gsm_modem'),
				'username' => $this->getInput('username'),
				'passwd' => $this->getInput('passwd'),
				'status' => $this->getInput('status')
			);
		}
		else {
			$data = array(
				'mediatypeid' => $this->mediatype['mediatypeid'],
				'type' => $this->mediatype['type'],
				'description' => $this->mediatype['description'],
				'smtp_server' => $this->mediatype['smtp_server'],
				'smtp_helo' => $this->mediatype['smtp_helo'],
				'smtp_email' => $this->mediatype['smtp_email'],
				'exec_path' => $this->mediatype['exec_path'],
				'gsm_modem' => $this->mediatype['gsm_modem'],
				'username' => $this->mediatype['username'],
				'passwd' => $this->mediatype['passwd'],
				'status' => $this->mediatype['status']
			);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
