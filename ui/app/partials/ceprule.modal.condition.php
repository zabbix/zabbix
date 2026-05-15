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
 * @var CPartial $this
 * @var array    $data
 */

$id = 'cep-condition';

$id_type = "$id-type";
$id_type_focus = "$id-type-focus";
$id_time_period = "$id-time-period";
$id_host_group = "$id-host-group";
$id_host = "$id-host";
$id_tag_value = "$id-tag-value";
$id_tag = "$id-tag";
$id_event_name = "$id-event-name";

?>

<form>
	<!-- Enable form submitting on Enter. -->
	<button type="submit" class="form-submit-hidden"></button>

	<div class="form-grid">
		<?= new CLabel('Type', $id_type_focus) ?>
		<div class="form-field">
			<?php
				$condition_type = (new CSelect('type'))
					->setId($id_type)
					->setFocusableElementId($id_type_focus);

				foreach (CCepRuleHelper::getConditionLabelStrings() as $value => $name) {
					$condition_type->addOption(new CSelectOption($value, $name));
				}

				echo $condition_type;
			?>
		</div>

		<template for-type="<?= ZBX_CEP_CONDITION_EVENT_NAME ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
					->setModern()
					// TODO: not translated use CCepRuleHelper::getConditionOperatorString
					->addValue('Equals', CONDITION_OPERATOR_EQUAL)
					->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
					->addValue('Contains', CONDITION_OPERATOR_LIKE)
					->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
				?>
			</div>

			<?= new CLabel('Event name', $id_event_name) ?>
			<div class="form-field">
				<?= (new CTextBox('event_name'))
					->setId($id_event_name)
					->setAttribute('placeholder', 'event name') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_HOST ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
					->setModern()
					->addValue('Equals', CONDITION_OPERATOR_EQUAL)
					->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
					->addValue('Contains', CONDITION_OPERATOR_LIKE)
					->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
				?>
			</div>

			<?= new CLabel('Host', $id_host) ?>
			<div class="form-field">
				<?= (new CTextBox('host'))
					->setId($id_host)
					->setAttribute('placeholder', 'host name') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_HOST_GROUP ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
						->setModern()
						->addValue('Equals', CONDITION_OPERATOR_EQUAL)
						->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
						->addValue('Contains', CONDITION_OPERATOR_LIKE)
						->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
				?>
			</div>

			<?= new CLabel('Host group', $id_host_group) ?>
			<div class="form-field">
				<?= (new CTextBox('host_group'))
					->setId($id_host_group)
					->setAttribute('placeholder', 'host group name') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_TAG_NAME ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
						->setModern()
						->addValue('Equals', CONDITION_OPERATOR_EQUAL)
						->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
						->addValue('Contains', CONDITION_OPERATOR_LIKE)
						->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
						->addValue('Does not exist', CONDITION_OPERATOR_NOT_EXISTS)
				?>
			</div>

			<?= new CLabel('Tag', $id_tag) ?>
			<div class="form-field">
				<?= (new CTextBox('tag'))
					->setId($id_tag)
					->setAttribute('placeholder', 'tag') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_TAG_VALUE ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
						->setModern()
						->addValue('Equals', CONDITION_OPERATOR_EQUAL)
						->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
						->addValue('Contains', CONDITION_OPERATOR_LIKE)
						->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
						->addValue('is greater than or equals', CONDITION_OPERATOR_MORE_EQUAL)
						->addValue('is less than or equals', CONDITION_OPERATOR_LESS_EQUAL)
				?>
			</div>

			<?= new CLabel('Tag value', $id_tag_value) ?>
			<div class="form-field">
				<?= (new CTextBox('tag_value'))
					->setId($id_tag_value)
					->setAttribute('placeholder', 'tag value') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_SEVERITY ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
						->setModern()
						->addValue('Equals', CONDITION_OPERATOR_EQUAL)
						->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
						->addValue('is greater than or equals', CONDITION_OPERATOR_MORE_EQUAL)
						->addValue('is less than or equals', CONDITION_OPERATOR_LESS_EQUAL)
				?>
			</div>

			<?= new CLabel('Severity') ?>
			<div class="form-field">
				<?= (new CSeverity('severity'))->setId('cep-condition-severity') ?>
			</div>
		</template>

		<template for-type="<?= ZBX_CEP_CONDITION_TIME_PERIOD ?>">
			<?= new CLabel('Operator') ?>
			<div class="form-field">
				<?= (new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
					->setModern()
					->addValue('In', CONDITION_OPERATOR_IN)
					->addValue('Not in', CONDITION_OPERATOR_NOT_IN)
				?>
			</div>

			<?= new CLabel('Time period', $id_time_period) ?>
			<div class="form-field">
				<?= (new CTextBox('time_period'))
					->setId($id_time_period)
					->setAttribute('placeholder', '1-7,00:00-24:00')
				?>
			</div>
		</template>

		<?= (new CInput('hidden', 'formulaid', ''))
			->setAttribute('data-field-type', 'hidden')
			->removeId()
		?>
	</div>
</form>
