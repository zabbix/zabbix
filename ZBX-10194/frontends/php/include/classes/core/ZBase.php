<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/CAutoloader.php';

class ZBase {
	const EXEC_MODE_DEFAULT = 'default';
	const EXEC_MODE_SETUP = 'setup';
	const EXEC_MODE_API = 'api';

	/**
	 * An instance of the current Z object.
	 *
	 * @var Z
	 */
	protected static $instance;

	/**
	 * The absolute path to the root directory.
	 *
	 * @var string
	 */
	protected $rootDir;

	/**
	 * @var array of config data from zabbix config file
	 */
	protected $config = [];

	/**
	 * Returns the current instance of Z.
	 *
	 * @static
	 *
	 * @return Z
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new Z();
		}

		return self::$instance;
	}

	/**
	 * Init modules required to run frontend.
	 */
	protected function init() {
		$this->rootDir = $this->findRootDir();
		$this->registerAutoloader();

		// initialize API classes
		$apiServiceFactory = new CApiServiceFactory();

		$client = new CLocalApiClient();
		$client->setServiceFactory($apiServiceFactory);
		$wrapper = new CFrontendApiWrapper($client);
		$wrapper->setProfiler(CProfiler::getInstance());
		API::setWrapper($wrapper);
		API::setApiServiceFactory($apiServiceFactory);

		// system includes
		require_once $this->getRootDir().'/include/debug.inc.php';
		require_once $this->getRootDir().'/include/gettextwrapper.inc.php';
		require_once $this->getRootDir().'/include/defines.inc.php';
		require_once $this->getRootDir().'/include/func.inc.php';
		require_once $this->getRootDir().'/include/html.inc.php';
		require_once $this->getRootDir().'/include/perm.inc.php';
		require_once $this->getRootDir().'/include/audit.inc.php';
		require_once $this->getRootDir().'/include/js.inc.php';
		require_once $this->getRootDir().'/include/users.inc.php';
		require_once $this->getRootDir().'/include/validate.inc.php';
		require_once $this->getRootDir().'/include/profiles.inc.php';
		require_once $this->getRootDir().'/include/locales.inc.php';
		require_once $this->getRootDir().'/include/db.inc.php';

		// page specific includes
		require_once $this->getRootDir().'/include/acknow.inc.php';
		require_once $this->getRootDir().'/include/actions.inc.php';
		require_once $this->getRootDir().'/include/discovery.inc.php';
		require_once $this->getRootDir().'/include/draw.inc.php';
		require_once $this->getRootDir().'/include/events.inc.php';
		require_once $this->getRootDir().'/include/graphs.inc.php';
		require_once $this->getRootDir().'/include/hosts.inc.php';
		require_once $this->getRootDir().'/include/httptest.inc.php';
		require_once $this->getRootDir().'/include/ident.inc.php';
		require_once $this->getRootDir().'/include/images.inc.php';
		require_once $this->getRootDir().'/include/items.inc.php';
		require_once $this->getRootDir().'/include/maintenances.inc.php';
		require_once $this->getRootDir().'/include/maps.inc.php';
		require_once $this->getRootDir().'/include/media.inc.php';
		require_once $this->getRootDir().'/include/services.inc.php';
		require_once $this->getRootDir().'/include/sounds.inc.php';
		require_once $this->getRootDir().'/include/triggers.inc.php';
		require_once $this->getRootDir().'/include/valuemap.inc.php';
	}

	/**
	 * Initializes the application.
	 */
	public function run($mode) {
		$this->init();

		$this->setMaintenanceMode();
		$this->setErrorHandler();

		switch ($mode) {
			case self::EXEC_MODE_DEFAULT:
				$this->loadConfigFile();
				$this->initDB();
				$this->authenticateUser();
				$this->initLocales();
				break;

			case self::EXEC_MODE_API:
				$this->loadConfigFile();
				$this->initDB();
				$this->initLocales();
				break;

			case self::EXEC_MODE_SETUP:
				try {
					// try to load config file, if it exists we need to init db and authenticate user to check permissions
					$this->loadConfigFile();
					$this->initDB();
					$this->authenticateUser();
					$this->initLocales();
				}
				catch (ConfigFileException $e) {}
				break;
		}

		// new MVC processing, otherwise we continue execution old style
		if (hasRequest('action')) {
			$router = new CRouter(getRequest('action'));
			if ($router->getController() !== null) {
				CProfiler::getInstance()->start();
				$this->processRequest();
				exit;
			}
		}
	}

	/**
	 * Returns the absolute path to the root dir.
	 *
	 * @return string
	 */
	public static function getRootDir() {
		return self::getInstance()->rootDir;
	}

	/**
	 * Returns the path to the frontend's root dir.
	 *
	 * @return string
	 */
	private function findRootDir() {
		return realpath(dirname(__FILE__).'/../../..');
	}

	/**
	 * Register autoloader.
	 */
	private function registerAutoloader() {
		$autoloader = new CAutoloader($this->getIncludePaths());
		$autoloader->register();
	}

	/**
	 * An array of directories to add to the autoloader include paths.
	 *
	 * @return array
	 */
	private function getIncludePaths() {
		return [
			$this->rootDir.'/include/classes/core',
			$this->rootDir.'/include/classes/mvc',
			$this->rootDir.'/include/classes/api',
			$this->rootDir.'/include/classes/api/services',
			$this->rootDir.'/include/classes/api/managers',
			$this->rootDir.'/include/classes/api/clients',
			$this->rootDir.'/include/classes/api/wrappers',
			$this->rootDir.'/include/classes/db',
			$this->rootDir.'/include/classes/debug',
			$this->rootDir.'/include/classes/validators',
			$this->rootDir.'/include/classes/validators/schema',
			$this->rootDir.'/include/classes/validators/string',
			$this->rootDir.'/include/classes/validators/object',
			$this->rootDir.'/include/classes/validators/hostgroup',
			$this->rootDir.'/include/classes/validators/host',
			$this->rootDir.'/include/classes/validators/hostprototype',
			$this->rootDir.'/include/classes/validators/event',
			$this->rootDir.'/include/classes/export',
			$this->rootDir.'/include/classes/export/writers',
			$this->rootDir.'/include/classes/export/elements',
			$this->rootDir.'/include/classes/graphdraw',
			$this->rootDir.'/include/classes/import',
			$this->rootDir.'/include/classes/import/converters',
			$this->rootDir.'/include/classes/import/importers',
			$this->rootDir.'/include/classes/import/preprocessors',
			$this->rootDir.'/include/classes/import/readers',
			$this->rootDir.'/include/classes/import/validators',
			$this->rootDir.'/include/classes/items',
			$this->rootDir.'/include/classes/triggers',
			$this->rootDir.'/include/classes/server',
			$this->rootDir.'/include/classes/screens',
			$this->rootDir.'/include/classes/services',
			$this->rootDir.'/include/classes/sysmaps',
			$this->rootDir.'/include/classes/helpers',
			$this->rootDir.'/include/classes/helpers/trigger',
			$this->rootDir.'/include/classes/macros',
			$this->rootDir.'/include/classes/tree',
			$this->rootDir.'/include/classes/html',
			$this->rootDir.'/include/classes/html/pageheader',
			$this->rootDir.'/include/classes/html/widget',
			$this->rootDir.'/include/classes/html/interfaces',
			$this->rootDir.'/include/classes/parsers',
			$this->rootDir.'/include/classes/parsers/results',
			$this->rootDir.'/include/classes/controllers',
			$this->rootDir.'/include/classes/routing',
			$this->rootDir.'/include/classes/json',
			$this->rootDir.'/include/classes/user',
			$this->rootDir.'/include/classes/setup',
			$this->rootDir.'/include/classes/regexp',
			$this->rootDir.'/include/classes/ldap',
			$this->rootDir.'/include/classes/pagefilter',
			$this->rootDir.'/local/app/controllers',
			$this->rootDir.'/app/controllers'
		];
	}

	/**
	 * An array of available themes.
	 *
	 * @return array
	 */
	public static function getThemes() {
		return [
			'blue-theme' => _('Blue'),
			'dark-theme' => _('Dark'),
		];
	}

	/**
	 * Set custom error handler for PHP errors.
	 */
	protected function setErrorHandler() {
		function zbx_err_handler($errno, $errstr, $errfile, $errline) {
			// necessary to suppress errors when calling with error control operator like @function_name()
			if (error_reporting() === 0) {
				return true;
			}

			// don't show the call to this handler function
			error($errstr.' ['.CProfiler::getInstance()->formatCallStack().']');
		}

		set_error_handler('zbx_err_handler');
	}

	/**
	 * Check if maintenance mode is enabled.
	 *
	 * @throws Exception
	 */
	protected function setMaintenanceMode() {
		require_once $this->getRootDir().'/conf/maintenance.inc.php';

		if (defined('ZBX_DENY_GUI_ACCESS')) {
			$user_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];
			if (!isset($ZBX_GUI_ACCESS_IP_RANGE) || !in_array($user_ip, $ZBX_GUI_ACCESS_IP_RANGE)) {
				throw new Exception($_REQUEST['warning_msg']);
			}
		}
	}

	/**
	 * Load zabbix config file.
	 */
	protected function loadConfigFile() {
		$configFile = $this->getRootDir().CConfigFile::CONFIG_FILE_PATH;
		$config = new CConfigFile($configFile);
		$this->config = $config->load();
	}

	/**
	 * Check if frontend can connect to DB.
	 * @throws DBException
	 */
	protected function initDB() {
		$error = null;
		if (!DBconnect($error)) {
			throw new DBException($error);
		}
	}

	/**
	 * Initialize translations.
	 */
	protected function initLocales() {
		init_mbstrings();

		$defaultLocales = [
			'C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'
		];

		if (function_exists('bindtextdomain')) {
			// initializing gettext translations depending on language selected by user
			$locales = zbx_locale_variants(CWebUser::$data['lang']);
			$locale_found = false;
			foreach ($locales as $locale) {
				// since LC_MESSAGES may be unavailable on some systems, try to set all of the locales
				// and then revert some of them back
				putenv('LC_ALL='.$locale);
				putenv('LANG='.$locale);
				putenv('LANGUAGE='.$locale);
				setlocale(LC_TIME, $locale);

				if (setlocale(LC_ALL, $locale)) {
					$locale_found = true;
					CWebUser::$data['locale'] = $locale;
					break;
				}
			}

			// reset the LC_CTYPE locale so that case transformation functions would work correctly
			// it is also required for PHP to work with the Turkish locale (https://bugs.php.net/bug.php?id=18556)
			// WARNING: this must be done before executing any other code, otherwise code execution could fail!
			// this will be unnecessary in PHP 5.5
			setlocale(LC_CTYPE, $defaultLocales);

			if (!$locale_found && CWebUser::$data['lang'] != 'en_GB' && CWebUser::$data['lang'] != 'en_gb') {
				error('Locale for language "'.CWebUser::$data['lang'].'" is not found on the web server. Tried to set: '.implode(', ', $locales).'. Unable to translate Zabbix interface.');
			}
			bindtextdomain('frontend', 'locale');
			bind_textdomain_codeset('frontend', 'UTF-8');
			textdomain('frontend');
		}

		// reset the LC_NUMERIC locale so that PHP would always use a point instead of a comma for decimal numbers
		setlocale(LC_NUMERIC, $defaultLocales);

		// should be after locale initialization
		require_once $this->getRootDir().'/include/translateDefines.inc.php';
	}

	/**
	 * Authenticate user.
	 */
	protected function authenticateUser() {
		$sessionId = CWebUser::checkAuthentication(CWebUser::getSessionCookie());

		if (!$sessionId) {
			CWebUser::setDefault();
		}

		// set the authentication token for the API
		API::getWrapper()->auth = $sessionId;

		// enable debug mode in the API
		API::getWrapper()->debug = CWebUser::getDebugMode();
	}

	/**
	 * Process request and generate response. Main entry for all processing.
	 */
	private function processRequest() {
		$router = new CRouter(getRequest('action'));

		$controller = $router->getController();

		$controller = new $controller();
		$controller->setAction($router->getAction());
		$response = $controller->run();

		// Controller returned data
		if ($response instanceof CControllerResponseData) {
			// if no view defined we pass data directly to layout
			if ($router->getView() === null) {
				$layout = new CView($router->getLayout(), $response->getData());
				echo $layout->getOutput();
			}
			else {
				$view = new CView($router->getView(), $response->getData());
				$data['page']['title'] = $response->getTitle();
				$data['controller']['action'] = $router->getAction();
				$data['main_block'] = $view->getOutput();
				$data['fullscreen'] = isset($_REQUEST['fullscreen']) && $_REQUEST['fullscreen'] == 1 ? 1 : 0;
				$data['javascript']['files'] = $view->getAddedJS();
				$data['javascript']['pre'] = $view->getIncludedJS();
				$data['javascript']['post'] = $view->getPostJS();
				$layout = new CView($router->getLayout(), $data);
				echo $layout->getOutput();
			}
		}
		// Controller returned redirect to another page
		else if ($response instanceof CControllerResponseRedirect) {
			header('Content-Type: text/html; charset=UTF-8');
			if ($response->getMessageOk() !== null) {
				CSession::setValue('messageOk', $response->getMessageOk());
			}
			if ($response->getMessageError() !== null) {
				CSession::setValue('messageError', $response->getMessageError());
			}
			global $ZBX_MESSAGES;
			if (isset($ZBX_MESSAGES)) {
				CSession::setValue('messages', $ZBX_MESSAGES);
			}
			if ($response->getFormData() !== null) {
				CSession::setValue('formData', $response->getFormData());
			}

			redirect($response->getLocation());
		}
		// Controller returned fatal error
		else if ($response instanceof CControllerResponseFatal) {
			header('Content-Type: text/html; charset=UTF-8');
			$response->addMessage('Controller: '.$router->getAction());
			ksort($_REQUEST);
			foreach ($_REQUEST as $key => $value) {
				// do not output SID
				if ($key != 'sid') {
					$response->addMessage($key.': '.$value);
				}
			}
			CSession::setValue('messages', $response->getMessages());

			redirect('zabbix.php?action=system.warning');
		}
	}
}
