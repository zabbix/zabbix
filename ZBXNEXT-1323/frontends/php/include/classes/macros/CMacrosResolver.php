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

	const PATTERN_HOST = 'HOSTNAME|HOST\.HOST|HOST\.NAME';
	const PATTERN_IP = 'IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN';

	/**
	 * Interface priorities.
	 *
	 * @var array
	 */
	public $interfacePriorities = array(
		INTERFACE_TYPE_AGENT => 4,
		INTERFACE_TYPE_SNMP => 3,
		INTERFACE_TYPE_JMX => 2,
		INTERFACE_TYPE_IPMI => 1
	);

	/**
	 * Batch resolving host and ip macros in text using host id.
	 *
	 * @param array $data (as $hostid => $text)
	 *
	 * @return array (as $hostid => $text)
	 */
	public function resolveMacrosInTextBatch(array $data) {
		$hostIds = array_keys($data);
		$macros = array();

		// host macros
		$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.DBcondition('h.hostid', $hostIds));
		while ($dbHost = DBfetch($dbHosts)) {
			$hostId = $dbHost['hostid'];
			$hostMacros = $this->findMacros(CMacrosResolver::PATTERN_HOST, $data[$hostId]);

			if (!empty($hostMacros)) {
				foreach ($hostMacros as $hostMacro) {
					switch ($hostMacro) {
						case '{HOSTNAME}':
						case '{HOST.HOST}':
							$macros[$hostId][$hostMacro] = $dbHost['host'];
							break;
						case '{HOST.NAME}':
							$macros[$hostId][$hostMacro] = $dbHost['name'];
							break;
					}
				}
			}
		}

		// ip macros, macro should be resolved to interface with highest priority
		$interfaces = array();

		$dbInterfaces = DBselect('SELECT i.hostid,i.ip,i.dns,i.useip,i.type FROM interface i WHERE '.DBcondition('i.hostid', $hostIds));
		while ($dbInterface = DBfetch($dbInterfaces)) {
			$hostId = $dbInterface['hostid'];

			if (!empty($this->interfacePriorities[$dbInterface['type']])
					&& (empty($interfaces[$hostId]) || $this->interfacePriorities[$dbInterface['type']] > $interfaces[$hostId]['type'])) {
				$interfaces[$hostId] = $dbInterface;
			}
		}

		if (!empty($interfaces)) {
			foreach ($interfaces as $hostId => $interface) {
				$ipMacros = $this->findMacros(CMacrosResolver::PATTERN_IP, $data[$hostId]);

				if (!empty($ipMacros)) {
					foreach ($ipMacros as $ipMacro) {
						switch ($ipMacro) {
							case '{IPADDRESS}':
							case '{HOST.IP}':
								$macros[$hostId][$ipMacro] = $interface['ip'];
								break;
							case '{HOST.DNS}':
								$macros[$hostId][$ipMacro] = $interface['dns'];
								break;
							case '{HOST.CONN}':
								$macros[$hostId][$ipMacro] = $interface['useip'] ? $interface['ip'] : $interface['dns'];
								break;
						}
					}
				}
			}
		}

		foreach ($data as $hostId => $text) {
			if (!empty($macros[$hostId])) {
				$data[$hostId] = $this->replaceMacroValues($text, $macros[$hostId]);
			}
		}

		return $data;
	}

	/**
	 * Resolve host and ip macros in text using host id.
	 *
	 * @param string $text
	 * @param string $hostId
	 *
	 * @return string
	 */
	public function resolveMacrosInText($text, $hostId) {
		$macros = array();

		// host macros
		$hostMacros = $this->findMacros(CMacrosResolver::PATTERN_HOST, $text);
		if (!empty($hostMacros)) {
			$dbHosts = DBselect('SELECT h.name,h.host FROM hosts h WHERE h.hostid='.$hostId);
			while ($dbHost = DBfetch($dbHosts)) {
				foreach ($hostMacros as $hostMacro) {
					switch ($hostMacro) {
						case '{HOSTNAME}':
						case '{HOST.HOST}':
							$macros[$hostMacro] = $dbHost['host'];
							break;
						case '{HOST.NAME}':
							$macros[$hostMacro] = $dbHost['name'];
							break;
					}
				}
			}
		}

		// ip macros, macro should be resolved to interface with highest priority
		$ipMacros = $this->findMacros(CMacrosResolver::PATTERN_IP, $text);
		if (!empty($ipMacros)) {
			$interface = array('type' => 0);

			$dbInterfaces = DBselect('SELECT i.ip,i.dns,i.useip,i.type FROM interface i WHERE i.hostid='.$hostId);
			while ($dbInterface = DBfetch($dbInterfaces)) {
				if (!empty($this->interfacePriorities[$dbInterface['type']]) && $this->interfacePriorities[$dbInterface['type']] > $interface['type']) {
					$interface = $dbInterface;
				}
			}

			foreach ($ipMacros as $ipMacro) {
				switch ($ipMacro) {
					case '{IPADDRESS}':
					case '{HOST.IP}':
						$macros[$ipMacro] = $interface['ip'];
						break;
					case '{HOST.DNS}':
						$macros[$ipMacro] = $interface['dns'];
						break;
					case '{HOST.CONN}':
						$macros[$ipMacro] = $interface['useip'] ? $interface['ip'] : $interface['dns'];
						break;
				}
			}
		}

		return $this->replaceMacroValues($text, $macros);
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
		preg_match_all('/{('.$pattern.')}/', $s, $matches);

		return !empty($matches[0]) ? $matches[0] : null;
	}

	/**
	 * Replace macros by values.
	 * All macros are resolved in one go.
	 *
	 * @param string $text
	 * @param array $macros
	 *
	 * @return string
	 */
	function replaceMacroValues($text, $macros) {
		$i = 0;
		$begin = false;
		while ($i = strpos($text, ($begin ? '}' : '{'), $i)) {
			$char = zbx_substr($text, $i, 1);

			if ($char == '{') {
				$begin = $i;
			}
			elseif ($char == '}') {
				if ($begin !== false) {
					$macro = zbx_substr($text, $begin, $i - $begin + 1);

					if (isset($macros[$macro])) {
						$value = $macros[$macro];
					}
					elseif ($this->isMacroAllowed($macro)) {
						$value = UNRESOLVED_MACRO_STRING;
					}
					else {
						$value = false;
					}

					if ($value !== false) {
						$text = zbx_substr_replace($text, $value, $begin, zbx_strlen($macro));

						// recalculate iterator
						$i = $begin + zbx_strlen($value) - 1;
						$begin = false;
					}
				}
			}
		}

		return $text;
	}

	/**
	 * Check if the macro is supported.
	 *
	 * @param string $macro
	 *
	 * @return bool
	 */
	function isMacroAllowed($macro) {
		return preg_match('/{'.CMacrosResolver::PATTERN_HOST.CMacrosResolver::PATTERN_IP.'?}/', $macro);
	}
}
