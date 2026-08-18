<?php include(FCPATH.'application/views/popup_header.php'); ?>

<script type="text/javascript">

	$( document ).ready(function() {

		var call_type_code = $("#call_type_code").val();
		var extensions = "<?php echo $extensions; ?>";
		var did_id = "<?php echo $id; ?>";

		if (call_type_code == 2 || call_type_code == 1) {
			$("#sip_profile_wrapper").show();
			$('.selectpicker').selectpicker('refresh');
		}

		if(call_type_code == 7 || call_type_code == 8 || call_type_code == 9 || call_type_code == 10 || call_type_code== 11){
			$.ajax({
				type: "POST",
				url: "<?= base_url()?>pbx_feature/pbx_destination_change/"+call_type_code+"/"+did_id+"/"+extensions,
				data:'',
				success:function(response) { 	
					$("#extensions_id").replaceWith(response);    
					if(call_type_code == 7 || call_type_code == 8 || call_type_code == 9 || call_type_code == 10 || call_type_code== 11){

						$('.selectpicker').selectpicker('refresh');
					}
				}
			});
		}
		if(call_type_code == 0 || call_type_code == 5){
			$.ajax({
				type: "POST",
				url: "<?= base_url()?>did/did_local_change/"+call_type_code+"/"+did_id+"/"+extensions,		    
				data:'',
				success:function(response) { 	
					$("#extensions_id").replaceWith(response);    
					$('.selectpicker').selectpicker('refresh');
				}
			});
		}  
	});	

	function calltype_change(val) {
		var did_id = "<?php echo $id; ?>";
		var call_type_code = val;

		if (call_type_code == 2 || call_type_code == 1) {
			$("#sip_profile_wrapper").show();
			$('.selectpicker').selectpicker('refresh');
		} else {
			$("#sip_profile_wrapper").hide();
		}

		if(call_type_code != ''){
			if(call_type_code == 7 || call_type_code == 9 || call_type_code == 10 || call_type_code== 11){
				
				$.ajax({
					type: "POST",
					url: "<?= base_url() ?>pbx_feature/pbx_destination_change/"+call_type_code+"/"+did_id,
					data:'',
					success:function(response) {
						$(".extensions_set").replaceWith(response);  
						$('.selectpicker').selectpicker('refresh');  
					}
				});
			} else if (call_type_code == 0 || call_type_code == 5) {
				$.ajax({
					type: "POST",
					url: "<?= base_url() ?>did/did_local_change/"+call_type_code+"/"+did_id,
					data:'',
					success:function(response) {
						$(".extensions_set").replaceWith(response);  
						$('.selectpicker').selectpicker('refresh');  
					}
				});
			} else {
				$(".extensions_set").replaceWith('<input type="text" name="extensions" id="extensions_id" value="" class="form-control form-control-lg extensions_set">');
			}
			}	 
		}	

</script>



<section class="slice m-0">
	<div class="w-section inverse p-0">
		<div class="col-md-12 card-header">
			<h3 class="fw4 p-4 m-0"><? echo $page_title; ?></h3 class="bg-secondary text-light p-3 rounded-top">
		</div>
	</div>    
</section>



<section class="slice color-three pb-4">
	<div id="floating-label" class="w-section inverse p-4">
		<?php if(isset($logtype) && ($logtype == 0 || $logtype == 3)){ ?>
			<form method="POST" action="<?= base_url() ?>user/user_did_forward_save/" enctype="multipart/form-data" id="did_forward" name="did_forward">    
			<?php } else{ ?>
				<form method="POST" action="<?= base_url() ?>did/did_forward_save/" enctype="multipart/form-data" id="did_forward" name="did_forward">   
				<?php } ?>

			<input name="id" value="<?php echo $id; ?>" type="hidden"/>

			<div class="form-group row">
				<label class="col-md-4 col-form-label"><?php echo gettext("Call Type"); ?></label>
				<div class="col-md-8">
						<?php
					$call_type_arr = array(
						"id"       => "call_type_code",
						"name"     => "call_type",
						"onChange" => "calltype_change(this.value)",
						"class"    => "form-control selectpicker form-control-lg call_type"
					);
					$did_call_type = form_dropdown(
						$call_type_arr,
						$this->db_model->build_dropdown("call_type_code,call_type", "did_call_types", "", ""),
						$call_type
					);
					echo gettext($did_call_type);
						?>
					</div>
					</div>

			<div class="form-group row">
				<label class="col-md-4 col-form-label"><?php echo gettext("Destination"); ?></label>
				<div class="col-md-8">
					<input type="text" name="extensions" id="extensions_id" value="<?php echo $extensions; ?>" class="form-control form-control-lg extensions_set">
				</div>
			</div>

			<div id="sip_profile_wrapper" class="form-group row" style="display:none">
				<label class="col-md-4 col-form-label"><?php echo gettext("SIP Profile"); ?></label>
				<div class="col-md-8">
					<select name="sip_profile" id="sip_profile_id" class="form-control selectpicker form-control-lg" data-live-search="true" data-live-search-style="begins">
					    <option value="0"><?php echo gettext('None'); ?></option>
					    <?php if (!empty($sip_profile)) { ?>
					        <?php foreach ($sip_profile as $row) { ?>
					            <?php $selected = (isset($current_sip_profile) && $current_sip_profile == $row['id']) ? 'selected="selected"' : ''; ?>
					            <option value="<?php echo $row['id']; ?>" <?php echo $selected; ?>><?php echo $row['name']; ?></option>
					        <?php } ?>
					    <?php } ?>
					</select>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-md-4 col-form-label"><?php echo gettext("Voicemail"); ?></label>
				<div class="col-md-8 d-flex align-items-center">
						<?php if($call_type_vm_flag == 0){ ?>
							<input type="hidden" name="call_type_vm_flag" value="1" />
							<input type="checkbox" name="call_type_vm_flag" value="0" />
						<?php } else{ ?>
							<input type="hidden" name="call_type_vm_flag" value="1" />
						<input type="checkbox" name="call_type_vm_flag" value="0"/>
					<?php } ?>
						</div>
					</div> 

			<div class="form-group row mt-3">
				<div class="col-md-6">
							<button class="btn btn-success btn-block" name="action" value="Save" type="submit"><?php echo gettext("Save"); ?></button>
						</div>
				<div class="col-md-6">
					<button class="btn btn-secondary btn-block" name="cancel" onclick="return redirect_page('NULL')" value="Cancel" type="button"><?php echo gettext("Cancel"); ?></button>
						</div>                        
					</div>
				</form>
			</div>
		</section>