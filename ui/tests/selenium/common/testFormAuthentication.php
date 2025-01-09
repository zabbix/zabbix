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
 * Base class for Authentication form function tests.
 */
class testFormAuthentication extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Open specific Authentication form tab and check basic common fields.
	 *
	 * @param string    $auth_type    LDAP or SAML
	 */
	protected function openFormAndCheckBasics($auth_type) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab($auth_type.' settings');
		$this->page->assertHeader('Authentication');
		$this->page->assertTitle('Configuration of authentication');

		$enable_auth_checkbox = $form->getField('Enable '.$auth_type.' authentication');
		$this->assertTrue($enable_auth_checkbox->isEnabled());
		$this->assertTrue($enable_auth_checkbox->isVisible());
		$form->checkValue(['Enable '.$auth_type.' authentication' => false]);

		// Check that Update button is clickable and no other buttons present.
		$this->assertTrue($form->query('button:Update')->one()->isClickable());
		$this->assertEquals(1, $form->query('xpath:.//ul[@class="table-forms"]//button')->count());

		return $form;
	}

	/**
	 * Check hints and form fields in mapping popups.
	 *
	 * @param CFormElement    $form              given form
	 * @param array           $hintboxes         hintboxes to be checked
	 * @param array           $mapping_tables    mapping tables to be checked
	 * @param string          $auth_type         LDAP or SAML
	 */
	protected function checkFormHintsAndMapping($form, $hintboxes, $mapping_tables, $auth_type) {
		// Open hintboxes and compare text.
		$this->checkHints($hintboxes, $form);

		// Check mapping tables headers.
		$this->checkTablesHeaders($mapping_tables, $form);

		// Check group mapping popup.
		$this->checkMappingDialog('User group mapping', 'New user group mapping', $form,
				[$auth_type.' group pattern', 'User groups', 'User role'], $auth_type
		);

		// Check media type mapping popup.
		$this->checkMappingDialog('Media type mapping', 'New media type mapping',
				$form, ['Name', 'Media type', 'Attribute', 'When active'], $auth_type
		);
	}

	/**
	 * Check headers in mapping tables.
	 *
	 * @param array           $tables    given tables
	 * @param CFormElement    $form      given form
	 */
	protected function checkTablesHeaders($tables, $form) {
		foreach ($tables as $name => $attributes) {
			$this->assertEquals($attributes['headers'], $form->getFieldContainer($name)
					->query('id', $attributes['id'])->asTable()->waitUntilVisible()->one()->getHeadersText()
			);
		}
	}

	/**
	 * Check mapping form in dialog.
	 *
	 * @param string          $field	    field which mapping is checked
	 * @param string          $title        title in dialog
	 * @param CFormElement    $form         given LDAP or SAML form
	 * @param array           $labels       labels in mapping form
	 * @param string          $auth_type    LDAP or SAML
	 */
	protected function checkMappingDialog($field, $title, $form, $labels, $auth_type) {
		$form->getFieldContainer($field)->query('button:Add')->waitUntilClickable()->one()->click();
		$mapping_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals($title, $mapping_dialog->getTitle());
		$mapping_form = $mapping_dialog->asForm();

		foreach ($labels as $label) {
			$mapping_field = $mapping_form->getField($label);
			$this->assertTrue($mapping_field->isVisible());
			$this->assertTrue($mapping_field->isEnabled());
		}

		$this->assertEquals($labels, $mapping_form->getRequiredLabels());

		$values = ($field === 'Media type mapping')
			? ['Name' => '', 'Media type' => 'Brevis.one', 'Attribute' => '']
			: [$auth_type.' group pattern' => '', 'User groups' => '', 'User role' => ''];

		$mapping_form->checkValue($values);

		// Check group mapping popup footer buttons.
		$this->assertTrue($mapping_dialog->getFooter()->query('button:Add')->one()->isClickable());

		// Check mapping dialog footer buttons.
		$footer = $this->checkFooterButtons($mapping_dialog, ['Add', 'Cancel']);

		// Check hint in group mapping popup.
		if ($field === 'User group mapping') {
			$this->checkHints([$auth_type.' group pattern' => "Naming requirements:\ngroup name must match ".$auth_type.
					" group name\nwildcard patterns with '*' may be used"], $mapping_dialog->asForm()
			);
		}

		// Close mapping dialog.
		$footer->query('button:Cancel')->waitUntilClickable()->one()->click();
	}

	/**
	 * Check buttons in dialog footer.
	 *
	 * @param COverlayDialogElement    $dialog     given dialog
	 * @param array                    $buttons    checked buttons array
	 */
	protected function checkFooterButtons($dialog, $buttons) {
		$footer = $dialog->getFooter();

		// Check that there are correct buttons count in the footer.
		$this->assertEquals(count($buttons), $footer->query('xpath:.//button')->all()->count());

		// Check that all footer buttons are clickable.
		$this->assertEquals(count($buttons), $footer->query('button', $buttons)->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		return $footer;
	}

	/**
	 * Check hints for labels in form.
	 *
	 * @param array            $hintboxes	given hintboxes to check
	 * @param CFormElement     $form        given form
	 */
	protected function checkHints($hintboxes, $form) {
		foreach ($hintboxes as $label => $text) {
			$form->getLabel($label)->query('xpath:./button[@data-hintbox]')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->all()->last();
			$this->assertEquals($text, $hint->getText());
			$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
		}
	}

	/**
	 * Set mapping for LDAP server.
	 *
	 * @param array            $mappings    given mappings
	 * @param CFormElement     $form        LDAP or SAML form
	 * @param string           $field       mapping field which is being filled
	 * @param boolean		   $success     true if mapping submits successfully, false if not
	 */
	protected function setMapping($mappings, $form, $field, $success = true) {
		foreach ($mappings as $mapping) {
			$form->getFieldContainer($field)->query('button:Add')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$param_form = $dialog->asForm();
			$param_form->fill($mapping);
			$param_form->submit();

			if ($success) {
				$dialog->waitUntilNotVisible();
			}
		}
	}
}
