<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class is used to validate if given string is valid HashiCorp Vault secret.
 *
 * Valid token must match format:
 * - <namespace>/<path/to/secret>:<key>
 * - <namespace>/<path/to/secret>
 * - <path/to/secret>:<key>
 * - <path/to/secret>
 *
 * Multibyte strings are supported.
 */
class CVaultSecretParser extends CParser {

	private $options = [
		'provider' => ZBX_VAULT_TYPE_UNKNOWN,
		'with_namespace' => false,
		'with_key' => true
	];

	private $cyberark_has_appid = true;
	private $cyberark_has_key = true;

	/**
	 * @param array $options
	 * @param int   $options['provider']        Vault provider.
	 * @param bool  $options['with_namespace']  (optional) Validated string must contain namespace. Only for HashiCorp.
	 * @param bool  $options['with_key']        (optional) Validated string must contain key.
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
		$this->errorClear();

		$path_pos = 0;

		if ($this->options['with_namespace']) {
			if (($namespace_sep = strpos($source, '/')) === false || $namespace_sep == 0) {
				$this->errorPos($source, 0);

				return self::PARSE_FAIL;
			}

			$path_pos = $namespace_sep + 1;
		}

		if ($this->options['with_key']) {
			if (($key_sep = strpos($source, ':', $path_pos)) === false || !isset($source[$key_sep + 1])) {
				$this->errorPos($source, $key_sep !== false ? $key_sep : $path_pos);

				return self::PARSE_FAIL;
			}

			$path_len = $key_sep - $path_pos;
		}
		else {
			$path_len = strlen($source) - $path_pos;
		}

		if ($path_len == 0 || $source[$path_pos] === '/' || $source[$path_pos + $path_len - 1] === '/'
				|| (($pos = strpos($source, '//', $path_pos)) !== false && $pos < $path_pos + $path_len)) {
			$this->errorPos($source, $path_pos);

			return self::PARSE_FAIL;
		}

		return self::PARSE_SUCCESS;
	}

	private function parseCyberArk($source, $pos) {
		$start = $pos;
		$this->errorClear();
		$this->cyberark_has_appid = true;
		$this->cyberark_has_key = true;
		$has_appid = false;

		while (preg_match('/^(?<parameter>[A-Za-z]+)=(?<value>[^+&%:]*)/', substr($source, $pos), $matches) == 1) {
			if ($matches['parameter'] === 'AppID') {
				$has_appid = true;
			}

			$pos += strlen($matches[0]);

			if (!isset($source[$pos]) || $source[$pos] !== '&') {
				break;
			}

			$pos++;
		}

		if ($start == $pos) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		if ($this->options['with_key']) {
			if (!isset($source[$pos]) || $source[$pos] !== ':' || !isset($source[++$pos])) {
				$this->errorPos($source, $pos);
				$this->cyberark_has_key = false;

				return self::PARSE_FAIL;
			}

			$pos += strlen(substr($source, $pos));
		}

		if (isset($source[$pos])) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		if (!$has_appid) {
			$this->cyberark_has_appid = false;
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		return self::PARSE_SUCCESS;
	}

	/**
	 * @inheritDoc
	 */
	public function getError(): string {
		if ($this->options['provider'] == ZBX_VAULT_TYPE_CYBERARK) {
			if (!$this->cyberark_has_appid) {
				return _s('mandatory parameter "%1$s" is missing', 'AppID');
			}
			elseif (!$this->cyberark_has_key) {
				return _('mandatory key is missing');
			}
		}

		return parent::getError();
	}
}
