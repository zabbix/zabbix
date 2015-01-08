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

class CControllerMediatypeFormCreate extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'type' =>				'fatal|db:media_type.type       |required_if:form,1|in:'.implode(',', array_keys(media_type2str())),
			'description' =>		'fatal|db:media_type.description|required_if:form,1',
			'smtp_server' =>		'fatal|db:media_type.smtp_server|required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'smtp_helo' =>			'fatal|db:media_type.smtp_helo  |required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'smtp_email' =>			'fatal|db:media_type.smtp_email |required_if:form,1|required_if:type,'.MEDIA_TYPE_EMAIL,
			'exec_path' =>			'fatal|db:media_type.exec_path  |required_if:form,1|required_if:type,'.MEDIA_TYPE_EXEC.','.MEDIA_TYPE_EZ_TEXTING,
			'gsm_modem' =>			'fatal|db:media_type.gsm_modem  |required_if:form,1|required_if:type,'.MEDIA_TYPE_SMS,
			'jabber_username' =>	'fatal|db:media_type.username   |required_if_all:form,1,type,'.MEDIA_TYPE_JABBER,
			'eztext_username' =>	'fatal|db:media_type.username   |required_if:form,1|required_if:type,'.MEDIA_TYPE_EZ_TEXTING,
			'passwd' =>				'fatal|db:media_type.passwd     |required_if:form,1|required_if:type,'.MEDIA_TYPE_JABBER.','.MEDIA_TYPE_EZ_TEXTING,
			'status' =>				'fatal|db:media_type.status     |required_if:form,1|in:'.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED
		);

		$ret = $this->validateInput($fields);

$ret = true;

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$data = array(
				'mediatypeid' => 0,
				'type' => $this->getInput('type'),
				'description' => $this->getInput('description'),
				'smtp_server' => $this->getInput('smtp_server'),
				'smtp_helo' => $this->getInput('smtp_helo'),
				'smtp_email' => $this->getInput('smtp_email'),
				'exec_path' => $this->getInput('exec_path'),
				'gsm_modem' => $this->getInput('gsm_modem'),
				'jabber_username' => $this->getInput('type') == MEDIA_TYPE_JABBER ? $this->getInput('username') : 'user@server',
				'eztext_username' => $this->getInput('type') == MEDIA_TYPE_EZTEXT ? $this->getInput('username') : '',
				'passwd' => $this->getInput('passwd'),
				'status' => $this->getInput('status')
			);
		}
		else {
			$data = array(
				'mediatypeid' => 0,
				'type' => MEDIA_TYPE_EMAIL,
				'description' => '',
				'smtp_server' => 'localhost',
				'smtp_helo' => 'localhost',
				'smtp_email' => 'zabbix@localhost',
				'exec_path' => '',
				'gsm_modem' => '/dev/ttyS0',
				'jabber_username' => 'user@server',
				'eztext_username' => '',
				'passwd' => '',
				'status' => MEDIA_TYPE_STATUS_ACTIVE,
			);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
