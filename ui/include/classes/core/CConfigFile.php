<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CConfigFile {

	const CONFIG_NOT_FOUND = 1;
	const CONFIG_ERROR = 2;
	const CONFIG_VAULT_ERROR = 3;

	const CONFIG_FILE_PATH = '/conf/zabbix.conf.php';

	private static $supported_db_types = [
		ZBX_DB_MYSQL => true,
		ZBX_DB_POSTGRESQL => true
	];

	public $configFile = null;
	public $config = [];
	public $error = '';

	private static function exception($error, $code = self::CONFIG_ERROR) {
		throw new ConfigFileException($error, $code);
	}

	public function __construct($file = null) {
		$this->setDefaults();

		if ($file !== null) {
			$this->setFile($file);
		}
	}

	public function setFile($file) {
		$this->configFile = $file;
	}

	public function load() {
		if (!file_exists($this->configFile)) {
			self::exception('Config file does not exist.', self::CONFIG_NOT_FOUND);
		}

		if (!is_readable($this->configFile)) {
			self::exception('Permission denied.');
		}

		ob_start();
		include($this->configFile);
		ob_end_clean();

		if (!isset($DB['TYPE'])) {
			self::exception('DB type is not set.');
		}

		if (!array_key_exists($DB['TYPE'], self::$supported_db_types)) {
			self::exception(
				'Incorrect value "'.$DB['TYPE'].'" for DB type. Possible values '.
				implode(', ', array_keys(self::$supported_db_types)).'.'
			);
		}

		$php_supported_db = array_keys(CFrontendSetup::getSupportedDatabases());

		if (!in_array($DB['TYPE'], $php_supported_db)) {
			self::exception('DB type "'.$DB['TYPE'].'" is not supported by current setup.'.
				($php_supported_db ? ' Possible values '.implode(', ', $php_supported_db).'.' : '')
			);
		}

		if (!isset($DB['DATABASE'])) {
			self::exception('DB database is not set.');
		}

		$this->setDefaults();

		$this->config['DB']['TYPE'] = $DB['TYPE'];
		$this->config['DB']['DATABASE'] = $DB['DATABASE'];

		if (isset($DB['SERVER'])) {
			$this->config['DB']['SERVER'] = $DB['SERVER'];
		}

		if (isset($DB['PORT'])) {
			$this->config['DB']['PORT'] = $DB['PORT'];
		}

		if (isset($DB['USER'])) {
			$this->config['DB']['USER'] = $DB['USER'];
		}

		if (isset($DB['PASSWORD'])) {
			$this->config['DB']['PASSWORD'] = $DB['PASSWORD'];
		}

		if (isset($DB['SCHEMA'])) {
			$this->config['DB']['SCHEMA'] = $DB['SCHEMA'];
		}

		if (isset($DB['ENCRYPTION'])) {
			$this->config['DB']['ENCRYPTION'] = $DB['ENCRYPTION'];
		}

		if (isset($DB['VERIFY_HOST'])) {
			$this->config['DB']['VERIFY_HOST'] = $DB['VERIFY_HOST'];
		}

		if (isset($DB['KEY_FILE'])) {
			$this->config['DB']['KEY_FILE'] = $DB['KEY_FILE'];
		}

		if (isset($DB['CERT_FILE'])) {
			$this->config['DB']['CERT_FILE'] = $DB['CERT_FILE'];
		}

		if (isset($DB['CA_FILE'])) {
			$this->config['DB']['CA_FILE'] = $DB['CA_FILE'];
		}

		if (isset($DB['CIPHER_LIST'])) {
			$this->config['DB']['CIPHER_LIST'] = $DB['CIPHER_LIST'];
		}

		if (isset($DB['VAULT'])) {
			$this->config['DB']['VAULT'] = $DB['VAULT'];
		}

		if (isset($DB['VAULT_URL'])) {
			$this->config['DB']['VAULT_URL'] = $DB['VAULT_URL'];
		}

		if (isset($DB['VAULT_PREFIX'])) {
			$this->config['DB']['VAULT_PREFIX'] = $DB['VAULT_PREFIX'];
		}

		if (isset($DB['VAULT_DB_PATH'])) {
			$this->config['DB']['VAULT_DB_PATH'] = $DB['VAULT_DB_PATH'];
		}

		if (isset($DB['VAULT_TOKEN'])) {
			$this->config['DB']['VAULT_TOKEN'] = $DB['VAULT_TOKEN'];
		}

		if (isset($DB['VAULT_CACHE'])) {
			$this->config['DB']['VAULT_CACHE'] = $DB['VAULT_CACHE'];
		}

		if (isset($DB['VAULT_KEY_FILE'])) {
			$this->config['DB']['VAULT_KEY_FILE'] = $DB['VAULT_KEY_FILE'];
		}

		if (isset($DB['VAULT_CERT_FILE'])) {
			$this->config['DB']['VAULT_CERT_FILE'] = $DB['VAULT_CERT_FILE'];
		}

		if (isset($ZBX_SERVER) && $ZBX_SERVER !== '') {
			$this->config['ZBX_SERVER'] = $ZBX_SERVER;
		}

		if (isset($ZBX_SERVER_PORT) && $ZBX_SERVER_PORT !== '') {
			$this->config['ZBX_SERVER_PORT'] = $ZBX_SERVER_PORT;
		}

		if (isset($ZBX_SERVER_NAME)) {
			$this->config['ZBX_SERVER_NAME'] = $ZBX_SERVER_NAME;
		}

		if (isset($IMAGE_FORMAT_DEFAULT)) {
			$this->config['IMAGE_FORMAT_DEFAULT'] = $IMAGE_FORMAT_DEFAULT;
		}

		if (isset($HISTORY)) {
			$this->config['HISTORY'] = $HISTORY;
		}

		if (isset($SSO)) {
			$this->config['SSO'] = $SSO;
		}

		if (isset($ALLOW_HTTP_AUTH)) {
			$this->config['ALLOW_HTTP_AUTH'] = $ALLOW_HTTP_AUTH;
		}

		$this->makeGlobal();

		return $this->config;
	}

	public function makeGlobal() {
		global $DB, $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_SERVER_NAME, $IMAGE_FORMAT_DEFAULT, $HISTORY, $SSO,
			$ALLOW_HTTP_AUTH;

		$DB = $this->config['DB'];
		$ZBX_SERVER = $this->config['ZBX_SERVER'];
		$ZBX_SERVER_PORT = $this->config['ZBX_SERVER_PORT'];
		$ZBX_SERVER_NAME = $this->config['ZBX_SERVER_NAME'];
		$IMAGE_FORMAT_DEFAULT = $this->config['IMAGE_FORMAT_DEFAULT'];
		$HISTORY = $this->config['HISTORY'];
		$SSO = $this->config['SSO'];
		$ALLOW_HTTP_AUTH = $this->config['ALLOW_HTTP_AUTH'];
	}

	public function save() {
		try {
			$file = $this->configFile;

			if ($file === null) {
				self::exception('Cannot save, config file is not set.');
			}

			if (is_link($file)) {
				$file = readlink($file);
			}

			$file_is_writable = ((!file_exists($file) && is_writable(dirname($file))) || is_writable($file));

			if ($file_is_writable && file_put_contents($file, $this->getString())) {
				if (!chmod($file, 0600)) {
					self::exception(_('Unable to change configuration file permissions to 0600.'));
				}
			}
			elseif (is_readable($file)) {
				if (file_get_contents($file) !== $this->getString()) {
					self::exception(_('Unable to overwrite the existing configuration file.'));
				}
			}
			else {
				self::exception(_('Unable to create the configuration file.'));
			}

			return true;
		}
		catch (Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	public function getString() {
		return
'<?php
// Zabbix GUI configuration file.

$DB[\'TYPE\']			= \''.addcslashes($this->config['DB']['TYPE'], "'\\").'\';
$DB[\'SERVER\']			= \''.addcslashes($this->config['DB']['SERVER'], "'\\").'\';
$DB[\'PORT\']			= \''.addcslashes($this->config['DB']['PORT'], "'\\").'\';
$DB[\'DATABASE\']			= \''.addcslashes($this->config['DB']['DATABASE'], "'\\").'\';
$DB[\'USER\']			= \''.addcslashes($this->config['DB']['USER'], "'\\").'\';
$DB[\'PASSWORD\']			= \''.addcslashes($this->config['DB']['PASSWORD'], "'\\").'\';

// Schema name. Used for PostgreSQL.
$DB[\'SCHEMA\']			= \''.addcslashes($this->config['DB']['SCHEMA'], "'\\").'\';

// Used for TLS connection.
$DB[\'ENCRYPTION\']		= '.($this->config['DB']['ENCRYPTION'] ? 'true' : 'false').';
$DB[\'KEY_FILE\']			= \''.addcslashes($this->config['DB']['KEY_FILE'], "'\\").'\';
$DB[\'CERT_FILE\']		= \''.addcslashes($this->config['DB']['CERT_FILE'], "'\\").'\';
$DB[\'CA_FILE\']			= \''.addcslashes($this->config['DB']['CA_FILE'], "'\\").'\';
$DB[\'VERIFY_HOST\']		= '.($this->config['DB']['VERIFY_HOST'] ? 'true' : 'false').';
$DB[\'CIPHER_LIST\']		= \''.addcslashes($this->config['DB']['CIPHER_LIST'], "'\\").'\';

// Vault configuration. Used if database credentials are stored in Vault secrets manager.
$DB[\'VAULT\']			= \''.addcslashes($this->config['DB']['VAULT'], "'\\").'\';
$DB[\'VAULT_URL\']		= \''.addcslashes($this->config['DB']['VAULT_URL'], "'\\").'\';
$DB[\'VAULT_PREFIX\']		= \''.addcslashes($this->config['DB']['VAULT_PREFIX'], "'\\").'\';
$DB[\'VAULT_DB_PATH\']		= \''.addcslashes($this->config['DB']['VAULT_DB_PATH'], "'\\").'\';
$DB[\'VAULT_TOKEN\']		= \''.addcslashes($this->config['DB']['VAULT_TOKEN'], "'\\").'\';
$DB[\'VAULT_CERT_FILE\']		= \''.addcslashes($this->config['DB']['VAULT_CERT_FILE'], "'\\").'\';
$DB[\'VAULT_KEY_FILE\']		= \''.addcslashes($this->config['DB']['VAULT_KEY_FILE'], "'\\").'\';
// Uncomment to bypass local caching of credentials.
// $DB[\'VAULT_CACHE\']		= true;

// Uncomment and set to desired values to override Zabbix hostname/IP and port.
// $ZBX_SERVER			= \'\';
// $ZBX_SERVER_PORT		= \'\';

$ZBX_SERVER_NAME		= \''.addcslashes($this->config['ZBX_SERVER_NAME'], "'\\").'\';

$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;

// Uncomment this block only if you are using Elasticsearch.
// Elasticsearch url (can be string if same url is used for all types).
//$HISTORY[\'url\'] = [
//	\'uint\' => \'http://localhost:9200\',
//	\'text\' => \'http://localhost:9200\'
//];
// Value types stored in Elasticsearch.
//$HISTORY[\'types\'] = [\'uint\', \'text\'];

// Used for SAML authentication.
// Uncomment to override the default paths to SP private key, SP and IdP X.509 certificates, and to set extra settings.
//$SSO[\'SP_KEY\']			= \'conf/certs/sp.key\';
//$SSO[\'SP_CERT\']			= \'conf/certs/sp.crt\';
//$SSO[\'IDP_CERT\']		= \'conf/certs/idp.crt\';
//$SSO[\'SETTINGS\']		= [];

// If set to false, support for HTTP authentication will be disabled.
// $ALLOW_HTTP_AUTH = true;
';
	}

	protected function setDefaults() {
		$this->config['DB'] = [
			'TYPE' => null,
			'SERVER' => 'localhost',
			'PORT' => '0',
			'DATABASE' => null,
			'USER' => '',
			'PASSWORD' => '',
			'SCHEMA' => '',
			'ENCRYPTION' => false,
			'KEY_FILE' => '',
			'CERT_FILE' => '',
			'CA_FILE' => '',
			'VERIFY_HOST' => true,
			'CIPHER_LIST' => '',
			'VAULT' => '',
			'VAULT_URL' => '',
			'VAULT_PREFIX' => '',
			'VAULT_DB_PATH' => '',
			'VAULT_TOKEN' => '',
			'VAULT_CERT_FILE' => '',
			'VAULT_KEY_FILE' => '',
			'VAULT_CACHE' => false
		];
		$this->config['ZBX_SERVER'] = null;
		$this->config['ZBX_SERVER_PORT'] = null;
		$this->config['ZBX_SERVER_NAME'] = '';
		$this->config['IMAGE_FORMAT_DEFAULT'] = IMAGE_FORMAT_PNG;
		$this->config['HISTORY'] = null;
		$this->config['SSO'] = null;
		$this->config['ALLOW_HTTP_AUTH'] = true;
	}
}
