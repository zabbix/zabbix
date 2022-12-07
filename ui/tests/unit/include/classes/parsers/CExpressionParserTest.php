<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class CExpressionParserTest extends TestCase {

	public static function dataProvider() {
		return [
			['', ['error' => 'incorrect expression starting from ""', 'match' => ''], CParser::PARSE_FAIL],
			[' ', ['error' => 'incorrect expression starting from ""', 'match' => ''], CParser::PARSE_FAIL],
			['+', ['error' => 'incorrect expression starting from "+"', 'match' => ''], CParser::PARSE_FAIL],
			['1+1', ['error' => '', 'match' => '1+1'], CParser::PARSE_SUCCESS],
			['1+1 ', null, CParser::PARSE_SUCCESS],
			[' 1+1 '."\t\r\n", null, CParser::PARSE_SUCCESS],
			['abc', ['error' => 'incorrect expression starting from "abc"', 'match' => ''], CParser::PARSE_FAIL],
			['{#LLD}', ['error' => 'incorrect expression starting from "{#LLD}"', 'match' => ''], CParser::PARSE_FAIL],
			['{#LLD}', null, CParser::PARSE_SUCCESS, ['lldmacros' => true]],
			['{#LLD}', ['error' => 'incorrect expression starting from "{#LLD}"', 'match' => ''], CParser::PARSE_FAIL],

			['.5', null, CParser::PARSE_SUCCESS],
			['5.', null, CParser::PARSE_SUCCESS],
			['..5', null, CParser::PARSE_FAIL],
			['5..', ['error' => 'incorrect expression starting from "."', 'match' => '5.'], CParser::PARSE_SUCCESS_CONT],

			['1', null, CParser::PARSE_SUCCESS],
			['1s', null, CParser::PARSE_SUCCESS],
			['1m', null, CParser::PARSE_SUCCESS],
			['1h', null, CParser::PARSE_SUCCESS],
			['1d', null, CParser::PARSE_SUCCESS],
			['1w', null, CParser::PARSE_SUCCESS],
			['1K', null, CParser::PARSE_SUCCESS],
			['1M', null, CParser::PARSE_SUCCESS],
			['1G', null, CParser::PARSE_SUCCESS],
			['1T', null, CParser::PARSE_SUCCESS],
			['1.5', null, CParser::PARSE_SUCCESS],
			['1.5s', null, CParser::PARSE_SUCCESS],
			['1.5m', null, CParser::PARSE_SUCCESS],
			['1.5h', null, CParser::PARSE_SUCCESS],
			['1.5d', null, CParser::PARSE_SUCCESS],
			['1.5w', null, CParser::PARSE_SUCCESS],
			['1.5K', null, CParser::PARSE_SUCCESS],
			['1.5M', null, CParser::PARSE_SUCCESS],
			['1.5G', null, CParser::PARSE_SUCCESS],
			['1.5T', null, CParser::PARSE_SUCCESS],
			['-1.5', null, CParser::PARSE_SUCCESS],
			['-1.5s', null, CParser::PARSE_SUCCESS],
			['-1.5m', null, CParser::PARSE_SUCCESS],
			['-1.5h', null, CParser::PARSE_SUCCESS],
			['-1.5d', null, CParser::PARSE_SUCCESS],
			['-1.5w', null, CParser::PARSE_SUCCESS],
			['-1.5K', null, CParser::PARSE_SUCCESS],
			['-1.5M', null, CParser::PARSE_SUCCESS],
			['-1.5G', null, CParser::PARSE_SUCCESS],
			['-1.5T', null, CParser::PARSE_SUCCESS],

			['{TRIGGER.VALUE}', null, CParser::PARSE_SUCCESS],
			['{$USERMACRO}', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['{$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => ''], CParser::PARSE_FAIL],

			['{TRIGGER.VALUE}=1', null, CParser::PARSE_SUCCESS],
			['{$USERMACRO}=1', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['{$USERMACRO}=1', ['error' => 'incorrect expression starting from "{$USERMACRO}=1"', 'match' => ''], CParser::PARSE_FAIL],

			['(/host)', null, CParser::PARSE_FAIL],
			['(/host/key)', null, CParser::PARSE_FAIL],

			['last(/host/key) and {TRIGGER.VALUE}', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and {TRIGGER.VALUE}', ['error' => 'incorrect expression starting from "and {TRIGGER.VALUE}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and{TRIGGER.VALUE}', ['error' => 'incorrect expression starting from "{TRIGGER.VALUE}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and + {TRIGGER.VALUE}', ['error' => 'incorrect expression starting from "+ {TRIGGER.VALUE}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and - {TRIGGER.VALUE}', null, CParser::PARSE_SUCCESS],

			['last(/host/key) and {$USERMACRO}', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['last(/host/key) and {$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)and {$USERMACRO}', null, CParser::PARSE_SUCCESS_CONT, ['usermacros' => true]],
			['last(/host/key)and {$USERMACRO}', ['error' => 'incorrect expression starting from "and {$USERMACRO}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and{$USERMACRO}', null, CParser::PARSE_SUCCESS_CONT, ['usermacros' => true]],
			['last(/host/key) and{$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and + {$USERMACRO}', null, CParser::PARSE_SUCCESS_CONT, ['usermacros' => true]],
			['last(/host/key) and + {$USERMACRO}', ['error' => 'incorrect expression starting from "+ {$USERMACRO}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and - {$USERMACRO}', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['last(/host/key) and - {$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => 'last(/host/key)'], CParser::PARSE_SUCCESS_CONT],

			// basic "not" support
			['not 1', null, CParser::PARSE_SUCCESS],
			['not (1)', null, CParser::PARSE_SUCCESS],
			['not -1', null, CParser::PARSE_SUCCESS],
			['not (-1)', null, CParser::PARSE_SUCCESS],
			['not -(1)', null, CParser::PARSE_SUCCESS],
			['not {TRIGGER.VALUE}', null, CParser::PARSE_SUCCESS],
			['not ({TRIGGER.VALUE})', null, CParser::PARSE_SUCCESS],
			['not -{TRIGGER.VALUE}', null, CParser::PARSE_SUCCESS],
			['not (-{TRIGGER.VALUE})', null, CParser::PARSE_SUCCESS],
			['not -({TRIGGER.VALUE})', null, CParser::PARSE_SUCCESS],
			['not {$USERMACRO}', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not {$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => ''], CParser::PARSE_FAIL],
			['not ({$USERMACRO})', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not ({$USERMACRO})', ['error' => 'incorrect expression starting from "{$USERMACRO})"', 'match' => ''], CParser::PARSE_FAIL],
			['not -{$USERMACRO}', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not -{$USERMACRO}', ['error' => 'incorrect expression starting from "{$USERMACRO}"', 'match' => ''], CParser::PARSE_FAIL],
			['not (-{$USERMACRO})', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not (-{$USERMACRO})', ['error' => 'incorrect expression starting from "{$USERMACRO})"', 'match' => ''], CParser::PARSE_FAIL],
			['not -({$USERMACRO})', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not -({$USERMACRO})', ['error' => 'incorrect expression starting from "{$USERMACRO})"', 'match' => ''], CParser::PARSE_FAIL],
			['not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['not (last(/host/key))', null, CParser::PARSE_SUCCESS],
			['not -last(/host/key)', null, CParser::PARSE_SUCCESS],
			['not (-last(/host/key))', null, CParser::PARSE_SUCCESS],
			['not -(last(/host/key))', null, CParser::PARSE_SUCCESS],

			['not1', null, CParser::PARSE_FAIL],
			['not(1)', null, CParser::PARSE_SUCCESS],
			['not-1', null, CParser::PARSE_FAIL],
			['not(-1)', null, CParser::PARSE_SUCCESS],
			['not-(1)', null, CParser::PARSE_FAIL],
			['not{TRIGGER.VALUE}', null, CParser::PARSE_FAIL],
			['not({TRIGGER.VALUE})', null, CParser::PARSE_SUCCESS],
			['not-{TRIGGER.VALUE}', null, CParser::PARSE_FAIL],
			['not(-{TRIGGER.VALUE})', null, CParser::PARSE_SUCCESS],
			['not-({TRIGGER.VALUE})', null, CParser::PARSE_FAIL],

			['not{$USERMACRO}', null, CParser::PARSE_FAIL, ['usermacros' => true]],
			['not({$USERMACRO})', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not({$USERMACRO})', ['error' => 'incorrect expression starting from "{$USERMACRO})"', 'match' => ''], CParser::PARSE_FAIL],
			['not-{$USERMACRO}', null, CParser::PARSE_FAIL, ['usermacros' => true]],
			['not(-{$USERMACRO})', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['not(-{$USERMACRO})', ['error' => 'incorrect expression starting from "{$USERMACRO})"', 'match' => ''], CParser::PARSE_FAIL],
			['not-({$USERMACRO})', null, CParser::PARSE_FAIL, ['usermacros' => true]],
			['not(last(/host/key))', null, CParser::PARSE_SUCCESS],
			['not(-last(/host/key))', null, CParser::PARSE_SUCCESS],
			['not-(last(/host/key))', null, CParser::PARSE_FAIL],

			['- not 1', null, CParser::PARSE_FAIL],
			['-not 1', null, CParser::PARSE_FAIL],
			['- not1', null, CParser::PARSE_FAIL],
			['-not1', null, CParser::PARSE_FAIL],
			['1 not 1', ['error' => 'incorrect expression starting from "not 1"', 'match' => '1'], CParser::PARSE_SUCCESS_CONT],
			['(1) not 1', ['error' => 'incorrect expression starting from "not 1"', 'match' => '(1)'], CParser::PARSE_SUCCESS_CONT],
			['1not1', ['error' => 'incorrect expression starting from "not1"', 'match' => '1'], CParser::PARSE_SUCCESS_CONT],
			['(1)not1', ['error' => 'incorrect expression starting from "not1"', 'match' => '(1)'], CParser::PARSE_SUCCESS_CONT],

			// operator cases
			['Not 1', null, CParser::PARSE_FAIL],
			['NOT 1', null, CParser::PARSE_FAIL],
			['1 Or 1', null, CParser::PARSE_SUCCESS_CONT],
			['1 OR 1', null, CParser::PARSE_SUCCESS_CONT],
			['1 And 1', null, CParser::PARSE_SUCCESS_CONT],
			['1 AND 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)=00', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=0 0', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=0 0=last(/host/key)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) = 00 = last(/host/key)', null, CParser::PARSE_SUCCESS],

			['count(/host/key[a,,"b",,[c,d,,"e",],,[f]],1,,"b",3)', null, CParser::PARSE_SUCCESS],
			['count(/host/key[a,,"b",,[[c,d,,"e"],[]],,[f]],1,,"b",3)', null, CParser::PARSE_FAIL],
			['count(/host/key[a,,"b",,[c,d,,"e",,,[f]],1,,"b",3)', null, CParser::PARSE_FAIL],
			['count(/host/key[a,,"b",,[c,d,,"e",],,f]],1,,"b",3)', null, CParser::PARSE_FAIL],

			['last(/abcdefghijklmnopqrstuvwxyz. _-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/abcdefghijklmnopqrstuvwxyz._-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890)', null, CParser::PARSE_SUCCESS],
			['abcdefghijklmnopqrstuvwxyz(/host/key)', null, CParser::PARSE_SUCCESS],

			['last(/host)', null, CParser::PARSE_FAIL],
			['last(/host/,)', null, CParser::PARSE_FAIL],
			['last(/host/;)', null, CParser::PARSE_FAIL],
			['last(/host//)', null, CParser::PARSE_FAIL],
			['last(/host//)', null, CParser::PARSE_FAIL],

			['last(/host/key)', null, CParser::PARSE_SUCCESS],
			['(last(/host/key))', null, CParser::PARSE_SUCCESS],
			['(last(/host/key)', null, CParser::PARSE_FAIL],
			['((last(/host/key)', null, CParser::PARSE_FAIL],
			['last((/host/key))=0', null, CParser::PARSE_FAIL],
			['(last(/host/key)=)0', null, CParser::PARSE_FAIL],
			['(last(/host/key))0', ['error' => 'incorrect expression starting from "0"', 'match' => '(last(/host/key))'], CParser::PARSE_SUCCESS_CONT],
			['0(=last(/host/key))', null, CParser::PARSE_SUCCESS_CONT],
			['0(last(/host/key))', null, CParser::PARSE_SUCCESS_CONT],
			['( last(/host/key) )', null, CParser::PARSE_SUCCESS],
			[' ( last(/host/key) ) ', null, CParser::PARSE_SUCCESS],
			['(( ( last(/host/key) ) ))', null, CParser::PARSE_SUCCESS],
			[' ( ( ( last(/host/key) ) ) ) ', null, CParser::PARSE_SUCCESS],
			['((( ( last(/host/key) ) )))', null, CParser::PARSE_SUCCESS],
			[' ( ( ( ( last(/host/key) ) ) ) ) ', null, CParser::PARSE_SUCCESS],
			['()0=( last(/host/key) )', null, CParser::PARSE_FAIL],
			['0()=( last(/host/key) )', ['error' => 'incorrect expression starting from "()=( last(/host/key) )"', 'match' => '0'], CParser::PARSE_SUCCESS_CONT],
			['0=()=( last(/host/key) )', null, CParser::PARSE_SUCCESS_CONT],
			['0=()( last(/host/key) )', null, CParser::PARSE_SUCCESS_CONT],
			['0=last(/host/key)()', null, CParser::PARSE_SUCCESS_CONT],
			['0=last(/host/key)+()()()()5', null, CParser::PARSE_SUCCESS_CONT],
			['0=last(/host/key)+((((()))))5', null, CParser::PARSE_SUCCESS_CONT],
			['(0)=last(/host/key)', null, CParser::PARSE_SUCCESS],
			['(0+)=last(/host/key)', null, CParser::PARSE_FAIL],
			['(0=)last(/host/key)', null, CParser::PARSE_FAIL],
			['(-5)=last(/host/key)', null, CParser::PARSE_SUCCESS],
			['(15 - 5.25 - 1)=last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = -5', null, CParser::PARSE_SUCCESS],
			['last(/0/0,0)=0', null, CParser::PARSE_SUCCESS],

			['((last(/host/key))=0)', null, CParser::PARSE_SUCCESS],
			['( ( last(/host/key) ) = 0 )', null, CParser::PARSE_SUCCESS],
			['((last(/host/key)) * 100) / 95', null, CParser::PARSE_SUCCESS],
			['((last(/host/key)) * 5.25K) / 95.0', null, CParser::PARSE_SUCCESS],
			['((last(/host/key)) * 1w) / 1d', null, CParser::PARSE_SUCCESS],
			['((last(/host/key)) * 1w) / 1Ks', ['error' => 'incorrect expression starting from "s"', 'match' => '((last(/host/key)) * 1w) / 1K'], CParser::PARSE_SUCCESS_CONT],
			['((last(/host/key)) * 1w) / (1d * (last(/host/key))', ['error' => 'incorrect expression starting from ""', 'match' => '((last(/host/key)) * 1w)'], CParser::PARSE_SUCCESS_CONT],
			['((last(/host/key)) * 1w) / (1d * last(/host/key))', ['error' => '', 'match' => '((last(/host/key)) * 1w) / (1d * last(/host/key))'], CParser::PARSE_SUCCESS],
			['((last(/host/key)) * 1w) / (1d * (last(/host/key)))', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or (-1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not (-1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)* (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and (-1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (-1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not (-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not (-1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not (-1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) -(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or(-1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not(-1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)*(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and(-1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(-1)', null, CParser::PARSE_SUCCESS_CONT],

			// "not(" is treated as math function name.
			['last(/host/key,1)*not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<not(-1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>not(-1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)andnot(-1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(-1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or -1', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not -1', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)* -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and -1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or -1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not -1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not -1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) --1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or-1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not-1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)--1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or-1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot-1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or (- 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not (- 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)* (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and (- 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (- 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not (- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not (- 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not (- 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) -(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or(- 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not(- 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)*(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and(- 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(- 1)', null, CParser::PARSE_SUCCESS_CONT],

			// "not(" is treated as math function name.
			['last(/host/key,1)*not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<not(- 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>not(- 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)andnot(- 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(- 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or - 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not - 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)* - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and - 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or - 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not - 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not - 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) -- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or- 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot- 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or- 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot- 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or (+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not (+1)', ['error' => 'incorrect expression starting from "+1)"', 'match' => 'last(/host/key,1)'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not (+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot (+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot (+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or(+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not(+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot(+1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(+1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or +1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not +1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or +1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not +1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not +1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ++1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or+1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot+1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)++1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or+1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot+1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot+1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or (+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not (+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or(+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot(+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot(+ 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(+ 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or + 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not + 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or + 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not + 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not + 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ++ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or+ 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not+ 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)++ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or+ 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot+ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot+ 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or (not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not (not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot (not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot (not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or(not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not(not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot(not1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(not1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not not1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not not1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnotnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornotnot1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>notnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnotnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornotnot1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or (not 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not (not 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)* (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and (not 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or (not 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not (not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not (not 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not (not 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) -(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or(not 1)', null, CParser::PARSE_SUCCESS],

			// "not(" is treated as math function name.
			['last(/host/key,1) *not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) /not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) +not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) -not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) =not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <>not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) >not(not 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) andnot(not 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot(not 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and(not 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or(not 1)', null, CParser::PARSE_SUCCESS_CONT],

			// "not(" is treated as math function name.
			['last(/host/key,1)*not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)-not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)=not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<>not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<not(not 1)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)>not(not 1)', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1)andnot(not 1)', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot(not 1)', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) / not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) + not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) - not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) = not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) <> not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) < not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) > not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) and not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1) or not 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key,1) * not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or not not 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)/ not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)+ not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)- not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)= not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)<> not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)< not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)> not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key,1)and not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)* not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/ not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+ not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)- not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)= not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<> not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)< not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)> not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)and not not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)or not not 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) * notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) / notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) + notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) - notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) = notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <> notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) < notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) > notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) and notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) or notnot 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornot 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1)*notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)/notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)+notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)-notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)=notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<>notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)<notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)>notnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)andnotnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1)ornotnot 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key,1) *not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) /not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) +not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) -not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) =not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <>not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) <not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) >not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) andnot 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1) ornot 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) + 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key) + not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or not 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key)+ 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)/ 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)* 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)= 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)<> 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)or 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)+ not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)- not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)/ not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)* not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)= not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)<> not 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and not 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)or not 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) +1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) -1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) /1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) *1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) =1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <>1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) or1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) + not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) - not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) / not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) * not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) = not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) <> not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) or not1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)+1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)-1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)/1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)*1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)<>1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)or1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)+not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)/not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)*not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)<>not1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)andnot1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)ornot1', null, CParser::PARSE_SUCCESS_CONT],

			// unary operators before binary operators
			['last(/host/key) -* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) -/ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -= 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -<> 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -and 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -or 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)-* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)-/ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-= 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-<> 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-and 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-or 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) -*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) --1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) -/1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -=1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -<>1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -and1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -or1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)-*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)--1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)-/1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-=1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-<>1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-and1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-or1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) not* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not/ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not= 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not<> 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) notand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) notor 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)not* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)not- 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)not/ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)not* 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)not= 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)not<> 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)notand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)notor 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) not*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not-1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not/1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not=1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not<>1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) notand1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) notor1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)-*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)--1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)-/1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-*1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-=1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-<>1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-and1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)-or1', null, CParser::PARSE_SUCCESS_CONT],

			// suffixes
			['last(/host/key)=1K', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1M', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1G', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1T', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1s', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1m', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1h', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1d', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1w', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=1.56K', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56M', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56G', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56T', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56s', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56m', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56h', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56d', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56w', null, CParser::PARSE_SUCCESS],

			// text operators after suffixes
			['last(/host/key)=1K and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1M and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1G and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1T and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1s and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1m and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1h and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1d and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1w and 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=1Kand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Mand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Gand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Tand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1sand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1mand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1hand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1dand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1wand 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)=1.56K and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56M and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56G and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56T and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56s and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56m and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56h and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56d and 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56w and 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=1.56Kand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Mand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Gand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Tand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56sand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56mand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56hand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56dand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56wand 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)=1K or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1M or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1G or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1T or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1s or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1m or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1h or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1d or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1w or 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=1Kor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Mor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Gor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1Tor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1sor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1mor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1hor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1dor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1wor 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)=1.56K or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56M or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56G or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56T or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56s or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56m or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56h or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56d or 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.56w or 1', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=1.56Kor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Mor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Gor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56Tor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56sor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56mor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56hor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56dor 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=1.56wor 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) + 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or 1.173640', null, CParser::PARSE_SUCCESS],

			['last(/host/key)+ 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)- 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)/ 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)* 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)= 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)<> 1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and 1.173640', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)or 1.173640', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) +1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) -1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) /1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) *1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) =1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <>1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and1.173640', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) or1.173640', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key)+1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)-1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)/1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)*1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)<>1.173640', null, CParser::PARSE_SUCCESS],
			['last(/host/key)and1.173640', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)or1.173640', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) + 1 or last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - 1 and last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / 1 <> last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * 1 = last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = 1 * last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> 1 / last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and 1 - last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or 1 + last(/host/key)', null, CParser::PARSE_SUCCESS],

			['last(/host/key) + not 1 or last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - not 1 and last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / not 1 <> last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * not 1 = last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = not 1 * last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> not 1 / last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and not 1 - last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or not 1 + last(/host/key)', null, CParser::PARSE_SUCCESS],

			['last(/host/key) + 1 or not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) - 1 and not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) / 1 <> not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) * 1 = not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) = 1 * not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) <> 1 / not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) and 1 - not last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key) or 1 + not last(/host/key)', null, CParser::PARSE_SUCCESS],

			['last(/host/key) -- 1', null, CParser::PARSE_SUCCESS],
			['last(/host/key) ++ 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) // 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) ** 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) == 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) <><> 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) andand 1', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) oror 1', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) +', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) -', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) /', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) *', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) =', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) <>', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) or', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) not', null, CParser::PARSE_SUCCESS_CONT],

			['last(/host/key) + not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) - not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) / not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) * not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) = not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) <> not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) and not', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key) or not', null, CParser::PARSE_SUCCESS_CONT],

			['- last(/host/key)', null, CParser::PARSE_SUCCESS],
			['+ last(/host/key)', null, CParser::PARSE_FAIL],
			['/ last(/host/key)', null, CParser::PARSE_FAIL],
			['* last(/host/key)', null, CParser::PARSE_FAIL],
			['= last(/host/key)', null, CParser::PARSE_FAIL],
			['<> last(/host/key)', null, CParser::PARSE_FAIL],
			['and last(/host/key)', null, CParser::PARSE_FAIL],
			['or last(/host/key)', null, CParser::PARSE_FAIL],

			['not - last(/host/key)', null, CParser::PARSE_SUCCESS],
			['not + last(/host/key)', null, CParser::PARSE_FAIL],
			['not / last(/host/key)', null, CParser::PARSE_FAIL],
			['not * last(/host/key)', null, CParser::PARSE_FAIL],
			['not = last(/host/key)', null, CParser::PARSE_FAIL],
			['not <> last(/host/key)', null, CParser::PARSE_FAIL],
			['not and last(/host/key)', null, CParser::PARSE_FAIL],
			['not or last(/host/key)', null, CParser::PARSE_FAIL],

			['- not last(/host/key)', null, CParser::PARSE_FAIL],
			['+ not last(/host/key)', null, CParser::PARSE_FAIL],
			['/ not last(/host/key)', null, CParser::PARSE_FAIL],
			['* not last(/host/key)', null, CParser::PARSE_FAIL],
			['= not last(/host/key)', null, CParser::PARSE_FAIL],
			['<> not last(/host/key)', null, CParser::PARSE_FAIL],
			['and not last(/host/key)', null, CParser::PARSE_FAIL],
			['or not last(/host/key)', null, CParser::PARSE_FAIL],

			// abs (expr)
			['abs(last(/host/item,#1)-last(/host/item,#2)) > 0', null, CParser::PARSE_SUCCESS],

			// avg, min, max, sum (item, period OR expr1, ..., exprN)
			['avg(/host/item,#5) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,30s) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,10m) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,1h) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,#5:now-1d) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,30m:now-1d) > 0', null, CParser::PARSE_SUCCESS],
			['avg(/host/item,1h:now/h) > 0', null, CParser::PARSE_SUCCESS],
			['min(/host/item,30m) > 0', null, CParser::PARSE_SUCCESS],
			['max(/host/item,30m) > 0', null, CParser::PARSE_SUCCESS],
			['sum(/host/item,30m) > 0', null, CParser::PARSE_SUCCESS],
			['sum(/host/item,60s) > 0', null, CParser::PARSE_SUCCESS],
			['sum(/host/item,60s:now-3600s) > 0', null, CParser::PARSE_SUCCESS],
			['sum(/host/item,1m:now-1h) > 0', null, CParser::PARSE_SUCCESS],

			['min(min(/host/key,1h),min(/host/key,1h),25)', null, CParser::PARSE_SUCCESS],
			['min(min(/host/key,1h),avg(/host/key,1h),25)', null, CParser::PARSE_SUCCESS],
			['min(min(/host/key,1h),avg(/host/key,1h)*100,25)', null, CParser::PARSE_SUCCESS],
			['min(min(/host1/key1,1h),min(/host2/key2,1h))', null, CParser::PARSE_SUCCESS],

			// band (item, period, mask)
			['band(/host/item,#1,32) > 0', null, CParser::PARSE_SUCCESS],
			['band(/host/item,#2:now-1h,64) > 0', null, CParser::PARSE_SUCCESS],

			// count (item, period, <operator>, <pattern>)
			['count(/host/item,#1,"eq","0") > 0', null, CParser::PARSE_SUCCESS],
			['count(/host/item,5m:now-2h,"regexp","xyz") > 0', null, CParser::PARSE_SUCCESS],
			['count(/host/item,5m:now-1h,"iregexp","10") > 0', null, CParser::PARSE_SUCCESS],
			['count(/host/item,5m:now-2d,"gt",100) > 0', null, CParser::PARSE_SUCCESS],
			['count(/host/item,1m,"band",32) > 0', null, CParser::PARSE_SUCCESS],

			// date, dateofmonth, dayofweek, now, time ()
			['date() > 0', null, CParser::PARSE_SUCCESS],
			['dayofmonth() > 0', null, CParser::PARSE_SUCCESS],
			['dayofweek() > 0', null, CParser::PARSE_SUCCESS],
			['now() > 0', null, CParser::PARSE_SUCCESS],
			['time() > 0', null, CParser::PARSE_SUCCESS],

			// forecast (item, period, time, <fit>, <mode>)
			['forecast(/host/item,#10,100s) > 0', null, CParser::PARSE_SUCCESS],
			['forecast(/host/item,3600s:now-7200s,600s,"linear","avg") > 0', null, CParser::PARSE_SUCCESS],
			['forecast(/host/item,30m:now-1d,600s,,"avg") > 0', null, CParser::PARSE_SUCCESS],

			// fuzzytime (item, period)
			['fuzzytime(/host/item,60s) > 0', null, CParser::PARSE_SUCCESS],

			// find (item, period, <operator>, <pattern>)
			['find(/host/key,#10,"iregexp","^error") > 0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,60s,"iregexp","^critical") > 0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,5m,"iregexp","^warning") > 0', null, CParser::PARSE_SUCCESS],

			// last (item, period)
			['last(/host/key) > 0', null, CParser::PARSE_SUCCESS],
			['last(/host/key,#5) > 0', null, CParser::PARSE_SUCCESS],
			['last(/host/key,#10:now-3600s) > 0', null, CParser::PARSE_SUCCESS],
			['last(/host/key,#1:now-1d) > 0', null, CParser::PARSE_SUCCESS],
			['last(/host/vfs.fs.size[/,pfree])<10', null, CParser::PARSE_SUCCESS],
			['last(/host/vfs.fs.size["/var/log",pfree])<10', null, CParser::PARSE_SUCCESS],

			// length (expr)
			['length(last(/host/key,30m)) > 0', null, CParser::PARSE_SUCCESS],
			['length(last(/host/key,60s)) > 0', null, CParser::PARSE_SUCCESS],
			['length(last(/host/key,#10)) > 0', null, CParser::PARSE_SUCCESS],
			['length(last(/host/key,60s:now-3600s)) > 0', null, CParser::PARSE_SUCCESS],
			['length(last(/host/key,1m:now-1h)) > 0', null, CParser::PARSE_SUCCESS],

			// logeventid (item, <pattern>)
			['logeventid(/host/key,,"^error") > 0', null, CParser::PARSE_SUCCESS],

			// logseverity (item)
			['logseverity(/host/key) > 0', null, CParser::PARSE_SUCCESS],

			// logsource (item, <pattern>)
			['logsource(/host/item,,"^system$") > 0', null, CParser::PARSE_SUCCESS],

			// nodata (item, period)
			['nodata(/host/item,60s) > 0', null, CParser::PARSE_SUCCESS],
			['nodata(/host/item,5m) > 0', null, CParser::PARSE_SUCCESS],

			// percentile (item, period, percentage)
			['percentile(/host/key,30m,50) > 0', null, CParser::PARSE_SUCCESS],
			['percentile(/host/key,60s,60) > 0', null, CParser::PARSE_SUCCESS],
			['percentile(/host/key,#10,70) > 0', null, CParser::PARSE_SUCCESS],
			['percentile(/host/key,60s:now-3600s,80) > 0', null, CParser::PARSE_SUCCESS],
			['percentile(/host/key,1m:now-1h,90) > 0', null, CParser::PARSE_SUCCESS],

			// timeleft (item, period, time, threshold, <fit>)
			['timeleft(/host/key,#10,100) > 0', null, CParser::PARSE_SUCCESS],
			['timeleft(/host/key,3600s:now-7200s,600,"linear") > 0', null, CParser::PARSE_SUCCESS],
			['timeleft(/host/key,30m:now-1d,600) > 0', null, CParser::PARSE_SUCCESS],

			// trendavg, trendcount, trendmax, trendmin, trendsum (item, period)
			['trendavg(/host/item,1h:now/h-1d) > 0', null, CParser::PARSE_SUCCESS],
			['trendavg(/host/key,1M:now/M) > 1.2*trendavg(/host/key,1M:now/M-1y)', null, CParser::PARSE_SUCCESS],
			['trendcount(/host/item,1h:now/h-1d) > 0', null, CParser::PARSE_SUCCESS],
			['trendmax(/host/item,1h:now/h-1d) > 0', null, CParser::PARSE_SUCCESS],
			['trendmin(/host/item,1h:now/h-1d) > 0', null, CParser::PARSE_SUCCESS],
			['trendsum(/host/item,1h:now/h-1d) > 0', null, CParser::PARSE_SUCCESS],

			['last(/host/key)=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,)', null, CParser::PARSE_SUCCESS],
			['count(/host/key, 1,)=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,  1,)=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1, )=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,  )=0', null, CParser::PARSE_SUCCESS],

			['find(/host/key,,"like",")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like","")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like",""")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like","""")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like", ")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like", "")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like", """)=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like", """")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",  ")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",  "")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like",  """)=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",  """")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1,")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,""")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"""")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1, ")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1, "")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1, """)=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1, """")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1,  ")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,  "")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,  """)=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,  """")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1,"",")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"","")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,"",""")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"","""")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1,"", ")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"", "")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,"", """)=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"", """")=0', null, CParser::PARSE_FAIL],

			['count(/host/key,1,"",  ")=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"",  "")=0', null, CParser::PARSE_SUCCESS],
			['count(/host/key,1,"",  """)=0', null, CParser::PARSE_FAIL],
			['count(/host/key,1,"",  """")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like","\\")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like","\\\\")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like","\\"")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like","\\\\\\"")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like","\\""")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like","\\"""")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",\")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",param\")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",param")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like", \")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like", param\")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like", param")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",  \")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",  param\")=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",  param")=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",()=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",param()=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like", ()=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like", param()=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",  ()=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",  param()=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",))=0', null, CParser::PARSE_SUCCESS_CONT],
			['find(/host/key,,"like",param))=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like", ))=0', null, CParser::PARSE_SUCCESS_CONT],
			['find(/host/key,,"like", param))=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like",  ))=0', null, CParser::PARSE_SUCCESS_CONT],
			['find(/host/key,,"like",  param))=0', null, CParser::PARSE_FAIL],

			['find(/host/key,,"like","(")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like","param(")=0', null, CParser::PARSE_SUCCESS],

			['find(/host/key,,"like",")")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like","param)")=0', null, CParser::PARSE_SUCCESS],

			['find(/host/key,,"like",)=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like", )=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like"," ")=0', null, CParser::PARSE_SUCCESS],
			['find(/host/key,,"like",abc)=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like",\'abc\')=0', null, CParser::PARSE_FAIL],
			['find(/host/key,,"like","")=0', null, CParser::PARSE_SUCCESS],

			['last(/host/key,0)=0', null, CParser::PARSE_SUCCESS],
			['(nodata(/hostA/keyA, "1h")=0) or (last(/hostB/keyB,123)=0)', null, CParser::PARSE_SUCCESS],
			['find(/host/key[asd[],aaa()=0', null, CParser::PARSE_FAIL],
			['find(/host/key[asd[,asd[,[]],aaa()=0', null, CParser::PARSE_FAIL],
			['find(/host/key[[],[],[]])', null, CParser::PARSE_SUCCESS],

			['last(/hostkey,0)=0', null, CParser::PARSE_FAIL],
			['last(host/key,0)=0', null, CParser::PARSE_FAIL],
			['last(/host/key,0)=', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,0)<>0', null, CParser::PARSE_SUCCESS],

			['(nodata(/hostA/keyA, "300s")=0) oror (last(/hostB/keyB,123)=0)', null, CParser::PARSE_SUCCESS_CONT],
			['(last(/host1/key1,0)/last(/host2/key2,#5))/10+2*{TRIGGER.VALUE} and {$USERMACRO1}+(-{$USERMACRO2})+-{$USERMACRO3}*-12K+12.5m', null, CParser::PARSE_SUCCESS, ['usermacros' => true]],
			['(last(/host1/key1,0)/last(/host2/key2,#5))/10+2*{TRIGGER.VALUE} and {$USERMACRO1}+(-{$USERMACRO2})+-{$USERMACRO3}*-12K+12.5m', ['error' => 'incorrect expression starting from "{$USERMACRO1}+(-{$USERMACRO2})+-{$USERMACRO3}*-12K+12.5m"', 'match' => '(last(/host1/key1,0)/last(/host2/key2,#5))/10+2*{TRIGGER.VALUE}'], CParser::PARSE_SUCCESS_CONT],
			['last(/host/key,1.23)', null, CParser::PARSE_FAIL],
			['last(/host/key,1.23s)', null, CParser::PARSE_FAIL],
			['date()', null, CParser::PARSE_SUCCESS],
			['date(0)', null, CParser::PARSE_SUCCESS],
			['date(0,)', null, CParser::PARSE_FAIL],
			['dayofweek()', null, CParser::PARSE_SUCCESS],
			['dayofweek(0)', null, CParser::PARSE_SUCCESS],
			['dayofweek(0,)', null, CParser::PARSE_FAIL],
			['last(/host/key)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,0)', null, CParser::PARSE_SUCCESS],
			['last(/host/key,#123)', null, CParser::PARSE_SUCCESS],
			['max(/host/key,123)', null, CParser::PARSE_SUCCESS],
			['now()', null, CParser::PARSE_SUCCESS],
			['now(0)', null, CParser::PARSE_SUCCESS],
			['now(0,)', null, CParser::PARSE_FAIL],
			['(--({host:key.last(0)}+K))', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TRIGGER', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TRIGGE', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TRIGG', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TRIG', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TRI', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{TR', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{T', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+{', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)+', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1)', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-1', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(-', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-(', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+-', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)+', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5)', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-5', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4-', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(4', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+(', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)+', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3)', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(3', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -(', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and -', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and ', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m and', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m an', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m a', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m ', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9m', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=9', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8=', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>8', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<>', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7<', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<7', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6<', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>6', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5>', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and 5', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and ', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 and', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 an', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 a', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4 ', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or 4', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or ', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 or', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 o', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3 ', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+3', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2+', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-2', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1-', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/1', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0/', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*0', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )*', null, CParser::PARSE_SUCCESS_CONT],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" )', null, CParser::PARSE_SUCCESS],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"" ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\""', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\"', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4\\', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p4', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "p', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", "', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3", ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3",', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3"', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p3', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "p', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, "', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1, ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1,', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #1', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], #', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ], ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ],', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ]', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"" ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\""', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\"', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4\\', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p4', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "p', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", "', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3", ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3",', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3"', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p3', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"p', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,"', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ,', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2 ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p2', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, p', null, CParser::PARSE_FAIL],
			['func(/host/key[p1, ', null, CParser::PARSE_FAIL],
			['func(/host/key[p1,', null, CParser::PARSE_FAIL],
			['func(/host/key[p1', null, CParser::PARSE_FAIL],
			['func(/host/key[p', null, CParser::PARSE_FAIL],
			['func(/host/key[', null, CParser::PARSE_FAIL],
			['func(/host/key', null, CParser::PARSE_FAIL],
			['func(/host/ke', null, CParser::PARSE_FAIL],
			['func(/host/k', null, CParser::PARSE_FAIL],
			['func(/host/', null, CParser::PARSE_FAIL],
			['func(/host', null, CParser::PARSE_FAIL],
			['func(/hos', null, CParser::PARSE_FAIL],
			['func(/ho', null, CParser::PARSE_FAIL],
			['func(/h', null, CParser::PARSE_FAIL],
			['func(/', null, CParser::PARSE_FAIL],
			['func(', null, CParser::PARSE_FAIL],
			['func', null, CParser::PARSE_FAIL],
			['fun', null, CParser::PARSE_FAIL],
			['fu', null, CParser::PARSE_FAIL],
			['f', null, CParser::PARSE_FAIL],

			// new lines and tabs
			["\rlast(/host/key,1)+1", null, CParser::PARSE_SUCCESS],
			["\nlast(/host/key,1)+1", null, CParser::PARSE_SUCCESS],
			["\r\nlast(/host/key,1)+1", null, CParser::PARSE_SUCCESS],
			["\tlast(/host/key,1)+1", null, CParser::PARSE_SUCCESS],

			["{\rhost:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{\nhost:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{\r\nhost:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{\thost:key.last(1)}+1", null, CParser::PARSE_FAIL],

			["{host\r:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host\n:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host\r\n:key.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host\t:key.last(1)}+1", null, CParser::PARSE_FAIL],

			["{host:\rkey.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:\nkey.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:\r\nkey.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:\tkey.last(1)}+1", null, CParser::PARSE_FAIL],

			["{host:key\r.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key\n.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key\r\n.last(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key\t.last(1)}+1", null, CParser::PARSE_FAIL],

			["{host:key.\rlast(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.\nlast(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.\r\nlast(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.\tlast(1)}+1", null, CParser::PARSE_FAIL],

			["{host:key.last\r(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.last\n(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.last\r\n(1)}+1", null, CParser::PARSE_FAIL],
			["{host:key.last\t(1)}+1", null, CParser::PARSE_FAIL],

			["{host:key.last(1)\r}+1", null, CParser::PARSE_FAIL],
			["{host:key.last(1)\n}+1", null, CParser::PARSE_FAIL],
			["{host:key.last(1)\r\n}+1", null, CParser::PARSE_FAIL],
			["{host:key.last(1)\t}+1", null, CParser::PARSE_FAIL],

			["last(/host/key,1)\r+1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\n+1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\r\n+1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\t+1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)+\r1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+\r\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+\t1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)+1\r", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+1\n", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+1\r\n", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)+1\t", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)\r\r+\r\r1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\n\n+\n\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\r\n\r\n+\r\n\r\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\t\t+\t\t1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)\r\t+\r\t1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\n\t+\n\t1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\r\n\t+\r\n\t1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\t\t+\t\t1", null, CParser::PARSE_SUCCESS],

			["(\rlast(/host/key,1)+1) or 1", null, CParser::PARSE_SUCCESS],
			["(\nlast(/host/key,1)+1) or 1", null, CParser::PARSE_SUCCESS],
			["(\r\nlast(/host/key,1)+1) or 1", null, CParser::PARSE_SUCCESS],
			["(\tlast(/host/key,1)+1) or 1", null, CParser::PARSE_SUCCESS],

			["(last(/host/key,1)+1\r) or 1", null, CParser::PARSE_SUCCESS],
			["(last(/host/key,1)+1\n) or 1", null, CParser::PARSE_SUCCESS],
			["(last(/host/key,1)+1\r\n) or 1", null, CParser::PARSE_SUCCESS],
			["(last(/host/key,1)+1\t) or 1", null, CParser::PARSE_SUCCESS],
			["(last(/host/key,1)+1\t) or 1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)\ror not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\nor not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\r\nor not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\tor not 1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1) or\rnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) or\nnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) or\r\nnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) or\tnot 1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1)\rand not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\nand not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\r\nand not 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1)\tand not 1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1) and\rnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and\nnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and\r\nnot 1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and\tnot 1", null, CParser::PARSE_SUCCESS],

			["last(/host/key,1) and not\r1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and not\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and not\r\n1", null, CParser::PARSE_SUCCESS],
			["last(/host/key,1) and not\t1", null, CParser::PARSE_SUCCESS],

			["count(/host/key,1,\"\"\r)=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\"\"\n)=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\"\"\r\n)=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\"\"\t)=0", null, CParser::PARSE_FAIL],

			["count(/host/key,1,\"\r\")=0", null, CParser::PARSE_SUCCESS],
			["count(/host/key,1,\"\n\")=0", null, CParser::PARSE_SUCCESS],
			["count(/host/key,1,\"\r\n\")=0", null, CParser::PARSE_SUCCESS],
			["count(/host/key,1,\"\t\")=0", null, CParser::PARSE_SUCCESS],

			["count(/host/key,1,\r\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\n\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\r\n\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1,\t\"\")=0", null, CParser::PARSE_FAIL],

			["count(/host/key,1\r,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1\n,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1\r\n,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,1\t,\"\")=0", null, CParser::PARSE_FAIL],

			["count(/host/key,\r1,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,\n1,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,\r\n1,\"\")=0", null, CParser::PARSE_FAIL],
			["count(/host/key,\t1,\"\")=0", null, CParser::PARSE_FAIL],

			// collapsed trigger expressions
			['func(/host/key)', null, CParser::PARSE_FAIL, ['collapsed_expression' => true]],
			['{123}', null, CParser::PARSE_SUCCESS, ['collapsed_expression' => true]],
			['{123} = {$MACRO}', null, CParser::PARSE_SUCCESS, ['collapsed_expression' => true, 'usermacros' => true]],
			['{123} = {$MACRO}', null, CParser::PARSE_SUCCESS_CONT, ['collapsed_expression' => true]],
			['{123} = {#MACRO}', null, CParser::PARSE_SUCCESS, ['collapsed_expression' => true, 'lldmacros' => true]],
			['{123} = {#MACRO}', null, CParser::PARSE_SUCCESS_CONT, ['collapsed_expression' => true]],

			// Compare strings.
			['last(/host/key)=""', null, CParser::PARSE_SUCCESS],
			['last(/host/key)=" "', null, CParser::PARSE_SUCCESS],
			['last(/host/key)="\"abc\""', null, CParser::PARSE_SUCCESS],
			['last(/host/key)="\"a\\bc\""', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)= "\"abc" ', null, CParser::PARSE_SUCCESS],
			['last(/host/key)="\\\"', null, CParser::PARSE_SUCCESS], // Actually looks like last(/host/key)="\\"
			['last(/host/key)="\\ \""', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=" "      ', null, CParser::PARSE_SUCCESS],
			['"abc"="abc"', null, CParser::PARSE_SUCCESS],
			['    "abc"  =   "abc"   ', null, CParser::PARSE_SUCCESS],
			['"abc"="abc"="abc"', null, CParser::PARSE_SUCCESS],
			['"abc"="abc" and "abc"', null, CParser::PARSE_SUCCESS],
			['last(/host/key)="\ "', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\\', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\"', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=""abc', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)=" "abc', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="abc\"', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\""\"', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\\ \"', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\\\\\ "', null, CParser::PARSE_SUCCESS_CONT], // Actually looks like last(/host/key)="\\\ "
			['last(/host/key)=" " "', null, CParser::PARSE_SUCCESS_CONT],
			['last(/host/key)="\n"', null, CParser::PARSE_SUCCESS_CONT],
			['"abc"="abc"and"abc"', null, CParser::PARSE_SUCCESS_CONT],
			['"abc"="abc" and abc"', null, CParser::PARSE_SUCCESS_CONT],
			['min(last(/{HOST.HOST}/key), 1)', null, CParser::PARSE_FAIL],
			['min(last(/{HOST.HOST9}/key), 1)', null, CParser::PARSE_FAIL, ['host_macro' => true]],
			['min(last(/{HOST.HOST}/key), 1)', null, CParser::PARSE_SUCCESS, ['host_macro' => true]],
			['min(last(/{HOST.HOST}/key), 1)', null, CParser::PARSE_SUCCESS, ['host_macro_n' => true]],
			['min(last(/{HOST.HOST2}/key), 1)', null, CParser::PARSE_SUCCESS, ['host_macro_n' => true]],

			['last(/*/agent.ping) = 1 or last(/host2/*) = 1 or last(/*/*)', null, CParser::PARSE_SUCCESS, ['calculated' => true]],
			['last(/'.'/agent.ping) = 1', null, CParser::PARSE_FAIL, ['calculated' => true]],
			['last(/'.'/agent.ping) = 1', null, CParser::PARSE_SUCCESS, ['empty_host' => true]],
			['last(/'.'/*) = 1', null, CParser::PARSE_FAIL, ['calculated' => true]],
			['last(/'.'/*) = 1', null, CParser::PARSE_FAIL, ['empty_host' => true]],
			['last(/'.'/*) = 1', null, CParser::PARSE_SUCCESS, ['calculated' => true, 'empty_host' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*?[group = "Zabbix servers" and (tag = "tag1" or tag = "tag2")]) = 1 or last(/*/*)', null, CParser::PARSE_SUCCESS, ['calculated' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*?[group = "Zabbix servers" and (tag = {$MACRO} or tag = "tag2")]) = 1 or last(/*/*)', null, CParser::PARSE_SUCCESS, ['usermacros' => true, 'calculated' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*?[group = "Zabbix servers" and (tag = {$MACRO} or tag = "tag2")]) = 1 or last(/*/*)', ['error' => 'incorrect expression starting from "last(/host2/*?[group = "Zabbix servers" and (tag = {$MACRO} or tag = "tag2")]) = 1 or last(/*/*)"', 'match' => 'last(/*/agent.ping) = 1'], CParser::PARSE_SUCCESS_CONT, ['calculated' => true]],
			['last(/host2/*?[group = "Zabbix servers" and (tag = {#MACRO} or tag = "tag2")]) = 1', null, CParser::PARSE_FAIL, ['calculated' => true]],
			['last(/host2/*?[group = "Zabbix servers" and (tag = {#MACRO} or tag = {{#MACRO}.func()})]) = 1', null, CParser::PARSE_SUCCESS, ['lldmacros' => true, 'calculated' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*) = 1 or last(/*/*) or last(/{HOST.HOST}/key)', ['error' => 'incorrect expression starting from "last(/{HOST.HOST}/key)"', 'match' => 'last(/*/agent.ping) = 1 or last(/host2/*) = 1 or last(/*/*)'], CParser::PARSE_SUCCESS_CONT, ['calculated' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*) = 1 or last(/*/*) or last(/{HOST.HOST}/key)', null, CParser::PARSE_SUCCESS, ['calculated' => true, 'host_macro' => true]],
			['last(/*/agent.ping) = {TRIGGER.VALUE}', ['error' => 'incorrect expression starting from "{TRIGGER.VALUE}"', 'match' => 'last(/*/agent.ping)'], CParser::PARSE_SUCCESS_CONT, ['calculated' => true]],
			['last(/*/agent.ping) = 1 or last(/host2/*) = 1', null, CParser::PARSE_FAIL]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string      $expression
	 * @param array|null  $result
	 * @param int         $rc
	 * @param array       $options
	 * @param bool        $options['lldmacros']
	 * @param bool        $options['collapsed_expression']
	 * @param bool        $options['calculated']
	 * @param bool        $options['host_macro']
	 */
	public function testParseExpression(string $expression, ?array $result, int $rc, array $options = []) {
		$expression_parser = new CExpressionParser($options);

		$this->assertSame($rc, $expression_parser->parse($expression));

		if ($result !== null) {
			$this->assertSame($result, [
				'error' => $expression_parser->getError(),
				'match' => $expression_parser->getMatch()
			]);
			$this->assertSame(strlen($result['match']), $expression_parser->getLength());
		}
	}

	public static function dataProviderTokens() {
		return [
			[
				'((-12 + {$MACRO})) = 1K or not {{#M}.regsub("^([0-9]+)", \1)} and {TRIGGER.VALUE} and "\\"str\\"" = func(/host/key, #25:now/M, "eq", "str") or math() or min( last(/host/key), {$MACRO}, 123, "abc" , min(min(/host/key, 1d:now/d), 125) + 10 )',
				[
					'match' => '((-12 + {$MACRO})) = 1K or not {{#M}.regsub("^([0-9]+)", \1)} and {TRIGGER.VALUE} and "\\"str\\"" = func(/host/key, #25:now/M, "eq", "str") or math() or min( last(/host/key), {$MACRO}, 123, "abc" , min(min(/host/key, 1d:now/d), 125) + 10 )',
					'length' => 237,
					'tokens' => [
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
							'pos' => 0,
							'match' => '(',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
							'pos' => 1,
							'match' => '(',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 2,
							'match' => '-',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
							'pos' => 3,
							'match' => '12',
							'length' => 2,
							'data' => [
								'suffix' => null
							]
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 6,
							'match' => '+',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
							'pos' => 8,
							'match' => '{$MACRO}',
							'length' => 8
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
							'pos' => 16,
							'match' => ')',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
							'pos' => 17,
							'match' => ')',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 19,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
							'pos' => 21,
							'match' => '1K',
							'length' => 2,
							'data' => [
								'suffix' => 'K'
							]
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 24,
							'match' => 'or',
							'length' => 2
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 27,
							'match' => 'not',
							'length' => 3
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_LLD_MACRO,
							'pos' => 31,
							'match' => '{{#M}.regsub("^([0-9]+)", \1)}',
							'length' => 30
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 62,
							'match' => 'and',
							'length' => 3
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_MACRO,
							'pos' => 66,
							'match' => '{TRIGGER.VALUE}',
							'length' => 15
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 82,
							'match' => 'and',
							'length' => 3
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_STRING,
							'pos' => 86,
							'match' => '"\\"str\\""',
							'length' => 9
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 96,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
							'pos' => 98,
							'match' => 'func(/host/key, #25:now/M, "eq", "str")',
							'length' => 39,
							'data' => [
								'function' => 'func',
								'parameters' => [
									[
										'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
										'pos' => 103,
										'match' => '/host/key',
										'length' => 9,
										'data' => [
											'host' => 'host',
											'item' => 'key',
											'filter' => [
												'match' => '',
												'tokens' => []
											]
										]
									],
									[
										'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
										'pos' => 114,
										'match' => '#25:now/M',
										'length' => 9,
										'data' => [
											'sec_num' => '#25',
											'time_shift' => 'now/M'
										]
									],
									[
										'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
										'pos' => 125,
										'match' => '"eq"',
										'length' => 4
									],
									[
										'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
										'pos' => 131,
										'match' => '"str"',
										'length' => 5
									]
								]
							]
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 138,
							'match' => 'or',
							'length' => 2
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
							'pos' => 141,
							'match' => 'math()',
							'length' => 6,
							'data' => [
								'function' => 'math',
								'parameters' => []
							]
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 148,
							'match' => 'or',
							'length' => 2
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
							'pos' => 151,
							'match' => 'min( last(/host/key), {$MACRO}, 123, "abc" , min(min(/host/key, 1d:now/d), 125) + 10 )',
							'length' => 86,
							'data' => [
								'function' => 'min',
								'parameters' => [
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 156,
										'match' => 'last(/host/key)',
										'length' => 15,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
													'pos' => 156,
													'match' => 'last(/host/key)',
													'length' => 15,
													'data' => [
														'function' => 'last',
														'parameters' => [
															[
																'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
																'pos' => 161,
																'match' => '/host/key',
																'length' => 9,
																'data' => [
																	'host' => 'host',
																	'item' => 'key',
																	'filter' => [
																		'match' => '',
																		'tokens' => []
																	]
																]
															]
														]
													]
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 173,
										'match' => '{$MACRO}',
										'length' => 8,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
													'pos' => 173,
													'match' => '{$MACRO}',
													'length' => 8
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 183,
										'match' => '123',
										'length' => 3,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
													'pos' => 183,
													'match' => '123',
													'length' => 3,
													'data' => [
														'suffix' => null
													]
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 188,
										'match' => '"abc"',
										'length' => 5,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_STRING,
													'pos' => 188,
													'match' => '"abc"',
													'length' => 5
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 196,
										'match' => 'min(min(/host/key, 1d:now/d), 125) + 10',
										'length' => 39,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
													'pos' => 196,
													'match' => 'min(min(/host/key, 1d:now/d), 125)',
													'length' => 34,
													'data' => [
														'function' => 'min',
														'parameters' => [
															[
																'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
																'pos' => 200,
																'match' => 'min(/host/key, 1d:now/d)',
																'length' => 24,
																'data' => [
																	'tokens' => [
																		[
																			'type' => CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
																			'pos' => 200,
																			'match' => 'min(/host/key, 1d:now/d)',
																			'length' => 24,
																			'data' => [
																				'function' => 'min',
																				'parameters' => [
																					[
																						'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
																						'pos' => 204,
																						'match' => '/host/key',
																						'length' => 9,
																						'data' => [
																							'host' => 'host',
																							'item' => 'key',
																							'filter' => [
																								'match' => '',
																								'tokens' => []
																							]
																						]
																					],
																					[
																						'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
																						'pos' => 215,
																						'match' => '1d:now/d',
																						'length' => 8,
																						'data' => [
																							'sec_num' => '1d',
																							'time_shift' => 'now/d'
																						]
																					]
																				]
																			]
																		]
																	]
																]
															],
															[
																'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
																'pos' => 226,
																'match' => '125',
																'length' => 3,
																'data' => [
																	'tokens' => [
																		[
																			'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
																			'pos' => 226,
																			'match' => '125',
																			'length' => 3,
																			'data' => [
																				'suffix' => null
																			]
																		]
																	]
																]
															]
														]
													]
												],
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
													'pos' => 231,
													'match' => '+',
													'length' => 1
												],
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
													'pos' => 233,
													'match' => '10',
													'length' => 2,
													'data' => [
														'suffix' => null
													]
												]
											]
										]
									]
								]
							]
						]
					]
				],
				['usermacros' => true, 'lldmacros' => true]
			],
			[
				'{52735} > 0 or min({52736}, {52737}, 5M + {$MACRO})',
				[
					'match' => '{52735} > 0 or min({52736}, {52737}, 5M + {$MACRO})',
					'length' => 51,
					'tokens' => [
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO,
							'pos' => 0,
							'match' => '{52735}',
							'length' => 7
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 8,
							'match' => '>',
							'length' => 1
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
							'pos' => 10,
							'match' => '0',
							'length' => 1,
							'data' => [
								'suffix' => null
							]
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
							'pos' => 12,
							'match' => 'or',
							'length' => 2
						],
						[
							'type' => CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
							'pos' => 15,
							'match' => 'min({52736}, {52737}, 5M + {$MACRO})',
							'length' => 36,
							'data' => [
								'function' => 'min',
								'parameters' => [
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 19,
										'match' => '{52736}',
										'length' => 7,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO,
													'pos' => 19,
													'match' => '{52736}',
													'length' => 7
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 28,
										'match' => '{52737}',
										'length' => 7,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO,
													'pos' => 28,
													'match' => '{52737}',
													'length' => 7
												]
											]
										]
									],
									[
										'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
										'pos' => 37,
										'match' => '5M + {$MACRO}',
										'length' => 13,
										'data' => [
											'tokens' => [
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
													'pos' => 37,
													'match' => '5M',
													'length' => 2,
													'data' => [
														'suffix' => 'M'
													]
												],
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
													'pos' => 40,
													'match' => '+',
													'length' => 1
												],
												[
													'type' => CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
													'pos' => 42,
													'match' => '{$MACRO}',
													'length' => 8
												]
											]
										]
									]
								]
							]
						]
					]
				],
				['usermacros' => true, 'collapsed_expression' => true]
			]
		];
	}

	/**
	 * @dataProvider dataProviderTokens
	 */
	public function testTokens(string $expression, array $expected, array $options = []) {
		$expression_parser = new CExpressionParser($options);
		$this->assertSame(CParser::PARSE_SUCCESS, $expression_parser->parse($expression));
		$this->assertSame($expected, [
			'match' => $expression_parser->getMatch(),
			'length' => $expression_parser->getLength(),
			'tokens' => $expression_parser->getResult()->getTokens()
		]);
	}
}
