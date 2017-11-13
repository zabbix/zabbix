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


class CControllerPopupMedia extends CController {
	private $severities = [];

	protected function init() {
		$this->disableSIDvalidation();

		$config = select_config();
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$this->severities[$severity] = getSeverityName($severity, $config);
		}
	}

	protected function checkInput() {
		$fields = [
			'dstfrm' =>			'string|fatal',
			'media' =>			'int32',
			'mediatypeid' =>	'db media_type.mediatypeid',
			'sendto' =>			'string',
			'period' =>			'string',
			'active' =>			'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]),
			'severity' =>		'',
			'add' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN
				|| (CWebUser::isGuest() && CWebUser::getType() < USER_TYPE_SUPER_ADMIN)) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$page_options = [
			'dstfrm' => $this->getInput('dstfrm'),
			'media' => $this->getInput('media', -1),
			'sendto' => $this->getInput('sendto', ''),
			'mediatypeid' => $this->getInput('mediatypeid', 0),
			'active' => $this->getInput('active', MEDIA_STATUS_ACTIVE),
			'period' => $this->getInput('period', ZBX_DEFAULT_INTERVAL)
		];

		// Validation before adding Media to user's Media tab.
		if ($this->getInput('add', false)) {
			if ($page_options['sendto'] === '') {
				error(_s('Incorrect value for field "%1$s": cannot be empty.', 'sendto'));
			}

			if (($messages = getMessages()) !== null) {
				echo (new CJson())->encode(['messages' => $messages->toString()]);
				exit;
			}

			$severity = 0;
			$input_severity = $this->getInput('severity', []);
			foreach ($input_severity as $id) {
				$severity |= 1 << $id;
			}

			echo (new CJson())->encode([
				'dstfrm' => $page_options['dstfrm'],
				'media' => $this->getInput('media', -1),
				'mediatypeid' => $page_options['mediatypeid'],
				'sendto' => $page_options['sendto'],
				'period' => $page_options['period'],
				'active' => $this->getInput('active', MEDIA_STATUS_DISABLED),
				'severity' => $severity
			]);
			exit;
		}

		// Prepare data for view.
		if ($page_options['media'] != -1) {
			$severity_request = $this->getInput('severity', 63);

			$page_options['severities'] = [];
			foreach ($this->severities as $severity => $foo) {
				if ($severity_request & (1 << $severity)) {
					$page_options['severities'][$severity] = $severity;
				}
			}
		}
		else {
			$page_options['severities'] = $this->getInput('severity', array_keys($this->severities));
		}

		$mediatypes = API::MediaType()->get([
			'output' => ['description'],
			'preservekeys' => true
		]);
		CArrayHelper::sort($mediatypes, ['description']);

		foreach ($mediatypes as &$mediatype) {
			$mediatype = $mediatype['description'];
		}
		unset($mediatype);

		$data = [
			'title' => _('Media'),
			'options' => $page_options,
			'mediatypes' => $mediatypes,
			'severities' => $this->severities
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
