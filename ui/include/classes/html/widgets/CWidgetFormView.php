<?php declare(strict_types = 0);
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


use Zabbix\Widgets\CWidgetField;

use Zabbix\Widgets\Fields\CWidgetFieldReference;

class CWidgetFormView {

	private array $data;
	private string $name;

	private array $vars = [];
	private array $javascript = [];
	private array $templates = [];

	private CFormGrid $form_grid;

	private array $registered_fields = [];

	public function __construct(array $data, string $name = 'widget_dialogue_form') {
		$this->data = $data;
		$this->name = $name;

		if (array_key_exists(CWidgetFieldReference::FIELD_NAME, $data['fields'])) {
			$this->addFieldVar($data['fields'][CWidgetFieldReference::FIELD_NAME]);
		}

		$this->makeFormGrid();
	}

	/**
	 * Add fieldset with multiple CWidgetFieldView-s as content.
	 */
	public function addFieldset(?CWidgetFormFieldsetCollapsibleView $fieldset): self {
		if ($fieldset === null) {
			return $this;
		}

		foreach ($fieldset->getFields() as $field) {
			if ($field instanceof CWidgetFieldView) {
				$this->registerField($field);
			}
		}

		return $this->addItem($fieldset);
	}

	/**
	 * Add configuration row with single label and multiple CWidgetFieldView-s as content.
	 */
	public function addFieldsGroup(?CWidgetFieldsGroupView $fields_group): self {
		if ($fields_group === null) {
			return $this;
		}

		foreach ($fields_group->getFields() as $field) {
			if ($field instanceof CWidgetFieldView) {
				$this->registerField($field);
			}
		}

		return $this->addItem([$fields_group->getLabel(), $fields_group]);
	}

	/**
	 * Add configuration row based on single CWidgetFieldView.
	 */
	public function addField(?CWidgetFieldView $field): self {
		if ($field === null || in_array($field->getName(), $this->registered_fields, true)) {
			return $this;
		}

		$this->registerField($field);

		foreach ($field->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
			$this->form_grid->addItem([$label, (new CFormField($view))->addClass($class)]);
		}

		return $this;
	}

	public function addFieldVar(?CWidgetField $field): self {
		if ($field === null) {
			return $this;
		}

		$this->vars[] = new CVar($field->getName(), $field->getValue());

		return $this;
	}

	public function addItem($item): self {
		$this->form_grid->addItem($item);

		return $this;
	}

	public function addVar(string $name, string $value): self {
		$this->vars[] = (new CVar($name, $value))->removeId();

		return $this;
	}

	public function registerField(CWidgetFieldView $field): CWidgetFieldView {
		$this->registered_fields[] = $field->getName();

		$field->setFormName($this->name);

		$this->addJavaScript($field->getJavaScript());

		foreach ($field->getTemplates() as $template) {
			$this->addTemplate($template);
		}

		return $field;
	}

	public function addJavaScript(string $javascript): self {
		$this->javascript[] = $javascript;

		return $this;
	}

	public function includeJsFile(string $file_path): self {
		$view = APP::View();

		if ($view !== null) {
			ob_start();

			if ((include $view->getDirectory().'/'.$file_path) === false) {
				ob_end_clean();

				throw new RuntimeException(sprintf('Cannot read file: "%s".', $file_path));
			}

			$this->javascript[] = ob_get_clean();
		}

		return $this;
	}

	/**
	 * @throws JsonException
	 */
	public function show(): void {
		$messages = get_and_clear_messages();
		$message_box = $messages ? makeMessageBox(ZBX_STYLE_MSG_BAD, $messages) : '';

		$output = [
			'header' => $this->data['unique_id'] !== null ? _('Edit widget') : _('Add widget'),
			'body' => implode('', [
				$message_box,
				(new CForm())
					->setId('widget-dialogue-form')
					->setName($this->name)
					->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FORM)
					->addClass('dashboard-widget-'.$this->data['type'])
					->addItem($this->vars)
					->addItem($this->form_grid)
					// Submit button is needed to enable submit event on Enter on inputs.
					->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN)),
				implode('', $this->templates),
				(new CScriptTag('ZABBIX.Dashboard.initWidgetPropertiesForm();'))->addItem($this->javascript)
			]),
			'buttons' => [
				[
					'title' => $this->data['unique_id'] !== null ? _('Apply') : _('Add'),
					'class' => 'dialogue-widget-save',
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'ZABBIX.Dashboard.applyWidgetProperties();'
				]
			],
			'doc_url' => $this->data['url'] === ''
				? CDocHelper::getUrl(CDocHelper::DASHBOARDS_WIDGET_EDIT)
				: $this->data['url']
		];

		if ($this->data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		echo json_encode($output, JSON_THROW_ON_ERROR);
	}

	private function addTemplate(?CTemplateTag $template): void {
		if ($template !== null) {
			$this->templates[$template->getId()] = $template;
		}
	}

	private function makeFormGrid(): void {
		$types_select = (new CSelect('type'))
			->setFocusableElementId('label-type')
			->setId('type')
			->setValue($this->data['type'])
			->setAttribute('autofocus', 'autofocus')
			->addOptions(CSelect::createOptionsFromArray($this->data['known_types']))
			->addStyle('max-width: '.ZBX_TEXTAREA_MEDIUM_WIDTH.'px');

		if ($this->data['deprecated_types']) {
			$types_select->addOptionGroup(
				(new CSelectOptionGroup(_('Deprecated')))
					->addOptions(CSelect::createOptionsFromArray($this->data['deprecated_types']))
			);
		}

		$this->form_grid = (new CFormGrid())
			->addItem([
				new CLabel(
					[
						_('Type'),
						array_key_exists($this->data['type'], $this->data['deprecated_types'])
							? makeWarningIcon(_('Widget is deprecated.'))
							: null
					],
					'label-type'
				),
				new CFormField($types_select)
			])
			->addItem(
				(new CFormField(
					(new CCheckBox('show_header'))
						->setLabel(_('Show header'))
						->setLabelPosition(CCheckBox::LABEL_POSITION_LEFT)
						->setId('show_header')
						->setChecked($this->data['view_mode'] == ZBX_WIDGET_VIEW_MODE_NORMAL)
				))->addClass('form-field-show-header')
			)
			->addItem([
				new CLabel(_('Name'), 'name'),
				new CFormField(
					(new CTextBox('name', $this->data['name']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('default'))
				)
			]);

		if (array_key_exists('rf_rate', $this->data['fields'])) {
			$this->addField(new CWidgetFieldSelectView($this->data['fields']['rf_rate']));
		}
	}
}
