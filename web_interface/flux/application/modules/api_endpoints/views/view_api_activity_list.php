<? extend('master.php') ?>
<? startblock('extra_head') ?>
<style>
.mt-0 {
}
</style>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        build_grid("api_activity","",<? echo $grid_fields; ?>,<? echo $grid_buttons; ?>);
        $('.checkall').click(function () {
            $('.chkRefNos').attr('checked', this.checked); 
        });
         $("#api_activity_search_btn").click(function(){
            post_request_for_search("api_activity","","api_activity_search");
        });        
        $("#id_reset").click(function(){ 
            clear_search_request("api_activity","");
        });
        
 	var currentdate = new Date(); 
        var from_date = currentdate.getFullYear() + "-"
            + ('0' + (currentdate.getMonth()+1)).slice(-2) + "-" 
                + ("0" + currentdate.getDate()).slice(-2) + " 00:00:00";
            
        var to_date = currentdate.getFullYear() + "-"
           +('0' + (currentdate.getMonth()+1)).slice(-2) + "-" 
            +("0" + currentdate.getDate()).slice(-2) + " 23:59:59"
        $("#created_at_from_date").datetimepicker({
             value:from_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });  
         $("#created_at_to_date").datetimepicker({
             value:to_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });  
        
    });
</script>
<? endblock() ?>
<? startblock('page-title') ?>
<?= $page_title ?>
<? endblock() ?>
<? startblock('content') ?>
<section class="slice color-three">
	<div class="w-section inverse p-0">
		<div class="col-12">
			<div class="portlet-content mb-4" id="search_bar"
				style="cursor: pointer; display: none">
                        <?php echo $form_search; ?>
                </div>
		</div>
	</div>
</section>
<section class="slice color-three pb-4">
	<div class="w-section inverse p-0">
		<div class="card col-md-12 pb-4">
			<form method="POST" action="del/0/" enctype="multipart/form-data"
				id="ListForm">
				<table id="api_activity" align="left" style="display: none;"></table>
			</form>
		</div>
	</div>
	</div>
</section>
<? endblock() ?>	
<? end_extend() ?>  
