

<?php if ($total_results == 0):?>
	<div class="tableFooter">
		<p class="notice"><?=lang('no_results')?></p>
	</div>
<?php else:?>

	<?php
		$this->table->set_template($cp_table_template);
		$this->table->set_heading($table_headings);

		echo $this->table->generate($data);
	?>


<?php endif; /* if $total_count > 0*/?>

<p><a class="submit" href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=custom_comment_fields'.AMP.'method=edit_comment_field'?>"><?=lang('create_comment_field')?></a></p>