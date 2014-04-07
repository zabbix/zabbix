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

/**
 * This class should be used for storing a collection of tokens supported by a parser.
 */
class CParserTokens {

	/**
	 * Array of supported tokens with tokens as keys.
	 *
	 * @var array
	 */
	protected $tokens = array();

	/**
	 * Array of supported chars with chars as keys.
	 *
	 * @var array
	 */
	protected $chars = array();

	/**
	 * @param array $tokens		array of supported tokens
	 */
	public function __construct(array $tokens) {
		$this->tokens = array_flip($tokens);

		$this->chars = array_flip(str_split(implode($tokens)));
	}

	/**
	 * Returns true if the given token is present in the collection.
	 *
	 * @param string $token
	 *
	 * @return bool
	 */
	public function hasToken($token) {
		return isset($this->tokens[$token]);
	}

	/**
	 * Returns true if at least one token contains the given character.
	 *
	 * @param string $char
	 *
	 * @return bool
	 */
	public function hasChar($char) {
		return isset($this->chars[$char]);
	}

}
