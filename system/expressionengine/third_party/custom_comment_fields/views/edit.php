<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=msm_member_group_switcher'.AMP.'method=save', array('id'=>'msm_member_group_switcher_edit_form'));?>


<?php 
$this->table->set_template($cp_pad_table_template);
/*$this->table->set_heading(
    array('data' => lang('preference'), 'style' => 'width:50%;'),
    lang('setting')
);*/


foreach ($data as $key => $val)
{
	$this->table->add_row(lang($key, $key), $val);
}

echo $this->table->generate();

$this->table->clear();

?>

<p><?=form_submit('submit', lang('submit'), 'class="submit"')?></p>

<?php
form_close();

