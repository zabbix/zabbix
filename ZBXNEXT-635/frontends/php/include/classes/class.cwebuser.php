<?php

class CWebUser {

	public static $data = null;

	public static function login($login, $password){
		global $USER_DETAILS;

		try{
			$sessionid = API::User()->login(array(
				'user' => $login,
				'password' => $password
			));
			if(!$sessionid) throw new Exception();

			$userData = API::User()->checkAuthentication($sessionid);
			if(!$userData) throw new Exception();

			if(empty($userData['url'])){
				$userData['url'] = CProfile::get('web.menu.view.last','index.php');
			}

			zbx_setcookie('zbx_sessionid', $sessionid, $userData['autologin'] ? (time()+86400*31) : 0);	//1 month

			self::$data = $USER_DETAILS = $userData;

			return true;
		}
		catch(Exception $e){
			self::setDefault();
			return false;
		}

		return self::$data;
	}

	public static function logout($sessionid){
		self::$data = API::User()->logout($sessionid);
		zbx_unsetcookie('zbx_sessionid');
	}

	public static function checkAuthentication($sessionid){
		global $USER_DETAILS;

		if($sessionid === null){
			self::setDefault();
			return false;
		}

		if($result = API::User()->checkAuthentication($sessionid)){
			self::$data = $USER_DETAILS = $result;
			return true;
		}
		else{
			self::setDefault();
			return false;
		}
	}

	private static function setDefault(){
		global $USER_DETAILS;
		self::$data = $USER_DETAILS = array(
			'alias'	=> ZBX_GUEST_USER,
			'userid'=> 0,
			'lang'	=> 'en_gb',
			'type'	=> '0',
			'node'	=> array( 'name'=>'- unknown -', 'nodeid'=>0 )
		);
	}
}

?>
