<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace SCIM;

use CHttpRequest;
use Exception;

/**
 * Class to access and parse SCIM HTTP request.
 */
class ScimHttpRequest extends CHttpRequest {

	private string $requestApi;

	public function __construct($add_headers = false) {
		parent::__construct($add_headers);

		$this->setRequestApi();
	}

	/**
	 * Extracts requestApi (users or groups) from the request header and saves it to requestApi property.
	 *
	 * @return void
	 */
	public function setRequestApi(): void {
		[, $requestApi] = explode('/', $this->header('PATH-INFO'), 3) + ['', ''];
		$this->requestApi = strtolower($requestApi);
	}

	public function getRequestApi(): string {
		return $this->requestApi;
	}

	public function parseId(): string {
		[, , $id] = explode('/', $this->header('PATH-INFO'), 3) + ['', '', ''];

		return $id;
	}

	/**
	 * Parses Users request filter and extracts username.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function parseUsersFilter(): string {
		preg_match('/^userName eq "(?<value>(?:[^"]|\\\\")*)"$/', $_GET['filter'], $filter_value);

		if (!array_key_exists('value', $filter_value)) {
			throw new Exception(_('This filter is not supported'), 400);
		}

		return $filter_value['value'];
	}

	/**
	 * Parses Groups request filter and extracts group's displayName.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function parseGroupsFilter(): string {
		preg_match('/^displayName eq "(?<value>(?:[^"]|\\\\")*)"$/', $_GET['filter'], $filter_value);

		if (!array_key_exists('value', $filter_value)) {
			throw new Exception(_('This filter is not supported'), 400);
		}

		return $filter_value['value'];
	}

	/**
	 * Returns SCIM HTTP request data in array form for SCIM API.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getRequestData(): array {
		$data = $this->body() === '' ? [] : json_decode($this->body(), true);
		$id = $this->parseId();

		if ($id !== '') {
			$data['id'] = $id;
		}

		if (array_key_exists('filter', $_GET)) {
			switch ($this->getRequestApi()) {
				case 'users':
					$data['userName'] = $this->parseUsersFilter();
					break;

				case 'groups':
					$data['displayName'] = $this->parseGroupsFilter();
			}
		}

		if (array_key_exists('startIndex', $_GET)) {
			$data['startIndex'] = $_GET['startIndex'];
		}

		if (array_key_exists('count', $_GET)) {
			$data['count'] = $_GET['count'];
		}

		return $data;
	}
}
