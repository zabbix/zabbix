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

	function check_php_version(){
		$required = '5.0';
		$recommended = '5.3.0';

		if(version_compare(phpversion(), $recommended, '>=')){
			$req = 2;
		}
		else if(version_compare(phpversion(), $required, '>=')){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP version',
			'current' => phpversion(),
			'required' => $required,
			'recommended' => $recommended,
			'result' => $req,
			'error' => 'Minimal version of PHP is 5.1.0'
		);

		return $result;
	}

	function check_php_memory_limit(){
		$required = 128*1024*1024;
		$recommended = 256*1024*1024;

		$current = ini_get('memory_limit');

		if(str2mem($current) >= $recommended){
			$req = 2;
		}
		else if(str2mem($current) >= $required){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP memory limit',
			'current' => $current,
			'required' => mem2str($required),
			'recommended' => mem2str($recommended),
			'result' => $req,
			'error' => '128M is a minimal PHP memory limitation'
		);

		return $result;
	}

	function check_php_post_max_size(){
		$required = 16*1024*1024;
		$recommended = 32*1024*1024;

		$current = ini_get('post_max_size');

		if(str2mem($current) >= $recommended){
			$req = 2;
		}
		else if(str2mem($current) >= $required){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP post max size',
			'current' => $current,
			'required' => mem2str($required),
			'recommended' => mem2str($recommended),
			'result' => $req,
			'error' => '16M is minimum size of PHP post'
		);

		return $result;
	}
	
	function check_php_upload_max_filesize(){
		$required = 2*1024*1024;
		$recommended = 16*1024*1024;

		$current = ini_get('upload_max_filesize');

		if(str2mem($current) >= $recommended){
			$req = 2;
		}
		else if(str2mem($current) >= $required){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP upload max filesize ',
			'current' => $current,
			'required' => mem2str($required),
			'recommended' => mem2str($recommended),
			'result' => $req,
			'error' => '2M is minimum for PHP upload filesize'
		);

		return $result;
	}

	function check_php_max_execution_time(){
		$required = 300;
		$recommended = 600;

		$current = ini_get('max_execution_time');

		if($current >= $recommended){
			$req = 2;
		}
		else if($current >= $required){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP max execution time',
			'current' => $current,
			'required' => $required,
			'recommended' => $recommended,
			'result' => $req,
			'error' => '300 sec is a minimal limitation on execution time of PHP scripts'
		);

		return $result;
	}

	function check_php_timezone(){
		$current = ini_get('date.timezone');
		$current = !empty($current);

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP timezone',
			'current' => $req ? ini_get('date.timezone') : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Timezone for PHP is not set. Please set "date.timezone" option in php.ini.'
		);

		return $result;
	}

	function check_php_databases(){
		$current = array();

		if(function_exists('mysql_pconnect') &&
			function_exists('mysql_select_db') &&
			function_exists('mysql_error') &&
			function_exists('mysql_query') &&
			function_exists('mysql_fetch_array') &&
			function_exists('mysql_fetch_row') &&
			function_exists('mysql_data_seek') &&
			function_exists('mysql_insert_id')){

			$current[] = 'MySQL';
		}

		if(function_exists('pg_pconnect') &&
			function_exists('pg_fetch_array') &&
			function_exists('pg_fetch_row') &&
			function_exists('pg_exec') &&
			function_exists('pg_getlastoid')){

			$current[] = 'PostgreSQL';
		}

		if(function_exists('ocilogon') &&
			function_exists('ocierror') &&
			function_exists('ociparse') &&
			function_exists('ociexecute') &&
			function_exists('ocifetchinto')){

			$current[] = 'Oracle';
		}

		if(function_exists('sqlite3_open') &&
			function_exists('sqlite3_close') &&
			function_exists('sqlite3_query') &&
			function_exists('sqlite3_error') &&
			function_exists('sqlite3_fetch_array') &&
			function_exists('sqlite3_query_close') &&
			function_exists('sqlite3_exec')){

			$current[] = 'SQLite3';
		}

		if(!empty($current)){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP databases support',
			'current' => empty($current) ? 'no' : new CJSscript(implode(SBR, $current)),
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Requires any database support [MySQL or PostgreSQL or Oracle or SQLite3]'
		);

		return $result;
	}

	function check_php_bc(){

		$current = function_exists('bcadd') &&
			function_exists('bccomp') &&
			function_exists('bcdiv') &&
			function_exists('bcmod') &&
			function_exists('bcmul') &&
			function_exists('bcpow') &&
			function_exists('bcpowmod') &&
			function_exists('bcscale') &&
			function_exists('bcsqrt') &&
			function_exists('bcsub');

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP BC math',
			'current' => $req ? 'yes' : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Requires bcmath module [configure PHP with --enable-bcmath]'
		);

		return $result;
	}

	function check_php_mbstring(){

		$current = function_exists('bcadd') &&
			function_exists('bccomp') &&
			function_exists('bcdiv') &&
			function_exists('bcmod') &&
			function_exists('bcmul') &&
			function_exists('bcpow') &&
			function_exists('bcpowmod') &&
			function_exists('bcscale') &&
			function_exists('bcsqrt') &&
			function_exists('bcsub');

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP MB string',
			'current' => $req ? 'yes' : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Requires mb string module [configure PHP with --enable-mbstring]'
		);

		return $result;
	}

	function check_php_sockets(){

		$current = function_exists('socket_create');

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP Sockets',
			'current' => $req ? 'yes' : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Required Sockets module [configured PHP with --enable-sockets]'
		);

		return $result;
	}

	function check_php_gd(){

		$required = '2.0';
		$recommended = '2.0.34';

		if(is_callable('gd_info')){
			$gd_info = gd_info();
			preg_match('/(\d\.?)+/', $gd_info['GD Version'], $current);
			$current = $current[0];
		}
		else{
			$current = 'unknown';
		}

		if(version_compare($current, $recommended, '>=')){
			$req = 2;
		}
		else if(version_compare($current, $required, '>=')){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'PHP GD',
			'current' => $current,
			'required' => $required,
			'recommended' => $recommended,
			'result' => $req,
			'error' => 'The GD extension isn\'t loaded.'
		);

		return $result;
	}

	function check_php_gd_png(){

		if(is_callable('gd_info')){
			$gd_info = gd_info();
			$current = isset($gd_info['PNG Support']);
		}
		else{
			$current = false;
		}

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'GD PNG Support',
			'current' => $req ? 'yes' : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Requires images generation support [PNG]'
		);

		return $result;
	}

	function check_php_xml(){
		$required = '2.6.15';
		$recommended = '2.7.6';

		$current = constant('LIBXML_DOTTED_VERSION');
		if(version_compare($current, $recommended, '>=')){
			$req = 2;
		}
		else if(version_compare($current, $required, '>=')){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'libxml module',
			'current' => $current,
			'required' => $required,
			'recommended' => $recommended,
			'result' => $req,
			'error' => 'php-xml module is not installed'
		);

		return $result;
	}

	function check_php_ctype(){

		$current = function_exists('ctype_alnum') &&
			function_exists('ctype_alpha') &&
			function_exists('ctype_cntrl') &&
			function_exists('ctype_digit') &&
			function_exists('ctype_graph') &&
			function_exists('ctype_lower') &&
			function_exists('ctype_print') &&
			function_exists('ctype_punct') &&
			function_exists('ctype_space') &&
			function_exists('ctype_xdigit') &&
			function_exists('ctype_upper');

		if($current){
			$req = 1;
		}
		else{
			$req = 0;
		}

		$result = array(
			'name' => 'ctype module',
			'current' => $req ? 'yes' : 'no',
			'required' => null,
			'recommended' => null,
			'result' => $req,
			'error' => 'Requires ctype module [configure PHP with --enable-ctype]'
		);

		return $result;
	}

	function check_php_requirements(){
		$result = array();

		$result[] = check_php_version();
		$result[] = check_php_memory_limit();
		$result[] = check_php_post_max_size();
		$result[] = check_php_upload_max_filesize();
		$result[] = check_php_max_execution_time();
		$result[] = check_php_timezone();
		$result[] = check_php_databases();
		$result[] = check_php_bc();
		$result[] = check_php_mbstring();
		$result[] = check_php_sockets();
		$result[] = check_php_gd();
		$result[] = check_php_gd_png();
		$result[] = check_php_xml();
		$result[] = check_php_ctype();

		return $result;
	}

?>
