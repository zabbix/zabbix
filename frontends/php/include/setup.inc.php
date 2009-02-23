<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	function zbx_is_callable($var){
		foreach($var as $e)
			if(!is_callable($e)) return false;

		return true;
	}

	class CsetupWizard extends CForm{
/* protected *//*
		var $ZBX_CONFIG;
		var $DISABLE_NEXT_BUTTON;
		var $stage = array();
		*/

/* public */	
		function __construct(&$ZBX_CONFIG){
			$this->DISABLE_NEXT_BUTTON = false;
			
			$this->ZBX_CONFIG = &$ZBX_CONFIG;

			$this->stage = array(
				0 => array('title' => '1. Introduction'			, 'fnc' => 'stage0' ),
				1 => array('title' => '2. Licence Agreement'		, 'fnc' => 'stage1' ),
				2 => array('title' => '3. Check of pre-requisites'	, 'fnc' => 'stage2' ),
				3 => array('title' => '4. Configure DB connection'	, 'fnc' => 'stage3' ),
				4 => array('title' => '5. ZABBIX server details'	, 'fnc' => 'stage4' ),
				//4 => array('title' => '5. Distributed monitoring'	, 'fnc' => 'stage4' ),
				5 => array('title' => '6. Pre-Installation Summary'	, 'fnc' => 'stage5' ),
				6 => array('title' => '7. Install'			, 'fnc' => 'stage6' ),
				7 => array('title' => '8. Finish'			, 'fnc' => 'stage7' )
				);
			
			$this->EventHandler();

			parent::__construct(null, 'post');
		}

		function getConfig($name, $default = null){
			return isset($this->ZBX_CONFIG[$name]) ? $this->ZBX_CONFIG[$name] : $default;
		}
		
		function setConfig($name, $value){
			return ($this->ZBX_CONFIG[$name] = $value);
		}

		function getStep(){
			return $this->getConfig('step', 0);
		}
		
		function DoNext(){
			if(isset($this->stage[$this->getStep() + 1])){
				$this->ZBX_CONFIG['step']++;
				return true;
			}
		return false;
		}

		function DoBack(){
			if(isset($this->stage[$this->getStep() - 1])){
				$this->ZBX_CONFIG['step']--;
				return true;
			}
		return false;
		}

		function BodyToString($destroy=true){
			$table = new CTable(null, 'setup_wizard');
			$table->setAlign('center');
			$table->setHeader(array(
				new CCol(S_ZABBIX_VER, 'left'), 
				SPACE
				),'header');
			$table->addRow(array(SPACE, new CCol($this->stage[$this->getStep()]['title'], 'right')),'title');
			$table->addRow(array(
				new CCol($this->getList(), 'left'),
				new CCol($this->getState(), 'right')
				), 'center');
			
			$next = new CButton('next['.$this->getStep().']', S_NEXT.' >>');
			if($this->DISABLE_NEXT_BUTTON) $next->setEnabled(false);
			
			$table->setFooter(array(
				new CCol(new CButton('cancel',S_CANCEL),'left'), 
				new CCol(array(
					isset($this->stage[$this->getStep()-1]) ? new CButton('back['.$this->getStep().']', '<< '.S_PREVIOUS) : null,
					isset($this->stage[$this->getStep()+1]) ? $next: new CButton('finish', S_FINISH)
					) , 'right')
				),'footer');

			return parent::BodyToString($destroy).$table->ToString();
		}

		function getList(){
			$list = new CList();
			foreach($this->stage as $id => $data){
				if($id < $this->getStep()) $style = 'completed';
				else if($id == $this->getStep()) $style = 'current';
				else $style = null;
				
				$list->addItem($data['title'], $style);
			}
		return $list;
		}

		function getState(){
			$fnc = $this->stage[$this->getStep()]['fnc'];
			return  $this->$fnc();
		}

		function stage0(){			
			return new CTag('div', 'yes', array('Welcome to the ZABBIX frontend installation wizard.',BR(),BR(),
				'This installation wizard will guide you through the installation of ZABBIX frontend',BR(),BR(),
				'Click to "Next" button to proceed to the next screen. If you want to change something '.
				'on a previous screen, click "Previous" button',BR(),BR(),
				'You may cancel installation at any time by clicking "Cancel" button'), 'text');
		}

		function stage1(){
			$LICENCE_FILE = 'conf/COPYING';

			$this->DISABLE_NEXT_BUTTON = !$this->getConfig('agree', false);
							
			return array(
				new CTag('div', 'yes', (file_exists($LICENCE_FILE) ? 
					new CScript(nl2br(nbsp(htmlspecialchars(file_get_contents($LICENCE_FILE))))) : 
					'Missing licence file. See GPL licence.')
				, 'licence'),
				BR(),
				new CTag('div', 'yes',
					array(
						new CCheckBox(
							'agree', 
							$this->getConfig('agree', false),
							'submit();'),
						'I agree'),
					'center')
				);
		}

		function get_test_result(&$result, $test_name, $test_value, $condition, $fail_message){
			$result &= $condition;
			
			$row = new CRow(array(
					$test_name,
					$test_value,
					$condition ? new CSpan(S_OK,'ok') : new CSpan(S_FAIL,'fail')
				),
				!$condition ? 'fail' : null);
			
			if(!$condition && isset($fail_message))
				$row->setHint($fail_message);

			return $row;
		}

		function stage2(){
			$final_result = true;

			$table = new CTable(null, 'requirements');
			$table->setAlign('center');
			
			/* Check PHP version */
			$table->addRow($this->get_test_result(
				$final_result,
				'PHP version: ',
				phpversion(),
				version_compare(phpversion(), '4.3.0', '>='),
				'Minimal version of PHP is 4.3.0'));

			$memory_limit = str2mem(ini_get('memory_limit'));
			$table->addRow($this->get_test_result(
				$final_result,
				'PHP Memory limit:',
				function_exists('memory_get_usage') ? mem2str($memory_limit) : 'unlimited',
				$memory_limit >= 8*1024*1024 || !function_exists('memory_get_usage'),
				'8M is a minimal PHP memory limitation'));

			$memory_limit = str2mem(ini_get('post_max_size'));
			$table->addRow(
				$this->get_test_result(
					$final_result,
					'PHP post max size:',
					mem2str($memory_limit),
					$memory_limit >= 8*1024*1024,
					'8M is minimum size of PHP post'));

			$table->addRow(
				$this->get_test_result(
					$final_result,
					'PHP max execution time:',
					ini_get('max_execution_time').' sec',
					ini_get('max_execution_time') >= 300,
					'300 sec is a maximal limitation on execution of PHP scripts'));
			
			/* Check supporteds databases */
			global $ZBX_CONFIG;

			$table->addRow(
				$this->get_test_result(
					$final_result,
					'PHP Databases support: ',
					new CScript(implode(SBR, $ZBX_CONFIG['allowed_db'])),
					!isset($ZBX_CONFIG['allowed_db']['no']),
					'Required any databases support [MySQL or PostgreSQL or Oracle]'));

/* Check BC math */
			$bcmath_fnc_exist = 
				function_exists('bcadd') &&
				function_exists('bccomp') &&
				function_exists('bcdiv') &&
				function_exists('bcmod') &&
				function_exists('bcmul') &&
				function_exists('bcpow') &&
/* This function is supported by PHP5 only */
/*				function_exists('bcpowmod') &&*/
				function_exists('bcscale') &&
				function_exists('bcsqrt') &&
				function_exists('bcsub');
			$table->addRow(
				$this->get_test_result(
					$final_result,
					'PHP BC math support',
					$bcmath_fnc_exist ? 'yes' : 'no',
					$bcmath_fnc_exist,
					'Required bcmath module [configured PHP with --enable-bcmath]'));
					
/* Check mb-strings 
			$mbstrings_fnc_exist = mbstrings_available();
			$table->addRow(
				$this->get_test_result(
					$final_result,
					'PHP MB String support',
					$mbstrings_fnc_exist ? 'yes' : 'no',
					$mbstrings_fnc_exist,
					'Required Multibyte String module [configured PHP with --enable-mbstring]'));
//*/

			/* Check GD existence */
			$gd_version = S_NO;
			if(is_callable('gd_info')){
				$gd_info = gd_info();
				$gd_version = $gd_info['GD Version'];
			}
			
			$table->addRow(
				$this->get_test_result(
					$final_result,
					'GD Version:',
					$gd_version,
					$gd_version != S_NO,
					'The GD extension isn\'t loaded.'));

			/* Check supported image formats */
			$img_formats = array();
			if(isset($gd_info)){
				//if($gd_info['JPG Support']) array_push($img_formats, 'JPEG');
				if($gd_info['PNG Support']) array_push($img_formats, 'PNG');
			}
			
			if(count($img_formats) == 0){
				$img_formats = array(S_NO);
				$no_img_formats = true;
			}
			$table->addRow(
				$this->get_test_result(
					$final_result,
					'Image formats:', 
					$img_formats,
					!isset($no_img_formats),
					'Required images genetarion support [PNG]'));	
			
			if(version_compare(phpversion(), '5.1.0RC1', '>=')){
				$tmezone = ini_get('date.timezone');
				$table->addRow(
					$this->get_test_result(
						$final_result,
						'PHP Timezone:', 
						empty($tmezone) ? 'n/a' : $tmezone,
						!empty($tmezone),
						'Timezone for PHP is not set. Please set "date.timezone" option in php.ini.'));
				unset($tmezone);
			}

			if(!$final_result){
				$this->DISABLE_NEXT_BUTTON = true;
				
				$this->addVar('trouble',true);
				
				$final_result = array(
					new CSpan(S_FAIL,'fail'),
					BR(), BR(),
					'Please correct all issuse and press "Retry" button',
					BR(), BR(),
					new CButton('retry', S_RETRY)
					);
			}
			else{
				$this->DISABLE_NEXT_BUTTON = false;
				$final_result = new CSpan(S_OK,'ok');
			}
			
		return array($table, BR(), $final_result);
		}

		function stage3(){
			global $ZBX_CONFIG;

			$table = new CTable();
			$table->setAlign('center');
			
			$DB['TYPE'] = $this->getConfig('DB_TYPE');

			$cmbType = new CComboBox('type', $DB['TYPE']);
			foreach($ZBX_CONFIG['allowed_db'] as $id => $name){
				$cmbType->addItem($id, $name);
			}
			$table->addRow(array(S_TYPE, $cmbType));
			$table->addRow(array(S_HOST, new CTextBox('server',		$this->getConfig('DB_SERVER',	'localhost'))));
			$table->addRow(array(S_PORT, array(new CNumericBox('port',		$this->getConfig('DB_PORT',	'0'),5),' 0 - use default port')));
			$table->addRow(array(S_NAME, new CTextBox('database',		$this->getConfig('DB_DATABASE',	'zabbix'))));
			$table->addRow(array(S_USER, new CTextBox('user',		$this->getConfig('DB_USER',	'root'))));
			$table->addRow(array(S_PASSWORD, new CPassBox('password',	$this->getConfig('DB_PASSWORD',	''))));

			return array(
				'Please create database manually.', BR(),
				'And set the configuration parameters of connection to this database.',
				BR(),BR(),
				'And press "Test connection" button.',
				BR(),BR(),
				$table,
				BR(),
				!$this->DISABLE_NEXT_BUTTON ? new CSpan(S_OK,'ok') :  new CSpan(S_FAIL, 'fail'),
				BR(),
				new  CButton('retry', 'Test connection')
				);
		}

		function stage4(){
			global $ZBX_CONFIG;

			$table = new CTable();
			$table->setAlign('center');
			
			$table->addRow(array(S_HOST, new CTextBox('zbx_server',		$this->getConfig('ZBX_SERVER',		'localhost'))));
			$table->addRow(array(S_PORT, new CNumericBox('zbx_server_port',	$this->getConfig('ZBX_SERVER_PORT',	'10051'),5)));

			return array(
				'Please enter host name or host IP address', BR(),
				'and port number of ZABBIX server', BR(), BR(),
				$table,
				);
		}
		/*
		function stage4()
		{
			global $_SERVER;

			if($this->getConfig('distributed', null))
			{
				$table = new CTable();
				$table->setAlign('center');
				$table->addRow(array(
					'Node name',
					new CTextBox('nodename', $this->getConfig('nodename',    $_SERVER["SERVER_NAME"]), 40)
					));
				$table->addRow(array(
					'Node ID',
					new CNumericBox('nodeid', $this->getConfig('nodeid',      0), 10)
					));
				
			}
			else
			{
				$table = null;
			}

			return new CTag('div', 'yes', array(
				'The goal in the distributed monitoring environment is a service checks from a "central" server '.
				'onto one or more "distributed" servers. Most small to medium sized systems '.
				'will not have a real need for setting up such an environment.',BR,BR,
				'Please check the "Use distributed monitoring" to enabling this functionality',BR,BR,
				 new CTag('div', 'yes', array(
				 	new CCheckBox('distributed', $this->getConfig('distributed', null), 'submit();'),
					'Use distributed monitoring'),
					'center'),
				BR,BR,
				$table
				), 'text');
		}
		*/
		
		function stage5(){
			$allowed_db = $this->getConfig('allowed_db', array());

			$table = new CTable(null, 'requirements');
			$table->setAlign('center');
			$table->addRow(array('Database type:',		$allowed_db[$this->getConfig('DB_TYPE',	'unknown')]));
			$table->addRow(array('Database server:',	$this->getConfig('DB_SERVER',	'unknown')));
			$table->addRow(array('Database port:',		$this->getConfig('DB_PORT',	'0')));
			$table->addRow(array('Database name:',		$this->getConfig('DB_DATABASE',	'unknown')));
			$table->addRow(array('Database user:',		$this->getConfig('DB_USER',	'unknown')));
			$table->addRow(array('Database password:',	ereg_replace('.','*',$this->getConfig('DB_PASSWORD',	'unknown'))));
			/* $table->addRow(array('Distributed monitoring',	$this->getConfig('distributed', null) ? 'Enabled' : 'Disabled')); */
			
			if($this->getConfig('distributed', null)){
				$table->addRow(array('Node name',	$this->getConfig('nodename',	'unknown')));
				$table->addRow(array('Node GUID',	$this->getConfig('nodeid',	'unknown')));
			}

			$table1 = new CTable(null, 'requirements');
			$table1->setAlign('center');
			$table1->addRow(array('ZABBIX server:',		$this->getConfig('ZBX_SERVER',		'unknown')));
			$table1->addRow(array('ZABBIX server port:',	$this->getConfig('ZBX_SERVER_PORT',	'unknown')));
			return array(
				'Please check configuration parameters.', BR(),
				'If all correct press "Next" button, or "Previous" button to change configuration parameters.', BR(), BR(),
				$table, BR(),
				$table1
				);
		}

		function stage6(){
			global $ZBX_CONFIGURATION_FILE;

			show_messages();
			/* Write the new contents */
			if($f = @fopen($ZBX_CONFIGURATION_FILE, 'w')){
				if(fwrite($f, $this->getNewConfigurationFileContent())){
					if(fclose($f)){
						if($this->setConfig('ZBX_CONFIG_FILE_CORRECT', $this->CheckConfigurationFile())){
							$this->DISABLE_NEXT_BUTTON = false;
						}
					}
				}
			}
			clear_messages(); /* don't show errors */
			
			$table = new CTable(null, 'requirements');
			$table->setAlign('center');

			$table->addRow(array('Configuration file:',  $this->getConfig('ZBX_CONFIG_FILE_CORRECT', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));

			/*
			$table->addRow(array('Table creation:',  $this->getConfig('ZBX_TABLES_CREATED', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));
			
			$table->addRow(array('Data loading:',  $this->getConfig('ZBX_DATA_LOADED', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));
			*/

			return array(
				$table, BR(),
				$this->DISABLE_NEXT_BUTTON ? array(new CButton('retry', S_RETRY), BR(),BR()) : null,
				!$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false) ? 
					array('Please install configuration file manualy.',BR(),BR(),
						'By pressing "Save configuration file" button download configuration file ',
						'and place them into the ',BR(),
						'"'.$ZBX_CONFIGURATION_FILE.'"',BR(),BR(),
						new CButton('save_config',"Save configuration file"),
						BR(),BR()
						)
					: null,
				'Press the '.($this->DISABLE_NEXT_BUTTON ? '"Retry"' : '"Next"').' button'
				);
		}

		function stage7(){
			return array(
				'Congratulation with succesfull instalation of ZABBIX frontend.',BR(),BR(),
				'Press "Finish" button to complete installation'
				);
		}

		function CheckConnection(){
			global $DB;

//			$old_DB		= $DB['DB'];
			if(!empty($DB)){
				$old_DB			= true;
				$old_DB_TYPE	= $DB['TYPE'];
				$old_DB_SERVER	= $DB['SERVER'];
				$old_DB_PORT	= $DB['PORT'];
				$old_DB_DATABASE= $DB['DATABASE'];
				$old_DB_USER	= $DB['USER'];
				$old_DB_PASSWORD= $DB['PASSWORD'];
			}
			
			$DB['TYPE']	= $this->getConfig('DB_TYPE');
			if(is_null($DB['TYPE']))	return false;

			$DB['SERVER']	= $this->getConfig('DB_SERVER',		'localhost');
			$DB['PORT']		= $this->getConfig('DB_PORT',		'0');
			$DB['DATABASE']	= $this->getConfig('DB_DATABASE',	'zabbix');
			$DB['USER']		= $this->getConfig('DB_USER',		'root');
			$DB['PASSWORD']	= $this->getConfig('DB_PASSWORD',	'');

			$error = '';
			if(!$result = DBconnect($error)){
				error($error);
			}
			else{
				$result = DBexecute('CREATE table zabbix_installation_test ( test_row integer )');
				$result &= DBexecute('DROP table zabbix_installation_test');
			}
			
			DBclose();

			if($DB['TYPE'] == 'SQLITE3' && !zbx_is_callable(array('sem_get','sem_acquire','sem_release','sem_remove'))){
				error('SQLite3 required IPC functions');
				$result &= false;
			}
			
			/* restore connection */
			global $DB;

			if(isset($old_DB)){
				$DB['TYPE']		= $old_DB_TYPE;
				$DB['SERVER']	= $old_DB_SERVER;
				$DB['PORT']		= $old_DB_PORT;
				$DB['DATABASE']	= $old_DB_DATABASE;
				$DB['USER']		= $old_DB_USER;
				$DB['PASSWORD']	= $old_DB_PASSWORD;
			}
			
			DBconnect($error);

		return $result;
		}
		
		/*
		function CreateTables()
		{
			global $ZBX_CONFIGURATION_FILE;

			$error = null;
			if(file_exists($ZBX_CONFIGURATION_FILE))
			{
				include $ZBX_CONFIGURATION_FILE;
			
				switch($DB['TYPE'])
				{
					case 'MYSQL':		$ZBX_SCHEMA_FILE = 'mysql.sql';		break;
					case 'POSTGRESQL':	$ZBX_SCHEMA_FILE = 'postgresql.sql';	break;
					case 'ORACLE':		$ZBX_SCHEMA_FILE = 'oracle.sql';	break;
				}

				if(isset($ZBX_SCHEMA_FILE))
				{
					$ZBX_SCHEMA_FILE = 'create/'.$ZBX_SCHEMA_FILE;
					if(DBconnect($error))
					{
						DBloadfile($ZBX_SCHEMA_FILE, $error);
					}
				}
				else
				{
					$error = 'Table creation. Incorrect configuration file ['.$ZBX_CONFIGURATION_FILE.']';
				}
				DBclose();
			}
			else
			{
				$error = 'Table creation. Missing configuration file['.$ZBX_CONFIGURATION_FILE.']';
			}
			if(isset($error))
			{
				error($error);
			}

			return !isset($error);
		}
		*/

		/*
		function LoadData()
		{
			global $ZBX_CONFIGURATION_FILE;

			$error = null;
			if(file_exists($ZBX_CONFIGURATION_FILE))
			{
				include $ZBX_CONFIGURATION_FILE;
			
				$ZBX_DATA_FILE = 'create/data.sql';
				if(DBconnect($error))
				{
					if(DBloadfile($ZBX_DATA_FILE, $error))
					{
						if($this->getConfig('distributed', null))
						{
							if(!DBexecute('insert into nodes (nodeid, name, nodetype) values('.
								$this->getConfig('nodeid', 0).','.
								zbx_dbstr($this->getConfig('nodename', 'local')).','.
								'1)'))
							{
								$error = '';
							}
						}
					}
				}
				DBclose();
			}
			else
			{
				$error = 'Table creation. Missing configuration file['.$ZBX_CONFIGURATION_FILE.']';
			}
			if(isset($error))
			{
				error($error);
			}

			return !isset($error);
		}
		*/

		function CheckConfigurationFile()
		{
			global $DB,$ZBX_SERVER,$ZBX_SERVER_PORT;

			if(!empty($DB)){
				$old_DB				= true;
				$old_DB_TYPE		= $DB['TYPE'];
				$old_DB_SERVER		= $DB['SERVER'];
				$old_DB_PORT		= $DB['PORT'];
				$old_DB_DATABASE	= $DB['DATABASE'];
				$old_DB_USER		= $DB['USER'];
				$old_DB_PASSWORD	= $DB['PASSWORD'];
				
				$old_ZBX_SERVER		= $ZBX_SERVER;
				$old_ZBX_SERVER_PORT	= $ZBX_SERVER_PORT;
			}
			

			$error = null;
			$error_msg = null;

			global $ZBX_CONFIGURATION_FILE;
						
			if(file_exists($ZBX_CONFIGURATION_FILE)){
				include $ZBX_CONFIGURATION_FILE;

				if(	isset($DB['TYPE']) && 
					isset($DB['SERVER']) && 
					isset($DB['DATABASE']) && 
					isset($DB['USER']) && 
					isset($DB['PASSWORD']) &&
					isset($ZBX_SERVER) &&
					isset($ZBX_SERVER_PORT) &&
					isset($IMAGE_FORMAT_DEFAULT) &&
					$DB['TYPE']		== $this->getConfig('DB_TYPE',				null) &&
					$DB['SERVER']		== $this->getConfig('DB_SERVER',			null) &&
					$DB['PORT']		== $this->getConfig('DB_PORT',				null) &&
					$DB['DATABASE']		== $this->getConfig('DB_DATABASE',			null) &&
					$DB['USER']		== $this->getConfig('DB_USER',				null) &&
					$DB['PASSWORD']		== $this->getConfig('DB_PASSWORD',			null)
					)
				{
					if(!DBconnect($error_msg)){
						$error_msg = 'Can not connect to database';
					}
				}
				else{
					$error_msg = 'Incorrect configuration file['.$ZBX_CONFIGURATION_FILE.']';
				}
				DBclose();
			}
			else{
				$error = 'Missing configuration file['.$ZBX_CONFIGURATION_FILE.']';
			}
			
			if(isset($error_msg)){
				error($error_msg);
			}

			/* restore connection */
			global $DB;

			if(isset($old_DB)){
				$DB['TYPE']		= $old_DB_TYPE;
				$DB['SERVER']	= $old_DB_SERVER;
				$DB['PORT']		= $old_DB_PORT;
				$DB['DATABASE']	= $old_DB_DATABASE;
				$DB['USER']		= $old_DB_USER;
				$DB['PASSWORD']	= $old_DB_PASSWORD;
				
				$ZBX_SERVER	= $old_ZBX_SERVER;
				$ZBX_SERVER_PORT= $old_ZBX_SERVER_PORT;
			}

			DBconnect($error2);

			return !isset($error)&&!isset($error_msg);
		}
		
		function EventHandler(){
			if(isset($_REQUEST['back'][$this->getStep()]))	$this->DoBack();	

			if($this->getStep() == 1){
				if(!isset($_REQUEST['next'][0]) && !isset($_REQUEST['back'][2])){
					$this->setConfig('agree', isset($_REQUEST['agree']));
				}
				
				if(isset($_REQUEST['next'][$this->getStep()]) && $this->getConfig('agree', false)){
					$this->DoNext();
				}
			}
			
			if($this->getStep() == 2 && isset($_REQUEST['next'][$this->getStep()]) && !isset($_REQUEST['trouble'])){
				$this->DoNext();
			}
			if($this->getStep() == 3){
				$this->setConfig('DB_TYPE',	get_request('type',	$this->getConfig('DB_TYPE')));
				$this->setConfig('DB_SERVER',	get_request('server',	$this->getConfig('DB_SERVER',	'localhost')));
				$this->setConfig('DB_PORT',	get_request('port',	$this->getConfig('DB_PORT',	'0')));
				$this->setConfig('DB_DATABASE',	get_request('database',	$this->getConfig('DB_DATABASE',	'zabbix')));
				$this->setConfig('DB_USER',	get_request('user',	$this->getConfig('DB_USER',	'root')));
				$this->setConfig('DB_PASSWORD',	get_request('password',	$this->getConfig('DB_PASSWORD',	'')));
		
				if(!$this->CheckConnection()){
					$this->DISABLE_NEXT_BUTTON = true;
					unset($_REQUEST['next']);
				}
				
				if(isset($_REQUEST['next'][$this->getStep()]))		$this->DoNext();
			}

			if($this->getStep() == 4){
				$this->setConfig('ZBX_SERVER',		get_request('zbx_server',	$this->getConfig('ZBX_SERVER',		'localhost')));
				$this->setConfig('ZBX_SERVER_PORT',	get_request('zbx_server_port',	$this->getConfig('ZBX_SERVER_PORT',	'10051')));
				if(isset($_REQUEST['next'][$this->getStep()]))		$this->DoNext();
			}

			/*
			if($this->getStep() == 4)
			{
				if(!isset($_REQUEST['next'][3]) && !isset($_REQUEST['back'][5]))
				{
					$this->setConfig('distributed',
						get_request('distributed',	null));
				}
				
				if($this->getConfig('distributed',	null))
				{
					$this->setConfig('nodename',
						get_request('nodename',
						$this->getConfig('nodename',	$_SERVER["SERVER_NAME"])));
					$this->setConfig('nodeid',
						get_request('nodeid',
						$this->getConfig('nodeid',	0)));
				}
				else
				{
					$this->setConfig('nodename', null);
					$this->setConfig('nodeid', null);
				}
			}
			*/

			if($this->getStep() == 5 && isset($_REQUEST['next'][$this->getStep()])){
				$this->DoNext();
			}

			if($this->getStep() == 6){
				$this->setConfig('ZBX_CONFIG_FILE_CORRECT', $this->CheckConfigurationFile());
				
				/*
				if($this->getConfig('ZBX_CONFIG_FILE_CORRECT', false) && !$this->getConfig('ZBX_TABLES_CREATED', false))
				{
					$this->setConfig('ZBX_TABLES_CREATED', $this->CreateTables());
				}
				
				if($this->getConfig('ZBX_TABLES_CREATED', false) && !$this->getConfig('ZBX_DATA_LOADED', false))
				{
					$this->setConfig('ZBX_DATA_LOADED', $this->LoadData());
				}
				*/

				if(/*!$this->getConfig('ZBX_TABLES_CREATED', false) ||
					!$this->getConfig('ZBX_DATA_LOADED', false) || */
					!$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false))
				{
					$this->DISABLE_NEXT_BUTTON = true;
				}

				if(isset($_REQUEST['save_config'])){
					global $ZBX_CONFIGURATION_FILE;

					/* Make zabbix.conf.php downloadable */
					header('Content-Type: application/x-httpd-php');
					header('Content-Disposition: attachment; filename="'.basename($ZBX_CONFIGURATION_FILE).'"');
					die($this->getNewConfigurationFileContent());
				 }
			}

			if(isset($_REQUEST['next'][$this->getStep()])){
				$this->DoNext();
			}
		}

		function getNewConfigurationFileContent()
		{
			return 
'<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

global $DB;

$DB["TYPE"]		= "'.$this->getConfig('DB_TYPE'		,'unknown').'";
$DB["SERVER"]		= "'.$this->getConfig('DB_SERVER'	,'unknown').'";
$DB["PORT"]		= "'.$this->getConfig('DB_PORT'		,'0').'";
$DB["DATABASE"]		= "'.$this->getConfig('DB_DATABASE'	,'unknown').'";
$DB["USER"]		= "'.$this->getConfig('DB_USER'		,'unknown').'";
$DB["PASSWORD"]		= "'.$this->getConfig('DB_PASSWORD'	,'').'";
$ZBX_SERVER		= "'.$this->getConfig('ZBX_SERVER'	,'').'";
$ZBX_SERVER_PORT	= "'.$this->getConfig('ZBX_SERVER_PORT'	,'0').'";


$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;
?>';
		}
	}
?>
