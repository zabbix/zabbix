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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for API tokens form function tests.
 */
class testFormApiTokens extends CWebTest {

	const DELETE_TOKEN = 'Token to be deleted';		                     // Token for deletion.
	const USER_ZABBIX_TOKEN = 'user-zabbix token';	                     // Token to be updated that belongs to user-zabbix.
	const CANCEL_SIMPLE_UPDATE = 'Token for cancel or simple update';    // Token for cancel or simple update.

	public static $update_token;
	public $url;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Function that checks the layout of the API token configuration form in Administration or User settings section.
	 *
	 * @param string $source	Section from which the scenario is executed.
	 */
	public function checkTokensFormLayout($source) {
		$this->page->login()->open($this->url);
		$this->page->waitUntilReady();
		$this->page->assertTitle('API tokens');
		$this->page->assertHeader('API tokens');

		$this->query('button:Create API token')->one()->waitUntilClickable()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:token_form')->asForm()->one();

		foreach (['Name' => '64', 'Description' => '65535'] as $field_name => $maxlength) {
			$field = $form->getField($field_name);
			$this->assertEquals('', $field->getValue());
			$this->assertEquals($maxlength, $field->getAttribute('maxlength'));
		}

		// Check the presence of User field and that it is empty by default if it exists.
		if ($source === 'administration') {
			$this->assertEquals('', $form->getField('User')->getValue());
		}
		else {
			$this->assertFalse($form->query('xpath://label[text()="User"]')->one(false)->isDisplayed());
		}

		// Check that "Set expiration date and time" checkbox is set by default.
		$expiration_checkbox = $form->getField('Set expiration date and time');
		$this->assertTrue($expiration_checkbox->getValue());

		$expires_at = $form->getField('Expires at')->query('id:expires_at')->one();
		$this->assertEquals('',$field->getValue());
		$this->assertEquals('255', $expires_at->getAttribute('maxlength'));
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

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Function checks the layout in Token regenerate form.
	 *
	 * @param string	$source		Section from which the scenario is executed.
	 */
	public function checkTokensRegenerateFormLayout($source) {
		$values = [
			'Name:' => 'Token for cancel or simple update',
			'User:' => 'Admin (Zabbix Administrator)',
			'Description:' => 'Token for testing cancelling',
			'Expires at:' => '2026-12-31 23:59:59'
		];

		// User field is not present in User settings => Api tokens configuration form.
		if ($source === 'user settings') {
			unset($values['User:']);
		}

		$this->page->login()->open($this->url);
		$this->query('link', self::CANCEL_SIMPLE_UPDATE)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('API token', $dialog->getTitle());
		$dialog->query('button:Regenerate')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$dialog->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'API token updated');
		$form = $dialog->asForm();
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
		$auth_token->query('xpath:./button[@data-hintbox]')->one()->click();
		$this->assertEquals('Make sure to copy the auth token as you won\'t be able to view it after the page is closed.',
				$this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible()->getText()
		);
		$this->assertTrue($dialog->query('xpath:.//button[@title="Close"]')->one()->isClickable());
		$dialog->close();
	}

	/**
	 * Function performs creation, update or regeneration of auth token and checks the result.
	 *
	 * @param array  $data		data provider
	 * @param string $action	create, update or regenerate
	 * @param string $token  	token name
	 */
	public function checkTokensAction($data, $action, $token = null) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = 'SELECT * FROM token ORDER BY tokenid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open($this->url)->waitUntilReady();

		if ($action === 'create') {
			$this->query('button:Create API token')->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('link', $token)->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:token_form')->asForm()->one();

		// Fill form or press appropriate button depending on the action.
		if ($action === 'regenerate') {
			$old_token = CDBHelper::getValue('SELECT token FROM token WHERE name='.zbx_dbstr($token));

			$dialog->query('button:Regenerate')->one()->click();
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
			$this->assertMessage(TEST_BAD, ($action === 'create') ? 'Cannot add API token' : 'Cannot update API token',
					$data['error_details']
			);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, ($action === 'create') ? 'API token added' : 'API token updated');

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
					$form->getField('Expires at:')->query('xpath:./button[@data-hintbox]')->one()->click();
					$hintbox_text = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible()->getText();
					$this->assertEquals('The token has expired. Please update the expiry date to use the token.', $hintbox_text);
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
				$dialog->close();
			}
			else {
				$dialog->ensureNotPresent();
				self::$update_token = $data['fields']['Name'];
			}

			// Open token configuration and check field values.
			$this->query('xpath://a[text()='.CXPathHelper::escapeQuotes($data['fields']['Name']).']')->one()->click();
			$dialog->waitUntilReady();
			$form->invalidate();

			if (array_key_exists('User', $data['fields'])) {
				$this->assertFalse($form->getField('User')->isEnabled());
			}

			$form->checkValue($data['fields']);
			$dialog->close();
		}
	}

	/**
	 * Function that checks that no database changes occurred if nothing was actually changed during token update.
	 */
	public function checkTokenSimpleUpdate() {
		$sql = 'SELECT * FROM token ORDER BY tokenid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open($this->url);
		$this->query('link', self::CANCEL_SIMPLE_UPDATE)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Update')->one()->waitUntilClickable()->click();
		$dialog->ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'API token updated');

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that check that no database changes are occurred if token create or update action is cancelled.
	 *
	 * @param string $action      create, update or regenerate
	 * @param string $username    user name in User field of the form if form opened from Administration section
	 */
	public function checkTokenCancel($action = 'create', $username = null) {
		$sql = 'SELECT * FROM token ORDER BY tokenid';
		$old_hash = CDBHelper::getHash($sql);

		$data = [
			'Name' => 'Token to be cancelled',
			'Description' => 'Token to be cancelled',
			'Set expiration date and time' => true,
			'Expires at' => '2038-01-01 00:00:00',
			'Enabled' => false
		];

		$this->page->login()->open($this->url);

		if ($action === 'create') {
			$this->query('button:Create API token')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link', self::CANCEL_SIMPLE_UPDATE)->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($username) {
			$data['User'] = $username;
		}

		$dialog->asForm()->fill($data);
		$dialog->query('button:Cancel')->one()->waitUntilClickable()->click();
		$dialog->ensureNotPresent();

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that checks token deletion from token edit form.
	 */
	public function checkTokenDelete() {
		$sql = 'SELECT tokenid FROM token WHERE name = '.zbx_dbstr(self::DELETE_TOKEN);

		$this->page->login()->open($this->url);
		$this->query('link', self::DELETE_TOKEN)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$dialog->ensureNotPresent();

		// Check that DB hash is not changed.
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Function that checks the token string.
	 *
	 * @param CElement $auth_token		  page element that contains the token string.
	 * @param string   $original_token    token string that belonged to the token before token regeneration.
	 */
	private function checkAuthToken($auth_token, $original_token) {
		// Get token text.
		$token_text = str_replace(' Copy to clipboard', '', $auth_token->query('tag:span')->one()->getText());
		$this->assertEquals(64, strlen($token_text));

		if ($original_token) {
			$this->assertFalse($original_token === $token_text);
		}

		// Check that token string will be copied to clipboard.
		$clipboard_element = $auth_token->query('button:Copy to clipboard')->one();
		$this->assertEquals($token_text, $clipboard_element->getAttribute('data-auth_token'));
	}
}
