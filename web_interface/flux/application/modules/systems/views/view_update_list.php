<? extend('master.php') ?>
<? startblock('extra_head') ?>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        
        function loading(){
            $(".overlay").show();
        }

        function stop_loading(){
            $(".overlay").hide();
        }

        build_grid("configuration_grid","",<? echo $grid_fields; ?>,<? echo $grid_buttons; ?>);
       
        $("#update_search_btn").click(function(){
            post_request_for_search("configuration_grid","","update_search");
        });        
        $("#id_reset").click(function(){
            clear_search_request("configuration_grid","");
        });

        $("#check_update").click(function() {
            loading();
            $.ajax({
                url: '<?= base_url('GitUpdate/executeUpdate') ?>', 
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    alert(response.message); 
                    refreshAjax();
                    stop_loading();
                },
                error: function(xhr, status, error) {
                    alert('Erro ao verificar atualização: ' + error);
                }
            });
        });

        $("#rollback").click(function() {
            loading();
            $.ajax({
                url: '<?= base_url('GitUpdate/executeRollback') ?>', 
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    alert(response.message); 
                    refreshAjax();
                    stop_loading();
                },
                error: function(xhr, status, error) {
                    alert('Erro ao verificar atualização: ' + error);
                }
            });
        });

        $(function() {
            refreshAjax = function(){$("#configuration_grid").flexReload();
        }
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
            	<div class="portlet-content mb-4"  id="search_bar" style="cursor:pointer; display:none">
                    	<?php echo $form_search; ?>
    	        </div>
        </div>
    </div>
</section>
<div class="col-md-12 pb-4 px-0 text-right">
	<span id="check_update" style="cursor: pointer;"
		class='btn btn-info'><?php echo gettext('Check Update'); ?></span>
    <span id="rollback" style="cursor: pointer;"
		class='btn btn-danger color-three'><?php echo gettext('Rollback'); ?></span>
</div>
<section class="slice color-three padding-b-20">
	<div class="w-section inverse no-padding">
    	<div class="">
        	<div class="">
                <div class="card col-md-12 pb-4">      
                        <form method="POST" action="del/0/" enctype="multipart/form-data" id="ListForm">
                            <table id="configuration_grid" align="left" style="display:none;"></table>
                        </form>
                </div>  
            </div>
        </div>
    </div>
</section>
<? endblock() ?>	
<? end_extend() ?>  
