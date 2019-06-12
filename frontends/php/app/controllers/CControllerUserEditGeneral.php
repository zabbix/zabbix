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
 * Class containing common methods, fields and operations with user edit form and profile form.
 */
abstract class CControllerUserEditGeneral extends CController {

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
	 * API request options for user.get method.
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Default field values as they are in DB.
	 *
	 * @var array
	 */
	protected $db_defaults = [];

	/**
	 * Array of data that is set to the view.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Page title.
	 *
	 * @var string
	 */
	protected $title;

	public function __construct() {
		parent::__construct();

		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$supported_locales = array_keys(getLocales());

		$this->fields = [
			'userid' =>				'db users.userid',
			'password1' =>			'db users.passwd',
			'password2' =>			'db users.passwd',
			'change_password' =>	'string',
			'lang' =>				'db users.lang|in '.implode(',', $supported_locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'autologout_visible' =>	'in 0,1',
			'url' =>				'string',
			'refresh' =>			'string',
			'rows_per_page' =>		'int32|ge 1|le 999999',
			'form_refresh' =>		'int32'
		];

		$this->db_defaults = DB::getDefaults('users');
		$this->options['output'] = [];
	}

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$ret = $this->validateInput($this->fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
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

	protected function checkPermissions() {
		if ($this->userid != 0) {
			$this->options['output'] = array_merge($this->options['output'], ['alias', 'name', 'surname', 'url',
				'autologin', 'autologout', 'lang', 'refresh', 'theme', 'rows_per_page'
			]);

			$this->options += [
				'userids' => [$this->userid],
				'editable' => true
			];

			$users = API::User()->get($this->options);

			if (!$users) {
				return false;
			}

			$this->user = $users[0];
		}

		return true;
	}

	/**
	 * Get and return default field values.
	 *
	 * @return array
	 */
	protected function getDataDefaults() {
		return [
			'sid' => $this->getUserSID(),
			'userid' => 0,
			'alias' => '',
			'name' => '',
			'surname' => '',
			'change_password' => false,
			'config' => select_config(),
			'form_refresh' => 0,
			'password1' => '',
			'password2' => '',
			'url' => '',
			'lang' => $this->db_defaults['lang'],
			'theme' => $this->db_defaults['theme'],
			'autologin' => $this->db_defaults['autologin'],
			'autologout' => $this->db_defaults['autologout'],
			'autologout_visible' => 0,
			'refresh' => $this->db_defaults['refresh'],
			'rows_per_page' => $this->db_defaults['rows_per_page'],
			'action' => $this->getAction()
		];
	}

	/**
	 * Set user medias in data.
	 */
	protected function setUserMedias() {
		if ($this->data['new_media']) {
			$this->data['user_medias'][] = $this->data['new_media'];
		}

		if ($this->data['user_medias']) {
			if ($this->hasInput('enable_media')) {
				if (array_key_exists($this->getInput('enable_media'), $this->data['user_medias'])) {
					$this->data['user_medias'][$this->getInput('enable_media')]['active'] = 0;
				}
			}
			elseif ($this->hasInput('disable_media')) {
				if (array_key_exists($this->getInput('disable_media'), $this->data['user_medias'])) {
					$this->data['user_medias'][$this->getInput('disable_media')]['active'] = 1;
				}
			}

			$media_type_descriptions = [];
			$db_media_types = DBselect(
				'SELECT mt.mediatypeid,mt.type,mt.description FROM media_type mt WHERE '.
					dbConditionInt('mt.mediatypeid', zbx_objectValues($this->data['user_medias'], 'mediatypeid'))
			);
			while ($db_media_type = DBfetch($db_media_types)) {
				$mediatypeid = $db_media_type['mediatypeid'];
				$media_type_descriptions[$mediatypeid]['description'] = $db_media_type['description'];
				$media_type_descriptions[$mediatypeid]['mediatype'] = $db_media_type['type'];
			}

			foreach ($this->data['user_medias'] as &$media) {
				$media['description'] = $media_type_descriptions[$media['mediatypeid']]['description'];
				$media['mediatype'] = $media_type_descriptions[$media['mediatypeid']]['mediatype'];
				$media['send_to_sort_field'] = is_array($media['sendto'])
					? implode(', ', $media['sendto'])
					: $media['sendto'];
			}
			unset($media);

			CArrayHelper::sort($this->data['user_medias'], ['description', 'send_to_sort_field']);

			foreach ($this->data['user_medias'] as &$media) {
				unset($media['send_to_sort_field']);
			}
			unset($media);
		}
	}

	/**
	 * Get user type, user groups and medias from DB if necessary.
	 */
	abstract protected function getDBData();

	/**
	 * Set user medias and user groups in data if necessary.
	 */
	abstract protected function setFormData();

	protected function doAction() {
		if ($this->userid != 0) {
			// Get specific values from the dabatase depeding on whether if it's user edit or profile form.
			$this->getDBData();

			// Get other common values from the dabatase.
			$this->data['userid'] = $this->userid;
			$this->data['alias'] = $this->user['alias'];
			$this->data['name'] = $this->user['name'];
			$this->data['surname'] = $this->user['surname'];
			$this->data['url'] = $this->user['url'];
			$this->data['password1'] = null;
			$this->data['password2'] = null;
			$this->data['autologin'] = $this->user['autologin'];
			$this->data['autologout'] = $this->user['autologout'];
			$this->data['autologout_visible']	= (!$this->hasInput('form_refresh')
				&& (bool) timeUnitToSeconds($this->user['autologout'])
			);
			$this->data['lang'] = $this->user['lang'];
			$this->data['theme'] = $this->user['theme'];
			$this->data['refresh'] = $this->user['refresh'];
			$this->data['rows_per_page'] = $this->user['rows_per_page'];
		}

		// Merge form specific fiels with common fields.
		$this->fields += array_merge($this->fields, ['url', 'password1', 'password2', 'change_password', 'autologin',
			'autologout', 'autologout_visible', 'lang', 'theme', 'refresh', 'rows_per_page', 'refresh', 'form_refresh'
		]);

		// Overwrite with input variables.
		$this->getInputs($this->data, $this->fields);

		if ($this->hasInput('form_refresh')) {
			if (!$this->hasInput('user_groups')) {
				$this->data['user_groups'] = [];
			}

			if (!$this->hasInput('change_password') && ($this->hasInput('password1') || $this->hasInput('password2'))) {
				$this->data['change_password'] = true;
			}
		}

		// Set form specific fields depending on user edit or profile form.
		$this->setFormData();

		$response = new CControllerResponseData($this->data);
		$response->setTitle($this->title);
		$this->setResponse($response);
	}
}
