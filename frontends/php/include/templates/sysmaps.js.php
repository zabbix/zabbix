<script type="text/x-jquery-tmpl" id="mapElementSubtype">
	<tr id="subtypeRow">
		<td><?php echo _('Show'); ?></td>
		<td>
			<div class="objectgroup border_dotted">
				<input id="subtypeHostGroup" type="radio" class="input radio" name="elementsubtype" value="0">
				<label for="subtypeHostGroup"><?php echo _('Host group'); ?></label>
				<br />
				<input id="subtypeHostGroupElements" type="radio" class="input radio" name="elementsubtype" value="1">
				<label for="subtypeHostGroupElements"><?php echo _('Host group elements'); ?></label>
			</div>
		</td>
	</tr>
	<tr id="areaTypeRow">
		<td><?php echo _('Area type'); ?></td>
		<td>
			<div class="objectgroup border_dotted">
				<input id="areaTypeAuto" type="radio" class="input radio" name="areatype" value="0">
				<label for="areaTypeAuto"><?php echo _('Fit to map'); ?></label>
				<br />
				<input id="areaTypeCustom" type="radio" class="input radio" name="areatype" value="1">
				<label for="areaTypeCustom"><?php echo _('Custom size'); ?></label>
			</div>
		</td>
	</tr>
	<tr id="areaSizeRow">
		<td><?php echo _('Area size'); ?></td>
		<td>
			<label for="areaSizeWidth"><?php echo _('Width'); ?></label>
			<input id="areaSizeWidth" type="text" class="input text" name="areasizewidth" value="200">
			<label for="areaSizeHeight"><?php echo _('Height'); ?></label>
			<input id="areaSizeHeight" type="text" class="input text" name="areasizeheight" value="200">
		</td>
	</tr>
	<tr id="areaPlacingRow">
		<td><label for="areaPlacing"><?php echo _('Placing algorithm'); ?></label></td>
		<td>
			<select id="areaPlacing" class="input">
				<option value="0"><?php echo _('Grid'); ?></option>
			</select>
		</td>
	</tr>
</script>

<script type="text/javascript">
	jQuery(document).ready(function(){

		(function($){
			$.fn.hideDisable = function(show){
				if(show){
					this.find(':input').removeAttr('disabled');
					this.find(':input:checked').click();
					this.find('select').change();
				}
				else{
					this.find(':input').attr('disabled', 'disabled');
				}

				return this.toggle(show);
			};
		})(jQuery);
	});
</script>
