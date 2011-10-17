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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
function sdb($return = false) {
	$backtrace = debug_backtrace();
	array_shift($backtrace);
	$result = 'DEBUG BACKTRACE: <br/>';
	foreach ($backtrace as $n => $bt) {
		$result .= '  --['.$n.']-- '.$bt['file'].' : '.$bt['line'].'<br/>';
		$result .= "&nbsp;&nbsp;<b>".(isset($bt['class']) ? $bt['class'].$bt['type'].$bt['function'] : $bt['function']).'</b>';
		$args = array();
		foreach ($bt['args'] as $arg) {
			$args[] = is_array($arg) ? print_r($arg, true) : $arg;
		}
		$result .= '( '.implode(', ', $args).' ) <br/>';
	}
	if ($return) {
		return $result;
	}
	else {
		echo $result;
	}
}

function sdi($msg = 'SDI') {
	echo 'DEBUG INFO: ';
	var_dump($msg);
	echo SBR;
}

function sdii($msg = 'SDII') {
	echo 'DEBUG INFO: ';
	echo '<pre>'.print_r($msg, true).'</pre>';
	echo SBR;
}

function vdp($var, $msg = null) {
	echo 'DEBUG DUMP: ';
	if (isset($msg)) {
		echo '"'.$msg.'"'.SPACE;
	}
	var_dump($var);
	echo SBR;
}

function todo($msg) {
	echo 'TODO: '.$msg.SBR;
}

function sdff($msg, $fileName = '/tmp/zabbix.log') {
	$fileStreem = @fopen($fileName, 'a');
	if (is_array($msg)) {
		$toImplode = array();
		foreach ($msg as $key => $value) {
			$toImplode[] = var_export($key, true).'=>'.var_export($value, true);
		}
		@fwrite($fileStreem, 'array('.implode(',', $toImplode).')'."\n\n");
	} else {
		@fwrite($fileStreem, var_export($msg, true)."\n\n");
	}
	@fclose($fileStreem);
}

function sdf(&$var) {
	$value = $var;
	$var = $new = null;
	$varname = false;
	foreach ($GLOBALS as $key => $val) {
		if ($val === $new) {
			$varname = $key;
		}
	}

	echo '$'.$varname.'=';

	if (is_array($value) || is_object($value)) {
		echo '<pre>'.print_r($value, true).'</pre>';
	}
	else {
		echo $value;
	}
	echo SBR;
}
?>
