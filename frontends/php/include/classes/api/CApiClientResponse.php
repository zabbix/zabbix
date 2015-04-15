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


/**
 * This class is used by the API client to return the results of an API call.
 */
class CApiClientResponse {

	/**
	 * Data returned by the service method.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Error code.
	 *
	 * @var	int
	 */
	public $errorCode;

	/**
	 * Error message.
	 *
	 * @var	string
	 */
	public $errorMessage;

	/**
	 * Debug information.
	 *
	 * @var	array
	 */
	public $debug;
}
