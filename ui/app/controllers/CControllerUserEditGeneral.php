<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * @var array
	 */
	protected $timezones = [];

	protected function init(): void {
		$this->disableSIDValidation();

		$timezone = CSettingsHelper::get(CSettingsHelper::DEFAULT_TIMEZONE);

		if ($timezone === ZBX_DEFAULT_TIMEZONE || $timezone === TIMEZONE_DEFAULT) {
			$timezone = CTimezoneHelper::getSystemTimezone();
		}

		$this->timezones = [
			TIMEZONE_DEFAULT => CTimezoneHelper::getTitle($timezone, _('System default'))
		] + CTimezoneHelper::getList();
	}

	/**
	 * Set user medias in data.
	 *
	 * @array $data
	 */
	protected function setUserMedias(array $data) {
		if ($this->hasInput('new_media')) {
			$data['medias'][] = $this->getInput('new_media');
		}
		elseif ($this->hasInput('enable_media')) {
			if (array_key_exists($this->getInput('enable_media'), $data['medias'])) {
				$data['medias'][$this->getInput('enable_media')]['active'] = MEDIA_STATUS_ACTIVE;
			}
		}
		elseif ($this->hasInput('disable_media')) {
			if (array_key_exists($this->getInput('disable_media'), $data['medias'])) {
				$data['medias'][$this->getInput('disable_media')]['active'] = MEDIA_STATUS_DISABLED;
			}
		}

		$mediatypeids = [];

		foreach ($data['medias'] as $media) {
			$mediatypeids[$media['mediatypeid']] = true;
		}

		$mediatypes = API::Mediatype()->get([
			'output' => ['name', 'type'],
			'mediatypeids' => array_keys($mediatypeids),
			'preservekeys' => true
		]);

		foreach ($data['medias'] as &$media) {
			$media['name'] = $mediatypes[$media['mediatypeid']]['name'];
			$media['mediatype'] = $mediatypes[$media['mediatypeid']]['type'];
			$media['send_to_sort_field'] = is_array($media['sendto'])
				? implode(', ', $media['sendto'])
				: $media['sendto'];
		}
		unset($media);

		CArrayHelper::sort($data['medias'], ['name', 'send_to_sort_field']);

		foreach ($data['medias'] as &$media) {
			unset($media['send_to_sort_field']);
		}
		unset($media);

		return $data;
	}

	protected function getPasswordRequirements(): array {
		return [
			'min_length' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_MIN_LENGTH),
			'check_rules' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_CHECK_RULES)
		];
	}
}
