<?php
include (FCPATH . 'application/views/popup_header.php');
?>
<?php

?>
<script type="text/javascript">
    $("#submit").click(function(){
        submit_form("pricing_form");
    })
</script>
<script type="text/javascript" language="javascript">
	$(document).ready(function() {
		$('.rm-col-md-12').addClass('float-right');
        $(".rm-col-md-12").removeClass("col-md-12");
        
        var from_date = date + " 00:00:00";
        var to_date = date + " 23:59:59";

        var date_refactor = date + " 22:00:00";

		$("#customer_from_date").datetimepicker({
			value:from_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });  
         $("#customer_to_date").datetimepicker({
			value:to_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });

		 $("#refactor_date").datetimepicker({
			value:date_refactor,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM',
            footer:true
         });
	});
</script>
<section class="slice m-0">
	<div class="w-section inverse p-0">
		<div class="col-md-12 p-0 card-header">
			<h3 class="fw4 p-4 m-0"><? echo $page_title; ?></h3 class="text-light p-3 rounded-top">
		</div>
	</div>
</section>
<div>
	<div>
		<section class="slice m-0">
			<div class="w-section inverse p-4">
				<div style="">
                <?php

if (isset($validation_errors)) {
                    echo $validation_errors;
                }
                ?> 
            </div>
            <?php echo $form; ?>
        </div>
		</section>
	</div>
</div>
<script type="text/javascript" language="javascript">
$(document).ready(function() {
    $("input[type='hidden']").parents('li.form-group').addClass("d-none");
});
</script>
