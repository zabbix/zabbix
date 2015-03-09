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


class Curl {

	private $url;
	protected $reference;
	protected $query;
	protected $arguments = array();

	public function __construct($url = null) {
		if (empty($url)) {
			$this->formatGetArguments();

			$this->url = basename($_SERVER['SCRIPT_NAME']);
		}
		else {
			$this->url = $url;

			// parse reference
			$tmp_pos = zbx_strpos($this->url, '#');
			if ($tmp_pos !== false) {
				$this->reference = zbx_substring($this->url, $tmp_pos + 1);
				$this->url = zbx_substring($this->url, 0, $tmp_pos);
			}

			$tmp_pos = zbx_strpos($url, '?');
			// parse query
			if ($tmp_pos !== false) {
				$this->query = zbx_substring($url, $tmp_pos + 1);
				$this->url = $url = zbx_substring($url, 0, $tmp_pos);
			}

			$this->formatArguments();
		}

		if (isset($_COOKIE['zbx_sessionid'])) {
			$this->setArgument('sid', substr($_COOKIE['zbx_sessionid'], 16, 16));
		}
	}

	public function formatQuery() {
		$query = array();

		foreach ($this->arguments as $key => $value) {
			if (is_null($value)) {
				continue;
			}

			if (is_array($value)) {
				foreach ($value as $vkey => $vvalue) {
					if (is_array($vvalue)) {
						continue;
					}

					$query[] = $key.'['.$vkey.']='.rawurlencode($vvalue);
				}
			}
			else {
				$query[] = $key.'='.rawurlencode($value);
			}
		}
		$this->query = implode('&', $query);
	}

	public function formatGetArguments() {
		$this->arguments = $_GET;
		if (isset($_COOKIE['zbx_sessionid'])) {
			$this->setArgument('sid', substr($_COOKIE['zbx_sessionid'], 16, 16));
		}
		$this->formatQuery();
	}

	public function formatArguments($query = null) {
		if ($query === null) {
			$query = $this->query;
		}
		if ($query !== null) {
			$args = explode('&', $query);
			foreach ($args as $id => $arg) {
				if (empty($arg)) {
					continue;
				}

				if (strpos($arg, '=') !== false) {
					list($name, $value) = explode('=', $arg);
					$this->arguments[$name] = isset($value) ? urldecode($value) : '';
				}
				else {
					$this->arguments[$arg] = '';
				}
			}
		}
		$this->formatQuery();
	}
	/**
	 * Return relative url.
	 *
	 * @return string
	 */
	public function getUrl() {
		$this->formatQuery();

		$url = $this->url;
		$url .= $this->query ? '?'.$this->query : '';
		$url .= $this->reference ? '#'.urlencode($this->reference) : '';
		return $url;
	}

	public function removeArgument($key) {
		unset($this->arguments[$key]);
	}

	public function setArgument($key, $value = '') {
		$this->arguments[$key] = $value;
	}

	public function getArgument($key) {
		return isset($this->arguments[$key]) ? $this->arguments[$key] : null;
	}

	public function setQuery($query) {
		$this->query = $query;
		$this->formatArguments();
		$this->formatQuery();
	}

	public function getQuery() {
		$this->formatQuery();
		return $this->query;
	}

	public function setReference($reference) {
		$this->reference = $reference;
	}

	// returns the reference of $this url, i.e. 'bookmark' in the url 'http://server/file.html#bookmark'
	public function getReference() {
		return $this->reference;
	}

	public function toString() {
		return $this->getUrl();
	}
}
