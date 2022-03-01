<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class is used to validate if given string is valid HashiCorp Vault secret.
 *
 * Valid token must match format:
 * - <namespace>/<path/to/secret>:<key>
 * - <namespace>/<path/to/secret>
 *
 * Mutlibyte strings are supported.
 */
class CVaultSecretParser extends CParser {

	private $options = [
		'provider' => null,
		'with_key' => true
	];

	/**
	 * @param array $options
	 * @param int   $options['provider']  (optional) Vault provider.
	 * @param bool  $options['with_key']  (optional) Validated string must contain key.
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * @inheritDoc
	 */
	public function parse($source, $pos = 0) {
		switch ($this->options['provider']) {
			case ZBX_VAULT_TYPE_HASHICORP:
				return $this->parseHashiCorp($source, $pos);
			case ZBX_VAULT_TYPE_CYBERARK:
				return $this->parseCyberArk($source, $pos);
			default:
				return self::PARSE_FAIL;
		}
	}

	private function parseHashiCorp($source, $pos) {
		$this->start = $pos;
		$this->errorClear();

		$src_size = strlen($source);
		$namespace_sep = strpos($source, '/');
		if ($namespace_sep === false || $namespace_sep == 0 || $namespace_sep == $src_size - 1) {
			$this->errorPos($source, 0);

			return self::PARSE_FAIL;
		}

		if ($this->options['with_key']) {
			$path_pos = $namespace_sep + 1;
			$key_sep = strpos($source, ':', $path_pos);

			if ($source[$key_sep - 1] === '/') {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}

			if ($key_sep === false || $key_sep == $path_pos || $key_sep == $src_size - 1) {
				$this->errorPos($source, $path_pos);

				return self::PARSE_FAIL;
			}

			if (strrpos($source, '//', $key_sep - $src_size) !== false) {
				$this->errorPos($source, $path_pos);

				return self::PARSE_FAIL;
			}
		}
		else {
			if (strpos($source, '//') !== false) {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}

			if ($source[$src_size - 1] === '/') {
				$this->errorPos($source, $namespace_sep + 1);

				return self::PARSE_FAIL;
			}
		}

		return self::PARSE_SUCCESS;
	}

	private function parseCyberArk($source, $pos) {

		$source = 'AppId=&Query=Safe=Object=buzz';
//		$source = 'AppID=3&Query=';


		$this->start = $pos;
		$this->errorClear();

//		preg_match('/appid=(?<appid>[^&:]+)(?:&query=(?<query>[^:]+))?:*(?<key>.*)/i', $source, $matches, PREG_UNMATCHED_AS_NULL);
		preg_match('/appid=(?<appid>[^&:]+)?(?:&query=(?<query>[^&:]+))?(?::*(?<key>.*))?/i', $source, $matches, PREG_UNMATCHED_AS_NULL);

		sdii($matches);

		if (!$matches) {
			sdii('no matches');

//			return self::PARSE_FAIL;
		}

		if (array_key_exists('appid', $matches) && $matches['appid'] === null) {
			sdii('empty appid');

//			return self::PARSE_FAIL;
		}

		if (array_key_exists('query', $matches)) {
			preg_match_all('/(?<key>[^=]+)=(?<value>[^;]*);?/', $matches['query'][0], $query);
			if (!array_combine($query['key'], $query['value'])) {
				sdii('Wrong query format');

//				return self::PARSE_FAIL;
			}
		}

		if ($this->options['with_key'] && array_key_exists('key', $matches)) {
			sdii('key error');
//			return self::PARSE_FAIL;
		}

		die('as');

		return self::PARSE_SUCCESS;
	}
}
