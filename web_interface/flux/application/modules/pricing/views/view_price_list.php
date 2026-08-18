<? extend('master.php') ?>
<? startblock('extra_head') ?>


<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        
        build_grid("price_grid","",<? echo $grid_fields; ?>,<? echo $grid_buttons; ?>);

        var from_date = date + " 00:00:00";
        var to_date = date + " 23:59:59";

        $("#customer_search_from_date").datetimepicker({
			value:from_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });

        $("#customer_search_to_date").datetimepicker({
			value:to_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal:true,
            format: 'yyyy-mm-dd HH:MM:ss',
            footer:true
         });  
         
        $('.checkall').click(function () {
           $('.chkRefNos').prop('checked', $(this).prop('checked')); 
        });
        $("#price_search_btn").click(function(){
            post_request_for_search("price_grid","","price_search");
        });
        $("#refactor_search_btn").click(function(){
            post_request_for_search("price_grid","","refactor_search");
        });        
        $("#id_reset").click(function(){
            clear_search_request("price_grid","");
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
				style="display: none">
                        <?php echo $form_search; ?>
                </div>
		</div>
	</div>
</section>

<section class="slice color-three pb-4">
	<div class="w-section inverse p-0">
		<div class="card col-md-12 pb-4">
			<table id="price_grid" align="left" style="display: none;"></table>
		</div>
	</div>
</section>

<? endblock() ?>	

<? end_extend() ?>  
