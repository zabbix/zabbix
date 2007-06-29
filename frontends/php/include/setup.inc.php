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
	function zbx_is_callable($var)
	{
		foreach($var as $e)
			if(!is_callable($e)) return false;

		return true;
	}

	class CSetupWizard extends CForm
	{
/* protected *//*
		var $ZBX_CONFIG;
		var $DISABLE_NEXT_BUTTON;
		var $stage = array();
		*/

/* public */	
		function CSetupWizard(&$ZBX_CONFIG)
		{
			$this->DISABLE_NEXT_BUTTON = false;
			
			$this->ZBX_CONFIG = &$ZBX_CONFIG;

			$this->stage = array(
				0 => array('title' => '1. Introduction'			, 'fnc' => 'Stage0' ),
				1 => array('title' => '2. Licence Agreement'		, 'fnc' => 'Stage1' ),
				2 => array('title' => '3. Check of pre-requisites'	, 'fnc' => 'Stage2' ),
				3 => array('title' => '4. Configure DB connection'	, 'fnc' => 'Stage3' ),
				//4 => array('title' => '5. Distributed monitoring'	, 'fnc' => 'Stage4' ),
				4 => array('title' => '5. Pre-Installation Summary'	, 'fnc' => 'Stage5' ),
				5 => array('title' => '6. Install'			, 'fnc' => 'Stage6' ),
				6 => array('title' => '7. Finish'			, 'fnc' => 'Stage7' )
				);
			
			$this->EventHandler();
			
			parent::CForm(null, 'post');
		}

		function GetConfig($name, $default = null)
		{
			return isset($this->ZBX_CONFIG[$name]) ? $this->ZBX_CONFIG[$name] : $default;
		}
		function SetConfig($name, $value)
		{
			return ($this->ZBX_CONFIG[$name] = $value);
		}

		function GetStep()
		{
			return $this->GetConfig('step', 0);
		}
		function DoNext()
		{
			if(isset($this->stage[$this->GetStep() + 1]))
			{
				$this->ZBX_CONFIG['step']++;
				return true;
			}
			return false;
		}
		function DoBack()
		{
			if(isset($this->stage[$this->GetStep() - 1]))
			{
				$this->ZBX_CONFIG['step']--;
				return true;
			}
			return false;
		}

		function BodyToString($destroy=true)
		{
			$table = new CTable(null, 'setup_wizard');
			$table->SetAlign('center');
			$table->SetHeader(array(
				new CCol(S_ZABBIX_VER, 'left'), 
				SPACE
				),'header');
			$table->AddRow(array(SPACE, new CCol($this->stage[$this->GetStep()]['title'], 'right')),'title');
			$table->AddRow(array(
				new CCol($this->GetList(), 'left'),
				new CCol($this->GetState(), 'right')
				), 'center');
			
			$next = new CButton('next['.$this->GetStep().']', S_NEXT);
			if($this->DISABLE_NEXT_BUTTON) $next->SetEnabled(false);
			
			$table->SetFooter(array(
				new CCol(new CButton('cancel',S_CANCEL),'left'), 
				new CCol(array(
					isset($this->stage[$this->GetStep()-1]) ? new CButton('back['.$this->GetStep().']', S_PREVIOUS) : null,
					isset($this->stage[$this->GetStep()+1]) ? $next: new CButton('finish', S_FINISH)
					) , 'right')
				),'footer');

			return parent::BodyToString($destroy).$table->ToString();
		}

		function GetList()
		{
			$list = new CList();
			foreach($this->stage as $id => $data)
			{
				if($id < $this->GetStep()) $style = 'completed';
				elseif($id == $this->GetStep()) $style = 'current';
				else $style = null;
				
				$list->AddItem($data['title'], $style);
			}
			return $list->ToString();
		}

		function GetState()
		{
			$fnc = $this->stage[$this->GetStep()]['fnc'];
			return  $this->$fnc();
		}

		function Stage0()
		{

			return new CTag('div', 'yes', 'Welcome to the ZABBIX frontend installation wizard.'.BR.BR.
				'This installation wizard will guide you through the installation of ZABBIX frontend'.BR.BR.
				'Click to "Next" button to proceed to the next screen. If you want to change something '.
				'on a previous screen, click "Previous" button'.BR.BR.
				'You may cancel installation at any time by clicking "Cancel" button', 'text');
		}

		function Stage1()
		{
			$LICENCE_FILE = 'conf/COPYING';

			$this->DISABLE_NEXT_BUTTON = !$this->GetConfig('agree', false);
							
			return array(
				new CTag('div', 'yes', (file_exists($LICENCE_FILE) ? 
					nl2br(nbsp(htmlspecialchars(file_get_contents($LICENCE_FILE)))) : 
					'Missing licence file. See GPL licence.')
				, 'licence'),
				BR,
				new CTag('div', 'yes',
					array(
						new CCheckBox(
							'agree', 
							$this->GetConfig('agree', false),
							'submit();'),
						'I agree'),
					'center')
				);
		}

		function get_test_result(&$result, $test_name, $test_value, $condition, $fail_message)
		{
			$result &= $condition;
			
			$row = new CRow(array(
					$test_name,
					$test_value,
					$condition ? new CSpan(S_OK,'ok') : new CSpan(S_FAIL,'fail')
				),
				!$condition ? 'fail' : null);
			
			if(!$condition && isset($fail_message))
				$row->SetHint($fail_message);

			return $row;
		}

		function Stage2()
		{
			$final_result = true;

			$table = new CTable(null, 'requirements');
			$table->SetAlign('center');
			
			/* Check PHP version */
			$table->AddRow($this->get_test_result(
				$final_result,
				'PHP version: ',
				phpversion(),
				version_compare(phpversion(), '4.3.0', '>='),
				'Minimal version of PHP is 4.3.0'));

			$memory_limit = str2mem(ini_get('memory_limit'));
			$table->AddRow($this->get_test_result(
				$final_result,
				'PHP Memory limit:',
				function_exists('memory_get_usage') ? mem2str($memory_limit) : 'unlimited',
				$memory_limit >= 8*1024*1024 || !function_exists('memory_get_usage'),
				'8M is a minimal PHP memory limitation'));

			$memory_limit = str2mem(ini_get('post_max_size'));
			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'PHP post max size:',
					mem2str($memory_limit),
					$memory_limit >= 8*1024*1024,
					'8M is minimum size of PHP post'));

			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'PHP max execution time:',
					ini_get('max_execution_time').' sec',
					ini_get('max_execution_time') >= 300,
					'300 sec is a maximal limitation on execution of PHP scripts'));
			
			/* Check supporteds databases */
			global $ZBX_CONFIG;

			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'PHP Databases support: ',
					implode(BR, $ZBX_CONFIG['allowed_db']),
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
			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'PHP BC math support',
					$bcmath_fnc_exist ? 'yes' : 'no',
					$bcmath_fnc_exist,
					'Required bcmath module [configured PHP with --enable-bcmath]'));

			/* Check GD existence */
			$gd_version = S_NO;
			if(is_callable('gd_info'))
			{
				$gd_info = gd_info();
				$gd_version = $gd_info['GD Version'];
			}
			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'GD Version:',
					$gd_version,
					$gd_version != S_NO,
					'The GD extension isn\'t loaded.'));

			/* Check supported image formats */
			$img_formats = array();
			if(isset($gd_info))
			{
				//if($gd_info['JPG Support']) array_push($img_formats, 'JPEG');
				if($gd_info['PNG Support']) array_push($img_formats, 'PNG');
			}
			if(count($img_formats) == 0)
			{
				$img_formats = array(S_NO);
				$no_img_formats = true;
			}
			$table->AddRow(
				$this->get_test_result(
					$final_result,
					'Image formats:', 
					implode(BR, $img_formats),
					!isset($no_img_formats),
					'Required images genetarion support [PNG]'));	
			
			if(version_compare(phpversion(), '5.1.0RC1', '>='))
			{
				$tmezone = ini_get('date.timezone');
				$table->AddRow(
					$this->get_test_result(
						$final_result,
						'PHP Timezone:', 
						empty($tmezone) ? 'n/a' : $tmezone,
						!empty($tmezone),
						'Timezone for PHP is not set. Please set "date.timezone" option in php.ini.'));
				unset($tmezone);
			}

			if(!$final_result)
			{
				$this->DISABLE_NEXT_BUTTON = true;
				
				$this->AddVar('trouble',true);
				
				$final_result = array(
					new CSpan(S_FAIL,'fail'),
					BR, BR,
					'Please correct all issuse and press "Retry" button',
					BR, BR,
					new CButton('retry', S_RETRY)
					);
			}
			else
			{
				$this->DISABLE_NEXT_BUTTON = false;
				$final_result = new CSpan(S_OK,'ok');
			}
			
			return array($table, BR, $final_result);
		}

		function Stage3()
		{
			global $ZBX_CONFIG, $_REQUEST;

			$table = new CTable();
			$table->SetAlign('center');
			
			$DB_TYPE = $this->GetConfig('DB_TYPE');

			$cmbType = new CComboBox('type', $DB_TYPE);
			foreach($ZBX_CONFIG['allowed_db'] as $id => $name)
			{
				$cmbType->AddItem($id, $name);
			}
			$table->AddRow(array(S_TYPE, $cmbType));
			$table->AddRow(array(S_HOST, new CTextBox('server',		$this->GetConfig('DB_SERVER',	'localhost'))));
			$table->AddRow(array(S_PORT, array(new CNumericBox('port',		$this->GetConfig('DB_PORT',	'0'),5),' 0 - use default port')));
			$table->AddRow(array(S_NAME, new CTextBox('database',		$this->GetConfig('DB_DATABASE',	'zabbix'))));
			$table->AddRow(array(S_USER, new CTextBox('user',		$this->GetConfig('DB_USER',	'root'))));
			$table->AddRow(array(S_PASSWORD, new CPassBox('password',	$this->GetConfig('DB_PASSWORD',	''))));

			return array(
				'Please create database manually.', BR,
				'And set the configuration parameters of connection to this database.',
				BR,BR,
				'And press "Test connection" button.',
				BR,BR,
				$table,
				BR,
				!$this->DISABLE_NEXT_BUTTON ? new CSpan(S_OK,'ok') :  new CSpan(S_FAIL, 'fail'),
				BR,
				new  CButton('retry', 'Test connection')
				);
		}
		/*
		function Stage4()
		{
			global $_SERVER;

			if($this->GetConfig('distributed', null))
			{
				$table = new CTable();
				$table->SetAlign('center');
				$table->AddRow(array(
					'Node name',
					new CTextBox('nodename', $this->GetConfig('nodename',    $_SERVER["SERVER_NAME"]), 40)
					));
				$table->AddRow(array(
					'Node ID',
					new CNumericBox('nodeid', $this->GetConfig('nodeid',      0), 10)
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
				 	new CCheckBox('distributed', $this->GetConfig('distributed', null), 'submit();'),
					'Use distributed monitoring'),
					'center'),
				BR,BR,
				$table
				), 'text');
		}
		*/
		
		function Stage5()
		{
			$allowed_db = $this->GetConfig('allowed_db', array());
			
			$table = new CTable(null, 'requirements');
			$table->SetAlign('center');
			$table->AddRow(array('Database type:',		$allowed_db[$this->GetConfig('DB_TYPE',	'unknown')]));
			$table->AddRow(array('Database server:',	$this->GetConfig('DB_SERVER',	'unknown')));
			$table->AddRow(array('Database port:',		$this->GetConfig('DB_PORT',	'0')));
			$table->AddRow(array('Database name:',		$this->GetConfig('DB_DATABASE',	'unknown')));
			$table->AddRow(array('Database user:',		$this->GetConfig('DB_USER',	'unknown')));
			$table->AddRow(array('Database password:',	$this->GetConfig('DB_PASSWORD',	'unknown')));
			/* $table->AddRow(array('Distributed monitoring',	$this->GetConfig('distributed', null) ? 'Enabled' : 'Disabled')); */
			if($this->GetConfig('distributed', null))
			{
				$table->AddRow(array('Node name',	$this->GetConfig('nodename',	'unknown')));
				$table->AddRow(array('Node GUID',	$this->GetConfig('nodeid',	'unknown')));
			}
			return array(
				'Please check configuration parameters.', BR,
				'If all correct press "Next" button, or "Previous" button to change configuration parameters.', BR, BR,
				$table
				);
		}

		function Stage6()
		{
			global $_SERVER, $ZBX_CONFIGURATION_FILE;

			show_messages();
			/* Write the new contents */
			if($f = @fopen($ZBX_CONFIGURATION_FILE, 'w'))
			{
				if(fwrite($f, $this->GetNewConfigurationFileContent()))
				{
					if(fclose($f))
					{
						if($this->SetConfig('ZBX_CONFIG_FILE_CORRECT', $this->CheckConfigurationFile()))
						{
							$this->DISABLE_NEXT_BUTTON = false;
						}
					}
				}
			}
			clear_messages(); /* don't show errors */
			
			$table = new CTable(null, 'requirements');
			$table->SetAlign('center');

			$table->AddRow(array('Configuration file:',  $this->GetConfig('ZBX_CONFIG_FILE_CORRECT', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));

			/*
			$table->AddRow(array('Table creation:',  $this->GetConfig('ZBX_TABLES_CREATED', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));
			
			$table->AddRow(array('Data loading:',  $this->GetConfig('ZBX_DATA_LOADED', false) ? 
									new CSpan(S_OK,'ok') :
									new CSpan(S_FAIL,'fail')
										));
			*/

			return array(
				$table, BR,
				$this->DISABLE_NEXT_BUTTON ? array(new CButton('retry', S_RETRY), BR,BR) : null,
				!$this->GetConfig('ZBX_CONFIG_FILE_CORRECT', false) ? 
					array('Please install configuration file manualy.',BR,BR,
						'By pressing "Save configuration file" button download configuration file ',
						'and place them into the ',BR,
						'"'.$ZBX_CONFIGURATION_FILE.'"',BR,BR,
						new CButton('save_config',"Save configuration file"),
						BR,BR
						)
					: null,
				'Press the '.($this->DISABLE_NEXT_BUTTON ? '"Retry"' : '"Next"').' button'
				);
		}

		function Stage7()
		{
			return array(
				'Congratulation with succesfull instalation of ZABBIX frontend.',BR,BR,
				'Press "Finish" button to complete installation'
				);
		}

		function CheckConnection()
		{
			global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

			$old_DB		= $DB;
			$old_DB_TYPE	= $DB_TYPE;
			$old_DB_SERVER	= $DB_SERVER;
			$old_DB_PORT	= $DB_PORT;
			$old_DB_DATABASE= $DB_DATABASE;
			$old_DB_USER	= $DB_USER;
			$old_DB_PASSWORD= $DB_PASSWORD;

			$DB_TYPE	= $this->GetConfig('DB_TYPE');
			if(is_null($DB_TYPE))	return false;

			$DB_SERVER	= $this->GetConfig('DB_SERVER',		'localhost');
			$DB_PORT	= $this->GetConfig('DB_PORT',		'0');
			$DB_DATABASE	= $this->GetConfig('DB_DATABASE',	'zabbix');
			$DB_USER	= $this->GetConfig('DB_USER',		'root');
			$DB_PASSWORD	= $this->GetConfig('DB_PASSWORD',	'');

			$error = '';
			if(!($result = DBconnect($error)))
			{
				error($error);
			}
			else
			{
				$result = DBexecute('create table zabbix_installation_test ( test_row integer )');
				$result &= DBexecute('drop table zabbix_installation_test');
			}
			
			DBclose();

			if($DB_TYPE == 'SQLITE3' && !zbx_is_callable(array('sem_get','sem_acquire','sem_release','sem_remove')))
			{
				error('SQLite3 required IPC functions');
				$result &= false;
			}
			
			/* restore connection */
			global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

			$DB		= $old_DB;
			$DB_TYPE	= $old_DB_TYPE;
			$DB_SERVER	= $old_DB_SERVER;
			$DB_PORT	= $old_DB_PORT;
			$DB_DATABASE	= $old_DB_DATABASE;
			$DB_USER	= $old_DB_USER;
			$DB_PASSWORD	= $old_DB_PASSWORD;

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
			
				switch($DB_TYPE)
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
						if($this->GetConfig('distributed', null))
						{
							if(!DBexecute('insert into nodes (nodeid, name, nodetype) values('.
								$this->GetConfig('nodeid', 0).','.
								zbx_dbstr($this->GetConfig('nodename', 'local')).','.
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
			global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

			$old_DB		= $DB;
			$old_DB_TYPE	= $DB_TYPE;
			$old_DB_SERVER	= $DB_SERVER;
			$old_DB_PORT	= $DB_PORT;
			$old_DB_DATABASE= $DB_DATABASE;
			$old_DB_USER	= $DB_USER;
			$old_DB_PASSWORD= $DB_PASSWORD;

			$error = null;
			$error_msg = null;

			global $ZBX_CONFIGURATION_FILE;
						
			if(file_exists($ZBX_CONFIGURATION_FILE))
			{
				include $ZBX_CONFIGURATION_FILE;

				if(	isset($DB_TYPE) && 
					isset($DB_SERVER) && 
					isset($DB_DATABASE) && 
					isset($DB_USER) && 
					isset($DB_PASSWORD) &&
					isset($IMAGE_FORMAT_DEFAULT) &&
					$DB_TYPE		== $this->GetConfig('DB_TYPE',				null) &&
					$DB_SERVER		== $this->GetConfig('DB_SERVER',			null) &&
					$DB_PORT		== $this->GetConfig('DB_PORT',				null) &&
					$DB_DATABASE		== $this->GetConfig('DB_DATABASE',			null) &&
					$DB_USER		== $this->GetConfig('DB_USER',				null) &&
					$DB_PASSWORD		== $this->GetConfig('DB_PASSWORD',			null)
					)
				{
					if(!DBconnect($error_msg))
					{
						$error_msg = 'Can not connect to database';
					}
				}
				else
				{
					$error_msg = 'Incorrect configuration file['.$ZBX_CONFIGURATION_FILE.']';
				}
				DBclose();
			}
			else
			{
				$error = 'Missing configuration file['.$ZBX_CONFIGURATION_FILE.']';
			}
			
			if(isset($error_msg))
			{
				error($error_msg);
			}

			/* restore connection */
			global $DB, $DB_TYPE, $DB_PORT, $DB_SERVER, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

			$DB		= $old_DB;
			$DB_TYPE	= $old_DB_TYPE;
			$DB_SERVER	= $old_DB_SERVER;
			$DB_PORT	= $old_DB_PORT;
			$DB_DATABASE	= $old_DB_DATABASE;
			$DB_USER	= $old_DB_USER;
			$DB_PASSWORD	= $old_DB_PASSWORD;

			DBconnect($error2);

			return !isset($error)&&!isset($error_msg);
		}
		
		function EventHandler()
		{
			global $_REQUEST;

			if(isset($_REQUEST['back'][$this->GetStep()]))	$this->DoBack();	

			if($this->GetStep() == 1)
			{
				if(!isset($_REQUEST['next'][0]) && !isset($_REQUEST['back'][2]))
				{
					$this->SetConfig('agree', isset($_REQUEST['agree']));
				}
				
				if(isset($_REQUEST['next'][$this->GetStep()]) && $this->GetConfig('agree', false))
				{
					$this->DoNext();
				}
			}
			if($this->GetStep() == 2 && isset($_REQUEST['next'][$this->GetStep()]) && !isset($_REQUEST['trouble']))
			{
				$this->DoNext();
			}
			if($this->GetStep() == 3)
			{
				$this->SetConfig('DB_TYPE',	get_request('type',	$this->GetConfig('DB_TYPE')));
				$this->SetConfig('DB_SERVER',	get_request('server',	$this->GetConfig('DB_SERVER',	'localhost')));
				$this->SetConfig('DB_PORT',	get_request('port',	$this->GetConfig('DB_PORT',	'0')));
				$this->SetConfig('DB_DATABASE',	get_request('database',	$this->GetConfig('DB_DATABASE',	'zabbix')));
				$this->SetConfig('DB_USER',	get_request('user',	$this->GetConfig('DB_USER',	'root')));
				$this->SetConfig('DB_PASSWORD',	get_request('password',	$this->GetConfig('DB_PASSWORD',	'')));
		
				if(!$this->CheckConnection())
				{
					$this->DISABLE_NEXT_BUTTON = true;
					unset($_REQUEST['next']);
				}
				if(isset($_REQUEST['next'][$this->GetStep()]))		$this->DoNext();
			}

			/*
			if($this->GetStep() == 4)
			{
				if(!isset($_REQUEST['next'][3]) && !isset($_REQUEST['back'][5]))
				{
					$this->SetConfig('distributed',
						get_request('distributed',	null));
				}
				
				if($this->GetConfig('distributed',	null))
				{
					$this->SetConfig('nodename',
						get_request('nodename',
						$this->GetConfig('nodename',	$_SERVER["SERVER_NAME"])));
					$this->SetConfig('nodeid',
						get_request('nodeid',
						$this->GetConfig('nodeid',	0)));
				}
				else
				{
					$this->SetConfig('nodename', null);
					$this->SetConfig('nodeid', null);
				}
			}
			*/

			if($this->GetStep() == 4 && isset($_REQUEST['next'][$this->GetStep()]))
			{
				$this->DoNext();
			}

			if($this->GetStep() == 5)
			{
				$this->SetConfig('ZBX_CONFIG_FILE_CORRECT', $this->CheckConfigurationFile());
				
				/*
				if($this->GetConfig('ZBX_CONFIG_FILE_CORRECT', false) && !$this->GetConfig('ZBX_TABLES_CREATED', false))
				{
					$this->SetConfig('ZBX_TABLES_CREATED', $this->CreateTables());
				}
				
				if($this->GetConfig('ZBX_TABLES_CREATED', false) && !$this->GetConfig('ZBX_DATA_LOADED', false))
				{
					$this->SetConfig('ZBX_DATA_LOADED', $this->LoadData());
				}
				*/

				if(/*!$this->GetConfig('ZBX_TABLES_CREATED', false) ||
					!$this->GetConfig('ZBX_DATA_LOADED', false) || */
					!$this->GetConfig('ZBX_CONFIG_FILE_CORRECT', false))
				{
					$this->DISABLE_NEXT_BUTTON = true;
				}

				if(isset($_REQUEST['save_config']))
				{
					global $ZBX_CONFIGURATION_FILE;

					/* Make zabbix.conf.php downloadable */
					header('Content-Type: application/x-httpd-php');
					header('Content-Disposition: attachment; filename="'.basename($ZBX_CONFIGURATION_FILE).'"');
					die($this->GetNewConfigurationFileContent());
				 }
			}
			
			if(isset($_REQUEST['next'][$this->GetStep()]))		$this->DoNext();
		}

		function GetNewConfigurationFileContent()
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

global $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD, $IMAGE_FORMAT_DEFAULT;

$DB_TYPE	= "'.$this->GetConfig('DB_TYPE'		,'unknown').'";
$DB_SERVER	= "'.$this->GetConfig('DB_SERVER'	,'unknown').'";
$DB_PORT	= "'.$this->GetConfig('DB_PORT'		,'0').'";
$DB_DATABASE	= "'.$this->GetConfig('DB_DATABASE'	,'unknown').'";
$DB_USER	= "'.$this->GetConfig('DB_USER'		,'unknown').'";
$DB_PASSWORD	= "'.$this->GetConfig('DB_PASSWORD'	,'').'";

$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;
?>';
		}
	}
?>
