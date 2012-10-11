<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
 * A helper class for resolving macros.
 */
class CMacrosResolver {

	/**
	 * Resolve host and ip macros in text using host id.
	 *
	 * @param string $text
	 * @param array|string $hostIds
	 *
	 * @return string
	 */
	public function resolveMacrosInText($text, $hostIds) {
		zbx_toArray($hostIds);

		// host macros
		$hostMacros = $this->findMacros('HOSTNAME|HOST\.HOST|HOST\.NAME', $text);
		if (!empty($hostMacros)) {
			$dbHosts = DBselect(
				'SELECT h.name,h.host'.
				' FROM hosts h'.
				' WHERE '.DBcondition('h.hostid', $hostIds)
			);
			while ($dbHost = DBfetch($dbHosts)) {
				foreach ($hostMacros as $hostMacro) {
					$value = '';

					switch ($hostMacro) {
						case '{HOSTNAME}':
						case '{HOST.HOST}':
							$value = $dbHost['host'];
							break;
						case '{HOST.NAME}':
							$value = $dbHost['name'];
							break;
					}

					if (!empty($value)) {
						$text = str_ireplace($hostMacro, $value, $text);
					}
				}
			}
		}

		// ip macros
		$ipMacros = $this->findMacros('IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN', $text);
		if (!empty($ipMacros)) {
			$dbInterfaces = DBselect(
				'SELECT i.ip,i.dns,i.useip'.
				' FROM interface i'.
				' WHERE '.DBcondition('i.hostid', $hostIds)
			);
			while ($dbInterface = DBfetch($dbInterfaces)) {
				foreach ($ipMacros as $ipMacro) {
					$value = '';

					switch ($ipMacro) {
						case '{IPADDRESS}':
						case '{HOST.IP}':
							$value = $dbInterface['ip'];
							break;
						case '{HOST.DNS}':
							$value = $dbInterface['dns'];
							break;
						case '{HOST.CONN}':
							$value = $dbInterface['useip'] ? $dbInterface['ip'] : $dbInterface['dns'];
							break;
					}

					if (!empty($value)) {
						$text = str_ireplace($ipMacro, $value, $text);
					}
				}
			}
		}

		return $text;
	}

	/**
	 * Find macros in string by pattern.
	 *
	 * @param string $pattern
	 * @param string $s
	 *
	 * @return array
	 */
	function findMacros($pattern, $s) {
		preg_match_all('/{('.$pattern.')([1-9]?)}/', $s, $matches);

		return !empty($matches[0]) ? $matches[0] : null;
	}
}
