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
 * Class containing common methods, fields and operations with user updating and profile updating.
 */
abstract class CControllerUserUpdateGeneral extends CController {

	/**
	 * User data from DB.
	 *
	 * @var array
	 */
	protected $user = [];

	/**
	 * Fields that are used to validate user input in checkInput() and in doAction().
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * User ID that is being edited or current user ID when in profile page.
	 *
	 * @var string
	 */
	protected $userid;

	/**
	 * Redirect URL in case of an error within checkInput().
	 *
	 * @var string
	 */
	protected $redirect;

	public function __construct() {
		parent::__construct();

		$locales = array_keys(getLocales());
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$this->fields = [
			'userid' =>				'fatal|required|db users.userid',
			'password1' =>			'db users.passwd',
			'password2' =>			'db users.passwd',
			'user_medias' =>		'array',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'autologout_visible' =>	'in 0,1',
			'url' =>				'string',
			'refresh' =>			'required|string|not_empty',
			'rows_per_page' =>		'required|int32|not_empty|ge 1|le 999999',
			'form_refresh' =>		'int32'
		];
	}

	protected function checkInput() {
		$ret = $this->validateInput($this->fields);
		$error = $this->GetValidationError();

		if ($ret) {
			$ret = $ret && $this->vadidatePassword();
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
				case self::VALIDATION_OK:
					$response = new CControllerResponseRedirect($this->redirect);
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update user'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	/**
	 * Add addition fields to validation.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function appendValidationRules(array $fields) {
		$this->fields += $fields;
	}

	/**
	 * Validate password when updating user and profile.
	 */
	abstract protected function vadidatePassword();

	protected function checkPermissions() {
		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->userid,
			'editable' => true
		]);
	}

	protected function doAction() {
		// Merge form specific fiels with common fields.
		$this->fields += array_merge($this->fields, ['url', 'autologin', 'theme', 'refresh', 'rows_per_page', 'lang']);

		// Overwrite with input variables.
		$this->getInputs($this->user, $this->fields);

		$this->user['autologout'] = $this->hasInput('autologout_visible') ? $this->getInput('autologout') : '0';

		if (bccomp(CWebUser::$data['userid'], $this->getInput('userid')) != 0) {
			$this->user['type'] = $this->getInput('type');
		}

		if (trim($this->getInput('password1', '')) !== '') {
			$this->user['passwd'] = $this->getInput('password1');
		}
	}

	/**
	 * Set submitted user medias to user data.
	 */
	protected function setUserMedias() {
		$user_medias = $this->getInput('user_medias', []);

		foreach ($user_medias as $media) {
			$this->user['user_medias'][] = [
				'mediatypeid' => $media['mediatypeid'],
				'sendto' => $media['sendto'],
				'active' => $media['active'],
				'severity' => $media['severity'],
				'period' => $media['period']
			];
		}
	}
}
