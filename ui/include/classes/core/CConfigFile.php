<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	/**
	 * Mapping between ITEM_VALUE_TYPE_* constants and strings from configuration file when configuring history storage.
	 * ITEM_VALUE_TYPE_BINARY is not supported.
	 */
	public const VALUE_TYPE_CONFIG_NAME = [
		ITEM_VALUE_TYPE_FLOAT => 'dbl',
		ITEM_VALUE_TYPE_STR => 'str',
		ITEM_VALUE_TYPE_LOG => 'log',
		ITEM_VALUE_TYPE_UINT64 => 'uint',
		ITEM_VALUE_TYPE_TEXT => 'text',
		ITEM_VALUE_TYPE_JSON => 'json'
	];

	public const SUPPORTED_SOURCE = [
		ZBX_HISTORY_SOURCE_CLICKHOUSE, ZBX_HISTORY_SOURCE_ELASTIC
	];

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

		if (isset($HISTORY) && isset($HISTORY_PROVIDERS)) {
			self::exception(_s('Cannot use both %1$s and %2$s at the same time.', '$HISTORY_PROVIDERS', '$HISTORY'));
		}

		if (isset($HISTORY)) {
			if (is_array($HISTORY)
					&& array_key_exists('types', $HISTORY) && is_array($HISTORY['types'])
					&& array_key_exists('url', $HISTORY) && (is_array($HISTORY['url']) || is_string($HISTORY['url']))) {
				$HISTORY_PROVIDERS = $this->validateHistoryProvidersDeprecated($HISTORY);
			}
			else {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', '$HISTORY',
					_('incorrect format'))
				);
			}
		}

		if (isset($HISTORY_PROVIDERS)) {
			if (!is_array($HISTORY_PROVIDERS)) {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', '$HISTORY_PROVIDERS',
					_('incorrect format'))
				);
			}

			$this->config['HISTORY_PROVIDERS'] = $this->validateHistoryProviders($HISTORY_PROVIDERS);
		}

		if (isset($SSO)) {
			$this->config['SSO'] = $SSO;
		}

		if (isset($ALLOW_HTTP_AUTH) && isset($ZBX_FEATURE_FLAGS['http_auth_enabled'])) {
			self::exception(_s('Cannot use both %1$s and %2$s at the same time.', '$ALLOW_HTTP_AUTH',
				'$ZBX_FEATURE_FLAGS[\'http_auth_enabled\']'));
		}

		if (isset($ALLOW_HTTP_AUTH)) {
			$ZBX_FEATURE_FLAGS['http_auth_enabled'] = $ALLOW_HTTP_AUTH;
			$this->config['ALLOW_HTTP_AUTH'] = $ALLOW_HTTP_AUTH;
		}

		if (isset($ZBX_FEATURE_FLAGS['banners_enabled'])) {
			$this->config['ZBX_FEATURE_FLAGS']['banners_enabled'] = $ZBX_FEATURE_FLAGS['banners_enabled'];
		}

		if (isset($ZBX_FEATURE_FLAGS['http_auth_enabled'])) {
			$this->config['ZBX_FEATURE_FLAGS']['http_auth_enabled'] = $ZBX_FEATURE_FLAGS['http_auth_enabled'];
		}

		if (isset($ZBX_FEATURE_FLAGS['modules_config_enabled'])) {
			$this->config['ZBX_FEATURE_FLAGS']['modules_config_enabled'] = $ZBX_FEATURE_FLAGS['modules_config_enabled'];
		}

		if (isset($ZBX_FEATURE_FLAGS['media_type_denylist'])) {
			if (!is_array($ZBX_FEATURE_FLAGS['media_type_denylist'])) {
				self::exception(_s('Incorrect configuration %1$s: %2$s.',
					'$ZBX_FEATURE_FLAGS[\'media_type_denylist\']',
					_('an array is expected')
				));
			}
			else {
				$this->config['ZBX_FEATURE_FLAGS']['media_type_denylist'] = $this->getMediaTypeDenylist(
					$ZBX_FEATURE_FLAGS['media_type_denylist']
				);
			}
		}

		if (isset($ZBX_SERVER_TLS) && is_array($ZBX_SERVER_TLS)) {
			if (array_key_exists('ACTIVE', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['ACTIVE'] = $ZBX_SERVER_TLS['ACTIVE'];
			}

			if (array_key_exists('CA_FILE', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['CA_FILE'] = $ZBX_SERVER_TLS['CA_FILE'];
			}

			if (array_key_exists('KEY_FILE', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['KEY_FILE'] = $ZBX_SERVER_TLS['KEY_FILE'];
			}

			if (array_key_exists('CERT_FILE', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['CERT_FILE'] = $ZBX_SERVER_TLS['CERT_FILE'];
			}

			if (array_key_exists('CERTIFICATE_ISSUER', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['CERTIFICATE_ISSUER'] = $ZBX_SERVER_TLS['CERTIFICATE_ISSUER'];
			}

			if (array_key_exists('CERTIFICATE_SUBJECT', $ZBX_SERVER_TLS)) {
				$this->config['ZBX_SERVER_TLS']['CERTIFICATE_SUBJECT'] = $ZBX_SERVER_TLS['CERTIFICATE_SUBJECT'];
			}
		}

		$this->makeGlobal();

		return $this->config;
	}

	public function makeGlobal() {
		global $DB, $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_SERVER_NAME, $IMAGE_FORMAT_DEFAULT, $HISTORY_PROVIDERS, $SSO,
			$ZBX_SERVER_TLS, $ZBX_FEATURE_FLAGS;

		$DB = $this->config['DB'];
		$ZBX_SERVER = $this->config['ZBX_SERVER'];
		$ZBX_SERVER_PORT = $this->config['ZBX_SERVER_PORT'];
		$ZBX_SERVER_NAME = $this->config['ZBX_SERVER_NAME'];
		$IMAGE_FORMAT_DEFAULT = $this->config['IMAGE_FORMAT_DEFAULT'];
		$HISTORY_PROVIDERS = $this->config['HISTORY_PROVIDERS'];
		$SSO = $this->config['SSO'];
		$ZBX_FEATURE_FLAGS = $this->config['ZBX_FEATURE_FLAGS'];
		$ZBX_SERVER_TLS = $this->config['ZBX_SERVER_TLS'];
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

$IMAGE_FORMAT_DEFAULT		= IMAGE_FORMAT_PNG;

// Configuration of history storage providers for Elasticsearch or ClickHouse.
// Supported configuration parameters:
// \'types\'    - Array of data types to be stored in the external storage.
// \'provider\' - History provider type: \'elasticsearch\' or \'clickhouse\'.
// \'url\'      - History provider URL.
// \'db\'       - Database name (used for ClickHouse).
// \'username\' - Database user (used for ClickHouse).
// \'password\' - Database password (used for ClickHouse).
// ClickHouse:
//$HISTORY_PROVIDERS[] = [
//	\'types\' => [\'uint\', \'dbl\', \'str\', \'log\', \'text\', \'json\'],
//	\'provider\' => \'clickhouse\',
//	\'url\' => \'http://localhost:8123\',
//	\'db\' => \'zabbix\',
//	\'username\' => \'zabbix\',
//	\'password\' => \'zabbix\'
//];
// Elasticsearch:
//$HISTORY_PROVIDERS[] = [
//	\'types\' => [\'uint\', \'dbl\', \'str\', \'log\', \'text\', \'json\'],
//	\'provider\' => \'elasticsearch\',
//	\'url\' => \'http://localhost:9200\'
//];
// ClickHouse and Elasticsearch:
//$HISTORY_PROVIDERS[] = [
//	\'types\' => [\'uint\', \'dbl\', \'str\'],
//	\'provider\' => \'clickhouse\',
//	\'url\' => \'http://localhost:8123\',
//	\'db\' => \'zabbix\',
//	\'username\' => \'zabbix\',
//	\'password\' => \'zabbix\'
//];
//$HISTORY_PROVIDERS[] = [
//	\'types\' => [\'log\', \'text\', \'json\'],
//	\'provider\' => \'elasticsearch\',
//	\'url\' => \'http://localhost:9200\'
//];

// SAML authentication.

// Uncomment to set extra settings.
//$SSO[\'SETTINGS\']		= [];

// Set to \'file\' to store the private key and certificates on the file system.
$SSO[\'CERT_STORAGE\']		= \'database\';

// Uncomment to override the default paths for the private key and certificates, if stored on the file system.
//$SSO[\'SP_KEY\']		= \'conf/certs/sp.key\';
//$SSO[\'SP_CERT\']		= \'conf/certs/sp.crt\';
//$SSO[\'IDP_CERT\']		= \'conf/certs/idp.crt\';

// Uncomment and set to false to disable support for banners.
//$ZBX_FEATURE_FLAGS[\'banners_enabled\'] = true;

// Uncomment and set to false to disable user HTTP authentication.
//$ZBX_FEATURE_FLAGS[\'http_auth_enabled\'] = true;

// Uncomment and set to false to disable access to modules.
//$ZBX_FEATURE_FLAGS[\'modules_config_enabled\'] = true;

// Uncomment and set to desired values to disable editing of specific media types.
// Possible values: \'email\', \'script\', \'sms\', \'webhook\'. One or more values can be set.
//$ZBX_FEATURE_FLAGS[\'media_type_denylist\'] = [];

$ZBX_SERVER_TLS[\'ACTIVE\'] = '.($this->config['ZBX_SERVER_TLS']['ACTIVE'] ? 'true' : 'false').';
$ZBX_SERVER_TLS[\'CA_FILE\'] = \''.addcslashes($this->config['ZBX_SERVER_TLS']['CA_FILE'], "'\\").'\';
$ZBX_SERVER_TLS[\'KEY_FILE\'] = \''.addcslashes($this->config['ZBX_SERVER_TLS']['KEY_FILE'], "'\\").'\';
$ZBX_SERVER_TLS[\'CERT_FILE\'] = \''.addcslashes($this->config['ZBX_SERVER_TLS']['CERT_FILE'], "'\\").'\';
$ZBX_SERVER_TLS[\'CERTIFICATE_ISSUER\']  = \''.addcslashes($this->config['ZBX_SERVER_TLS']['CERTIFICATE_ISSUER'], "'\\").'\';
$ZBX_SERVER_TLS[\'CERTIFICATE_SUBJECT\'] = \''.addcslashes($this->config['ZBX_SERVER_TLS']['CERTIFICATE_SUBJECT'], "'\\").'\';
';
	}

	/**
	 * Validate and return $ZBX_FEATURE_FLAGS['media_type_denylist'] configuration.
	 *
	 * @param array $media_type_denylist  Array from configuration file.
	 *
	 * @throws Exception
	 */
	protected function getMediaTypeDenylist(array $media_type_denylist): array {
		$type_flag = [
			MEDIA_TYPE_EMAIL => 'email',
			MEDIA_TYPE_EXEC => 'script',
			MEDIA_TYPE_SMS => 'sms',
			MEDIA_TYPE_WEBHOOK => 'webhook'
		];

		if (array_diff($media_type_denylist, $type_flag)) {
			self::exception(_s('Incorrect configuration %1$s: %2$s.',
				'$ZBX_FEATURE_FLAGS[\'media_type_denylist\']',
				_s('value must be one of %1$s', implode(',', $type_flag))
			));
		}

		return array_keys(array_intersect($type_flag, $media_type_denylist));
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
		$this->config['HISTORY_PROVIDERS'] = [];
		$this->config['SSO'] = null;
		$this->config['ZBX_FEATURE_FLAGS'] = [
			'banners_enabled' => true,
			'http_auth_enabled' => true,
			'modules_config_enabled' => true,
			'media_type_denylist' => []
		];
		$this->config['ZBX_SERVER_TLS'] = [
			'ACTIVE' => false,
			'CA_FILE' => '',
			'KEY_FILE' => '',
			'CERT_FILE' => '',
			'CERTIFICATE_ISSUER' => '',
			'CERTIFICATE_SUBJECT' => ''
		];
	}

	/**
	 * Get valid history storage configuration.
	 *
	 * @param array $providers
	 * @throws ConfigFileException
	 */
	protected function validateHistoryProviders(array $providers): array {
		$result = [];
		$value_types = [];
		$required = [
			ZBX_HISTORY_SOURCE_CLICKHOUSE => ['types', 'url', 'db', 'username', 'password'],
			ZBX_HISTORY_SOURCE_ELASTIC => ['types', 'url']
		];

		foreach ($providers as $i => $provider) {
			$path = ($i + 1).'/';
			$missing = array_key_exists('provider', $provider) && array_key_exists($provider['provider'], $required)
				? $required[$provider['provider']]
				: ['provider'];
			$missing = array_diff($missing, array_keys($provider));

			if ($missing) {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', $path,
					_s('the parameter "%1$s" is missing', reset($missing))
				));
			}

			if (!in_array($provider['provider'], self::SUPPORTED_SOURCE)) {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', $path.'provider',
					_s('value must be one of %1$s', implode(',', self::SUPPORTED_SOURCE))
				));
			}

			if (!is_array($provider['types']) || array_diff($provider['types'], self::VALUE_TYPE_CONFIG_NAME)) {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', $path.'types',
					_s('value must be one of %1$s', implode(',', self::VALUE_TYPE_CONFIG_NAME))
				));
			}

			$provider_value_types = array_fill_keys($provider['types'], $i);
			$in_use = array_intersect_key($value_types, $provider_value_types);
			$value_types += $provider_value_types;

			if ($in_use) {
				self::exception(_s('Incorrect history storage configuration %1$s: %2$s.', $path.'types',
					_s('value "%1$s" already exists', key($in_use))
				));
			}

			$provider['url'] = rtrim($provider['url'], '/');
			$provider['types'] = array_keys(array_intersect(self::VALUE_TYPE_CONFIG_NAME, $provider['types']));
			$result[] = $provider;
		}

		return $result;
	}

	/**
	 * Get valid Elastic history storage configuration from deprecated format.
	 * Return array of providers configurations in format compatible with $HISTORY_PROVIDERS configuration.
	 *
	 * @param array $config
	 * @throws ConfigFileException
	 */
	protected function validateHistoryProvidersDeprecated(array $config): array {
		if (is_string($config['url'])) {
			return [[
				'types' => $config['types'],
				'provider' => ZBX_HISTORY_SOURCE_ELASTIC,
				'url' => $config['url']
			]];
		}

		$providers = [];

		foreach ($config['types'] as $type_name) {
			if (!array_key_exists($type_name, $config['url'])) {
				self::exception(_s('Elasticsearch URL is not set for type: %1$s.', $type_name));
			}

			$url = $config['url'][$type_name];

			if (array_key_exists($url, $providers)) {
				$providers[$url]['types'][] = $type_name;
			}
			else {
				$providers[$url] = [
					'types' => [$type_name],
					'provider' => ZBX_HISTORY_SOURCE_ELASTIC,
					'url' => $url
				];
			}
		}

		return array_values($providers);
	}
}
