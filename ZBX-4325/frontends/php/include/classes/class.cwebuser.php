<?php

class CWebUser{

	public static $data = null;

	public static function login($login, $password){
		try{
			self::setDefault();

			self::$data = API::User()->login(array(
				'user' => $login,
				'password' => $password,
				'userData' => true
			));

			if(!self::$data) throw new Exception();

			if(self::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED){
				error(_('GUI access disabled.'));
				throw new Exception();
			}

			if(empty(self::$data['url'])){
				self::$data['url'] = CProfile::get('web.menu.view.last', 'index.php');
			}


			if(isset(self::$data['attempt_failed']) && self::$data['attempt_failed']){
				CProfile::init();
				CProfile::update('web.login.attempt.failed', self::$data['attempt_failed'], PROFILE_TYPE_INT);
				CProfile::update('web.login.attempt.ip', self::$data['attempt_ip'], PROFILE_TYPE_STR);
				CProfile::update('web.login.attempt.clock', self::$data['attempt_clock'], PROFILE_TYPE_INT);
				CProfile::flush();
			}

			zbx_setcookie('zbx_sessionid', self::$data['sessionid'], self::$data['autologin'] ? time() + SEC_PER_DAY * 31 : 0);

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
			if($sessionid !== null){
				self::$data = API::User()->checkAuthentication($sessionid);
			}

			if(($sessionid === null) || !self::$data){
				self::setDefault();
				self::$data = API::User()->login(array(
					'user' => ZBX_GUEST_USER,
					'password' => '',
					'userData' => true
				));

				if(!self::$data){
					clear_messages(1);
					throw new Exception();
				}

				$sessionid = self::$data['sessionid'];
			}

			if(self::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED){
				error(_('GUI access disabled.'));
				throw new Exception();
			}

			zbx_setcookie('zbx_sessionid', $sessionid, self::$data['autologin'] ? time() + SEC_PER_DAY * 31 : 0);

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
			'node'	=> array('name' => '- unknown -', 'nodeid' => 0)
		);

		self::makeGlobal();
	}

	private static function makeGlobal(){
		global $USER_DETAILS;
		$USER_DETAILS = self::$data;
	}
}

?>
