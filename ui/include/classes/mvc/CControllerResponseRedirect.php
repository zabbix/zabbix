<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerResponseRedirect extends CControllerResponse {

	protected $formData = [];

	/**
	 * @param CUrl $location
	 */
	public function __construct(CUrl $location) {
		$url = $location->getUrl();
		$url_parts = parse_url($url);

		if (!$url_parts || array_key_exists('host', $url_parts)) {
			access_deny(ACCESS_DENY_PAGE);
		}

		if (!CHtmlUrlValidator::validateSameSite($url)) {
			access_deny();
		}

		$this->location = $url;
	}

	public function setFormData(array $formData): void {
		$this->formData = $formData;
	}

	public function getFormData(): array {
		return $this->formData;
	}
}
