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
 * Class for storing the result returned by the function macro parser.
 */
class CFunctionMacroParserResult extends CParserResult {

	/**
	 * Array containing information about the parsed function macro.
	 *
	 * Example:
	 *   array(
	 *     'expression' => '{Zabbix server:agent.ping.last(0)}',
	 *     'pos' => 0,
	 *     'host' => 'Zabbix server',
	 *     'item' => 'agent.ping',
	 *     'function' => 'last(0)',
	 *     'functionName' => 'last',
	 *     'functionParam' => '0',
	 *     'functionParamList' => array (0 => '0')
	 *   )
	 *
	 * @deprecated  implement tokens instead
	 *
	 * @var array
	 */
	public $expression = [];

}
