<?php
include (FCPATH . 'application/views/popup_header.php');
?>
<?php
if (isset($trunk_id) and ! empty($trunk_id)) {
    $trunk_id_json = json_encode((array) $trunk_id);
} else {
    $trunk_id = array();
    $trunk_id_json = json_encode((array) $trunk_id);
}
if (isset($carrier_id) and ! empty($carrier_id)) {
    $carrier_id_json = json_encode((array) $carrier_id);
} else {
    $carrier_id = array();
    $carrier_id_json = json_encode((array) $carrier_id);
}
if (isset($percentage) and ! empty($percentage)) {
    $percentage_json = json_encode((array) $percentage);
} else {
    $percentage = array();
    $percentage_json = json_encode((array) $percentage);
}

?>
<script type="text/javascript">
    $("#submit").click(function(){
        submit_form("pricing_form");
    })
</script>
<script type="text/javascript" language="javascript">
	$(document).ready(function() {
		$(".reseller_id").change(function(){
                var reseller_id=$("#reseller").val();
                if(reseller_id==0){
			$(".routing_type").parents('li.form-group').removeClass("d-none");
			$("select[name='trunk_id[]']").parents('li.form-group').removeClass("d-none");
			$("select[name='carrier_id[]']").parents('li.form-group').removeClass("d-none");
		}
		else{
			$(".routing_type").parents('li.form-group').addClass("d-none");
			$("select[name='trunk_id[]']").parents('li.form-group').addClass("d-none");
			$("select[name='carrier_id[]']").parents('li.form-group').addClass("d-none");
		}
        });
//        console.log("READY");
        var check_carrier=$("#check_carrier").val();
//        console.log("check_carrier "+ check_carrier);        
        $("#check_carrier").change(function(){
        var check_carrier=$("#check_carrier").val();
            if(check_carrier==0){
        			$("select[name='carrier_id[]']").parents('li.form-group').removeClass("d-none");
        		}
        	else{
        			$("select[name='carrier_id[]']").parents('li.form-group').addClass("d-none");
        		}
                });
        function trunk_change(routing_type){
         	if(routing_type == 0){
        	    $("#trunk_id").parents('li.form-group').addClass("d-none");  
        	    $('label[for="Trunks"]').hide();
        	    var trunk_count='<?= $trunk_count ?>';
        	    for(i=1;i <= trunk_count;i++){
        		$(".trunk_id_"+i).parents('li.form-group').removeClass("d-none");
        		$(".trunk_percentage_"+i).parents('li.form-group').addClass("d-none");
        	    }
        	    for(i=1;i <= trunk_count;i++){
        		var trunk_name= "Trunks"+i;
        		$("label[for="+trunk_name+"]").show(); 
        		$("#trunk_percentage_"+i).parents('li.form-group').addClass("d-none"); 
        	    }
        	}
        	else{
        		$("#trunk_id").parents('li.form-group').addClass("d-none");  
        	    $('label[for="Trunks"]').hide();
        	    $(".selectpicker").parents('li.form-group').removeClass("col-md-5");  
        		$(".selectpicker").parents('li.form-group').addClass("col-md-2"); 
        	    var trunk_count='<?= $trunk_count ?>';
        	    for(i=1;i <= trunk_count;i++){
        		$(".trunk_id_"+i).parents('li.form-group').removeClass("d-none");
        		$("#trunk_percentage_"+i).parents('li.form-group').removeClass("d-none");
        	    }
        	    for(i=1;i <= trunk_count;i++){
        		var trunk_name= "Trunks"+i;
        		$("label[for="+trunk_name+"]").show();
        		}
        	  }
        	  
            }
        function carrier_change(check_carrier){
        var check_carrier=$("#check_carrier").val();   
                 	if(check_carrier == 0){
                	    $("#carrier_id").parents('li.form-group').addClass("d-none");  
                	    $('label[for="Carriers"]').hide();
                	    console.log("Cadup 0");
                	    var carrier_count='<?= $carrier_count ?>';
                	    for(i=1;i <= carrier_count;i++){
                		$(".carrier_id_"+i).parents('li.form-group').removeClass("d-none");
                		$(".carrier_percentage_"+i).parents('li.form-group').addClass("d-none");
                	    }
                	    for(i=1;i <= carrier_count;i++){
                		var carrier_name= "Carriers"+i;
                		$("label[for="+carrier_name+"]").show(); 
                		$("#carrier_percentage_"+i).parents('li.form-group').addClass("d-none"); 
                	    }
                	}
                	else{
                	    console.log("Cadup 1");
                		$("#carrier_id").parents('li.form-group').addClass("d-none");  
                	    $('label[for="Carriers"]').hide();
                	    $(".selectpicker").parents('li.form-group').addClass("col-md-5");  
                		$(".selectpicker").parents('li.form-group').addClass("col-md-2"); 
                	    var carrier_count='<?= $carrier_count ?>';
                	    for(i=1;i <= carrier_count;i++){
                		$(".carrier_id_"+i).parents('li.form-group').addClass("d-none");
                		$("#carrier_percentage_"+i).parents('li.form-group').addClass("d-none");
                	    }
                	    for(i=1;i <= carrier_count;i++){
                		var carrier_name= "Carriers"+i;
                		$("label[for="+carrier_name+"]").show();
                		}
                	  }
                	  
                    }
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
