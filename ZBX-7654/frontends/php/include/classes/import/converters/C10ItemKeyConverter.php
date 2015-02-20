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
 * A converted for converting simple check item keys from 1.8 format to 2.0.
 */
class C10ItemKeyConverter extends CConverter {

	public function convert($value) {
		$keys = array(
			'tcp',
			'ftp',
			'http',
			'imap',
			'ldap',
			'nntp',
			'ntp',
			'pop',
			'smtp',
			'ssh'
		);

		$perfKeys = array(
			'tcp_perf',
			'ftp_perf',
			'http_perf',
			'imap_perf',
			'ldap_perf',
			'nntp_perf',
			'ntp_perf',
			'pop_perf',
			'smtp_perf',
			'ssh_perf'
		);

		$value = explode(',', $value);
		$key = $value[0];
		$port = (isset($value[1])) ? $value[1] : '';

		if (in_array($key, $keys)) {
			$key = 'net.tcp.service['.$key.',,'.$port.']';
		}
		elseif (in_array($key, $perfKeys)) {
			list($key, $perfSuffix) = explode('_', $key);
			$key = 'net.tcp.service.perf['.$key.',,'.$port.']';
		}

		return $key;
	}

}
