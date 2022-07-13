<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
			throw new CAccessDeniedException();
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
