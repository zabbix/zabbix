<?php

class CConfigFile{
	public $configFile = null;
	public $config = array();
	public $error = '';

// STATIC methods
	private static function exception($error){
		throw new Exception($error);
	}

// PUBLIC methods
	public function __construct($file=null){
		$this->setDefaults();

		if(!is_null($file))
			$this->setFile($file);
	}

	public function setFile($file){
		$this->configFile = $file;
	}

	public function load(){
		try{
			if(!file_exists($this->configFile)){
				self::exception('Config file does not exist.');
			}

			ob_start();
			include($this->configFile);
			ob_end_clean();

// config file in plain php is bad
// {{{
			$dbs = array('MYSQL', 'POSTGRESQL', 'ORACLE', 'IBM_DB2', 'SQLITE3');
			if(!isset($DB['TYPE']) && !isset($DB_TYPE))
				self::exception('DB type is not set.');
			else if(isset($DB['TYPE']) && !in_array($DB['TYPE'], $dbs))
				self::exception('DB type has wrong value. Possible values '.implode(', ', $dbs));
			else if(isset($DB_TYPE) && !isset($DB['TYPE']) && !in_array($DB_TYPE, $dbs))
				self::exception('DB type has wrong value. Possible values '.implode(', ', $dbs));
			else if(!isset($DB['DATABASE']) && !isset($DB_DATABASE))
				self::exception('DB database is not set.');
// }}}

			$this->setDefaults();

			if(isset($DB['TYPE'])) $this->config['DB']['TYPE'] = $DB['TYPE'];
			else if(isset($DB_TYPE)) $this->config['DB']['TYPE'] = $DB_TYPE;

			if(isset($DB['DATABASE'])) $this->config['DB']['DATABASE'] = $DB['DATABASE'];
			else if(isset($DB_DATABASE)) $this->config['DB']['DATABASE'] = $DB_DATABASE;

			if(isset($DB['SERVER'])) $this->config['DB']['SERVER'] = $DB['SERVER'];
			else if(isset($DB_SERVER)) $this->config['DB']['SERVER'] = $DB_SERVER;

			if(isset($DB['PORT'])) $this->config['DB']['PORT'] = $DB['PORT'];
			else if(isset($DB_PORT)) $this->config['DB']['PORT'] = $DB_PORT;

			if(isset($DB['USER'])) $this->config['DB']['USER'] = $DB['USER'];
			else if(isset($DB_USER)) $this->config['DB']['USER'] = $DB_USER;

			if(isset($DB['PASSWORD'])) $this->config['DB']['PASSWORD'] = $DB['PASSWORD'];
			else if(isset($DB_PASSWORD)) $this->config['DB']['PASSWORD'] = $DB_PASSWORD;

			if(isset($DB['SCHEMA'])) $this->config['DB']['SCHEMA'] = $DB['SCHEMA'];

			if(isset($ZBX_SERVER)) $this->config['ZBX_SERVER'] = $ZBX_SERVER;
			if(isset($ZBX_SERVER_PORT)) $this->config['ZBX_SERVER_PORT'] = $ZBX_SERVER_PORT;
			if(isset($ZBX_SERVER_NAME)) $this->config['ZBX_SERVER_NAME'] = $ZBX_SERVER_NAME;

			return true;
		}
		catch(Exception $e){
			$this->error = $e->getMessage();
			return false;
		}
	}

	public function makeGlobal(){
		global $DB, $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_SERVER_NAME;

		$DB = $this->config['DB'];
		$ZBX_SERVER = $this->config['ZBX_SERVER'];
		$ZBX_SERVER_PORT = $this->config['ZBX_SERVER_PORT'];
		$ZBX_SERVER_NAME = $this->config['ZBX_SERVER_NAME'];
	}

	public function save(){
		try{
			if(is_null($this->configFile)){
				self::exception('Cannot save, config file is not set.');
			}

			$this->check();

			if(!file_put_contents($this->configFile, $this->getString())){
				self::exception('Cannot write config file.');
			}
		}
		catch(Exception $e){
			$this->error = $e->getMessage();
			return false;
		}
	}

	public function getString(){
		return
'<?php
// Zabbix GUI configuration file
global $DB;

$DB["TYPE"]				= \''.$this->config['DB']['TYPE'].'\';
$DB["SERVER"]			= \''.$this->config['DB']['SERVER'].'\';
$DB["PORT"]				= \''.$this->config['DB']['PORT'].'\';
$DB["DATABASE"]			= \''.$this->config['DB']['DATABASE'].'\';
$DB["USER"]				= \''.$this->config['DB']['USER'].'\';
$DB["PASSWORD"]			= \''.$this->config['DB']['PASSWORD'].'\';
// SCHEMA is relevant only for IBM_DB2 database
$DB["SCHEMA"]			= \''.$this->config['DB']['SCHEMA'].'\';

$ZBX_SERVER				= \''.$this->config['ZBX_SERVER'].'\';
$ZBX_SERVER_PORT		= \''.$this->config['ZBX_SERVER_PORT'].'\';
$ZBX_SERVER_NAME		= \''.$this->config['ZBX_SERVER_NAME'].'\';

$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;
?>
';
	}

// PROTECTED methods
	protected function setDefaults(){
		$this->config['DB'] = array(
			'TYPE' => null,
			'SERVER' => 'localhost',
			'PORT' => '0',
			'DATABASE' => null,
			'USER' => '',
			'PASSWORD' => '',
			'SCHEMA' => '',
		);
		$this->config['ZBX_SERVER'] = 'localhost';
		$this->config['ZBX_SERVER_PORT'] = '10051';
		$this->config['ZBX_SERVER_NAME'] = '';
	}

	protected function check(){
		$dbs = array('MYSQL', 'POSTGRESQL', 'ORACLE', 'IBM_DB2', 'SQLITE3');

		if(!isset($this->config['DB']['TYPE'])){
			self::exception('DB type is not set.');
		}
		else if(!in_array($this->config['DB']['TYPE'], $dbs)){
			self::exception('DB type has wrong value. Possible values '.implode(', ', $dbs));
		}
		else if(!isset($this->config['DB']['DATABASE'])){
			self::exception('DB database is not set.');
		}
	}
}
?>
