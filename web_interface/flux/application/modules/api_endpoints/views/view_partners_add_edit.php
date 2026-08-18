<? extend('master.php') ?>
<? startblock('extra_head') ?>

<script type="text/javascript">
  $("#submit").click(function(){
    submit_form("partners_form");
  })
</script>
<?php endblock()?>
<?php startblock('page-title')?>
<?=$page_title?>
<?php endblock()?>
<?php startblock('content')?>
<div class="p-0">
	<section class="slice color-three">
		<div class="w-section inverse p-0">
<?php echo $form;?>
    <?php
if (isset($validation_errors) && $validation_errors != '') {
	?>
		      <script>
		       var ERR_STR = '<?php echo $validation_errors;?>';
		       print_error(ERR_STR);
		   </script>
	<?}?>
</div>
	</section>
</div>
<?endblock()?>
<?startblock('sidebar')?>
<?endblock()?>
<?end_extend()?>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        $("input[type='hidden']").parents('li.form-group').addClass("d-none");


    });
</script>
