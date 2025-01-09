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


/**
 * A class for creating buttons that redirect you to a different page.
 */
class CRedirectButton extends CSimpleButton {

	/**
	 * @param string      $caption
	 * @param string|CUrl $url           URL to redirect to
	 * @param string      $confirmation  confirmation message text
	 */
	public function __construct($caption, $url, $confirmation = null) {
		parent::__construct($caption);

		$this->setUrl($url, $confirmation);
	}

	/**
	 * Set the URL and confirmation message.
	 *
	 * If the confirmation is set, a confirmation pop up will appear before redirecting to the URL.
	 * If CSRF token is present in Url, the data will be submitted with POST request.
	 *
	 * @param string|CUrl $url
	 * @param string|null $confirmation
	 *
	 * @return CRedirectButton
	 */
	public function setUrl($url, $confirmation = null) {
		if ($url instanceof CUrl) {
			if ($url->hasArgument(CSRF_TOKEN_NAME)) {
				$this->setAttribute('data-post', 1);
			}

			$url = $url->getUrl();
		}

		$this->setAttribute('data-url', $url);

		if ($confirmation !== null) {
			$this->setAttribute('data-confirmation', $confirmation);
		}
		return $this;
	}
}
