<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=custom_comment_fields'.AMP.'method=save_comment_field', array('id'=>'edit_comment_field_form'));?>


<?php
$this->table->clear();

$this->table->set_template($cp_table_template);

$this->table->set_heading(
	array('width' => '40%', 'data' => lang('field_settings')),
	''
);

foreach ($data as $key => $val)
{
	$this->table->add_row(lang($key, $key), $val);
}

echo $this->table->generate();

$this->table->clear();
?>


<?php $this->table->set_template($cp_table_template);

foreach($field_type_tables as $ft => $d):?>

	<div id="ft_<?=$ft?>" class="js_hide">
	
	<?php
		if (is_array($d))
		{
			$this->table->rows = $d;
			$this->table->set_heading(
				array('width' => '40%', /*'colspan' => 2, */'data' => lang('field_type_options')/*.' :: '.$field_type_options[$ft]*/),
				''
			);

			$d = $this->table->generate();
			$this->table->clear();
		}
		echo $d;
	?>

	</div>

<?php endforeach;?>

<p><?=form_submit('field_edit_submit', lang($submit_lang_key), 'class="submit"')?></p>

<?=form_close()?>

