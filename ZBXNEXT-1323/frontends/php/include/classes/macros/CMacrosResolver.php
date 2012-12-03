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

	const PATTERN_HOST = '{HOSTNAME}|{HOST\.HOST}|{HOST\.NAME}';
	const PATTERN_IP = '{IPADDRESS}|{HOST\.IP}|{HOST\.DNS}|{HOST\.CONN}';

	/**
	 * Interface priorities.
	 *
	 * @var array
	 */
	private $interfacePriorities = array(
		INTERFACE_TYPE_AGENT => 4,
		INTERFACE_TYPE_SNMP => 3,
		INTERFACE_TYPE_JMX => 2,
		INTERFACE_TYPE_IPMI => 1
	);

	/**
	 * Batch resolving host and ip macros in text using host id.
	 *
	 * @param array $data (as $hostid => $texts)
	 *
	 * @return array (as $hostid => $texts)
	 */
	public function resolveMacrosInTextBatch(array $data) {
		// check if macros exist
		$isHostMacrosExist = false;
		$isIpMacrosExist = false;

		foreach ($data as $texts) {
			if (!$isHostMacrosExist && $this->findMacros(self::PATTERN_HOST, $texts)) {
				$isHostMacrosExist = true;
			}
			if (!$isIpMacrosExist && $this->findMacros(self::PATTERN_IP, $texts)) {
				$isIpMacrosExist = true;
			}

			if ($isHostMacrosExist && $isIpMacrosExist) {
				break;
			}
		}

		$hostIds = array_keys($data);
		$macros = array();

		// host macros
		if ($isHostMacrosExist) {
			$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.DBcondition('h.hostid', $hostIds));
			while ($dbHost = DBfetch($dbHosts)) {
				$hostId = $dbHost['hostid'];
				$hostMacros = $this->findMacros(self::PATTERN_HOST, $data[$hostId]);

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
		}

		// ip macros, macro should be resolved to interface with highest priority
		if ($isIpMacrosExist) {
			$interfaces = array();

			$dbInterfaces = DBselect(
				'SELECT i.hostid,i.ip,i.dns,i.useip,i.type'.
				' FROM interface i'.
				' WHERE i.main=1'.
					' AND '.DBcondition('i.hostid', $hostIds).
					' AND '.DBcondition('i.type', $this->interfacePriorities)
			);
			while ($dbInterface = DBfetch($dbInterfaces)) {
				$hostId = $dbInterface['hostid'];

				if (!isset($interfaces[$hostId]) || $this->interfacePriorities[$dbInterface['type']] > $interfaces[$hostId]['type']) {
					$interfaces[$hostId] = $dbInterface;
				}
			}

			if (!empty($interfaces)) {
				foreach ($interfaces as $hostId => $interface) {
					$ipMacros = $this->findMacros(self::PATTERN_IP, $data[$hostId]);

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

							// Resolving macros in macros. If interface is AGENT macros stay unresolved.
							if ($interface['type'] != INTERFACE_TYPE_AGENT) {
								if ($this->findMacros(self::PATTERN_HOST, array($macros[$hostId][$ipMacro]))
										|| $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($macros[$hostId][$ipMacro]))) {
									// attention recursion!
									$macrosInMacros = $this->resolveMacrosInTextBatch(array($hostId => array($macros[$hostId][$ipMacro])));
									$macros[$hostId][$ipMacro] = $macrosInMacros[$hostId][0];
								}
								elseif ($this->findMacros(self::PATTERN_IP, array($macros[$hostId][$ipMacro]))) {
									$macros[$hostId][$ipMacro] = UNRESOLVED_MACRO_STRING;
								}
							}
						}
					}
				}
			}
		}

		foreach ($data as $hostId => $texts) {
			// get user macros
			foreach ($this->expandUserMacros($texts, $hostId) as $macro => $value) {
				$macros[$hostId][$macro] = $value;
			}

			// replace macros to value
			foreach ($texts as $tnum => $text) {
				if (!empty($macros[$hostId])) {
					$offset = 0;
					while (preg_match('/'.self::PATTERN_HOST.'|'.self::PATTERN_IP.'|'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
						$macrosValue = isset($macros[$hostId][$matches[0][0]]) ? $macros[$hostId][$matches[0][0]] : $matches[0][0];
						$text = substr_replace($text, $macrosValue, $matches[0][1], strlen($matches[0][0]));
						$offset = $matches[0][1] + strlen($macrosValue);
					}

					$data[$hostId][$tnum] = $text;
				}
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
		$data = $this->resolveMacrosInTextBatch(array($hostId => array($text)));

		return $data[$hostId][0];
	}

	/**
	 * Find user macros.
	 *
	 * @param array $text
	 * @param int $hostId
	 *
	 * @return mixed
	 */
	private function expandUserMacros($texts, $hostId) {
		$matches = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, $texts);

		$macros = array();
		if (!empty($matches)) {
			$macros = API::UserMacro()->getMacros(array(
				'macros' => $matches,
				'hostid' => $hostId
			));
		}

		return $macros;
	}

	/**
	 * Find macros in string by pattern.
	 *
	 * @param string $pattern
	 * @param array $texts
	 *
	 * @return array
	 */
	private function findMacros($pattern, array $texts) {
		$result = array();

		foreach ($texts as $text) {
			preg_match_all('/'.$pattern.'/', $text, $matches);

			$result = array_merge($result, $matches[0]);
		}

		return array_unique($result);
	}
}
