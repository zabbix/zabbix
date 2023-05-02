<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


use Zabbix\Widgets\CWidgetField;

class CWidgetFormView {

	private array $data;
	private string $name;

	private array $vars = [];
	private array $javascript = [];
	private array $templates = [];

	private CFormGrid $form_grid;

	public function __construct(array $data, string $name = 'widget_dialogue_form') {
		$this->data = $data;
		$this->name = $name;

		$this->makeFormGrid();
	}

	/**
	 * Add fieldset with multiple CWidgetFieldView-s as content.
	 *
	 * @param CWidgetFormFieldsetCollapsibleView|null $fieldset
	 *
	 * @return $this
	 */
	public function addFieldset(?CWidgetFormFieldsetCollapsibleView $fieldset): self {
		if ($fieldset !== null) {
			foreach ($fieldset->getFields() as $field) {
				if ($field instanceof CWidgetFieldView) {
					$this->registerField($field);
				}
			}
		}

		return $this->addItem($fieldset);
	}

	/**
	 * Add configuration row with single label and multiple CWidgetFieldView-s as content.
	 *
	 * @param CWidgetFieldsGroupView|null $fields_group
	 *
	 * @return $this
	 */
	public function addFieldsGroup(?CWidgetFieldsGroupView $fields_group): self {
		if ($fields_group !== null) {
			foreach ($fields_group->getFields() as $field) {
				if ($field instanceof CWidgetFieldView) {
					$this->registerField($field);
				}
			}
		}

		return $this->addItem([$fields_group->getLabel(), $fields_group]);
	}

	/**
	 * Add configuration row based on single CWidgetFieldView.
	 *
	 * @param CWidgetFieldView|null $field
	 *
	 * @return $this
	 */
	public function addField(?CWidgetFieldView $field): self {
		if ($field !== null) {
			$this->registerField($field);

			$this->form_grid->addItem([
				$field->getLabel(),
				(new CFormField($field->getView()))->addClass($field->getClass())
			]);
		}

		return $this;
	}

	public function addFieldVar(?CWidgetField $field): self {
		if ($field !== null) {
			$this->vars[] = new CVar($field->getName(), $field->getValue());
		}

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
		$output = [
			'header' => $this->data['unique_id'] !== null ? _('Edit widget') : _('Add widget'),
			'body' => implode('', [
				(new CForm())
					->setId('widget-dialogue-form')
					->setName($this->name)
					->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FORM)
					->addClass('dashboard-widget-'.$this->data['type'])
					->addItem($this->vars)
					->addItem($this->form_grid)
					// Submit button is needed to enable submit event on Enter on inputs.
					->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;')),
				implode('', $this->templates),
				$this->javascript ? new CScriptTag($this->javascript) : ''
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
				: $this->data['url'],
			'data' => [
				'original_properties' => [
					'type' => $this->data['type'],
					'unique_id' => $this->data['unique_id'],
					'dashboard_page_unique_id' => $this->data['dashboard_page_unique_id']
				]
			]
		];

		if ($error = get_and_clear_messages()) {
			$output['error'] = [
				'messages' => array_column($error, 'message')
			];
		}

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
