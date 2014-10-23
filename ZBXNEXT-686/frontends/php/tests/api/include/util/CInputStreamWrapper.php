<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CInputStreamWrapper {
	protected static $data;
	protected static $length;
	protected static $position = 0;

	public function stream_open($path, $mode, $options, &$opened_path) {
		if ($path !== 'php://input') {
			throw new Exception('Sorry, we support nothing but php://input at the moment');
		}

		self::$position = 0;

		return true;
	}

	public function stream_write($data) {
		self::$data = $data;

		self::$length = strlen($data);

		return self::$length;
	}

	public function stream_read($index) {
		// check this code for really long data
		$chunk = min($index, self::$length - self::$position);

		$data = substr(self::$data, self::$position, $chunk);

		self::$position += $chunk;

		return $data;
	}

	public function stream_stat() {
		return array();
	}

	public function stream_eof() {
		return self::$position >= self::$length;
	}
}
