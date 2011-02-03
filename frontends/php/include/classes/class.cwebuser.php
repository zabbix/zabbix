<?php

class CWebUser {

	public static $data = null;

	public static function login($login, $password){
		try{
			self::$data = API::User()->login(array(
				'user' => $login,
				'password' => $password
			));
			if(!self::$data) throw new Exception();

			self::makeGlobal();

			if(!check_perm2login(self::$data['userid'])) throw new Exception();

			if(empty(self::$data['url'])){
				self::$data['url'] = CProfile::get('web.menu.view.last', 'index.php');
			}

			zbx_setcookie('zbx_sessionid', self::$data['sessionid'], self::$data['autologin'] ? (time()+86400*31) : 0);

			if(self::$data['attempt_failed']){
				CProfile::update('web.login.attempt.failed', self::$data['attempt_failed'], PROFILE_TYPE_INT);
				CProfile::update('web.login.attempt.ip', self::$data['attempt_ip'], PROFILE_TYPE_STR);
				CProfile::update('web.login.attempt.clock', self::$data['attempt_clock'], PROFILE_TYPE_INT);
				CProfile::flush();
			}

			self::makeGlobal();
			return true;
		}
		catch(Exception $e){
			self::setDefault();
			return false;
		}
	}

	public static function logout($sessionid){
		self::$data = API::User()->logout($sessionid);
		zbx_unsetcookie('zbx_sessionid');
	}

	public static function checkAuthentication($sessionid){
		try{
			if($sessionid === null) throw new Exception();

			self::$data = API::User()->checkAuthentication($sessionid);
			if(!self::$data){
				if(!self::login(ZBX_GUEST_USER, '')) throw new Exception();
			}
			self::makeGlobal();

			if(!check_perm2login(self::$data['userid'])) throw new Exception();

			self::makeGlobal();
			return true;
		}
		catch(Exception $e){
			self::setDefault();
			return false;
		}
	}

	private static function setDefault(){
		self::$data = array(
			'alias'	=> ZBX_GUEST_USER,
			'userid'=> 0,
			'lang'	=> 'en_gb',
			'type'	=> '0',
			'node'	=> array( 'name'=>'- unknown -', 'nodeid'=>0 )
		);

		self::makeGlobal();
	}

	private static function makeGlobal(){
		global $USER_DETAILS;
		$USER_DETAILS = self::$data;
	}
}

?>
