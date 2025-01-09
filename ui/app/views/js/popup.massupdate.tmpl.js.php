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


/**
 * @var CView $this
 * @var array $data
 */
?>
<?= (new CTemplateTag('valuemap-rename-row-tmpl'))->addItem(
	(new CRow([
		(new CTextBox('valuemap_rename[#{rowNum}][from]', '', false, DB::getFieldLength('valuemap', 'name')))
			->addStyle('width: 100%;'),
		(new CTextBox('valuemap_rename[#{rowNum}][to]', '', false, DB::getFieldLength('valuemap', 'name')))
			->addStyle('width: 100%;'),
		(new CCol(
			(new CButtonLink(_('Remove')))->addClass('element-table-remove'))
		)
			->addClass(ZBX_STYLE_TOP)
	]))->addClass('form_row')
); ?>

<script type="text/x-jquery-tmpl" id="macro-row-tmpl">
	<?= (new CRow([
			(new CCol([
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
					->disableSpellcheck()
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CButton('macros[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_TOP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}', ['tag' => '', 'value' => ''], ['add_post_js' => false]) ?>
</script>

<script type="text/x-jquery-tmpl" id="custom-intervals-tmpl">
	<tr class="form_row">
		<td>
			<ul class="<?= CRadioButtonList::ZBX_STYLE_CLASS ?>" id="delay_flex_#{rowNum}_type">
				<li>
					<input type="radio" id="delay_flex_#{rowNum}_type_0" name="delay_flex[#{rowNum}][type]" value="0" checked="checked">
					<label for="delay_flex_#{rowNum}_type_0"><?= _('Flexible') ?></label>
				</li><li>
					<input type="radio" id="delay_flex_#{rowNum}_type_1" name="delay_flex[#{rowNum}][type]" value="1">
					<label for="delay_flex_#{rowNum}_type_1"><?= _('Scheduling') ?></label>
				</li>
			</ul>
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="255" placeholder="<?= ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT ?>" style="max-width: 100px; width: 100%;">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="<?= ZBX_ITEM_SCHEDULING_DEFAULT ?>" style="max-width: 100px; width: 100%;" class="<?= ZBX_STYLE_DISPLAY_NONE ?>">
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_period" name="delay_flex[#{rowNum}][period]" maxlength="255" placeholder="<?= ZBX_DEFAULT_INTERVAL ?>" style="max-width: 110px; width: 100%;">
		</td>
		<td>
			<button type="button" id="delay_flex_#{rowNum}_remove" name="delay_flex[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="dependency-row-tmpl">
	<tr id="dependency_#{triggerid}" data-triggerid="#{triggerid}">
		<td>
			<input type="hidden" name="dependencies[]" id="dependencies_#{triggerid}" value="#{triggerid}">
			<a href="#{trigger_url}" class="js-edit-dependency" data-triggerid="#{triggerid}" data-context="#{context}" data-parent_discoveryid="#{parent_discoveryid}" data-prototype="#{prototype}" data-action="#{action}">#{name}</a>
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<?= (new CButton('remove', _('Remove')))
					->onClick("javascript: removeDependency('#{triggerid}');")
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId()
			?>
		</td>
	</tr>
</script>
