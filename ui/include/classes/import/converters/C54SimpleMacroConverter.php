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
 * Expression macros converter in event name field.
 */
class C54SimpleMacroConverter extends C52TriggerExpressionConverter {

	/**
	 * Converting simple macros to expression macros in the selected text.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function convert($text): string {
		$function_macro_parser = new C10FunctionMacroParser(['host_macro' => [ '{HOST.HOST}', '{HOSTNAME}',
			'{HOST.HOST1}', '{HOSTNAME1}', '{HOST.HOST2}', '{HOSTNAME2}', '{HOST.HOST3}', '{HOSTNAME3}',
			'{HOST.HOST4}', '{HOSTNAME4}', '{HOST.HOST5}', '{HOSTNAME5}', '{HOST.HOST6}', '{HOSTNAME6}',
			'{HOST.HOST7}', '{HOSTNAME7}', '{HOST.HOST8}', '{HOSTNAME8}', '{HOST.HOST9}', '{HOSTNAME9}'
		]]);
		$function_parser = new C10FunctionParser();
		$macro_values = [];

		for ($pos = strpos($text, '{'); $pos !== false; $pos = strpos($text, '{', $pos + 1)) {
			if ($function_macro_parser->parse($text, $pos) == CParser::PARSE_FAIL) {
				continue;
			}

			$function_parser->parse($function_macro_parser->getFunction());
			$function_param_list = [];

			for ($n = 0; $n < $function_parser->getParamsNum(); $n++) {
				$function_param_list[] = $function_parser->getParam($n);
			}

			$data = [
				'host' => $function_macro_parser->getHost(),
				'item' => $function_macro_parser->getItem(),
				'functionName' => $function_parser->getFunction(),
				'functionParamsRaw' => $function_parser->getParamsRaw(),
				'functionParams' => $function_param_list
			];
			$data['host'] = strtr($data['host'], ['HOSTNAME' => 'HOST.HOST']);
			if ($data['host'] === '{HOST.HOST}' || $data['host'] === '{HOST.HOST1}') {
				$data['host'] = '';
			}

			[$new_expr] = $this->convertFunction($data, '', '');

			$macro_values[$function_macro_parser->getMatch()] = '{?'.$new_expr.'}';
		}

		return strtr($text, $macro_values);
	}
}
