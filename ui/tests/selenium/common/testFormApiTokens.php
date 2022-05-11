<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for API tokens form function tests.
 */
class testFormApiTokens extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	const UPDATE_TOKEN = 'Admin reference token';	// Token for update.
	const DELETE_TOKEN = 'Token to be deleted';		// Token for deletion.
	const USER_ZABBIX_TOKEN = 'user-zabbix token';	// Token to be updated that belongs to user-zabbix.

	public static $tokenid;

	/**
	 * Function retrieves the tokenid based on token name.
	 *
	 * @param string $token_name	The name of the token for which the ID is obtained.
	 * @param boolean $return		Flag that specifies whether token id should be returned by this method.
	 *
	 * @return string
	 */
	public function getTokenId($token_name = self::UPDATE_TOKEN) {
		self::$tokenid = CDBHelper::getValue('SELECT tokenid FROM token WHERE name='.zbx_dbstr($token_name));

		return self::$tokenid;
	}

	/**
	 * Function that checks the layout of the API token configuration form in Administration or User settings section.
	 *
	 * @param string $source	Section from which the scenario is executed.
	 */
	public function checkTokensFormLayout($source) {
		$this->page->login()->open('zabbix.php?action='.(($source === 'user settings') ? 'user.token.edit' : 'token.edit'));
		$this->page->assertTitle('API tokens');
		$this->page->assertHeader('API tokens');

		$form = $this->query('id:token_form')->asForm()->one();

		foreach (['Name' => '64', 'Description' => '65535'] as $field_name => $maxlength) {
			$field = $form->getField($field_name);
			$this->assertEquals('', $field->getValue());
			$this->assertEquals($maxlength, $field->getAttribute('maxlength'));
		}

		// Check the presence of User field and that it is empty by default if it exists.
		if ($source === 'administration') {
			$this->assertEquals([], $form->getField('User')->getValue());
		}
		else {
			$this->assertFalse($form->query('xpath://label[text()="User"]')->one(false)->isDisplayed());
		}

		// Check that "Set expiration date and time" checkbox is set by default.
		$expiration_checkbox = $form->getField('Set expiration date and time');
		$this->assertTrue($expiration_checkbox->getValue());

		$expires_at = $form->getField('Expires at')->query('id:expires_at')->one();
		$this->assertEquals('',$field->getValue());
		$this->assertEquals('19', $expires_at->getAttribute('maxlength'));
		$this->assertEquals('YYYY-MM-DD hh:mm:ss', $expires_at->getAttribute('placeholder'));
		$calendar = $form->query('id:expires_at_calendar')->one();
		$this->assertTrue($calendar->isClickable());
		$this->assertEquals('toggleCalendar(this, "expires_at", "Y-m-d H:i:s");', $calendar->getAttribute('onclick'));

		// Check that "Expires at" field is removed if "Set expiration date and time" is not set.
		$expiration_checkbox->set(false);
		$this->assertFalse($form->getField('Expires at')->isVisible());
		$this->assertTrue($form->getField('Enabled')->getValue());

		foreach($form->query('button', ['Add', 'Cancel'])->all() as $button) {
			$this->assertTrue($button->isClickable());
		}
	}

	/**
	 * Function checks the layout in Token regenerate form.
	 *
	 * @param string	$source		Section from which the scenario is executed.
	 * @param integer	$tokenid	ID of the token for which the regenerate form is opened.
	 */
	public function checkTokensRegenerateFormLayout($source) {
		$values = [
			'Name:' => 'Admin reference token',
			'User:' => 'Admin (Zabbix Administrator)',
			'Description:' => 'admin token to be used in update scenarios',
			'Expires at:' => '2026-12-31 23:59:59'
		];

		// User field is not present in User settings => Api tokens configuration form.
		if ($source === 'user settings') {
			unset($values['User:']);
		}

		$this->page->login()->open('zabbix.php?&tokenid='.self::$tokenid.'&action='.(($source === 'user settings')
			? 'user.token.edit'
			: 'token.edit'));

		$this->query('button:Regenerate')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->page->assertTitle('API tokens');
		$this->page->assertHeader('API tokens');
		$this->assertMessage(TEST_GOOD, 'API token updated');
		$form = $this->query('id:token_form')->asForm()->one();
		$this->assertEquals(true, $form->getField('Enabled:')->getValue());

		foreach ($values as $name => $value) {
			$this->assertEquals($value, $form->getField($name)->getText());
		}

		if ($source === 'user settings') {
			$this->assertFalse($form->query('xpath://label[text()="User"]')->one(false)->isDisplayed());
		}

		// Check Auth token field.
		$auth_token = $form->getField('Auth token:');
		$this->checkAuthToken($auth_token, null);

		// Check the hintbox text in the Auth token field.
		$auth_token->query('xpath:./a[@data-hintbox]')->one()->click();
		$hintbox_text = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible()->getText();
		$this->assertEquals('Make sure to copy the auth token as you won\'t be able to view it after the page is closed.',
				$hintbox_text);
		$this->assertTrue($form->query('button:Close')->one()->isClickable());
	}

	/**
	 * Function performs creation, update or regeneration of auth token and checks the result.
	 *
	 * @param array $data		data provider
	 * @param string $url		the URL that leads to the form where the action needs to be performed
	 * @param string $action	action that needs to be executed within this method
	 */
	public function checkTokensAction($data, $url, $action) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = 'SELECT * FROM token ORDER BY tokenid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open($url);
		$form = $this->query('id:token_form')->asForm()->one();

		// Fill form or press appropriate button depending on the action.
		if ($action === 'regenerate') {
			$old_token = CDBHelper::getValue('SELECT token FROM token WHERE tokenid='.$data['tokenid']);

			$form->query('button:Regenerate')->one()->click();
			$this->page->acceptAlert();
		}
		elseif ($action === 'update' && array_key_exists('User', $data['fields'])) {
			$userless_data = $data['fields'];

			// Field "User" is read only when editing an API token.
			$this->assertFalse($form->getField('User')->isEnabled());
			unset($userless_data['User']);
			$form->fill($userless_data);
			$form->submit();
		}
		else {
			$form->fill($data['fields']);
			$form->submit();
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$title = ($action === 'create') ? 'Cannot add API token' : 'Cannot update API token';
			$this->assertMessage(TEST_BAD, $title, $data['error_details']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$title = ($action === 'create') ? 'API token added' : 'API token updated';
			$this->assertMessage(TEST_GOOD, $title);

			// Substitute user name with full name in the data provider for reference.
			if (array_key_exists('full_name', $data)) {
				$data['fields']['User'] = $data['full_name'];
			}

			if ($action !== 'update') {
				$form->invalidate();

				// Prepare the data provider for token generate view.
				$generate_data = $data['fields'];
				unset($generate_data['Set expiration date and time']);

				// Check warning in case if token is already expired.
				if (CTestArrayHelper::get($data, 'already_expired')) {
					$form->getField('Expires at:')->query('xpath:./a[@data-hintbox]')->one()->click();
					$hintbox_text = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible()->getText();
					$this->assertEquals('The token has expired. Please update the expiry date to use the token.', $hintbox_text);

					// In case if token is expired an empty space (separator) is added to the value in token generate form.
					$generate_data['Expires at'] = $generate_data['Expires at'].' ';
				}

				foreach ($generate_data as $name => $value) {
					if ($name === 'Enabled') {
						$this->assertEquals($value, $form->getField('Enabled:')->getValue());
					}
					else {
						$this->assertEquals($value, $form->getField($name.':')->getText());
					}
				}

				// Check Auth token field.
				$original_token = ($action === 'regenerate') ? $old_token : null;
				$auth_token = $form->getField('Auth token:');
				$this->checkAuthToken($auth_token, $original_token);
				$form->query('button:Close')->one()->click();
			}

			// Open token configuration and check field values.
			$this->query('xpath://a[text()='.CXPathHelper::escapeQuotes($data['fields']['Name']).']')->one()->click();
			$this->page->waitUntilReady();
			$form->invalidate();

			if (array_key_exists('User', $data['fields'])) {
				$this->assertFalse($form->getField('User')->isEnabled());
			}

			$form->checkValue($data['fields']);
		}
	}

	/**
	 * Function that checks that no database changes occurred if nothing was actually changed during token update.
	 *
	 * @param string $url	URL that leads to the form to be updated.
	 */
	public function checkTokenSimpleUpdate($url) {
		$sql = 'SELECT * FROM token ORDER BY tokenid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open($url);
		$this->query('button:Update')->one()->waitUntilClickable()->click();

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that check that no database changes are occurred if token create or update action is cancelled.
	 *
	 * @param string $url		URL that leads to the form to be updated.
	 * @param string $username	user name in User field of the form if form opened from Administration section.
	 */
	public function checkTokenCancel($url, $username = null ) {
		$sql = 'SELECT * FROM token ORDER BY tokenid';
		$old_hash = CDBHelper::getHash($sql);

		$data = [
			'Name' => 'Token to be cancelled',
			'Description' => 'Token to be cancelled',
			'Set expiration date and time' => true,
			'Expires at' => '2038-01-01 00:00:00',
			'Enabled' => false
		];

		$this->page->login()->open($url);
		$form = $this->query('id:token_form')->asForm()->one();

		if ($username) {
			$data['User'] = $username;
		}

		$form->fill($data);
		$this->query('button:Cancel')->one()->waitUntilClickable()->click();

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that checks token deletion from token edit form.
	 *
	 * @param string $url			URL that leads to the form to be updated.
	 * @param string $token_name	The name of the token to be deleted.
	 */
	public function checkTokenDelete($url, $token_name) {
		$sql = 'SELECT tokenid FROM token WHERE name = '.zbx_dbstr($token_name);

		$this->page->login()->open($url);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that DB hash is not changed.
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Function that checks the token string.
	 *
	 * @param CElement $auth_token		page element that contains the token string.
	 * @param string $original_token	token string that belonged to the token before token regeneration.
	 */
	private function checkAuthToken($auth_token, $original_token) {
		// Get token text.
		$token_text = str_replace('  Copy to clipboard', '', $auth_token->getText());
		$this->assertEquals(64, strlen($token_text));

		if ($original_token) {
			$this->assertFalse($original_token === $token_text);
		}

		// Check that token string will be copied to clipboard.
		$clipboard_element = $auth_token->query('xpath:./a[text()="Copy to clipboard"]')->one();
		$this->assertEquals('writeTextClipboard("'.$token_text.'")', $clipboard_element->getAttribute('onclick'));
	}
}
