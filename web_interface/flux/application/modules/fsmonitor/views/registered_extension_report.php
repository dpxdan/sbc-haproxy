<? extend('master.php') ?>
<? startblock('extra_head') ?>
<?php 
session_start();
include "view_freeswitch_request.php";
include "config.php";
$url_array=explode("/",$_SERVER['REQUEST_URI']);
$url='';
$folder_name=$url_array[1];
for($i=1;$i<count($url_array)-1;$i++){
	$url.="/".$url_array[$i];
}
?>
<script type="text/javascript">

$(document).ready(function(){ 
  var ip = location.host;
  $.ajax({
    type:'POST',
    url: "<?php echo base_url();?>fsmonitor/sip_devices_file_exits/",
    cache    : false,                 
    async: false, 
    success: function(data) {
	if(!data){
	      window.location.href = "<?php echo base_url();?>fsmonitor/sip_devices/";
}
    }
   });
});
</script>
<?php 
	$filename = $licence_file;
	if(file_exists($filename))
	{ 
	 $localkey = file_get_contents($filename);
}
else{
	 $localkey = '';
}
?>

<?php
	if(common_model::$global_config['system_config']['opensips'] == 0){
		redirect(base_url()."fsmonitor/opensips_devices/");
	}
 	    $this->db->select("value"); 
        $this->db->where("name",'refresh_second'); 
        $system      = $this->db->get("system");
        $system_res  = $system->result_array();
	
        if(isset($system_res[0]) && !empty($system_res[0])) {
    		$result = $system_res[0];    	
        }
?>


<?php 

	if(isset($result['value']) && !empty($result['value'])) {
?>


<meta http-equiv="refresh" content="<?php echo $result['value']; ?>" > 

<?php
	}

?>


<?php

if(empty($_POST)){
$_POST['host_id']=0;
} 

if(isset($_POST['second_reload']) && $_POST['second_reload'] != '') {
		$update_array = array("value"=>$_POST['second_reload']);
		$this->db->where("name","refresh_second");
		$this->db->update("system",$update_array);
	}
?>
<script type="text/javascript">
$(document).ready(function(){
 var id="<?php echo $_POST['host_id']?>";
  $("#fs_extension").flexigrid({
    url: "<?php echo base_url();?>fsmonitor/sip_devices_json/"+id,
    method: 'GET',
    dataType: 'json',
	colModel : [
		{display: '<?php echo gettext("User"); ?>', name: 'User', width: 200, sortable: false, align: 'center'},
		{display: '<?php echo gettext("Contact"); ?>', name: 'Contact', width: 200, sortable: false, align: 'center'},
		{display: '<?php echo gettext("Client-IP"); ?>', name: 'network-ip', width: 190, sortable: false, align: 'center'},
		{display: '<?php echo gettext("Client-Port"); ?>', name: 'network-port', width: 220, sortable: false, align: 'center'},
		{display: '<?php echo gettext("Latency (ms)"); ?>', name: 'ping-time', width: 220, sortable: false, align: 'center'},
		{display: '<?php echo gettext("Status"); ?>', name: 'status', width: 400, sortable: false, align: 'center'},
		{display: '<?php echo gettext("User Agent"); ?>', name: 'agent', width: 195, sortable: false, align: 'center'},
	],
	nowrap: false,
	showToggleBtn: false,
	sortname: "call-id",
	sortorder: "asc",
	usepager: true,
	resizable: true,
	useRp: true,
	showTableToggleBtn: false,
	width: "auto",
	height: "auto",
	page:'1',
	procmsg: '<?php echo gettext("Processing, please wait ..."); ?>',
	pagestat: '<?php echo sprintf(_("Displaying %s to %s of %s items"), "{from}", "{to}", "{total}"); ?>',
	onSuccess: function(data){
	  $('a[rel*=facebox]').facebox({
		    loadingImage : '<?php echo base_url();?>/images/loading.gif',
		    closeImage   : '<?php echo base_url();?>/images/closelabel.png'
	    });
	},
	onError: function(){
	    alert('Sorry, we are unable to connect to FluxSBC!!!');
	}
});
  $("#host_id").change(function(){
	var id = document.getElementById("host_id").value;

  });
});
</script>
<script>
function myFunction() {
	document.getElementById("extension").submit();
}
</script>
<? endblock() ?>
<? startblock('page-title') ?>
<?=$page_title?>
<? endblock() ?>
<? startblock('content') ?>

<script type="text/javascript">
setInterval( "refreshAjax();", 30000 ); 

$(function() {
	refreshAjax = function(){$("#fs_extension").flexReload();
}
});
</script>

<section class="slice color-three pb-4">
	<div class="w-section inverse p-0">
		<div class="card col-md-12 py-4">     
			<form method="POST" action="del/0/" enctype="multipart/form-data" id="ListForm">    
				<table id="fs_extension" align="left" style="display:none;"></table>
			</form>
		</div>  
	</div>
</section>


 <? endblock() ?>
	        
<? end_extend() ?>  

