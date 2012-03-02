<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

function SDB($return=false){
	$backtrace = debug_backtrace();
	array_shift($backtrace);
	$result = 'DEBUG BACKTRACE: <br/>';
	foreach($backtrace as $n => $bt){
		$result .= '  --['.$n.']-- '.$bt['file'].' : '.$bt['line'].'<br/>';
		$result .= "&nbsp;&nbsp;<b>".(isset($bt['class']) ? $bt['class'].$bt['type'].$bt['function'] : $bt['function']).'</b>';
		
		$args = array();
		foreach($bt['args'] as $arg){
			$args[] = is_array($arg) ? print_r($arg, true) : $arg;
		}
		
		$result .= '( '.implode(', ', $args).' ) <br/>';
	}
	if($return) return $result;
	else echo $result;
}
function SDI($msg='SDI') { echo 'DEBUG INFO: '; var_dump($msg); echo SBR; } // DEBUG INFO!!!
function SDII($msg='SDII') { echo 'DEBUG INFO: '; echo '<pre>'.print_r($msg, true).'</pre>'; echo SBR; } // DEBUG INFO!!!
function VDP($var, $msg=null) { echo 'DEBUG DUMP: '; if(isset($msg)) echo '"'.$msg.'"'.SPACE; var_dump($var); echo SBR; } // DEBUG INFO!!!
function TODO($msg) { echo 'TODO: '.$msg.SBR; }  // DEBUG INFO!!!

?>
