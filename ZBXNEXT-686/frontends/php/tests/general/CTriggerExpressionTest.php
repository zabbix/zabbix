<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/classes/parsers/CTriggerExpression.php';

class CTriggerExpressionTest extends PHPUnit_Framework_TestCase {
	public static function provider() {
		return array(
	// Correct trigger expressions
			array('', null, false),
			array('+', null, false),
			array('1', null, true),
			array('1+1', null, true),
			array('abc', null, false),
			array('{TRIGGER.VALUE}', null, true),
			array('{$USERMACRO}', null, true),
			array('{TRIGGER.VALUE}=1', null, true),
			array('{$USERMACRO}=1', null, true),
			array('{host}', null, false),
			array('{host:key}', null, false),
			array('{host:key.str}', null, false),

			array('{host:key.diff()} & {TRIGGER.VALUE}', null, true),
			array('{host:key.diff()}& {TRIGGER.VALUE}', null, true),
			array('{host:key.diff()}  &{TRIGGER.VALUE}', null, true),
			array('{host:key.diff()}&{TRIGGER.VALUE}', null, true),
			array('{host:key.diff()}&+{TRIGGER.VALUE}', null, false),
			array('{host:key.diff()}&-{TRIGGER.VALUE}', null, true),
			array('{host:key.diff()} & + {TRIGGER.VALUE}', null, false),
			array('{host:key.diff()} & - {TRIGGER.VALUE}', null, true),

			array('{host:key.diff()} & {$USERMACRO}', null, true),
			array('{host:key.diff()}& {$USERMACRO}', null, true),
			array('{host:key.diff()} &{$USERMACRO}', null, true),
			array('{host:key.diff()}&{$USERMACRO}', null, true),
			array('{host:key.diff()} & + {$USERMACRO}', null, false),
			array('{host:key.diff()} & - {$USERMACRO}', null, true),

			array('{host:key.diff()}=00', null, true),
			array('{host:key.diff()}=0 0', null, false),
			array('{host:key.diff()}=0 0={host:key.diff()}', null, false),
			array('{host:key.diff()} = 00 = {host:key.diff()}', null, true),

			array('{host:key.str(ГУГЛ)}=0', null, true),
			array('{host:key.str("ГУГЛ")}=0', null, true),
			array('{host:key[ГУГЛ].str(ГУГЛ)}=0', null, true),
			array('{host:key["ГУГЛ"].str("ГУГЛ")}=0', null, true),
			array('{host:key.str("こんにちは、世界")}', null, true),
			array('{host:key.str(こんにちは、世界)}', null, true),
			array('{host:key["こんにちは、世界"].str("こんにちは、世界")}', null, true),
			array('{host:key[こんにちは、世界].str(こんにちは、世界)}', null, true),

			array('{host:key[a,,"b",,[c,d,,"e",],,[f]].count(1,,"b",3)}', null, true),
			array('{host:key[a,,"b",,[[c,d,,"e"],[]],,[f]].count(1,,"b",3)}', null, true),
			array('{host:key[a,,"b",,[c,d,,"e",,,[f]].count(1,,"b",3)}', null, false),
			array('{host:key[a,,"b",,[c,d,,"e",],,f]].count(1,,"b",3)}', null, false),

			array('{abcdefghijklmnopqrstuvwxyz. _-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890:key.diff()}', null, true),
			array('{host:abcdefghijklmnopqrstuvwxyz._-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.diff()}', null, true),
			array('{host:,.diff()}', null, false),
			array('{host:;.diff()}', null, false),
			array('{host::.diff()}', null, false),
			array('{host:.diff()}', null, false),
			array('{:key.diff()}', null, false),
			array('{host:key.()}', null, false),

			array(' {host:key.diff()} ', null, true),
			array('({host:key.diff()})', null, true),
			array('{(host:key.diff()})', null, false),
			array('({host:key.diff())}', null, false),
			array('{(host:key.diff())}', null, false),
			array('{(host:key.diff()})=0', null, false),
			array('({host:key.diff())}=0', null, false),
			array('{(host:key.diff())}=0', null, false),
			array('({host:key.diff()}=)0', null, false),
			array('({host:key.diff()})0', null, false),
			array('0(={host:key.diff()})', null, false),
			array('0({host:key.diff()})', null, false),
			array('( {host:key.diff()} )', null, true),
			array(' ( {host:key.diff()} ) ', null, true),
			array('(( {host:key.diff()} ))', null, true),
			array(' ( ( {host:key.diff()} ) ) ', null, true),
			array('((( {host:key.diff()} )))', null, true),
			array(' ( ( ( {host:key.diff()} ) ) ) ', null, true),
			array('()0={host:key.diff()}', null, false),
			array('0()={host:key.diff()}', null, false),
			array('0=()={host:key.diff()}', null, false),
			array('0=(){host:key.diff()}', null, false),
			array('0={()host:key.diff()}', null, false),
			array('0={host:key.diff()()}', null, false),
			array('0={host:key.diff()}()', null, false),
			array('0={host:key.diff()}+()()()()5', null, false),
			array('0={host:key.diff()}+((((()))))5', null, false),
			array('(0)={host:key.diff()}', null, true),
			array('(0+)={host:key.diff()}', null, false),
			array('(0=){host:key.diff()}', null, false),
			array('({host:key.diff)()}', null, false),
			array('({host:key.)diff()}', null, false),
			array('({host:key).diff()}', null, false),
			array('(-5)={host:key.diff()}', null, true),
			array('(15 - 5.25 - 1)={host:key.diff()}', null, true),
			array('{host:key.diff()} = -5', null, true),

			array('(({host:key.diff()})=0)', null, true),
			array('( ( {host:key.diff()} ) = 0 )', null, true),
			array('(({host:key.diff()}) * 100) / 95', null, true),
			array('(({host:key.diff()}) * 5.25K) / 95.0', null, true),
			array('(({host:key.diff()}) * 1w) / 1d', null, true),
			array('(({host:key.diff()}) * 1w) / 1Ks', null, false),
			array('(({host:key.diff()}) * 1w) / (1d * ({host:key.diff()})', null, false),
			array('(({host:key.diff()}) * 1w) / (1d * host:key.diff()}))', null, false),
			array('(({host:key.diff()}) * 1w) / (1d * ({host:key.diff()}))', null, true),

			array('{host:key.last(1)} * (-1)', null, true),
			array('{host:key.last(1)} / (-1)', null, true),
			array('{host:key.last(1)} + (-1)', null, true),
			array('{host:key.last(1)} - (-1)', null, true),
			array('{host:key.last(1)} = (-1)', null, true),
			array('{host:key.last(1)} # (-1)', null, true),
			array('{host:key.last(1)} < (-1)', null, true),
			array('{host:key.last(1)} > (-1)', null, true),
			array('{host:key.last(1)} & (-1)', null, true),
			array('{host:key.last(1)} | (-1)', null, true),

			array('{host:key.last(1)}* (-1)', null, true),
			array('{host:key.last(1)}/ (-1)', null, true),
			array('{host:key.last(1)}+ (-1)', null, true),
			array('{host:key.last(1)}- (-1)', null, true),
			array('{host:key.last(1)}= (-1)', null, true),
			array('{host:key.last(1)}# (-1)', null, true),
			array('{host:key.last(1)}< (-1)', null, true),
			array('{host:key.last(1)}> (-1)', null, true),
			array('{host:key.last(1)}& (-1)', null, true),
			array('{host:key.last(1)}| (-1)', null, true),

			array('{host:key.last(1)} *(-1)', null, true),
			array('{host:key.last(1)} /(-1)', null, true),
			array('{host:key.last(1)} +(-1)', null, true),
			array('{host:key.last(1)} -(-1)', null, true),
			array('{host:key.last(1)} =(-1)', null, true),
			array('{host:key.last(1)} #(-1)', null, true),
			array('{host:key.last(1)} <(-1)', null, true),
			array('{host:key.last(1)} >(-1)', null, true),
			array('{host:key.last(1)} &(-1)', null, true),
			array('{host:key.last(1)} |(-1)', null, true),

			array('{host:key.last(1)}*(-1)', null, true),
			array('{host:key.last(1)}/(-1)', null, true),
			array('{host:key.last(1)}+(-1)', null, true),
			array('{host:key.last(1)}-(-1)', null, true),
			array('{host:key.last(1)}=(-1)', null, true),
			array('{host:key.last(1)}#(-1)', null, true),
			array('{host:key.last(1)}<(-1)', null, true),
			array('{host:key.last(1)}>(-1)', null, true),
			array('{host:key.last(1)}&(-1)', null, true),
			array('{host:key.last(1)}|(-1)', null, true),

			array('{host:key.last(1)} * -1', null, true),
			array('{host:key.last(1)} / -1', null, true),
			array('{host:key.last(1)} + -1', null, true),
			array('{host:key.last(1)} - -1', null, true),
			array('{host:key.last(1)} = -1', null, true),
			array('{host:key.last(1)} # -1', null, true),
			array('{host:key.last(1)} < -1', null, true),
			array('{host:key.last(1)} > -1', null, true),
			array('{host:key.last(1)} & -1', null, true),
			array('{host:key.last(1)} | -1', null, true),

			array('{host:key.last(1)}* -1', null, true),
			array('{host:key.last(1)}/ -1', null, true),
			array('{host:key.last(1)}+ -1', null, true),
			array('{host:key.last(1)}- -1', null, true),
			array('{host:key.last(1)}= -1', null, true),
			array('{host:key.last(1)}# -1', null, true),
			array('{host:key.last(1)}< -1', null, true),
			array('{host:key.last(1)}> -1', null, true),
			array('{host:key.last(1)}& -1', null, true),
			array('{host:key.last(1)}| -1', null, true),

			array('{host:key.last(1)} *-1', null, true),
			array('{host:key.last(1)} /-1', null, true),
			array('{host:key.last(1)} +-1', null, true),
			array('{host:key.last(1)} --1', null, true),
			array('{host:key.last(1)} =-1', null, true),
			array('{host:key.last(1)} #-1', null, true),
			array('{host:key.last(1)} <-1', null, true),
			array('{host:key.last(1)} >-1', null, true),
			array('{host:key.last(1)} &-1', null, true),
			array('{host:key.last(1)} |-1', null, true),

			array('{host:key.last(1)}*-1', null, true),
			array('{host:key.last(1)}/-1', null, true),
			array('{host:key.last(1)}+-1', null, true),
			array('{host:key.last(1)}--1', null, true),
			array('{host:key.last(1)}=-1', null, true),
			array('{host:key.last(1)}#-1', null, true),
			array('{host:key.last(1)}<-1', null, true),
			array('{host:key.last(1)}>-1', null, true),
			array('{host:key.last(1)}&-1', null, true),
			array('{host:key.last(1)}|-1', null, true),

			array('{host:key.last(1)} * (- 1)', null, true),
			array('{host:key.last(1)} / (- 1)', null, true),
			array('{host:key.last(1)} + (- 1)', null, true),
			array('{host:key.last(1)} - (- 1)', null, true),
			array('{host:key.last(1)} = (- 1)', null, true),
			array('{host:key.last(1)} # (- 1)', null, true),
			array('{host:key.last(1)} < (- 1)', null, true),
			array('{host:key.last(1)} > (- 1)', null, true),
			array('{host:key.last(1)} & (- 1)', null, true),
			array('{host:key.last(1)} | (- 1)', null, true),

			array('{host:key.last(1)}* (- 1)', null, true),
			array('{host:key.last(1)}/ (- 1)', null, true),
			array('{host:key.last(1)}+ (- 1)', null, true),
			array('{host:key.last(1)}- (- 1)', null, true),
			array('{host:key.last(1)}= (- 1)', null, true),
			array('{host:key.last(1)}# (- 1)', null, true),
			array('{host:key.last(1)}< (- 1)', null, true),
			array('{host:key.last(1)}> (- 1)', null, true),
			array('{host:key.last(1)}& (- 1)', null, true),
			array('{host:key.last(1)}| (- 1)', null, true),

			array('{host:key.last(1)} *(- 1)', null, true),
			array('{host:key.last(1)} /(- 1)', null, true),
			array('{host:key.last(1)} +(- 1)', null, true),
			array('{host:key.last(1)} -(- 1)', null, true),
			array('{host:key.last(1)} =(- 1)', null, true),
			array('{host:key.last(1)} #(- 1)', null, true),
			array('{host:key.last(1)} <(- 1)', null, true),
			array('{host:key.last(1)} >(- 1)', null, true),
			array('{host:key.last(1)} &(- 1)', null, true),
			array('{host:key.last(1)} |(- 1)', null, true),

			array('{host:key.last(1)}*(- 1)', null, true),
			array('{host:key.last(1)}/(- 1)', null, true),
			array('{host:key.last(1)}+(- 1)', null, true),
			array('{host:key.last(1)}-(- 1)', null, true),
			array('{host:key.last(1)}=(- 1)', null, true),
			array('{host:key.last(1)}#(- 1)', null, true),
			array('{host:key.last(1)}<(- 1)', null, true),
			array('{host:key.last(1)}>(- 1)', null, true),
			array('{host:key.last(1)}&(- 1)', null, true),
			array('{host:key.last(1)}|(- 1)', null, true),

			array('{host:key.last(1)} * - 1', null, true),
			array('{host:key.last(1)} / - 1', null, true),
			array('{host:key.last(1)} + - 1', null, true),
			array('{host:key.last(1)} - - 1', null, true),
			array('{host:key.last(1)} = - 1', null, true),
			array('{host:key.last(1)} # - 1', null, true),
			array('{host:key.last(1)} < - 1', null, true),
			array('{host:key.last(1)} > - 1', null, true),
			array('{host:key.last(1)} & - 1', null, true),
			array('{host:key.last(1)} | - 1', null, true),

			array('{host:key.last(1)}* - 1', null, true),
			array('{host:key.last(1)}/ - 1', null, true),
			array('{host:key.last(1)}+ - 1', null, true),
			array('{host:key.last(1)}- - 1', null, true),
			array('{host:key.last(1)}= - 1', null, true),
			array('{host:key.last(1)}# - 1', null, true),
			array('{host:key.last(1)}< - 1', null, true),
			array('{host:key.last(1)}> - 1', null, true),
			array('{host:key.last(1)}& - 1', null, true),
			array('{host:key.last(1)}| - 1', null, true),

			array('{host:key.last(1)} *- 1', null, true),
			array('{host:key.last(1)} /- 1', null, true),
			array('{host:key.last(1)} +- 1', null, true),
			array('{host:key.last(1)} -- 1', null, true),
			array('{host:key.last(1)} =- 1', null, true),
			array('{host:key.last(1)} #- 1', null, true),
			array('{host:key.last(1)} <- 1', null, true),
			array('{host:key.last(1)} >- 1', null, true),
			array('{host:key.last(1)} &- 1', null, true),
			array('{host:key.last(1)} |- 1', null, true),

			array('{host:key.last(1)}*- 1', null, true),
			array('{host:key.last(1)}/- 1', null, true),
			array('{host:key.last(1)}+- 1', null, true),
			array('{host:key.last(1)}-- 1', null, true),
			array('{host:key.last(1)}=- 1', null, true),
			array('{host:key.last(1)}#- 1', null, true),
			array('{host:key.last(1)}<- 1', null, true),
			array('{host:key.last(1)}>- 1', null, true),
			array('{host:key.last(1)}&- 1', null, true),
			array('{host:key.last(1)}|- 1', null, true),

			array('{host:key.last(1)} * (+1)', null, false),
			array('{host:key.last(1)} / (+1)', null, false),
			array('{host:key.last(1)} + (+1)', null, false),
			array('{host:key.last(1)} - (+1)', null, false),
			array('{host:key.last(1)} = (+1)', null, false),
			array('{host:key.last(1)} # (+1)', null, false),
			array('{host:key.last(1)} < (+1)', null, false),
			array('{host:key.last(1)} > (+1)', null, false),
			array('{host:key.last(1)} & (+1)', null, false),
			array('{host:key.last(1)} | (+1)', null, false),

			array('{host:key.last(1)}* (+1)', null, false),
			array('{host:key.last(1)}/ (+1)', null, false),
			array('{host:key.last(1)}+ (+1)', null, false),
			array('{host:key.last(1)}- (+1)', null, false),
			array('{host:key.last(1)}= (+1)', null, false),
			array('{host:key.last(1)}# (+1)', null, false),
			array('{host:key.last(1)}< (+1)', null, false),
			array('{host:key.last(1)}> (+1)', null, false),
			array('{host:key.last(1)}& (+1)', null, false),
			array('{host:key.last(1)}| (+1)', null, false),

			array('{host:key.last(1)} *(+1)', null, false),
			array('{host:key.last(1)} /(+1)', null, false),
			array('{host:key.last(1)} +(+1)', null, false),
			array('{host:key.last(1)} -(+1)', null, false),
			array('{host:key.last(1)} =(+1)', null, false),
			array('{host:key.last(1)} #(+1)', null, false),
			array('{host:key.last(1)} <(+1)', null, false),
			array('{host:key.last(1)} >(+1)', null, false),
			array('{host:key.last(1)} &(+1)', null, false),
			array('{host:key.last(1)} |(+1)', null, false),

			array('{host:key.last(1)}*(+1)', null, false),
			array('{host:key.last(1)}/(+1)', null, false),
			array('{host:key.last(1)}+(+1)', null, false),
			array('{host:key.last(1)}-(+1)', null, false),
			array('{host:key.last(1)}=(+1)', null, false),
			array('{host:key.last(1)}#(+1)', null, false),
			array('{host:key.last(1)}<(+1)', null, false),
			array('{host:key.last(1)}>(+1)', null, false),
			array('{host:key.last(1)}&(+1)', null, false),
			array('{host:key.last(1)}|(+1)', null, false),

			array('{host:key.last(1)} * +1', null, false),
			array('{host:key.last(1)} / +1', null, false),
			array('{host:key.last(1)} + +1', null, false),
			array('{host:key.last(1)} - +1', null, false),
			array('{host:key.last(1)} = +1', null, false),
			array('{host:key.last(1)} # +1', null, false),
			array('{host:key.last(1)} < +1', null, false),
			array('{host:key.last(1)} > +1', null, false),
			array('{host:key.last(1)} & +1', null, false),
			array('{host:key.last(1)} | +1', null, false),

			array('{host:key.last(1)}* +1', null, false),
			array('{host:key.last(1)}/ +1', null, false),
			array('{host:key.last(1)}+ +1', null, false),
			array('{host:key.last(1)}- +1', null, false),
			array('{host:key.last(1)}= +1', null, false),
			array('{host:key.last(1)}# +1', null, false),
			array('{host:key.last(1)}< +1', null, false),
			array('{host:key.last(1)}> +1', null, false),
			array('{host:key.last(1)}& +1', null, false),
			array('{host:key.last(1)}| +1', null, false),

			array('{host:key.last(1)} *+1', null, false),
			array('{host:key.last(1)} /+1', null, false),
			array('{host:key.last(1)} ++1', null, false),
			array('{host:key.last(1)} -+1', null, false),
			array('{host:key.last(1)} =+1', null, false),
			array('{host:key.last(1)} #+1', null, false),
			array('{host:key.last(1)} <+1', null, false),
			array('{host:key.last(1)} >+1', null, false),
			array('{host:key.last(1)} &+1', null, false),
			array('{host:key.last(1)} |+1', null, false),

			array('{host:key.last(1)}*+1', null, false),
			array('{host:key.last(1)}/+1', null, false),
			array('{host:key.last(1)}++1', null, false),
			array('{host:key.last(1)}-+1', null, false),
			array('{host:key.last(1)}=+1', null, false),
			array('{host:key.last(1)}#+1', null, false),
			array('{host:key.last(1)}<+1', null, false),
			array('{host:key.last(1)}>+1', null, false),
			array('{host:key.last(1)}&+1', null, false),
			array('{host:key.last(1)}|+1', null, false),

			array('{host:key.last(1)} * (+ 1)', null, false),
			array('{host:key.last(1)} / (+ 1)', null, false),
			array('{host:key.last(1)} + (+ 1)', null, false),
			array('{host:key.last(1)} - (+ 1)', null, false),
			array('{host:key.last(1)} = (+ 1)', null, false),
			array('{host:key.last(1)} # (+ 1)', null, false),
			array('{host:key.last(1)} < (+ 1)', null, false),
			array('{host:key.last(1)} > (+ 1)', null, false),
			array('{host:key.last(1)} & (+ 1)', null, false),
			array('{host:key.last(1)} | (+ 1)', null, false),

			array('{host:key.last(1)}* (+ 1)', null, false),
			array('{host:key.last(1)}/ (+ 1)', null, false),
			array('{host:key.last(1)}+ (+ 1)', null, false),
			array('{host:key.last(1)}- (+ 1)', null, false),
			array('{host:key.last(1)}= (+ 1)', null, false),
			array('{host:key.last(1)}# (+ 1)', null, false),
			array('{host:key.last(1)}< (+ 1)', null, false),
			array('{host:key.last(1)}> (+ 1)', null, false),
			array('{host:key.last(1)}& (+ 1)', null, false),
			array('{host:key.last(1)}| (+ 1)', null, false),

			array('{host:key.last(1)} *(+ 1)', null, false),
			array('{host:key.last(1)} /(+ 1)', null, false),
			array('{host:key.last(1)} +(+ 1)', null, false),
			array('{host:key.last(1)} -(+ 1)', null, false),
			array('{host:key.last(1)} =(+ 1)', null, false),
			array('{host:key.last(1)} #(+ 1)', null, false),
			array('{host:key.last(1)} <(+ 1)', null, false),
			array('{host:key.last(1)} >(+ 1)', null, false),
			array('{host:key.last(1)} &(+ 1)', null, false),
			array('{host:key.last(1)} |(+ 1)', null, false),

			array('{host:key.last(1)}*(+ 1)', null, false),
			array('{host:key.last(1)}/(+ 1)', null, false),
			array('{host:key.last(1)}+(+ 1)', null, false),
			array('{host:key.last(1)}-(+ 1)', null, false),
			array('{host:key.last(1)}=(+ 1)', null, false),
			array('{host:key.last(1)}#(+ 1)', null, false),
			array('{host:key.last(1)}<(+ 1)', null, false),
			array('{host:key.last(1)}>(+ 1)', null, false),
			array('{host:key.last(1)}&(+ 1)', null, false),
			array('{host:key.last(1)}|(+ 1)', null, false),

			array('{host:key.last(1)} * + 1', null, false),
			array('{host:key.last(1)} / + 1', null, false),
			array('{host:key.last(1)} + + 1', null, false),
			array('{host:key.last(1)} - + 1', null, false),
			array('{host:key.last(1)} = + 1', null, false),
			array('{host:key.last(1)} # + 1', null, false),
			array('{host:key.last(1)} < + 1', null, false),
			array('{host:key.last(1)} > + 1', null, false),
			array('{host:key.last(1)} & + 1', null, false),
			array('{host:key.last(1)} | + 1', null, false),

			array('{host:key.last(1)}* + 1', null, false),
			array('{host:key.last(1)}/ + 1', null, false),
			array('{host:key.last(1)}+ + 1', null, false),
			array('{host:key.last(1)}- + 1', null, false),
			array('{host:key.last(1)}= + 1', null, false),
			array('{host:key.last(1)}# + 1', null, false),
			array('{host:key.last(1)}< + 1', null, false),
			array('{host:key.last(1)}> + 1', null, false),
			array('{host:key.last(1)}& + 1', null, false),
			array('{host:key.last(1)}| + 1', null, false),

			array('{host:key.last(1)} *+ 1', null, false),
			array('{host:key.last(1)} /+ 1', null, false),
			array('{host:key.last(1)} ++ 1', null, false),
			array('{host:key.last(1)} -+ 1', null, false),
			array('{host:key.last(1)} =+ 1', null, false),
			array('{host:key.last(1)} #+ 1', null, false),
			array('{host:key.last(1)} <+ 1', null, false),
			array('{host:key.last(1)} >+ 1', null, false),
			array('{host:key.last(1)} &+ 1', null, false),
			array('{host:key.last(1)} |+ 1', null, false),

			array('{host:key.last(1)}*+ 1', null, false),
			array('{host:key.last(1)}/+ 1', null, false),
			array('{host:key.last(1)}++ 1', null, false),
			array('{host:key.last(1)}-+ 1', null, false),
			array('{host:key.last(1)}=+ 1', null, false),
			array('{host:key.last(1)}#+ 1', null, false),
			array('{host:key.last(1)}<+ 1', null, false),
			array('{host:key.last(1)}>+ 1', null, false),
			array('{host:key.last(1)}&+ 1', null, false),
			array('{host:key.last(1)}|+ 1', null, false),

			array('{host:key.diff()} + 1', null, true),
			array('{host:key.diff()} - 1', null, true),
			array('{host:key.diff()} / 1', null, true),
			array('{host:key.diff()} * 1', null, true),
			array('{host:key.diff()} = 1', null, true),
			array('{host:key.diff()} # 1', null, true),
			array('{host:key.diff()} & 1', null, true),
			array('{host:key.diff()} | 1', null, true),

			array('{host:key.diff()}+ 1', null, true),
			array('{host:key.diff()}- 1', null, true),
			array('{host:key.diff()}/ 1', null, true),
			array('{host:key.diff()}* 1', null, true),
			array('{host:key.diff()}= 1', null, true),
			array('{host:key.diff()}# 1', null, true),
			array('{host:key.diff()}& 1', null, true),
			array('{host:key.diff()}| 1', null, true),

			array('{host:key.diff()} +1', null, true),
			array('{host:key.diff()} -1', null, true),
			array('{host:key.diff()} /1', null, true),
			array('{host:key.diff()} *1', null, true),
			array('{host:key.diff()} =1', null, true),
			array('{host:key.diff()} #1', null, true),
			array('{host:key.diff()} &1', null, true),
			array('{host:key.diff()} |1', null, true),

			array('{host:key.diff()}+1', null, true),
			array('{host:key.diff()}-1', null, true),
			array('{host:key.diff()}/1', null, true),
			array('{host:key.diff()}*1', null, true),
			array('{host:key.diff()}=1', null, true),
			array('{host:key.diff()}#1', null, true),
			array('{host:key.diff()}&1', null, true),
			array('{host:key.diff()}|1', null, true),

			array('{host:key.diff()}=1K', null, true),
			array('{host:key.diff()}=1M', null, true),
			array('{host:key.diff()}=1G', null, true),
			array('{host:key.diff()}=1T', null, true),
			array('{host:key.diff()}=1s', null, true),
			array('{host:key.diff()}=1m', null, true),
			array('{host:key.diff()}=1h', null, true),
			array('{host:key.diff()}=1d', null, true),
			array('{host:key.diff()}=1w', null, true),

			array('{host:key.diff()}=1.56K', null, true),
			array('{host:key.diff()}=1.56M', null, true),
			array('{host:key.diff()}=1.56G', null, true),
			array('{host:key.diff()}=1.56T', null, true),
			array('{host:key.diff()}=1.56s', null, true),
			array('{host:key.diff()}=1.56m', null, true),
			array('{host:key.diff()}=1.56h', null, true),
			array('{host:key.diff()}=1.56d', null, true),
			array('{host:key.diff()}=1.56w', null, true),

			array('{host:key.diff()} + 1.173640', null, true),
			array('{host:key.diff()} - 1.173640', null, true),
			array('{host:key.diff()} / 1.173640', null, true),
			array('{host:key.diff()} * 1.173640', null, true),
			array('{host:key.diff()} = 1.173640', null, true),
			array('{host:key.diff()} # 1.173640', null, true),
			array('{host:key.diff()} & 1.173640', null, true),
			array('{host:key.diff()} | 1.173640', null, true),

			array('{host:key.diff()}+ 1.173640', null, true),
			array('{host:key.diff()}- 1.173640', null, true),
			array('{host:key.diff()}/ 1.173640', null, true),
			array('{host:key.diff()}* 1.173640', null, true),
			array('{host:key.diff()}= 1.173640', null, true),
			array('{host:key.diff()}# 1.173640', null, true),
			array('{host:key.diff()}& 1.173640', null, true),
			array('{host:key.diff()}| 1.173640', null, true),

			array('{host:key.diff()} +1.173640', null, true),
			array('{host:key.diff()} -1.173640', null, true),
			array('{host:key.diff()} /1.173640', null, true),
			array('{host:key.diff()} *1.173640', null, true),
			array('{host:key.diff()} =1.173640', null, true),
			array('{host:key.diff()} #1.173640', null, true),
			array('{host:key.diff()} &1.173640', null, true),
			array('{host:key.diff()} |1.173640', null, true),

			array('{host:key.diff()}+1.173640', null, true),
			array('{host:key.diff()}-1.173640', null, true),
			array('{host:key.diff()}/1.173640', null, true),
			array('{host:key.diff()}*1.173640', null, true),
			array('{host:key.diff()}=1.173640', null, true),
			array('{host:key.diff()}#1.173640', null, true),
			array('{host:key.diff()}&1.173640', null, true),
			array('{host:key.diff()}|1.173640', null, true),

			array('{host:key.diff()} + 1 | {host:key.diff()}', null, true),
			array('{host:key.diff()} - 1 & {host:key.diff()}', null, true),
			array('{host:key.diff()} / 1 # {host:key.diff()}', null, true),
			array('{host:key.diff()} * 1 = {host:key.diff()}', null, true),
			array('{host:key.diff()} = 1 * {host:key.diff()}', null, true),
			array('{host:key.diff()} # 1 / {host:key.diff()}', null, true),
			array('{host:key.diff()} & 1 - {host:key.diff()}', null, true),
			array('{host:key.diff()} | 1 + {host:key.diff()}', null, true),

			array('{host:key.diff()} -- 1', null, true),
			array('{host:key.diff()} ++ 1', null, false),
			array('{host:key.diff()} // 1', null, false),
			array('{host:key.diff()} ** 1', null, false),
			array('{host:key.diff()} == 1', null, false),
			array('{host:key.diff()} ## 1', null, false),
			array('{host:key.diff()} && 1', null, false),
			array('{host:key.diff()} || 1', null, false),

			array('{host:key.diff()} +', null, false),
			array('{host:key.diff()} -', null, false),
			array('{host:key.diff()} /', null, false),
			array('{host:key.diff()} *', null, false),
			array('{host:key.diff()} =', null, false),
			array('{host:key.diff()} #', null, false),
			array('{host:key.diff()} &', null, false),
			array('{host:key.diff()} |', null, false),

			array('- {host:key.diff()}', null, true),
			array('+ {host:key.diff()}', null, false),
			array('/ {host:key.diff()}', null, false),
			array('* {host:key.diff()}', null, false),
			array('= {host:key.diff()}', null, false),
			array('# {host:key.diff()}', null, false),
			array('& {host:key.diff()}', null, false),
			array('| {host:key.diff()}', null, false),

			array('{host:key.diff()}=0', null, true),
			array('{host:key.count(1,)}=0', null, true),
			array('{host:key.count( 1,)}=0', null, true),
			array('{host:key.count(  1,)}=0', null, true),
			array('{host:key.count(1, )}=0', null, true),
			array('{host:key.count(1,  )}=0', null, true),

			array('{host:key.str(")}=0', null, false),
			array('{host:key.str("")}=0', null, true),
			array('{host:key.str(""")}=0', null, false),
			array('{host:key.str("""")}=0', null, false),

			array('{host:key.str( ")}=0', null, false),
			array('{host:key.str( "")}=0', null, true),
			array('{host:key.str( """)}=0', null, false),
			array('{host:key.str( """")}=0', null, false),

			array('{host:key.str(  ")}=0', null, false),
			array('{host:key.str(  "")}=0', null, true),
			array('{host:key.str(  """)}=0', null, false),
			array('{host:key.str(  """")}=0', null, false),

			array('{host:key.count(1,")}=0', null, false),
			array('{host:key.count(1,"")}=0', null, true),
			array('{host:key.count(1,""")}=0', null, false),
			array('{host:key.count(1,"""")}=0', null, false),

			array('{host:key.count(1, ")}=0', null, false),
			array('{host:key.count(1, "")}=0', null, true),
			array('{host:key.count(1, """)}=0', null, false),
			array('{host:key.count(1, """")}=0', null, false),

			array('{host:key.count(1,  ")}=0', null, false),
			array('{host:key.count(1,  "")}=0', null, true),
			array('{host:key.count(1,  """)}=0', null, false),
			array('{host:key.count(1,  """")}=0', null, false),

			array('{host:key.count(1,"",")}=0', null, false),
			array('{host:key.count(1,"","")}=0', null, true),
			array('{host:key.count(1,"",""")}=0', null, false),
			array('{host:key.count(1,"","""")}=0', null, false),

			array('{host:key.count(1,"", ")}=0', null, false),
			array('{host:key.count(1,"", "")}=0', null, true),
			array('{host:key.count(1,"", """)}=0', null, false),
			array('{host:key.count(1,"", """")}=0', null, false),

			array('{host:key.count(1,"",  ")}=0', null, false),
			array('{host:key.count(1,"",  "")}=0', null, true),
			array('{host:key.count(1,"",  """)}=0', null, false),
			array('{host:key.count(1,"",  """")}=0', null, false),

			array('{host:key.str("\")}=0', null, false),
			array('{host:key.str("\"")}=0', null, true),
			array('{host:key.str("\\\\"")}=0', null, true),
			array('{host:key.str("\""")}=0', null, false),
			array('{host:key.str("\"""")}=0', null, false),

			array('{host:key.str(\")}=0', null, true),
			array('{host:key.str(param\")}=0', null, true),
			array('{host:key.str(param")}=0', null, true),

			array('{host:key.str( \")}=0', null, true),
			array('{host:key.str( param\")}=0', null, true),
			array('{host:key.str( param")}=0', null, true),

			array('{host:key.str(  \")}=0', null, true),
			array('{host:key.str(  param\")}=0', null, true),
			array('{host:key.str(  param")}=0', null, true),

			array('{host:key.str(()}=0', null, true),
			array('{host:key.str(param()}=0', null, true),

			array('{host:key.str( ()}=0', null, true),
			array('{host:key.str( param()}=0', null, true),

			array('{host:key.str(  ()}=0', null, true),
			array('{host:key.str(  param()}=0', null, true),

			array('{host:key.str())}=0', null, false),
			array('{host:key.str(param))}=0', null, false),

			array('{host:key.str( ))}=0', null, false),
			array('{host:key.str( param))}=0', null, false),

			array('{host:key.str(  ))}=0', null, false),
			array('{host:key.str(  param))}=0', null, false),

			array('{host:key.str("(")}=0', null, true),
			array('{host:key.str("param(")}=0', null, true),

			array('{host:key.str(")")}=0', null, true),
			array('{host:key.str("param)")}=0', null, true),

			array('{host:key.str()}=0', null, true),
			array('{host:key.str( )}=0', null, true),
			array('{host:key.str(" ")}=0', null, true),
			array('{host:key.str(abc)}=0', null, true),
			array('{host:key.str(\'abc\')}=0', null, true),
			array('{host:key.str("")}=0', null, true),
			array('{host:key.last(0)}=0', null, true),
			array('{host:key.str(aaa()}=0', null, true),

			array('{host:key.last(0)}=0', null, true),
			array('{host:key.str(aaa()}=0', null, true),
			array('({hostA:keyA.str("abc")}=0) | ({hostB:keyB.last(123)}=0)', null, true),
			array('{host:key[asd[].str(aaa()}=0', null, true),
			array('{host:key["param].diff()"].diff()}=0', null, true),
			array('{host:key[param].diff()].diff()}', null, false),
			array('{host:key[asd[,asd[,[]].str(aaa()}=0', null, true),
			array('{host:key[[],[],[]].str()}=0', null, true),
			array('{host:key[].count(1,[],[])}=0', null, true),
			array('({hostA:keyA.str("abc")}) / ({hostB:keyB.last(123)})=(0)', null, true),
			array('({hostA:keyA.str("abc")}=0) | ({hostB:keyB.last(123)}=0)', null, true),
			array('({hostA:keyA.str("abc")}=0) & ({hostB:keyB.last(123)}=0)', null, true),
	// Incorrect trigger expressions
			array('{hostkey.last(0)}=0', null, false),
			array('{host:keylast(0)}=0', null, false),
			array('{host:key.last(0}=0', null, false),
			array('{host:key.last(0)}=', null, false),
			array('{host:key.str()=0', null, false),
			array('{host:key.last()}#0}', null, false),
			array('{host:key.str()}<>0', null, false),
			array('({host:key.str(aaa()}=0', null, false),
			array('(({host:key.str(aaa()}=0)', null, false),
			array('{host:key.str(aaa()}=0)', null, false),
			array('({hostA:keyA.str("abc")}=0) || ({hostB:keyB.last(123)}=0)', null, false),
			array('{host:key.last()}<>0', null, false),
			array('{hostA:keyA.str("abc")} / ({hostB:keyB.last(123)}=0', null, false),
			array('({hostA:keyA.str("abc")} / ({hostB:keyB.last(123)})=0', null, false),
	// by Aleksandrs
			array('{constant}', null, false),
			array('{cons tant}', null, false),
			array('{expression}', null, false),
			array('{expre ssion}', null, false),
			array('host:key.str()', null, false),
			array('{host:key.str()', null, false),
			array('host:key.str()}', null, false),
			array(' {host:key.str()}', null, true),
			array('{host:key.str()} ', null, true),
			array('{ host:key.str()}', null, true),
			array('{host :key.str()}', null, true),
			array('{host:key.str(-5)}', null, true),
			array('{host:key.str(+5)}', null, true),
			array('{host:key.str([-5)}', null, true),
			array('{host:key.str(-5])}', null, true),
			array('{host:key.str((-5)}', null, true),
			array('{host:key.str(-5))}', null, false),
			array('{host:key.str(-5)*1}', null, false),
			array('0={host:key["a"b].str()}', null, false),
			array('0={host:key[].str(a"b"c)}', null, true),
			array('0={host:key[].str("a\"b\"c")}', null, true),
			array('0={host:key[].str("a\\\\"b\\\\"c")}', null, true),
			array('0={host:key[].str("a"b)}', null, false),
			array('0={host:key[].str(,"a"b,)}', null, false),
			array('0={host:key[].str("","a"b,"")}', null, false),
			array('0={host:key.str)', null, false),
			array('1z={host:key.str()', null, false),
			array('1z={host:key.str()}', null, false),
			array('0={host:key[].str(")}', null, false),
			array('0={host:key[].str( ")}', null, false),
			array('0={host:key[].str(")}")}', null, true),
			array('0={host:key[].str( ")}")}', null, true),
			// updated by Egita (January 02, 2013)
			array(
					'({host1:key1.last(0)}/{host2:key2.last(5)})/10+2*{TRIGGER.VALUE}&{$USERMACRO1}+(-{$USERMACRO2})+'.
						'-{$USERMACRO3}*-12K+12.5m',
					array(
					'error' => '',
					'expressions' => array(
						0 => array(
							'expression' => '{host1:key1.last(0)}',
							'pos'=> 1,
							'host' => 'host1',
							'item' => 'key1',
							'function' => 'last(0)',
							'functionName' => 'last',
							'functionParam' => '0',
							'functionParamList' => array('0')
						),
						1 => array(
							'expression' => '{host2:key2.last(5)}',
							'pos'=> 22,
							'host' => 'host2',
							'item' => 'key2',
							'function' => 'last(5)',
							'functionName' => 'last',
							'functionParam' => '5',
							'functionParamList' => array('5')
						)
					),
					'macros' => array(
						0 => array(
							'expression' => '{TRIGGER.VALUE}'
						)
					),
					'usermacros' => array(
						0 => array(
							'expression' => '{$USERMACRO1}'
						),
						1 => array(
							'expression' => '{$USERMACRO2}'
						),
						2 => array(
							'expression' => '{$USERMACRO3}'
						)
					)
				),
					true
			),
			array('-12+{TRIGGER.VALUE}/(({host1:key1[].last(5)}+(5*(1-(-3*5&((7|9))#1)*{host2:key2[""].last(123)})/10'.
						'/10/10)+{$USERMACRO1})-{$USERMACRO2})', null, true),
			array('{host:key["{$USERMACRO1}",{$USERMACRO2}].str()}', null, true),
			array('{host:key[].str("{$USERMACRO1}",{$USERMACRO2})}', null, true),
			array('{host:key["{HOSTNAME1}",{HOSTNAME2}].str()}', null, true),
			// updated by Egita (January 02, 2013)
			array(
					'{host:key[].str("{HOSTNAME1}",{HOSTNAME2})}',
					array(
					'error' => '',
					'expressions' => array(
						array(
							'expression' => '{host:key[].str("{HOSTNAME1}",{HOSTNAME2})}',
							'pos' => 0,
							'host' => 'host',
							'item' => 'key[]',
							'function' => 'str("{HOSTNAME1}",{HOSTNAME2})',
							'functionName' => 'str',
							'functionParam' => '"{HOSTNAME1}",{HOSTNAME2}',
							'functionParamList' => array('{HOSTNAME1}', '{HOSTNAME2}')
								)
							),
					'macros' => array(),
					'usermacros' => array()
				),
					true),

			array('{host:key[].count(1,"{HOSTNAME1}",{HOSTNAME2})}', null, true),
			array('{host:key[].str()}=-1=--2=---3=----4=-----5', null, false),
			array('{host:key[].str()}=-1=--2=---3=----4=-----5', null, false),
	// by Aleksandrs (January 6, 2011)
			array('{host:key[].str()}=-1', null, true),
			array('{host:key[].str()}+-1', null, true),
			array('{host:key[].str()}--1', null, true),
			array('{host:key[].str()}-(-1)', null, true),
			array('{host:key[].str()}-(-(-(-1)))', null, true),
			array('{host:key[{$$$},"{$$$}"].str()}', null, true),
			array('{host:key[{!!!},"{!!!}"].str()}', null, true),
			array('{host:key[{$USERMACRO1,"{$USERMACRO"].str()}', null, true),
			array('{host:key[].count(1,{$USERMACRO1,"{$USERMACRO")}', null, true),
			array('{host:key[{$USERMACRO1}abc,"{$USERMACRO2}"].str()}', null, true),
			array('{host:key[{$USERMACRO1},"{$USERMACRO2}abc"].str()}', null, true),
			array('{host:key[].count(1,{$USERMACRO1}abc,"{$USERMACRO2}")}', null, true),
			array('{host:key[].count(1,{$USERMACRO1},"{$USERMACRO2}abc")}', null, true),
			array('{host:key[abc{HOSTNAME1},"{HOSTNAME2}"].str()}', null, true),
			array('{host:key[{HOSTNAME1},"abc{HOSTNAME2}"].str()}', null, true),
			array('{host:key[].count(1,abc{HOSTNAME1},"{HOSTNAME2}")}', null, true),
			array('{host:key[].count(1,{HOSTNAME1},"abc{HOSTNAME2}")}', null, true),
			array('{host:key[{host:key.last(0)},"{host:key.last(0)}"].str()}', null, true),
			array('{host:key[].count(1,{host:key.last(0)},"{host:key.last(0)}")}', null, false),
			array('{host:key[].count(1,{host:key.last(0},"{host:key.last(0)}")}', null, true),
			array('{host:key[].count(1,{host:key.last(0)}+{host:key.last(0)}', null, true),
			array('{host:key[].count(1,{host:key.last(0)}', null, true),
			array('{host:key[{host:key[].last(0)},"{host:key[].last(0)}"].str()}', null, false),
			array('{host:key["{host:key[].last(0)}",{host:key[].last(0)}].str()}', null, false),
			array('{host:key["{host:key[].last(0)}",{host:key[].last(0)}', null, true),
			array('{host:key[{host:key[].last(0)}+{host:key[].last(0)}', null, true),
			array('{host:key[{host:key[].last(0)}', null, true),
	// by Aleksandrs (January 14, 2011)
			array('{host:key.last({$UPPERCASE})}', null, true),
			array('{host:key.last(0)}+{$UPPERCASE}', null, true),
			array('{host:key.last({$lowercase})}', null, true),
			array('{host:key.last(0)}+{$lowercase}', null, false),
	// by Aleksandrs (January 14, 2011)
			array('{host:key.last(1.23)}', null, true),
			array('{host:key.last(1.23s)}', null, true),
			array('{host:key.last(#1.23)}', null, true),
	// by Aleksandrs (January 14 and January 19, 2011)
			array('{host:key.abschange()}', null, true),
			array('{host:key.abschange(0)}', null, true),
			array('{host:key.abschange(0,)}', null, true),
			array('{host:key.avg()}', null, true),
			array('{host:key.avg(123)}', null, true),
			array('{host:key.avg(123s)}', null, true),
			array('{host:key.avg(#123)}', null, true),
			array('{host:key.avg(123,456)}', null, true),
			array('{host:key.avg(123,456s)}', null, true),
			array('{host:key.avg(123,456s,)}', null, true),
			array('{host:key.change()}', null, true),
			array('{host:key.change(0)}', null, true),
			array('{host:key.change(0,)}', null, true),
			array('{host:key.count()}', null, true),
			array('{host:key.count(123)}', null, true),
			array('{host:key.count(123,text)}', null, true),
			array('{host:key.count(123s,text)}', null, true),
			array('{host:key.count(#123,text)}', null, true),
			array('{host:key.count(123,text,eq)}', null, true),
			array('{host:key.count(123,text,ne)}', null, true),
			array('{host:key.count(123,text,gt)}', null, true),
			array('{host:key.count(123,text,ge)}', null, true),
			array('{host:key.count(123,text,lt)}', null, true),
			array('{host:key.count(123,text,le)}', null, true),
			array('{host:key.count(123,text,like)}', null, true),
			array('{host:key.count(123,text,like,456)}', null, true),
			array('{host:key.count(123,text,like,456s)}', null, true),
			array('{host:key.count(123,text,nonexistent,456s)}', null, true),
			array('{host:key.count(123,text,nonexistent,456s,)}', null, true),
			array('{host:key.date()}', null, true),
			array('{host:key.date(0)}', null, true),
			array('{host:key.date(0,)}', null, true),
			array('{host:key.dayofweek()}', null, true),
			array('{host:key.dayofweek(0)}', null, true),
			array('{host:key.dayofweek(0,)}', null, true),
			array('{host:key.delta()}', null, true),
			array('{host:key.delta(123)}', null, true),
			array('{host:key.delta(123s)}', null, true),
			array('{host:key.delta(#123)}', null, true),
			array('{host:key.delta(123,456)}', null, true),
			array('{host:key.delta(123,456s)}', null, true),
			array('{host:key.delta(123,456s,)}', null, true),
			array('{host:key.diff()}', null, true),
			array('{host:key.diff(0)}', null, true),
			array('{host:key.diff(0,)}', null, true),
			array('{host:key.fuzzytime()}', null, true),
			array('{host:key.fuzzytime(123)}', null, true),
			array('{host:key.fuzzytime(#123)}', null, true),
			array('{host:key.fuzzytime(123,)}', null, true),
			array('{host:key.iregexp()}', null, true),
			array('{host:key.iregexp(text)}', null, true),
			array('{host:key.iregexp(text,123)}', null, true),
			array('{host:key.iregexp(text,123s)}', null, true),
			array('{host:key.iregexp(text,#123)}', null, true),
			array('{host:key.iregexp(text,#123,)}', null, true),
			array('{host:key.last()}', null, true),
			array('{host:key.last(0)}', null, true),
			array('{host:key.last(#123)}', null, true),
			array('{host:key.last(#123,456)}', null, true),
			array('{host:key.last(#123,456s)}', null, true),
			array('{host:key.last(#123,456s,)}', null, true),
			array('{host:key.logseverity()}', null, true),
			array('{host:key.logseverity(0)}', null, true),
			array('{host:key.logseverity(0,)}', null, true),
			array('{host:key.logsource()}', null, true),
			array('{host:key.logsource(text)}', null, true),
			array('{host:key.logsource(text,)}', null, true),
			array('{host:key.max()}', null, true),
			// updated by Egita (January 02, 2013)
			array(
				'{host:key.max(123)}',
				array(
					'error' => '',
					'expressions' => array(
						0 => array(
							'expression' => '{host:key.max(123)}',
							'pos' => 0,
							'host' => 'host',
							'item' => 'key',
							'function' => 'max(123)',
							'functionName' => 'max',
							'functionParam' => '123',
							'functionParamList' => array('123')
						)
					),
					'macros' => array(),
					'usermacros' => array()
				),
				true
			),
			array('{host:key.max(123s)}', null, true),
			array('{host:key.max(#123)}', null, true),
			array('{host:key.max(123,456)}', null, true),
			array('{host:key.max(123,456s)}', null, true),
			array('{host:key.max(123,456s,)}', null, true),
			array('{host:key.min()}', null, true),
			array('{host:key.min(123)}', null, true),
			array('{host:key.min(123s)}', null, true),
			array('{host:key.min(#123)}', null, true),
			array('{host:key.min(123,456)}', null, true),
			array('{host:key.min(123,456s)}', null, true),
			array('{host:key.min(123,456s,)}', null, true),
			array('{host:key.nodata()}', null, true),
			array('{host:key.nodata(123)}', null, true),
			array('{host:key.nodata(123s)}', null, true),
			array('{host:key.nodata(#123)}', null, true),
			array('{host:key.nodata(123s,)}', null, true),
			array('{host:key.now()}', null, true),
			array('{host:key.now(0)}', null, true),
			array('{host:key.now(0,)}', null, true),
			array('{host:key.prev()}', null, true),
			array('{host:key.prev(0)}', null, true),
			array('{host:key.prev(0,)}', null, true),
			array('{host:key.regexp()}', null, true),
			array('{host:key.regexp(text)}', null, true),
			array('{host:key.regexp(text,123)}', null, true),
			array('{host:key.regexp(text,123s)}', null, true),
			array('{host:key.regexp(text,#123)}', null, true),
			array('{host:key.regexp(text,#123,)}', null, true),
			array('{host:key.str()}', null, true),
			array('{host:key.str(text)}', null, true),
			array('{host:key.str(text,123)}', null, true),
			array('{host:key.str(text,123s)}', null, true),
			array('{host:key.str(text,#123)}', null, true),
			array('{host:key.str(text,#123,)}', null, true),
			array('{host:key.strlen()}', null, true),
			array('{host:key.strlen(0)}', null, true),
			array('{host:key.strlen(#123)}', null, true),
			array('{host:key.strlen(#123,456)}', null, true),
			array('{host:key.strlen(#123,456s)}', null, true),
			array('{host:key.strlen(#123,456s,)}', null, true),
			array('{host:key.sum()}', null, true),
			array('{host:key.sum(123)}', null, true),
			array('{host:key.sum(123s)}', null, true),
			array('{host:key.sum(#123)}', null, true),
			array('{host:key.sum(123,456)}', null, true),
			array('{host:key.sum(123,456s)}', null, true),
			array('{host:key.sum(123,456s,)}', null, true),
			array('{host:key.time()}', null, true),
			array('{host:key.time(0)}', null, true),
			array('{host:key.time(0,)}', null, true),
			array('{host:key.nonexistent()}', null, true),
	// by Aleksandrs (January 18, 2011)
			array('{host:key.last(0)}+{TRIGGER.VALUE}', null, true),
			array('{host:key.last(0)}+{trigger.value}', null, false),
	// by Alexei's gen.php (January 7-14, 2011)
			array('(({host:key.last(0)}+1.)) + + (({host:key.last(0)}+1.))', null, false),
			array('(({host:key.last(0)}+1.)) + - (({host:key.last(0)}+1.))', null, false),
			array('(({host:key.last(0)}+1.)) + (({host:key.last(0)}+1.))', null, false),
			array('(({host:key.last(0)}+1.))', null, false),
			array('(--({host:key.last(0)}+1.))', null, false),
			array('(+({host:key.last(0)}+1.))', null, false),
			array('(/({host:key.last(0)}+1.))', null, false),
			array('({host:key.last(0)}+1.)/({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)-({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)+({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)|({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)&({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)&({host:key.last(0)}+1.)/({host:key.last(0)}+1.)', null, false),
			array('({host:key.last(0)}+1.)+({host:key.last(0)}+1.)/({host:key.last(0)}+1.)', null, false),
			array('1 - (1/({host:key.last(0)}+1.))+(({host:key.last(0)}+1.))/({host:key.last(0)}+1.)/({host:key.last('.
					'0)}+1.)', null, false),
			array('(({host:key.last(0)}+.1)) + + (({host:key.last(0)}+.1))', null, false),
			array('(({host:key.last(0)}+.1)) + - (({host:key.last(0)}+.1))', null, false),
			array('(({host:key.last(0)}+.1)) + (({host:key.last(0)}+.1))', null, false),
			array('(({host:key.last(0)}+.1))', null, false),
			array('(--({host:key.last(0)}+.1))', null, false),
			array('(+({host:key.last(0)}+.1))', null, false),
			array('(/({host:key.last(0)}+.1))', null, false),
			array('({host:key.last(0)}+.1)/({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)-({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)+({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)|({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)&({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)&({host:key.last(0)}+.1)/({host:key.last(0)}+.1)', null, false),
			array('({host:key.last(0)}+.1)+({host:key.last(0)}+.1)/({host:key.last(0)}+.1)', null, false),
			array('1 - (1/({host:key.last(0)}+.1))+(({host:key.last(0)}+.1))/({host:key.last(0)}+.1)/({host:key.last('.
					'0)}+.1)', null, false),
			array('(({host:key.last(0)}+0 .1)) + + (({host:key.last(0)}+0 .1))', null, false),
			array('(({host:key.last(0)}+0 .1)) + - (({host:key.last(0)}+0 .1))', null, false),
			array('(({host:key.last(0)}+0 .1)) + (({host:key.last(0)}+0 .1))', null, false),
			array('(({host:key.last(0)}+0 .1))', null, false),
			array('(--({host:key.last(0)}+0 .1))', null, false),
			array('(+({host:key.last(0)}+0 .1))', null, false),
			array('(/({host:key.last(0)}+0 .1))', null, false),
			array('({host:key.last(0)}+0 .1)/({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)-({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)+({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)|({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)&({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)&({host:key.last(0)}+0 .1)/({host:key.last(0)}+0 .1)', null, false),
			array('({host:key.last(0)}+0 .1)+({host:key.last(0)}+0 .1)/({host:key.last(0)}+0 .1)', null, false),
			array('1 - (1/({host:key.last(0)}+0 .1))+(({host:key.last(0)}+0 .1))/({host:key.last(0)}+0 .1)/({host:key'.
					'.last(0)}+0 .1)', null, false),
			array('(({host:key.last(0)}+1 K)) + + (({host:key.last(0)}+1 K))', null, false),
			array('(({host:key.last(0)}+1 K)) + - (({host:key.last(0)}+1 K))', null, false),
			array('(({host:key.last(0)}+1 K)) + (({host:key.last(0)}+1 K))', null, false),
			array('(({host:key.last(0)}+1 K))', null, false),
			array('(--({host:key.last(0)}+1 K))', null, false),
			array('(+({host:key.last(0)}+1 K))', null, false),
			array('(/({host:key.last(0)}+1 K))', null, false),
			array('({host:key.last(0)}+1 K)/({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)-({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)+({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)|({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)&({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)&({host:key.last(0)}+1 K)/({host:key.last(0)}+1 K)', null, false),
			array('({host:key.last(0)}+1 K)+({host:key.last(0)}+1 K)/({host:key.last(0)}+1 K)', null, false),
			array('1 - (1/({host:key.last(0)}+1 K))+(({host:key.last(0)}+1 K))/({host:key.last(0)}+1 K)/({host:key.la'.
					'st(0)}+1 K)', null, false),
			array('(({host:key.last(0)}+.)) + + (({host:key.last(0)}+.))', null, false),
			array('(({host:key.last(0)}+.)) + - (({host:key.last(0)}+.))', null, false),
			array('(({host:key.last(0)}+.)) + (({host:key.last(0)}+.))', null, false),
			array('(({host:key.last(0)}+.))', null, false),
			array('(--({host:key.last(0)}+.))', null, false),
			array('(+({host:key.last(0)}+.))', null, false),
			array('(/({host:key.last(0)}+.))', null, false),
			array('({host:key.last(0)}+.)/({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)-({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)+({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)|({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)&({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)&({host:key.last(0)}+.)/({host:key.last(0)}+.)', null, false),
			array('({host:key.last(0)}+.)+({host:key.last(0)}+.)/({host:key.last(0)}+.)', null, false),
			array('1 - (1/({host:key.last(0)}+.))+(({host:key.last(0)}+.))/({host:key.last(0)}+.)/({host:key.last(0)}'.
					'+.)', null, false),
			array('(({host:key.last(0)}+.K)) + + (({host:key.last(0)}+.K))', null, false),
			array('(({host:key.last(0)}+.K)) + - (({host:key.last(0)}+.K))', null, false),
			array('(({host:key.last(0)}+.K)) + (({host:key.last(0)}+.K))', null, false),
			array('(({host:key.last(0)}+.K))', null, false),
			array('(--({host:key.last(0)}+.K))', null, false),
			array('(+({host:key.last(0)}+.K))', null, false),
			array('(/({host:key.last(0)}+.K))', null, false),
			array('({host:key.last(0)}+.K)/({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)-({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)+({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)|({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)&({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)&({host:key.last(0)}+.K)/({host:key.last(0)}+.K)', null, false),
			array('({host:key.last(0)}+.K)+({host:key.last(0)}+.K)/({host:key.last(0)}+.K)', null, false),
			array('1 - (1/({host:key.last(0)}+.K))+(({host:key.last(0)}+.K))/({host:key.last(0)}+.K)/({host:key.last('.
					'0)}+.K)', null, false),
			array('(({host:key.last(0)}+K)) + + (({host:key.last(0)}+K))', null, false),
			array('(({host:key.last(0)}+K)) + - (({host:key.last(0)}+K))', null, false),
			array('(({host:key.last(0)}+K)) + (({host:key.last(0)}+K))', null, false),
			array('(({host:key.last(0)}+K))', null, false),
			array('(--({host:key.last(0)}+K))', null, false),
			array('(+({host:key.last(0)}+K))', null, false),
			array('(/({host:key.last(0)}+K))', null, false),
			array('({host:key.last(0)}+K)/({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)-({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)+({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)|({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)&({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)&({host:key.last(0)}+K)/({host:key.last(0)}+K)', null, false),
			array('({host:key.last(0)}+K)+({host:key.last(0)}+K)/({host:key.last(0)}+K)', null, false),
			array('1 - (1/({host:key.last(0)}+K))+(({host:key.last(0)}+K))/({host:key.last(0)}+K)/({host:key.last(0)}'.
					'+K)', null, false),
			array('({host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}) + + ({host:key.last(1)}+(1/2+2*2-3|4)'.
					'|23-34>{host:key.last(#1)})', null, false),
			// updated by Egita (January 02, 2013)
			array(
					'({host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}) + - ({host:key.last(1)}+(1/2+2*2-3|'.
						'4)|23-34>{host:key.last(#1)})',
					array(
					'error' => '',
					'expressions' => array(
						0 => array(
							'expression' => '{host:key.last(1)}',
							'pos'=> 1,
							'host' => 'host',
							'item' => 'key',
							'function' => 'last(1)',
							'functionName' => 'last',
							'functionParam' => '1',
							'functionParamList' => array('1')
						),
						1 => array(
							'expression' => '{host:key.last(#1)}',
							'pos'=> 40,
							'host' => 'host',
							'item' => 'key',
							'function' => 'last(#1)',
							'functionName' => 'last',
							'functionParam' => '#1',
							'functionParamList' => array('#1')
						),
						2 => array(
							'expression' => '{host:key.last(1)}',
							'pos'=> 66,
							'host' => 'host',
							'item' => 'key',
							'function' => 'last(1)',
							'functionName' => 'last',
							'functionParam' => '1',
							'functionParamList' => array('1')
						),
						3 => array(
							'expression' => '{host:key.last(#1)}',
							'pos'=> 105,
							'host' => 'host',
							'item' => 'key',
							'function' => 'last(#1)',
							'functionName' => 'last',
							'functionParam' => '#1',
							'functionParamList' => array('#1')
						)
					),
					'macros' => array(),
					'usermacros' => array()
				),
					true),
			array('({host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}) + ({host:key.last(1)}+(1/2+2*2-3|4)|'.
					'23-34>{host:key.last(#1)})', null, true),
			array('({host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)})', null, true),
			array('(--{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)})', null, false),
			array('(+{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)})', null, false),
			array('(/{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)})', null, false),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}/{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}-{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}+{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}|{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}&{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}&{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}/{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}', null, true),
			array('{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}+{host:key.last(1)}+(1/2+2*2-3|4)|23-34'.
					'>{host:key.last(#1)}/{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}', null, true),
			array('1 - (1/{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)})+({host:key.last(1)}+(1/2+2*2-3|'.
					'4)|23-34>{host:key.last(#1)})/{host:key.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}/{host:ke'.
					'y.last(1)}+(1/2+2*2-3|4)|23-34>{host:key.last(#1)}', null, true),
			array('({host:key.last(0)}+(1/2+2*2-3|4)|23-34) + + ({host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, false),
			array('({host:key.last(0)}+(1/2+2*2-3|4)|23-34) + - ({host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, true),
			array('({host:key.last(0)}+(1/2+2*2-3|4)|23-34) + ({host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, true),
			array('({host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, true),
			array('(--{host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, false),
			array('(+{host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, false),
			array('(/{host:key.last(0)}+(1/2+2*2-3|4)|23-34)', null, false),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34/{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34-{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34+{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34|{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34&{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34&{host:key.last(0)}+(1/2+2*2-3|4)|23-34/{host:key.last(0)}+('.
					'1/2+2*2-3|4)|23-34', null, true),
			array('{host:key.last(0)}+(1/2+2*2-3|4)|23-34+{host:key.last(0)}+(1/2+2*2-3|4)|23-34/{host:key.last(0)}+(1'.
					'/2+2*2-3|4)|23-34', null, true),
			array('1 - (1/{host:key.last(0)}+(1/2+2*2-3|4)|23-34)+({host:key.last(0)}+(1/2+2*2-3|4)|23-34)/{host:key.'.
					'last(0)}+(1/2+2*2-3|4)|23-34/{host:key.last(0)}+(1/2+2*2-3|4)|23-34', null, true),
			array('({host:key.{a}}) + + ({host:key.{a}})', null, false),
			array('({host:key.{a}}) + - ({host:key.{a}})', null, false),
			array('({host:key.{a}}) + ({host:key.{a}})', null, false),
			array('({host:key.{a}})', null, false),
			array('(--{host:key.{a}})', null, false),
			array('(+{host:key.{a}})', null, false),
			array('(/{host:key.{a}})', null, false),
			array('{host:key.{a}}/{host:key.{a}}', null, false),
			array('{host:key.{a}}-{host:key.{a}}', null, false),
			array('{host:key.{a}}+{host:key.{a}}', null, false),
			array('{host:key.{a}}|{host:key.{a}}', null, false),
			array('{host:key.{a}}&{host:key.{a}}', null, false),
			array('{host:key.{a}}&{host:key.{a}}/{host:key.{a}}', null, false),
			array('{host:key.{a}}+{host:key.{a}}/{host:key.{a}}', null, false),
			array('1 - (1/{host:key.{a}})+({host:key.{a}})/{host:key.{a}}/{host:key.{a}}', null, false),
			array('({host::key.last{a}}) + + ({host::key.last{a}})', null, false),
			array('({host::key.last{a}}) + - ({host::key.last{a}})', null, false),
			array('({host::key.last{a}}) + ({host::key.last{a}})', null, false),
			array('({host::key.last{a}})', null, false),
			array('(--{host::key.last{a}})', null, false),
			array('(+{host::key.last{a}})', null, false),
			array('(/{host::key.last{a}})', null, false),
			array('{host::key.last{a}}/{host::key.last{a}}', null, false),
			array('{host::key.last{a}}-{host::key.last{a}}', null, false),
			array('{host::key.last{a}}+{host::key.last{a}}', null, false),
			array('{host::key.last{a}}|{host::key.last{a}}', null, false),
			array('{host::key.last{a}}&{host::key.last{a}}', null, false),
			array('{host::key.last{a}}&{host::key.last{a}}/{host::key.last{a}}', null, false),
			array('{host::key.last{a}}+{host::key.last{a}}/{host::key.last{a}}', null, false),
			array('1 - (1/{host::key.last{a}})+({host::key.last{a}})/{host::key.last{a}}/{host::key.last{a}}',
					null,
					false
				),
			array('({host::key.(0)}) + + ({host::key.(0)})', null, false),
			array('({host::key.(0)}) + - ({host::key.(0)})', null, false),
			array('({host::key.(0)}) + ({host::key.(0)})', null, false),
			array('({host::key.(0)})', null, false),
			array('(--{host::key.(0)})', null, false),
			array('(+{host::key.(0)})', null, false),
			array('(/{host::key.(0)})', null, false),
			array('{host::key.(0)}/{host::key.(0)}', null, false),
			array('{host::key.(0)}-{host::key.(0)}', null, false),
			array('{host::key.(0)}+{host::key.(0)}', null, false),
			array('{host::key.(0)}|{host::key.(0)}', null, false),
			array('{host::key.(0)}&{host::key.(0)}', null, false),
			array('{host::key.(0)}&{host::key.(0)}/{host::key.(0)}', null, false),
			array('{host::key.(0)}+{host::key.(0)}/{host::key.(0)}', null, false),
			array('1 - (1/{host::key.(0)})+({host::key.(0)})/{host::key.(0)}/{host::key.(0)}', null, false),
			array('({:key.(0)}) + + ({:key.(0)})', null, false),
			array('({:key.(0)}) + - ({:key.(0)})', null, false),
			array('({:key.(0)}) + ({:key.(0)})', null, false),
			array('({:key.(0)})', null, false),
			array('(--{:key.(0)})', null, false),
			array('(+{:key.(0)})', null, false),
			array('(/{:key.(0)})', null, false),
			array('{:key.(0)}/{:key.(0)}', null, false),
			array('{:key.(0)}-{:key.(0)}', null, false),
			array('{:key.(0)}+{:key.(0)}', null, false),
			array('{:key.(0)}|{:key.(0)}', null, false),
			array('{:key.(0)}&{:key.(0)}', null, false),
			array('{:key.(0)}&{:key.(0)}/{:key.(0)}', null, false),
			array('{:key.(0)}+{:key.(0)}/{:key.(0)}', null, false),
			array('1 - (1/{:key.(0)})+({:key.(0)})/{:key.(0)}/{:key.(0)}', null, false),
			array('({:key.last(0)}) + + ({:key.last(0)})', null, false),
			array('({:key.last(0)}) + - ({:key.last(0)})', null, false),
			array('({:key.last(0)}) + ({:key.last(0)})', null, false),
			array('({:key.last(0)})', null, false),
			array('(--{:key.last(0)})', null, false),
			array('(+{:key.last(0)})', null, false),
			array('(/{:key.last(0)})', null, false),
			array('{:key.last(0)}/{:key.last(0)}', null, false),
			array('{:key.last(0)}-{:key.last(0)}', null, false),
			array('{:key.last(0)}+{:key.last(0)}', null, false),
			array('{:key.last(0)}|{:key.last(0)}', null, false),
			array('{:key.last(0)}&{:key.last(0)}', null, false),
			array('{:key.last(0)}&{:key.last(0)}/{:key.last(0)}', null, false),
			array('{:key.last(0)}+{:key.last(0)}/{:key.last(0)}', null, false),
			array('1 - (1/{:key.last(0)})+({:key.last(0)})/{:key.last(0)}/{:key.last(0)}', null, false),
			array('(({host:key.last(0)})) + + (({host:key.last(0)}))', null, false),
			array('(({host:key.last(0)})) + - (({host:key.last(0)}))', null, true),
			array('(({host:key.last(0)})) + (({host:key.last(0)}))', null, true),
			array('(({host:key.last(0)}))', null, true),
			array('(--({host:key.last(0)}))', null, false),
			array('(+({host:key.last(0)}))', null, false),
			array('(/({host:key.last(0)}))', null, false),
			array('({host:key.last(0)})/({host:key.last(0)})', null, true),
			array('({host:key.last(0)})-({host:key.last(0)})', null, true),
			array('({host:key.last(0)})+({host:key.last(0)})', null, true),
			array('({host:key.last(0)})|({host:key.last(0)})', null, true),
			array('({host:key.last(0)})&({host:key.last(0)})', null, true),
			array('({host:key.last(0)})&({host:key.last(0)})/({host:key.last(0)})', null, true),
			array('({host:key.last(0)})+({host:key.last(0)})/({host:key.last(0)})', null, true),
			array('1 - (1/({host:key.last(0)}))+(({host:key.last(0)}))/({host:key.last(0)})/({host:key.last(0)})',
					null,
					true
				),
			array('({host:.last(0)}) + + ({host:.last(0)})', null, false),
			array('({host:.last(0)}) + - ({host:.last(0)})', null, false),
			array('({host:.last(0)}) + ({host:.last(0)})', null, false),
			array('({host:.last(0)})', null, false),
			array('(--{host:.last(0)})', null, false),
			array('(+{host:.last(0)})', null, false),
			array('(/{host:.last(0)})', null, false),
			array('{host:.last(0)}/{host:.last(0)}', null, false),
			array('{host:.last(0)}-{host:.last(0)}', null, false),
			array('{host:.last(0)}+{host:.last(0)}', null, false),
			array('{host:.last(0)}|{host:.last(0)}', null, false),
			array('{host:.last(0)}&{host:.last(0)}', null, false),
			array('{host:.last(0)}&{host:.last(0)}/{host:.last(0)}', null, false),
			array('{host:.last(0)}+{host:.last(0)}/{host:.last(0)}', null, false),
			array('1 - (1/{host:.last(0)})+({host:.last(0)})/{host:.last(0)}/{host:.last(0)}', null, false),
			array('({$}+{host:key.last(0)}) + + ({$}+{host:key.last(0)})', null, false),
			array('({$}+{host:key.last(0)}) + - ({$}+{host:key.last(0)})', null, false),
			array('({$}+{host:key.last(0)}) + ({$}+{host:key.last(0)})', null, false),
			array('({$}+{host:key.last(0)})', null, false),
			array('(--{$}+{host:key.last(0)})', null, false),
			array('(+{$}+{host:key.last(0)})', null, false),
			array('(/{$}+{host:key.last(0)})', null, false),
			array('{$}+{host:key.last(0)}/{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}-{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}+{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}|{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}&{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}&{$}+{host:key.last(0)}/{$}+{host:key.last(0)}', null, false),
			array('{$}+{host:key.last(0)}+{$}+{host:key.last(0)}/{$}+{host:key.last(0)}', null, false),
			array('1 - (1/{$}+{host:key.last(0)})+({$}+{host:key.last(0)})/{$}+{host:key.last(0)}/{$}+{host:key.last('.
					'0)}', null, false),
			array('( - {$MACRO}+{host:key.last(0)}) + + ( - {$MACRO}+{host:key.last(0)})', null, false),
			array('( - {$MACRO}+{host:key.last(0)}) + - ( - {$MACRO}+{host:key.last(0)})', null, true),
			array('( - {$MACRO}+{host:key.last(0)}) + ( - {$MACRO}+{host:key.last(0)})', null, true),
			array('( - {$MACRO}+{host:key.last(0)})', null, true),
			array('(-- - {$MACRO}+{host:key.last(0)})', null, false),
			array('(+ - {$MACRO}+{host:key.last(0)})', null, false),
			array('(/ - {$MACRO}+{host:key.last(0)})', null, false),
			array(' - {$MACRO}+{host:key.last(0)}/ - {$MACRO}+{host:key.last(0)}', null, true),
			array(' - {$MACRO}+{host:key.last(0)}- - {$MACRO}+{host:key.last(0)}', null, true),
			array(' - {$MACRO}+{host:key.last(0)}+ - {$MACRO}+{host:key.last(0)}', null, true),
			array(' - {$MACRO}+{host:key.last(0)}| - {$MACRO}+{host:key.last(0)}', null, true),
			array(' - {$MACRO}+{host:key.last(0)}& - {$MACRO}+{host:key.last(0)}', null, true),
			array(' - {$MACRO}+{host:key.last(0)}& - {$MACRO}+{host:key.last(0)}/ - {$MACRO}+{host:key.last(0)}',
					null,
					true
				),
			array(' - {$MACRO}+{host:key.last(0)}+ - {$MACRO}+{host:key.last(0)}/ - {$MACRO}+{host:key.last(0)}',
					null,
					true
				),
			array('1 - (1/ - {$MACRO}+{host:key.last(0)})+( - {$MACRO}+{host:key.last(0)})/ - {$MACRO}+{host:key.last'.
					'(0)}/ - {$MACRO}+{host:key.last(0)}', null, true),
			array('(100G+{host:key.last(0)}) + + (100G+{host:key.last(0)})', null, false),
			array('(100G+{host:key.last(0)}) + - (100G+{host:key.last(0)})', null, true),
			array('(100G+{host:key.last(0)}) + (100G+{host:key.last(0)})', null, true),
			array('(100G+{host:key.last(0)})', null, true),
			array('(--100G+{host:key.last(0)})', null, false),
			array('(+100G+{host:key.last(0)})', null, false),
			array('(/100G+{host:key.last(0)})', null, false),
			array('100G+{host:key.last(0)}/100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}-100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}+100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}|100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}&100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}&100G+{host:key.last(0)}/100G+{host:key.last(0)}', null, true),
			array('100G+{host:key.last(0)}+100G+{host:key.last(0)}/100G+{host:key.last(0)}', null, true),
			array('1 - (1/100G+{host:key.last(0)})+(100G+{host:key.last(0)})/100G+{host:key.last(0)}/100G+{host:key.l'.
					'ast(0)}', null, true),
			array('({host:key.last(0)}) + + ({host:key.last(0)})', null, false),
			array('({host:key.last(0)}) + - ({host:key.last(0)})', null, true),
			array('({host:key.last(0)}) + ({host:key.last(0)})', null, true),
			array('({host:key.last(0)})', null, true),
			array('(--{host:key.last(0)})', null, false),
			array('(+{host:key.last(0)})', null, false),
			array('(/{host:key.last(0)})', null, false),
			array('{host:key.last(0)}/{host:key.last(0)}', null, true),
			array('{host:key.last(0)}-{host:key.last(0)}', null, true),
			array('{host:key.last(0)}+{host:key.last(0)}', null, true),
			array('{host:key.last(0)}|{host:key.last(0)}', null, true),
			array('{host:key.last(0)}&{host:key.last(0)}', null, true),
			array('{host:key.last(0)}&{host:key.last(0)}/{host:key.last(0)}', null, true),
			array('{host:key.last(0)}+{host:key.last(0)}/{host:key.last(0)}', null, true),
			array('1 - (1/{host:key.last(0)})+({host:key.last(0)})/{host:key.last(0)}/{host:key.last(0)}', null, true),
			array('({host:key.str(0 - 1 / 2 |-4*-56.34K)}) + + ({host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, false),
			array('({host:key.str(0 - 1 / 2 |-4*-56.34K)}) + - ({host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, true),
			array('({host:key.str(0 - 1 / 2 |-4*-56.34K)}) + ({host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, true),
			array('({host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, true),
			array('(--{host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, false),
			array('(+{host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, false),
			array('(/{host:key.str(0 - 1 / 2 |-4*-56.34K)})', null, false),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}/{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}-{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}+{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}|{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}&{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}&{host:key.str(0 - 1 / 2 |-4*-56.34K)}/{host:key.str(0 - 1 / '.
					'2 |-4*-56.34K)}', null, true),
			array('{host:key.str(0 - 1 / 2 |-4*-56.34K)}+{host:key.str(0 - 1 / 2 |-4*-56.34K)}/{host:key.str(0 - 1 / '.
					'2 |-4*-56.34K)}', null, true),
			array('1 - (1/{host:key.str(0 - 1 / 2 |-4*-56.34K)})+({host:key.str(0 - 1 / 2 |-4*-56.34K)})/{host:key.st'.
					'r(0 - 1 / 2 |-4*-56.34K)}/{host:key.str(0 - 1 / 2 |-4*-56.34K)}', null, true),
			array('({host:key.str(^$$$^%)}) + + ({host:key.str(^$$$^%)})', null, false),
			array('({host:key.str(^$$$^%)}) + - ({host:key.str(^$$$^%)})', null, true),
			array('({host:key.str(^$$$^%)}) + ({host:key.str(^$$$^%)})', null, true),
			array('({host:key.str(^$$$^%)})', null, true),
			array('(--{host:key.str(^$$$^%)})', null, false),
			array('(+{host:key.str(^$$$^%)})', null, false),
			array('(/{host:key.str(^$$$^%)})', null, false),
			array('{host:key.str(^$$$^%)}/{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}-{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}+{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}|{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}&{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}&{host:key.str(^$$$^%)}/{host:key.str(^$$$^%)}', null, true),
			array('{host:key.str(^$$$^%)}+{host:key.str(^$$$^%)}/{host:key.str(^$$$^%)}', null, true),
			array('1 - (1/{host:key.str(^$$$^%)})+({host:key.str(^$$$^%)})/{host:key.str(^$$$^%)}/{host:key.str(^$$$'.
					'^%)}', null, true),
			array('({host:key.str("(^*&%#$%)*")}) + + ({host:key.str("(^*&%#$%)*")})', null, false),
			array('({host:key.str("(^*&%#$%)*")}) + - ({host:key.str("(^*&%#$%)*")})', null, true),
			array('({host:key.str("(^*&%#$%)*")}) + ({host:key.str("(^*&%#$%)*")})', null, true),
			array('({host:key.str("(^*&%#$%)*")})', null, true),
			array('(--{host:key.str("(^*&%#$%)*")})', null, false),
			array('(+{host:key.str("(^*&%#$%)*")})', null, false),
			array('(/{host:key.str("(^*&%#$%)*")})', null, false),
			array('{host:key.str("(^*&%#$%)*")}/{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}-{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}+{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}|{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}&{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}&{host:key.str("(^*&%#$%)*")}/{host:key.str("(^*&%#$%)*")}', null, true),
			array('{host:key.str("(^*&%#$%)*")}+{host:key.str("(^*&%#$%)*")}/{host:key.str("(^*&%#$%)*")}', null, true),
			array('1 - (1/{host:key.str("(^*&%#$%)*")})+({host:key.str("(^*&%#$%)*")})/{host:key.str("(^*&%#$%)*")}/{'.
					'host:key.str("(^*&%#$%)*")}', null, true),
			array('(((((((({host:key.str("")})))))))) + + (((((((({host:key.str("")}))))))))', null, false),
			array('(((((((({host:key.str("")})))))))) + - (((((((({host:key.str("")}))))))))', null, true),
			array('(((((((({host:key.str("")})))))))) + (((((((({host:key.str("")}))))))))', null, true),
			array('(((((((({host:key.str("")}))))))))', null, true),
			array('(--((((((({host:key.str("")}))))))))', null, false),
			array('(+((((((({host:key.str("")}))))))))', null, false),
			array('(/((((((({host:key.str("")}))))))))', null, false),
			array('((((((({host:key.str("")})))))))/((((((({host:key.str("")})))))))', null, true),
			array('((((((({host:key.str("")})))))))-((((((({host:key.str("")})))))))', null, true),
			array('((((((({host:key.str("")})))))))+((((((({host:key.str("")})))))))', null, true),
			array('((((((({host:key.str("")})))))))|((((((({host:key.str("")})))))))', null, true),
			array('((((((({host:key.str("")})))))))&((((((({host:key.str("")})))))))', null, true),
			array('((((((({host:key.str("")})))))))&((((((({host:key.str("")})))))))/((((((({host:key.str("")})))))))'.
					'', null, true),
			array('((((((({host:key.str("")})))))))+((((((({host:key.str("")})))))))/((((((({host:key.str("")})))))))',
					null,
					true
				),
			array('1 - (1/((((((({host:key.str("")}))))))))+(((((((({host:key.str("")}))))))))/((((((({host:key.str("'.
					'")})))))))/((((((({host:key.str("")})))))))', null, true),
			array('((1 - 1-2-((((((({host:key.str("0")}))))))))) + + ((1 - 1-2-((((((({host:key.str("0")})))))))))',
					null,
					false
				),
			array('((1 - 1-2-((((((({host:key.str("0")}))))))))) + - ((1 - 1-2-((((((({host:key.str("0")})))))))))',
					null,
					true
				),
			array('((1 - 1-2-((((((({host:key.str("0")}))))))))) + ((1 - 1-2-((((((({host:key.str("0")})))))))))',
					null,
					true
				),
			array('((1 - 1-2-((((((({host:key.str("0")})))))))))', null, true),
			array('(--(1 - 1-2-((((((({host:key.str("0")})))))))))', null, false),
			array('(+(1 - 1-2-((((((({host:key.str("0")})))))))))', null, false),
			array('(/(1 - 1-2-((((((({host:key.str("0")})))))))))', null, false),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))/(1 - 1-2-((((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))-(1 - 1-2-((((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))+(1 - 1-2-((((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))|(1 - 1-2-((((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))&(1 - 1-2-((((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))&(1 - 1-2-((((((({host:key.str("0")}))))))))/(1 - 1-2-('.
					'(((((({host:key.str("0")}))))))))', null, true),
			array('(1 - 1-2-((((((({host:key.str("0")}))))))))+(1 - 1-2-((((((({host:key.str("0")}))))))))/(1 - 1-2-('.
					'(((((({host:key.str("0")}))))))))', null, true),
			array('1 - (1/(1 - 1-2-((((((({host:key.str("0")})))))))))+((1 - 1-2-((((((({host:key.str("0")})))))))))'.
						'/(1 - 1-2-((((((({host:key.str("0")}))))))))/(1 - 1-2-((((((({host:key.str("0")}))))))))',
					null,
					true
				),
	// by Aleksandrs (July 19, 2011; test cases for ZBX-3911)
			array('{host:log[/data/logs/test.log,incorrect:FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect^FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect/FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect*FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect+FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect-FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect&FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect|FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,(incorrect|FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect|FAIL)].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,(incorrect|FAIL)].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,{incorrect|FAIL].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,incorrect|FAIL}].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,{incorrect|FAIL}].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,text1(incorrect|FAILtext2].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,text1incorrect|FAIL)text2].last(0)}', null, true),
			array('{host:log[/data/logs/test.log,text1(incorrect|FAIL)text2].last(0)}', null, true),
			array('{Template_App_CCWS:web.page.regexp[0.0.0.0,/ws-callcontrol-1.1/test,{$CCWS_PORT},"[Ss]moke [Tt]est'.
						' = ([Ss]uccess|[Ww]arning|[Ff]ail).*([[:space:]].*)+"].count(#1,event service = failed)}=1',
					null,
					true
				),
			array('{host:key.str({$M})} | {host:key.str({$M})} | {$M} + {TRIGGER.VALUE}', null, true),
			array('{$M} | {host:key.str({$M})}', null, true),
			array('({$M} + 5) | {host:key.str({$M})}', null, true),
			// by Egita (January 03, 2013)
			array(
				'{hostA:keyA[1,2,3].str("abc",123)}*{hostB:keyB.last(123,"abc","def")}/{host:key["param","abc"].'.
					'last(1,2,3,4,5)}+{host:key.diff()}+{TRIGGER.VALUE}/{$M}-{$M1234}*{$CUSTOM}-{TRIGGER.VALUE}',
				array(
					'error' => '',
					'expressions' => array(
						0 => array(
							'expression' => '{hostA:keyA[1,2,3].str("abc",123)}',
							'pos'=> 0,
							'host' => 'hostA',
							'item' => 'keyA[1,2,3]',
							'function' => 'str("abc",123)',
							'functionName' => 'str',
							'functionParam' => '"abc",123',
							'functionParamList' => array('abc', 123)
						),
						1 => array(
							'expression' => '{hostB:keyB.last(123,"abc","def")}',
							'pos'=> 35,
							'host' => 'hostB',
							'item' => 'keyB',
							'function' => 'last(123,"abc","def")',
							'functionName' => 'last',
							'functionParam' => '123,"abc","def"',
							'functionParamList' => array(123, 'abc', 'def')
						),
						2 => array(
							'expression' => '{host:key["param","abc"].last(1,2,3,4,5)}',
							'pos'=> 70,
							'host' => 'host',
							'item' => 'key["param","abc"]',
							'function' => 'last(1,2,3,4,5)',
							'functionName' => 'last',
							'functionParam' => '1,2,3,4,5',
							'functionParamList' => array(1, 2, 3, 4, 5)
						),
						3 => array(
							'expression' => '{host:key.diff()}',
							'pos'=> 112,
							'host' => 'host',
							'item' => 'key',
							'function' => 'diff()',
							'functionName' => 'diff',
							'functionParam' => '',
							'functionParamList' => array('')
						)
					),
					'macros' => array(
						0 => array(
							'expression' => '{TRIGGER.VALUE}'
							),
						1 => array(
							'expression' => '{TRIGGER.VALUE}'
							)
					),
					'usermacros' => array(
						0 => array(
							'expression' => '{$M}'
						),
						1 => array(
							'expression' => '{$M1234}'
						),
						2 => array(
							'expression' => '{$CUSTOM}'
						)
					)
				),
					true
				),
			// Decreasing large expression -true
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7#8=9m',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7#8=9',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7#8',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0',
					null,
					true
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}',
					null,
					true
				),

			// Decreasing large expression -true
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7#8=',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<7#',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>6<',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&5>',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|4&',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+3|',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-2+',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/1-',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*0/',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])}*',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0])',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0]',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param0',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",param',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",para',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",par',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",pa',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",p',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"",',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\""',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\"',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC\\',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","paramC',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","param',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","para',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","par',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","pa',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","p',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'","',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'",',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2'.
						'"',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB2',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"paramB',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"param',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"para',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"par',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"pa',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"p',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,"',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1,',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA1',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(paramA',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(param',
					null,
					false
				),
			array(
					'{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(para',
					null,
					false
				),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(par', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(pa', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(p', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function(', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].function', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].functio', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].functi', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].funct', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].func', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].fun', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].fu',	null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].f', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)].', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)]', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0)', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param0', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",param', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",para', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",par', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",pa', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",p', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"",', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\"', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC\\', null,false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","paramC', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","param', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","para', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","par', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","pa', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","p', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2","', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2",', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2"', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB2', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"paramB', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"param', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"para', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"par', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"pa', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"p', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,"', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1,', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA1', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[paramA', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[param', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[para', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[par', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[pa', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[p', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10[', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C10', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C1', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_C', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-_', null, false),
			array('{hostNAME.01- B5_:keyNAME.02-', null, false),
			array('{hostNAME.01- B5_:keyNAME.02', null, false),
			array('{hostNAME.01- B5_:keyNAME.0', null, false),
			array('{hostNAME.01- B5_:keyNAME.', null, false),
			array('{hostNAME.01- B5_:keyNAME', null, false),
			array('{hostNAME.01- B5_:keyNAM', null, false),
			array('{hostNAME.01- B5_:keyNA', null, false),
			array('{hostNAME.01- B5_:keyN', null, false),
			array('{hostNAME.01- B5_:key', null, false),
			array('{hostNAME.01- B5_:ke', null, false),
			array('{hostNAME.01- B5_:k', null, false),
			array('{hostNAME.01- B5_:', null, false),
			array('{hostNAME.01- B5_', null, false),
			array('{hostNAME.01- B5', null, false),
			array('{hostNAME.01- B', null, false),
			array('{hostNAME.01- ', null, false),
			array('{hostNAME.01-', null, false),
			array('{hostNAME.01', null, false),
			array('{hostNAME.0', null, false),
			array('{hostNAME.', null, false),
			array('{hostNAME', null, false),
			array('{hostNAM', null, false),
			array('{hostNA', null, false),
			array('{hostN', null, false),
			array('{host', null, false),
			array('{hos', null, false),
			array('{ho', null, false),
			array('{h', null, false),
			array('{', null, false),
			array('', null, false)
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_parse($expression, $result, $rc) {
		$expressionData = new CTriggerExpression();
		if ($expressionData->parse($expression)) {
			$this->assertEquals($rc, true);
			$this->assertEquals($rc, $expressionData->isValid);
		if (isset($result)){
			$this->assertEquals($result['error'], $expressionData->error);
			$this->assertEquals($result['expressions'], $expressionData->expressions);
			$this->assertEquals($result['macros'], $expressionData->macros);
			$this->assertEquals($result['usermacros'], $expressionData->usermacros);
			}
		}
		else {
			$this->assertEquals($rc,false, "\nError with expression $expression: ".$expressionData->error);
		}
	}
}
