<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

// TODO
// 2. Add strict validation of all function parameters
// 3. Think about handling clearCookies()
// 4. Probably we should not remember selected rows at all. If action is executed then selection should be forgotten.
// 6. Perhaps we should set view in constructor and always call generateOutput
// 9. _REQUEST must not be used, input ($_REQUEST) must be set in constructor.
// 10. Controller should not print output, only generate and return it
// 11. All dependencies on globals must be localized in CController, should not be visible in real controllers: add_audit, DBstart, DBend, $_REQUEST, get_request, getRequest
// 12. DBstart, DBend -> startTransaction, endTransaction
// 13. Go button if renamed to 'action' does not activate confirmation messages, bug?
// 14. Get rid of ZBX_PAGE_NO_THEME in page_header.php
// 15. Fix views, they should generate HTML code. Methods render() and show() are very inconsistent! Already discussed with Pavel.
// 16. show_messages() should be rewritten to get rid of globals
// 17. Split ControllerResponse into several classes
// 18. Cloning should be handled by the form itself.
// 19. Modify Proxy form to have 'Add' and 'Update' buttons. https://support.zabbix.com/browse/ZBXNEXT-1467
// 20. session_start() now is in CController, not sure if it is a correct place

// LATER
// 1. check_fields() should accept two parameters: input and validation rules. It should produce output instead of modifying existing input.
// 8. Add new type for validation T_ZBX_ARR_INT and simplify validation and permission check

class CController {

	private $showGuiMessaging = false;
	private $validationRules = array();
	public $input = array();

	public function __construct() {
		session_start();
	}

	public function setValidationRules(array $validationRules) {
		$this->validationRules = $validationRules;
	}

	private function validateInput() {
		check_fields($this->validationRules);
		$this->input = $_REQUEST;
		if(isset($_SESSION['formData']))
		{
			$this->input = array_merge($this->input, $_SESSION['formData']);
			unset($_SESSION['formData']);
		}
// TODO uncomment when ready
//		unset($_REQUEST);
//		unset($_GET);
//		unset($_POST);
//		unset($_COOKIE);
	}

	public function hasInput($var) {
		return isset($this->input[$var]);
	}

	public function getInput($var, $default = null) {
		return isset($this->input[$var]) ? $this->input[$var] : $default;
	}

	public function getInputAll() {
		return $this->input;
	}

	protected function checkPermissions() {
		access_deny();
	}

	protected function doAction() {
		return null;
	}

//	public function setMessage($message) {
//		$_SESSION['msg'] = $message;
//	}

	final public function run() {
		$this->validateInput();
		$this->checkPermissions();
		return $this->doAction();
	}
}
