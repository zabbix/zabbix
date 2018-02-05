<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CLink extends CTag {

	private	$use_sid = false;
	private	$confirm_message = '';
	private $url = null;

	public function __construct($item = null, $url = null) {
		parent::__construct('a', true);

		if ($item !== null) {
			$this->addItem($item);
		}
		$this->url = $url;
	}

	/*
	 * Add a "sid" argument into the URL.
	 * POST method will be used for the "sid" argument.
	 */
	public function addSID() {
		$this->use_sid = true;
		return $this;
	}

	/*
	 * Add a confirmation message
	 */
	public function addConfirmation($value) {
		$this->confirm_message = $value;
		return $this;
	}

	public function setTarget($value = null) {
		$this->attributes['target'] = $value;
		return $this;
	}

	public function toString($destroy = true) {
		$url = $this->url;

		if ($url === null) {
			$this->setAttribute('role', 'button');
		}

		if ($this->use_sid) {
			if (array_key_exists('zbx_sessionid', $_COOKIE)) {
				$url .= (strpos($url, '&') !== false || strpos($url, '?') !== false) ? '&' : '?';
				$url .= 'sid='.substr($_COOKIE['zbx_sessionid'], 16, 16);
			}
			$confirm_script = ($this->confirm_message !== '')
				? 'Confirm('.CJs::encodeJson($this->confirm_message).') && '
				: '';
			$this->onClick("javascript: return ".$confirm_script."redirect('".$url."', 'post', 'sid', true)");
			$this->setAttribute('href', 'javascript:void(0)');
		}
		else {
			$this->setAttribute('href', ($url == null) ? 'javascript:void(0)' : $url);

			if ($this->confirm_message !== '') {
				$this->onClick('javascript: return Confirm('.CJs::encodeJson($this->confirm_message).');');
			}
		}

		return parent::toString($destroy);
	}
}
