<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * - <path/to/secret>:<key>
 * - <path/to/secret>
 */
class CVaultSecretParser extends CParser {

	const STATE_NEW = 0;
	const STATE_AFTER_PATH_NODE_CHAR= 1;
	const STATE_AFTER_PATH_SEP_CHAR = 2;
	const STATE_AFTER_COLON = 3;
	const STATE_AFTER_KEY_CHAR= 4;

	private $options = [
		'with_key' => true
	];

	/**
	 * Parser constructor.
	 *
	 * @param array $options
	 * @param array $options['with_key']  (optional) Validated string must contain key.
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('with_key', $options)) {
			$this->options['with_key'] = $options['with_key'];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$slash_symbol = false;
		$state = self::STATE_NEW;
		$this->errorClear();

		$p = $pos;
		for (; isset($source[$p]); $p++) {
			if (in_array($state, [self::STATE_NEW, self::STATE_AFTER_PATH_NODE_CHAR, self::STATE_AFTER_PATH_SEP_CHAR])
					&& $this->isPathNodeChar($source[$p])) {
				$state = self::STATE_AFTER_PATH_NODE_CHAR;
			}
			elseif ($state == self::STATE_AFTER_PATH_NODE_CHAR && $source[$p] === '/') {
				$state = self::STATE_AFTER_PATH_SEP_CHAR;
				$slash_symbol = true;
			}
			elseif ($slash_symbol && $state == self::STATE_AFTER_PATH_NODE_CHAR && $source[$p] === ':') {
				if (!$this->options['with_key']) {
					break;
				}

				$state = self::STATE_AFTER_COLON;
			}
			elseif (($state == self::STATE_AFTER_COLON || $state == self::STATE_AFTER_KEY_CHAR)
					&& $this->isKeyChar($source[$p])) {
				$state = self::STATE_AFTER_KEY_CHAR;
			}
			else {
				break;
			}
		}

		$is_valid = $this->options['with_key']
			? ($slash_symbol && $state == self::STATE_AFTER_KEY_CHAR)
			: ($slash_symbol && $state == self::STATE_AFTER_PATH_NODE_CHAR);

		if (!$is_valid) {
			$this->errorPos($source, $p);

			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		if (isset($source[$p])) {
			$this->errorPos(substr($source, $pos), $p - $pos);

			return self::PARSE_SUCCESS_CONT;
		}
		else {
			return self::PARSE_SUCCESS;
		}
	}

	/**
	 * Check if give character can be used in path node.
	 *
	 * @param string $char  Single character.
	 *
	 * @return bool
	 */
	protected function isPathNodeChar(string $char): bool {
		return (
			($char >= 'a' && $char <= 'z')
			|| ($char >= 'A' && $char <= 'Z')
			|| ($char >= '0' && $char <= '9')
			|| $char === '.' || $char === '_' || $char === '-'
		);
	}

	/**
	 * Check if give character can be used in key.
	 *
	 * @param string $char  Single character.
	 *
	 * @return bool
	 */
	protected function isKeyChar(string $char): bool {
		return (
			($char >= 'a' && $char <= 'z')
			|| ($char >= 'A' && $char <= 'Z')
			|| ($char >= '0' && $char <= '9')
			|| $char === '_'
		);
	}
}
