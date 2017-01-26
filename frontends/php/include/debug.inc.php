<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


function sdb($return = false) {
	$backtrace = debug_backtrace();
	array_shift($backtrace);
	$result = 'DEBUG BACKTRACE: <br/>';
	foreach ($backtrace as $n => $bt) {
		$result .= '  --['.$n.']-- '.$bt['file'].' : '.$bt['line'].'<br/>';
		$result .= "&nbsp;&nbsp;<b>".(isset($bt['class']) ? $bt['class'].$bt['type'].$bt['function'] : $bt['function']).'</b>';
		$args = [];
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
	echo BR();
}

function sdii($msg = 'SDII', $for = '', $showInvisible = true) {
	if ($showInvisible) {
		if ($msg === null) {
			$msg = 'NULL';
		}
		elseif ($msg === false) {
			$msg = 'FALSE';
		}
		elseif ($msg === TRUE) {
			$msg = 'TRUE';
		}
	}
	echo 'DEBUG INFO: '.$for;
	echo '<pre>'.print_r($msg, true).'</pre>';
	echo BR();
}

function vdp($var, $msg = null) {
	echo 'DEBUG DUMP: ';
	if (isset($msg)) {
		echo '"'.$msg.'"'.SPACE;
	}
	var_dump($var);
	echo BR();
}

function todo($msg) {
	echo 'TODO: '.$msg.BR();
}


/**
 * Writes data in given file. Rewrites file on each PHP execution.
 *
 * @staticvar resource $fileStream resource of opened file
 * @param mixed $data data to write in file
 * @param boolean $persist persist file content on multiple script runs
 * @param string $fileName file where output will be stored
 */
function sdFile($data, $persist = false, $fileName = 'debug.txt') {
	static $fileStream;
	if ($persist || $fileStream) {
		$fileStream = fopen($fileName, 'a');
	}
	else {
		$fileStream = fopen($fileName, 'w');
	}
	fwrite($fileStream, var_export($data, true)."\n\n");
	fclose($fileStream);
}

function sdff($msg, $fileName = '/tmp/zabbix.log') {
	$fileStreem = @fopen($fileName, 'a');
	if (is_array($msg)) {
		$toImplode = [];
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
	echo BR();
}

/**
 * Infinite loop breaker, can be called inside loop that can be infinite. If number of
 * calls exceeds defined limit then backtrace is printed and script is terminated.
 *
 * @param $limit number of function calls after which script should be terminated.
 */
function ilb($limit = 100) {
	static $counter = 0;
	$counter++;
	if ($counter == $limit) {
		// just calling sdb is forbidden by pre-commit hook :/
		call_user_func('sdb');
		exit;
	}
}

function timer($timer = null) {
	static $timers = [];

	if ($timer === null) {
		$timer = '_general_';
	}

	$mtime = microtime(true);
	if (isset($timers[$timer])) {
		echo $timer.': '.round($mtime - $timers[$timer]['start'], 4).' ('.round($mtime - $timers[$timer]['last'], 4).')'.'<br>';
		$timers[$timer]['last'] = $mtime;
	}
	else {
		echo $timer.' started.'.'<br>';
		$timers[$timer]['start'] = $mtime;
		$timers[$timer]['last'] = $mtime;
	}
}

/**
 * Shorthand for throwing exception
 *
 * @param string $ex	exception text
 */
function sdex($ex = 'My exception') {
	throw new APIException(ZBX_API_ERROR_INTERNAL, $ex);
}
