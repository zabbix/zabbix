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

	public function __construct($data, $name = 'widget_dialogue_form') {
		$this->data = $data;
		$this->name = $name;

		$this->makeFormGrid();
	}

	/**
	 * Add configuration row with single label and multiple CWidgetFieldView-s as content.
	 *
	 * @param array|string|null $label
	 * @param array             $items
	 * @param string|null       $row_class
	 *
	 * @return $this
	 */
	public function addFieldsGroup($label, array $items, string $row_class = null): self {
		foreach ($items as &$item) {
			if ($item instanceof CWidgetFieldView) {
				$item = $this->makeField($item);
			}
		}
		unset($item);

		$this->form_grid->addItem([
			$label !== null
				? (new CLabel($label))
					->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
					->addClass($row_class)
				: null,
			(new CDiv($items))
				->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
				->addClass($row_class)
		]);

		return $this;
	}

	/**
	 * Add configuration row based on single CWidgetFieldView.
	 *
	 * @param CWidgetFieldView|null $field_view
	 * @param string|null           $row_class
	 * @param bool                  $show_label
	 *
	 * @return $this
	 */
	public function addField(?CWidgetFieldView $field_view, string $row_class = null, bool $show_label = true): self {
		if ($field_view !== null) {
			$this->registerFieldView($field_view);

			$this->form_grid->addItem($this->makeField($field_view, $row_class, $show_label));
		}

		return $this;
	}

	/**
	 * Prepare CWidgetFieldView for addFieldGroup() items array in default view or by custom CHTML.
	 *
	 * @param CWidgetFieldView $field_view
	 * @param array            $items
	 * @param string|null      $row_class
	 *
	 * @return array  Label and field views taken from field object or $items array if not empty.
	 */
	public function makeCustomField(CWidgetFieldView $field_view, array $items = [], string $row_class = null): array {
		$this->registerFieldView($field_view);

		return $items ?: $this->makeField($field_view, $row_class);
	}

	public function addItem($value): self {
		$this->form_grid->addItem($value);

		return $this;
	}

	public function addVar(string $name, string $value): self {
		$this->vars[] = (new CVar($name, $value))->removeId();

		return $this;
	}

	public function addFieldVar(?CWidgetField $field): self {
		if ($field !== null) {
			$this->vars[] = new CVar($field->getName(), $field->getValue());
		}

		return $this;
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
			'doc_url' => CDocHelper::getUrl(CDocHelper::DASHBOARDS_WIDGET_EDIT),
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

	private function registerFieldView(CWidgetFieldView $field_view): void {
		$field_view->setFormName($this->name);

		$this->addJavaScript($field_view->getJavaScript());

		foreach ($field_view->getTemplates() as $template) {
			$this->addTemplate($template);
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
				new CLabel(_('Type'), 'label-type'),
				new CFormField(array_key_exists($this->data['type'], $this->data['deprecated_types'])
					? [$types_select, ' ', makeWarningIcon(_('Widget is deprecated.'))]
					: $types_select
				)
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

	private function makeField(CWidgetFieldView $field_view, string $row_class = null, bool $show_label = true): array {
		$label = $show_label ? $field_view->getLabel() : null;

		return [
			$label !== null
				? $label
					->addClass($row_class)
					->setAsteriskMark($field_view->isRequired())
				: null,
			(new CFormField($field_view->getView()))
				->addClass($row_class)
		];
	}
}
