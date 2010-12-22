<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../../../include/locales/en_gb.inc.php');
require_once(dirname(__FILE__).'/../../../include/locales.inc.php');
process_locales();
require_once(dirname(__FILE__).'/../../../include/defines.inc.php');
require_once(dirname(__FILE__).'/../../../include/validate.inc.php');
require_once(dirname(__FILE__).'/../../../include/func.inc.php');
require_once(dirname(__FILE__).'/../../../include/items.inc.php');
require_once(dirname(__FILE__).'/../../../include/triggers.inc.php');
require_once(dirname(__FILE__).'/../../../api/classes/class.ctriggerexpression.php');

class class_triggerexpressionTest extends PHPUnit_Framework_TestCase
{
	public static function provider()
	{
		return array(



	// Correct trigger expressions
			array('',false),
			array('1',false),
			array('1+1',false),
			array('abc',false),
			array('{TRIGGER.VALUE}',false),
			array('{$USERMACRO}',false),
			array('{TRIGGER.VALUE}=1',false),
			array('{$USERMACRO}=1',false),
			array('{host}',false),
			array('{host:key}',false),
			array('{host:key.str}',false),
			array('{host:key.str()} & {TRIGGER.VALUE}',true),
			array('{host:key.str()} & {$USERMACRO}',true),

			array('{host:key.str()} + 1',true),
			array('{host:key.str()} - 1',true),
			array('{host:key.str()} / 1',true),
			array('{host:key.str()} * 1',true),
			array('{host:key.str()} = 1',true),
			array('{host:key.str()} # 1',true),
			array('{host:key.str()} & 1',true),
			array('{host:key.str()} | 1',true),

			array('{host:key.str()} + 1 | {host:key.str()}',true),
			array('{host:key.str()} - 1 & {host:key.str()}',true),
			array('{host:key.str()} / 1 # {host:key.str()}',true),
			array('{host:key.str()} * 1 = {host:key.str()}',true),
			array('{host:key.str()} = 1 * {host:key.str()}',true),
			array('{host:key.str()} # 1 / {host:key.str()}',true),
			array('{host:key.str()} & 1 - {host:key.str()}',true),
			array('{host:key.str()} | 1 + {host:key.str()}',true),

			array('{host:key.str()} ++ 1',false),
			array('{host:key.str()} -- 1',false),
			array('{host:key.str()} // 1',false),
			array('{host:key.str()} ** 1',false),
			array('{host:key.str()} == 1',false),
			array('{host:key.str()} ## 1',false),
			array('{host:key.str()} && 1',false),
			array('{host:key.str()} || 1',false),

			array('{host:key.str()} +',false),
			array('{host:key.str()} -',false),
			array('{host:key.str()} /',false),
			array('{host:key.str()} *',false),
			array('{host:key.str()} =',false),
			array('{host:key.str()} #',false),
			array('{host:key.str()} &',false),
			array('{host:key.str()} |',false),

			array('+ {host:key.str()}',false),
			array('- {host:key.str()}',false),
			array('/ {host:key.str()}',false),
			array('* {host:key.str()}',false),
			array('= {host:key.str()}',false),
			array('# {host:key.str()}',false),
			array('& {host:key.str()}',false),
			array('| {host:key.str()}',false),

			array('{host:key.str()}=0',true),
			array('{host:key.str(,)}=0',true),
			array('{host:key.str( ,)}=0',true),
			array('{host:key.str(  ,)}=0',true),
			array('{host:key.str(, )}=0',true),
			array('{host:key.str(,  )}=0',true),

			array('{host:key.str(")}=0',false),
			array('{host:key.str("")}=0',true),
			array('{host:key.str(""")}=0',false),
			array('{host:key.str("""")}=0',false),

			array('{host:key.str( ")}=0',false),
			array('{host:key.str( "")}=0',true),
			array('{host:key.str( """)}=0',false),
			array('{host:key.str( """")}=0',false),

			array('{host:key.str(  ")}=0',false),
			array('{host:key.str(  "")}=0',true),
			array('{host:key.str(  """)}=0',false),
			array('{host:key.str(  """")}=0',false),

			array('{host:key.str(,")}=0',false),
			array('{host:key.str(,"")}=0',true),
			array('{host:key.str(,""")}=0',false),
			array('{host:key.str(,"""")}=0',false),

			array('{host:key.str(, ")}=0',false),
			array('{host:key.str(, "")}=0',true),
			array('{host:key.str(, """)}=0',false),
			array('{host:key.str(, """")}=0',false),

			array('{host:key.str(,  ")}=0',false),
			array('{host:key.str(,  "")}=0',true),
			array('{host:key.str(,  """)}=0',false),
			array('{host:key.str(,  """")}=0',false),

			array('{host:key.str("",")}=0',false),
			array('{host:key.str("","")}=0',true),
			array('{host:key.str("",""")}=0',false),
			array('{host:key.str("","""")}=0',false),

			array('{host:key.str("", ")}=0',false),
			array('{host:key.str("", "")}=0',true),
			array('{host:key.str("", """)}=0',false),
			array('{host:key.str("", """")}=0',false),

			array('{host:key.str("",  ")}=0',false),
			array('{host:key.str("",  "")}=0',true),
			array('{host:key.str("",  """)}=0',false),
			array('{host:key.str("",  """")}=0',false),

			array('{host:key.str("\")}=0',false),
			array('{host:key.str("\"")}=0',true),
			array('{host:key.str("\\"")}=0',true),
			array('{host:key.str("\""")}=0',false),
			array('{host:key.str("\"""")}=0',false),

			array('{host:key.str(\")}=0',true),
			array('{host:key.str(param\")}=0',true),
			array('{host:key.str(param")}=0',true),

			array('{host:key.str( \")}=0',true),
			array('{host:key.str( param\")}=0',true),
			array('{host:key.str( param")}=0',true),

			array('{host:key.str(  \")}=0',true),
			array('{host:key.str(  param\")}=0',true),
			array('{host:key.str(  param")}=0',true),

			array('{host:key.str(()}=0',true),
			array('{host:key.str(param()}=0',true),

			array('{host:key.str( ()}=0',true),
			array('{host:key.str( param()}=0',true),

			array('{host:key.str(  ()}=0',true),
			array('{host:key.str(  param()}=0',true),

			array('{host:key.str())}=0',false),
			array('{host:key.str(param))}=0',false),

			array('{host:key.str( ))}=0',false),
			array('{host:key.str( param))}=0',false),

			array('{host:key.str(  ))}=0',false),
			array('{host:key.str(  param))}=0',false),

			array('{host:key.str("(")}=0',true),
			array('{host:key.str("param(")}=0',true),

			array('{host:key.str(")")}=0',true),
			array('{host:key.str("param)")}=0',true),


			array('{host:key.str()}=0',true),
			array('{host:key.str(abc)}=0',true),
			array('{host:key.str(\'abc\')}=0',true),
			array('{host:key.str(ГУГЛ)}=0',true),
			array('{host:key.str("ГУГЛ")}=0',true),
			array('{host:key.str("")}=0',true),
			array('{host:key.last(0)}=0',true),
			array('{host:key.str(aaa()}=0',true),

			array('{host:key.last(0)}=0',true),
			array('{host:key.str(aaa()}=0',true),
			array('({hostA:keyA.str("abc")}=0) | ({hostB:keyB.last(123)}=0)',true),
			array('{host:key[asd[].str(aaa()}=0',true),
			array('{host:key[asd[,asd[,[]].str(aaa()}=0',true),
			array('{host:key[[],[],[]].str([],[],[])}=0',true),
			array('({hostA:keyA.str("abc")} / ({hostB:keyB.last(123)})=(0)',true),
			array('({hostA:keyA.str("abc")}=0) | ({hostB:keyB.last(123)}=0)',true),
			array('({hostA:keyA.str("abc")}=0) & ({hostB:keyB.last(123)}=0)',true),
	// Incorrect trigger expressions
			array('{hostkey.last(0)}=0',false),
			array('{host:keylast(0)}=0',false),
			array('{host:key.last(0}=0',false),
			array('{host:key.last(0)}=',false),
			array('{host:key.str()=0',false),
			array('{host:key.last()}#0}',false),
			array('{host:key.incorrect()}=0',false),
			array('{host:key.str()}<>0',false),
			array('({host:key.str(aaa()}=0',false),
			array('(({host:key.str(aaa()}=0)',false),
			array('{host:key.str(aaa()}=0)',false),
			array('({hostA:keyA.str("abc")}=0) || ({hostB:keyB.last(123)}=0)',false),
			array('{host:key.last()}<>0',false),
			array('{hostA:keyA.str("abc")} / ({hostB:keyB.last(123)}=0',false),
			array('({hostA:keyA.str("abc")} / ({hostB:keyB.last(123)})=0',false)
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_parse($a, $b)
	{
		$trigger = new CTriggerExpression(array('expression'=>$a));
		if(empty($trigger->errors)) {
			$this->assertEquals(true,$b);
		} else {
//			print_r($trigger->errors);
			$this->assertEquals(false,$b,$trigger->errors[0]);
		}
	}

}
?>
