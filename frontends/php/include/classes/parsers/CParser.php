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

abstract class CParser {

	/**
	 * Source string.
	 *
	 * @var
	 */
	public $source;

	/**
	 * Current cursor position.
	 *
	 * @var
	 */
	protected $pos;

	/**
	 * Try to parse the source until one of the given tokens is found. If a token has been found, move the cursor to
	 * the last symbol of the token.
	 *
	 * @param CParserTokens $tokens
	 *
	 * @return null|string			the found token or null if no token has been found
	 */
	protected function parseToken(CParserTokens $tokens) {
		$j = $this->pos;

		while (isset($this->source[$j]) && $tokens->hasChar($this->source[$j])) {
			$j++;
		}

		// empty token
		if ($this->pos == $j) {
			return null;
		}

		$token = substr($this->source, $this->pos, $j - $this->pos);

		// check if this is a valid token
		if (!$tokens->hasToken($token)) {
			return null;
		}

		$this->pos = $j - 1;

		return $token;
	}
}
