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
 * Convert net.tcp.service to net.udp.service
 */
class C20ItemKeyConverter extends CConverter {

	/**
	 * Convert item key
	 *
	 * @param string	$value	item key
	 *
	 * @return string			converted item key
	 */
	public function convert($value) {
		$item_key = new CItemKey($value);
		$key_parameters = [];
		if (($item_key->getKeyId() === 'net.tcp.service' || $item_key->getKeyId() === 'net.tcp.service.perf')
				&& trim($item_key->getParameters()[0]) === 'ntp') {
			if ($item_key->getKeyId() === 'net.tcp.service') {
				$new_key_id = 'net.udp.service';
			}
			else {
				$new_key_id = 'net.udp.service.perf';
			}
			foreach ($item_key->getParameters() as $key_parameter) {
				$key_parameters[] = $key_parameter;
			}
			$value = $new_key_id.'['.implode(',', $key_parameters).']';
		}

		return $value;
	}

}
