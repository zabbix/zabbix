<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

	private $mediatype = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'mediatypeid' =>			'db media_type.mediatypeid',
			'type' =>					'db media_type.type|in '.implode(',', array_keys(media_type2str())),
			'description' =>			'db media_type.description',
			'smtp_server' =>			'db media_type.smtp_server',
			'smtp_port' =>				'db media_type.smtp_port',
			'smtp_helo' =>				'db media_type.smtp_helo',
			'smtp_email' =>				'db media_type.smtp_email',
			'smtp_security' =>			'db media_type.smtp_security|in '.SMTP_CONNECTION_SECURITY_NONE.','.SMTP_CONNECTION_SECURITY_STARTTLS.','.SMTP_CONNECTION_SECURITY_SSL_TLS,
			'smtp_verify_peer' =>		'db media_type.smtp_verify_peer|in 0,1',
			'smtp_verify_host' =>		'db media_type.smtp_verify_host|in 0,1',
			'smtp_authentication' =>	'db media_type.smtp_authentication|in '.SMTP_AUTHENTICATION_NONE.','.SMTP_AUTHENTICATION_NORMAL,
			'exec_path' =>				'db media_type.exec_path',
			'eztext_limit' =>			'in '.EZ_TEXTING_LIMIT_USA.','.EZ_TEXTING_LIMIT_CANADA,
			'exec_params' =>			'array',
			'gsm_modem' =>				'db media_type.gsm_modem',
			'jabber_username' =>		'db media_type.username',
			'eztext_username' =>		'db media_type.username',
			'smtp_username' =>			'db media_type.username',
			'passwd' =>					'db media_type.passwd',
			'status' =>					'db media_type.status|in '.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('exec_params')) {
			foreach ($this->getInput('exec_params') as $exec_param) {
				if (count($exec_param) != 1
						|| !array_key_exists('exec_param', $exec_param) || !is_string($exec_param['exec_param'])) {
					$ret = false;
					break;
				}
			}
		}

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
			$mediatypes = API::Mediatype()->get([
				'output' => ['mediatypeid', 'type', 'description', 'smtp_server', 'smtp_port', 'smtp_helo',
					'smtp_email', 'exec_path', 'gsm_modem', 'username', 'passwd', 'status', 'smtp_security',
					'smtp_verify_peer', 'smtp_verify_host', 'smtp_authentication', 'exec_params'
				],
				'mediatypeids' => $this->getInput('mediatypeid'),
				'editable' => true
			]);

			if (!$mediatypes) {
				return false;
			}

			$this->mediatype = $mediatypes[0];
		}

		return true;
	}

	protected function doAction() {
		// default values
		$data = [
			'sid' => $this->getUserSID(),
			'mediatypeid' => 0,
			'type' => MEDIA_TYPE_EMAIL,
			'description' => '',
			'smtp_server' => 'localhost',
			'smtp_port' => '25',
			'smtp_helo' => 'localhost',
			'smtp_email' => 'zabbix@localhost',
			'smtp_security' => '0',
			'smtp_verify_peer' => '0',
			'smtp_verify_host' => '0',
			'smtp_authentication' => '0',
			'exec_params' => [],
			'exec_path' => '',
			'gsm_modem' => '/dev/ttyS0',
			'jabber_username' => 'user@server',
			'eztext_username' => '',
			'eztext_limit' => EZ_TEXTING_LIMIT_USA,
			'smtp_username' => '',
			'passwd' => '',
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		];

		// get values from the dabatase
		if ($this->hasInput('mediatypeid')) {
			$data['mediatypeid'] = $this->mediatype['mediatypeid'];
			$data['type'] = $this->mediatype['type'];
			$data['description'] = $this->mediatype['description'];
			$data['smtp_server'] = $this->mediatype['smtp_server'];
			$data['smtp_port'] = $this->mediatype['smtp_port'];
			$data['smtp_helo'] = $this->mediatype['smtp_helo'];
			$data['smtp_email'] = $this->mediatype['smtp_email'];
			$data['smtp_security'] = $this->mediatype['smtp_security'];
			$data['smtp_verify_peer'] = $this->mediatype['smtp_verify_peer'];
			$data['smtp_verify_host'] = $this->mediatype['smtp_verify_host'];
			$data['smtp_authentication'] = $this->mediatype['smtp_authentication'];
			$data['exec_path'] = $this->mediatype['exec_path'];

			$this->mediatype['exec_params'] = explode("\n", $this->mediatype['exec_params']);
			foreach ($this->mediatype['exec_params'] as $exec_param) {
				$data['exec_params'][] = ['exec_param' => $exec_param];
			}
			// Remove last empty new line param.
			array_pop($data['exec_params']);

			$data['gsm_modem'] = $this->mediatype['gsm_modem'];
			$data['passwd'] = $this->mediatype['passwd'];
			$data['status'] = $this->mediatype['status'];

			switch ($data['type']) {
				case MEDIA_TYPE_EMAIL:
					$data['smtp_username'] = $this->mediatype['username'];
					break;

				case MEDIA_TYPE_JABBER:
					$data['jabber_username'] = $this->mediatype['username'];
					break;

				case MEDIA_TYPE_EZ_TEXTING:
					$data['eztext_username'] = $this->mediatype['username'];
					$data['eztext_limit'] = $this->mediatype['exec_path'];
					break;
			}
		}

		// overwrite with input variables
		$this->getInputs($data, [
			'type',
			'description',
			'smtp_server',
			'smtp_port',
			'smtp_helo',
			'smtp_email',
			'smtp_security',
			'smtp_verify_peer',
			'smtp_verify_host',
			'smtp_authentication',
			'exec_params',
			'exec_path',
			'eztext_limit',
			'gsm_modem',
			'jabber_username',
			'eztext_username',
			'smtp_username',
			'passwd',
			'status'
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
