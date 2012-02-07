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
?>
<?php

class CZBXAPI{
public static $error = array();
private static $transaction = array('counter' => 0);

// TRANSACTION METHODS{
	protected static function BeginTransaction($caller = 'CZBXAPI'){
		global $DB;

		if(!isset(self::$transaction[$caller])) self::$transaction[$caller] = 0;
		self::$transaction[$caller]++;

//SDII(self::$transaction);
		if(self::$transaction['counter'] > 0){
			self::$transaction['counter']++;
		}
		else{
			if($DB['TRANSACTIONS'] == 0){
				DBstart();
				self::$transaction['counter'] = 1;
				self::$transaction['owner'] = $caller;
			}
			else{
				self::$transaction['counter'] = 2;
				self::$transaction['owner'] = 'DB_CLASS';
			}
		}

//SDII(self::$transaction);

	return true;
	}

	protected static function EndTransaction($result = true, $caller = 'CZBXAPI'){
		$result = $result;

		if(!isset(self::$transaction[$caller])){
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL,
									'data' => 'Trying to end not started transaction from: '.$caller
									);
		}
		else if((self::$transaction['owner'] == $caller) && (self::$transaction[$caller] == 1)){
			if(self::$transaction['counter'] > 1){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL,
										'data' => 'Ending transaction regardless to opened logical subtransactions: '.$caller
										);
			}

			unset(self::$transaction['owner']);
			self::$transaction[$caller] = 0;
			self::$transaction['counter'] = 0;

			$result = DBend($result);
		}
		else{
			if(self::$transaction[$caller] > 0) self::$transaction[$caller]--;
			else self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL,
										'data' => 'Attempt to close not started transaction from: '.$caller
										);

			if(self::$transaction['counter'] > 0) self::$transaction['counter']--;
			else self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL,
										'data' => 'Count of opened transaction is not equal to attempts to close them. Attemp from: '.$caller
										);
		}

	return $result;
	}

	protected static function endAPITransactions($result){
		if((self::$transaction['counter'] > 0) && (self::$transaction['owner'] != 'DB_CLASS')){
			unset(self::$transaction['owner']);
			self::$transaction['counter'] = 0;

			$result = DBend($result);
		}
	}
// TRANSACTION METHODS}

// ERROR METHODS{
	protected static function setError($method, $errno = ZBX_API_ERROR_INTERNAL, $error = 'Unknown Zabbix internal error') {
		if ($error) {
			CZBXAPI::$error[] = array('error' => $errno, 'data' => '[ '.$method.' ] '.$error);
		}
	}

	protected static function setMethodErrors($method, $errors){
		global $ZBX_MESSAGES;

		if(empty($errors)){
			$php_error = is_array($ZBX_MESSAGES) ? end($ZBX_MESSAGES) : false;
			while($php_error && ($php_error['type'] != 'error')){
				$php_error = prev($ZBX_MESSAGES);
			}

			$php_error = $php_error ? $php_error['message'] : null;

			if(is_null($php_error)){
				self::setError($method);
			}
			else{
				self::setError($method, ZBX_API_ERROR_INTERNAL, $php_error);
			}
		}
		else{
			foreach($errors as $enum => $error){
				self::setError($method, $error['errno'], $error['error']);
			}
		}

	}

	public static function clearErrors(){
		self::$error = array();
	}

	public static function getErrorMessages(){
		$return = array();
		foreach(self::$error as $error){
			$return[] = $error['data'];
		}

	return $return;
	}

	public static function resetErrors(){
		$errors = self::getErrorMessages();
		self::clearErrors();

	return $errors;
	}

// ERROR METHODS}

	protected static function exception($code=ZBX_API_ERROR_INTERNAL, $errors=array()){
		throw new APIException($code, $errors);
	}

}
