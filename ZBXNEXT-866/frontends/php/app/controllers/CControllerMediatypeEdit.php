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


class CControllerMediatypeEdit extends CController {

	private $mediatype = array();

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = array(
			'mediatypeid' =>		'db media_type.mediatypeid',
			'type' =>				'db media_type.type       |in '.implode(',', array_keys(media_type2str())),
			'description' =>		'db media_type.description',
			'smtp_server' =>		'db media_type.smtp_server',
			'smtp_helo' =>			'db media_type.smtp_helo',
			'smtp_email' =>			'db media_type.smtp_email',
			'exec_path' =>			'db media_type.exec_path',
			'gsm_modem' =>			'db media_type.gsm_modem',
			'jabber_username' =>	'db media_type.username',
			'eztext_username' =>	'db media_type.username',
			'passwd' =>				'db media_type.passwd',
			'status' =>				'db media_type.status     |in '.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED
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

		if ($this->hasInput('mediatypeid')) {
			$mediatypes = API::Mediatype()->get(array(
				'output' => array('mediatypeid', 'type', 'description', 'smtp_server', 'smtp_helo', 'smtp_email',
					'exec_path', 'gsm_modem', 'username', 'passwd', 'status'
				),
				'mediatypeids' => $this->getInput('mediatypeid'),
				'editable' => true
			));

			if (!$mediatypes) {
				return false;
			}

			$this->mediatype = $mediatypes[0];
		}

		return true;
	}

	protected function doAction() {
		// default values
		$data = array(
			'sid' => $this->getUserSID(),
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
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		);

		// get values from the dabatase
		if ($this->hasInput('mediatypeid')) {
			$data['mediatypeid'] = $this->mediatype['mediatypeid'];
			$data['type'] = $this->mediatype['type'];
			$data['description'] = $this->mediatype['description'];
			$data['smtp_server'] = $this->mediatype['smtp_server'];
			$data['smtp_helo'] = $this->mediatype['smtp_helo'];
			$data['smtp_email'] = $this->mediatype['smtp_email'];
			$data['exec_path'] = $this->mediatype['exec_path'];
			$data['gsm_modem'] = $this->mediatype['gsm_modem'];
			$data['passwd'] = $this->mediatype['passwd'];
			$data['status'] = $this->mediatype['status'];

			switch ($data['type']) {
				case MEDIA_TYPE_JABBER:
					$data['jabber_username'] = $this->mediatype['username'];
					break;

				case MEDIA_TYPE_EZ_TEXTING:
					$data['eztext_username'] = $this->mediatype['username'];
					break;
			}
		}

		// overwrite with input variables
		$this->getInputs($data, array(
			'type',
			'description',
			'smtp_server',
			'smtp_helo',
			'smtp_email',
			'exec_path',
			'gsm_modem',
			'jabber_username',
			'eztext_username',
			'passwd',
			'status'
		));

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
