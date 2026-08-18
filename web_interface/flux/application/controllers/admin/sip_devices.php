<?php
defined ( 'BASEPATH' ) or exit ( 'No direct script access allowed' );
/**
 * ****************************************************************
 * IMPORTANT!! : This is API for SIP Device CURD Operation : IMPORTANT!!
 * ****************************************************************
 *
 * ==================================================
 * API Expected parameters :
 * ===================================================
 * Integer : start_limit (Start limit for customer list)
 * Integer : end_limit (End limit for customer list)
 * Integer : accountid (Unique accountid for each customer)
 * String : password (Customer Password (password must be alphabetic, numeric and sepcial characters))
 * Integer : username (Unique account username for each customer)
 * Integer : sipdevice_id (Unique sipdevice id for each customer)
 * String : type (Device type for create: "registration" (default) or "ip")
 * String : destination_ip (Destination IP for type=ip DIDs, stored in dids.extensions)
 *
 * ===================================================
 * API Possible actions : create,read,update,delete,list
 * ===================================================
 * create : registration -> username,password,accountid ; ip -> username,destination_ip,accountid
 * read : sipdevice_id,accountid
 * update : registration -> mandatory : sipdevice_id,accountid,password ; non-mandatory : all fields of database (exclude id)
 *			ip -> mandatory : type=ip,sipdevice_id (sipdevice_id = dids.id) ; non-mandatory : destination_ip,ring_timeout,status
 * delete : sipdevice_id,accountid
 * list : start_limit,end_limit,accountid (returns registration devices and ip DIDs, each tagged with a "type" field)
 *
 * ===================================================
 * API URL
 * ===================================================
 * For Index : 
 */

require APPPATH . '/controllers/common/account.php';
class Sip_devices extends Account {

	protected $postdata = "";
	function __construct() {		
		parent::__construct ();
		$this->load->model ( 'common_model' );
		$this->load->library ( 'common' );
		$this->load->library ( 'flux/order' );
		$this->load->model ( 'db_model' );
		$this->load->library ( 'flux/order' );
		$this->load->library('Form_validation');
		$this->accountinfo = $this->get_account_info(); 
		if($this->accountinfo['type'] != -1 && $this->accountinfo ['type'] != 1 && $this->accountinfo ['type'] != 2 ){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'error_invalid_key' )
			), 400 );
		}
		$rawinfo = $this->post ();
		$this->postdata = array();
		foreach ( $rawinfo as $key => $value ) {
				$this->postdata [$key] = $this->_xss_clean ( $value, TRUE );
			}
		$this->postdata ['client_ip'] = $_SERVER['SERVER_ADDR'];
	}
	public function index() {
		$function = isset ( $this->postdata ['action'] ) ? $this->postdata ['action'] : '';
		$this->api_log->write_log ( 'API URL : ',base_url()."".$_SERVER['REQUEST_URI']);
		$this->api_log->write_log ( 'Params : ', json_encode($this->postdata) );
		$accountid = $this->postdata ['id'];
		$where = array('id'=>$accountid,'status'=>0);
		if($this->accountinfo['type'] == -1 || $this->accountinfo['type'] == 2){
			$this->db->where_in('type',array(2,-1));
		}else{
			$where = array('id' => $this->accountinfo['id'] , 'type' => 1);
			$this->db->where($where);
		}
		$accountinfo = (array)$this->db->get('accounts')->first_row();
		if(empty($accountinfo) || !isset($accountinfo)){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'account_not_found' )
			), 400 );
		}
		$accountinfo = $this->_authorize_account ( $accountinfo,true,true);
		if ($function != '') {
			$function = '_' . $function;
			$function_2 = '_sip_devices' . $function;
			$this->api_log->write_log ( 'Function : ', json_encode($function) );
			if (( int ) method_exists ( $this, $function ) > 0) {
				$this->$function ();
			} else if (( int ) method_exists ( $this, $function_2 ) > 0) {
				$this->$function_2 ();
			} else {
				$this->response ( array (
					'status' => false,
					'error' => $this->lang->line ( 'unknown_method' )
				), 400 );
			}
		} else {
			$this->response ( array (
				'status'=> false,
				'error' => $this->lang->line ( 'unknown_method' )
			), 400 );
		}
	}

	function _sip_devices_list(){
			if (empty($this->postdata['end_limit']) || empty($this->postdata['start_limit']) ){
				if(!( $this->postdata['start_limit'] == '0' || $this->postdata['end_limit'] == '0' )){
					$this->response ( array (
						'status' => false,
						'error' => $this->lang->line ( 'error_param_missing' ) . " integer:end_limit,integer:start_limit"
					), 400 );
				}else{
					$this->response ( array (
						'status' => false,
						'error' => $this->lang->line('number_greater_zero')
					), 400 );
				}
			}
			if(!($this->postdata['start_limit'] < $this->postdata['end_limit'])){
				$this->response ( array (
						'status' => false,
						'error' => $this->lang->line('valid_start_limit')
				), 400 );
			}
			if($this->postdata['object_where_params']['mailto']){
				$this->response ( array (
					'status' => false,
					'error' => $this->lang->line ( 'not_allowed_mailto_search' ) 
				), 400 );
			}
			$object_where_params = $this->postdata['object_where_params'];
			foreach($object_where_params as $object_where_key => $object_where_value) {
				if($object_where_value != '') {	
				if($object_where_key == "accountid") {
					if(!$this->form_validation->integer($object_where_value)){
						$this->response ( array (
							'status' => false,   
							'success' =>  $this->lang->line ('enter_valid_accountid')  
						), 400 );
					}
					$accountid = $object_where_value;
					$where = "accountid" . ' = "' . $object_where_value . '" AND ';            
				}
					$where = $object_where_key . ' = "' . $object_where_value . '" AND ';
					if(!empty($where)) {
						$where = rtrim($where,"AND ");
						$this->db->where($where);
					}
				}
			}
			$start = $this->postdata['start_limit']-1;
			$limit = $this->postdata['end_limit'];
			$no_of_records = (int)$limit - (int)$start;
			if($this->accountinfo['type'] == 1){
				$where = array('reseller_id' => $this->accountinfo['id'] );
				$this->db->where($where);
			}
			$query = $this->db->limit($no_of_records, $start)
				->order_by('id','desc')
				->select('*')
				->get ('sip_devices');
			$count = $query->num_rows();

			$sipdeviceinfo = array();
			$sipdevice_info = $query->result_array();
			foreach ($sipdevice_info as $key => $sipdevice_value) {
				$sipdevice_value['dir_params'] = json_decode($sipdevice_value['dir_params'],true);
				$decoded_pass =  $this->common->decode($sipdevice_value['dir_params']['password']);
				$sipdevice_value['sip_profile_name'] = $this->common->get_field_name('name','sip_profiles',array('id'=>$sipdevice_value['sip_profile_id']));
				$sipdevice_value['creation_date'] = $this->common->convert_GMT_to('','',$sipdevice_value['creation_date'],$this->accountinfo['timezone_id']);
				$sipdevice_value['last_modified_date'] = $this->common->convert_GMT_to('','',$sipdevice_value['last_modified_date'],$this->accountinfo['timezone_id']);
				$sipdevice_value['status'] = $sipdevice_value['status'] == '1' ? 'Inactive' : 'Active';
				unset($sipdevice_value['call_waiting'],$sipdevice_value['sip_profile_id']);
				if($sipdevice_value['reseller_id'] == '0'){
				unset($sipdevice_value['reseller_id']);
				}
				if($this->accountinfo['type'] == '1'){
					unset($sipdevice_value['reseller_id']);
				}
				$sipdevice_value['type'] = 'registration';
				$sipdeviceinfo[] =$sipdevice_value;
			}

			// IP DIDs (call_type = 2) have no sip_devices row; fetch them from the dids table.
			// object_where_params filters are not applied here (they target sip_devices columns).
			if($this->accountinfo['type'] == 1){
				$reseller_account_ids = array();
				$reseller_accounts = $this->db->select('id')->get_where('accounts', array('reseller_id' => $this->accountinfo['id']))->result_array();
				foreach($reseller_accounts as $reseller_account){
					$reseller_account_ids[] = $reseller_account['id'];
				}
				$this->db->where_in('accountid', !empty($reseller_account_ids) ? $reseller_account_ids : array(0));
			}
			$this->db->where('call_type', 2);
			if(!empty($accountid) || isset($accountid)){
			$this->db->where('accountid', $accountid);
			}
			$ip_did_query = $this->db->limit($no_of_records, $start)
				->order_by('id','desc')
				->select('*')
				->get('dids');
			$count += $ip_did_query->num_rows();
			foreach($ip_did_query->result_array() as $ip_did_value){
				$sipdeviceinfo[] = array(
					'id'                 => $ip_did_value['id'],
					'username'           => $ip_did_value['number'],
					'accountid'          => $ip_did_value['accountid'],
					'destination_ip'     => $ip_did_value['extensions'],
					'call_type'          => $ip_did_value['call_type'],
					'product_id'         => $ip_did_value['product_id'],
					'leg_timeout'        => $ip_did_value['leg_timeout'],
					'status'             => $ip_did_value['status'] == '1' ? 'Inactive' : 'Active',
					'last_modified_date' => $this->common->convert_GMT_to('','',$ip_did_value['last_modified_date'],$this->accountinfo['timezone_id']),
					'type'               => 'ip'
				);
			}

			if (!empty($sipdeviceinfo)) {
				$this->response ( array (
					'status' => true,
					'total_count'=>$count,
					'data' => $sipdeviceinfo,
					'success' => $this->lang->line( "sipdevice_list_information" )
				), 200 );
			}else{
				$this->response ( array (
					'status' => true,
					'total_count'=>0,
					'data' => array(),
					'success' => $this->lang->line( "no_records_found" )
				), 200 );
			}
	}
			
	function _sip_devices_create() {
		$postdata = $this->postdata;
		$type = isset($postdata['type']) && $postdata['type'] != '' ? strtolower($postdata['type']) : 'registration';
		if(!in_array($type, array('ip','registration'))){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'invalid_device_type' )
			), 400 );
		}
		if($this->form_validation->required($postdata['accountid'] == '')){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'account_not_found' )
			), 400 );
		}else{
			$account_info = '';
			if($this->accountinfo['type'] != '1'){
				if($this->form_validation->required($postdata['reseller_id'] == '')){
					$this->response ( array (
						'status'  => false,
						'error'   => $this->lang->line ( 'enter_reseller_id' )
					), 400 );
				}else{
					$resellerinfo = (array)$this->db->get_where ("accounts",array("reseller_id"=>$postdata['reseller_id'],"deleted"=>0,"status"=>0))->first_row();
					if(empty($resellerinfo)){
						$this->response ( array (
						'status'  => false,
						'error'   => $this->lang->line ( 'valid_reseller_id' )
						), 400 );
					}else{
							$account_info = $this->common->get_field_name('id','accounts',array('id' => $postdata['accountid'],'reseller_id'=> $postdata['reseller_id'],'deleted'=>0)); 
					}
				}
			}

			if($this->accountinfo['type'] == '1'){
				$account_info = $this->common->get_field_name('id','accounts',array('id' => $postdata['accountid'],'reseller_id'=> $postdata['id'],'deleted'=>0)); 
			}
			if(empty($account_info)){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'account_not_found' )
				), 400 );
			}
			if(!($postdata['status'] == '1' || $postdata['status']=='0') ){
				$postdata['status'] = '0';
			}
			if ($postdata['username'] == "" || !isset($postdata['username'])) {
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line('invalid_sip_number')
				), 400);
			}else{
				if($this->accountinfo['type'] == '1'){
					$where_array = array('username' => $postdata['username'],
					'reseller_id' => $postdata['id']);
					$where_did = array('number' => $postdata['username'],
					'parent_id' => $postdata['id']);
				}else{
					$where_array = array('username' => $postdata['username'],'reseller_id' => $postdata['reseller_id']);
					$where_did = array('number' => $postdata['username'],'parent_id' => $postdata['reseller_id']);

				}
				
				$sip_device_id = $this->common->get_field_name('id','sip_devices',$where_array);
				if(!empty($sip_device_id)){
					$this->response ( array (
						'status'  => false,
						'error'   => $this->lang->line ( 'duplicate_sip_device' )
					), 400 );
				}

				$did_id = $this->common->get_field_name('id','dids',$where_did);
				if(!empty($did_id)){
					$this->response ( array (
						'status'  => false,
						'error'   => $this->lang->line ( 'duplicate_sip_device' )
					), 400 );
				}

				if(!$this->form_validation->integer($postdata['username'])) {
					$this->response(array(
						'status' => false,
						'error' => $this->lang->line('invalid_sip_number')
					), 400);
				}
			}

			if($type == 'ip' && (!isset($postdata['destination_ip']) || $postdata['destination_ip'] == '')){
				$this->response ( array (
					'status' => false,
					'error'  => $this->lang->line ( 'destination_ip_required' )
				), 400 );
			}

			if($type == 'registration'){
				$sip_device_id_external = $this->common->get_field_name('id_sip_external','sip_devices',array('id_sip_external' => $postdata['id_sip_external']));
				if(!empty($sip_device_id_external)){
						$this->response ( array (
							'status'  => false,
							'error'   => $this->lang->line ( 'duplicate_sip_device' )
						), 400 );
					}

				if($postdata['password'] == ''){
					$password = $this->common->generate_password();

				}else{
					$password = $postdata['password'];
				}
				
				if (!isset($postdata['caller_name']) || $postdata['caller_name'] == ""){
					$postdata['caller_name'] = $postdata['username'];
				}

				if (!isset($postdata['caller_number']) || $postdata['caller_number'] == ""){
					$postdata['caller_number'] = $postdata['username'];
				}

				if(isset($postdata['mailto']) && !empty($postdata['mailto']) && (!filter_var($postdata['mailto'], FILTER_VALIDATE_EMAIL))){
					$this->response ( array (
						'status' => false,
						'error' => $this->lang->line('invalid_email_format')
					), 400 );
				}
				
				if($this->form_validation->required($postdata['sip_profile_id'] == '')){
					$postdata['sip_profile_id'] = '1';
				}else{
					$this->db->select ( '*' );
					$this->db->order_by('id', 'ASC');
					$this->db->where('id', $this->postdata['sip_profile_id']);
					$this->db->limit('1');
					$sip_profile_id = ( array ) $this->db->get ( 'sip_profiles' )->first_row ();
					if(empty($sip_profile_id) || $sip_profile_id == ''){
						$this->response ( array (
							'status' => false,
							'error' => $this->lang->line ( 'valid_sip_profile_id' ) 
						), 400 );
					}
				}

				if(!($postdata['voice_mail_enable'] =='false' || $postdata['voice_mail_enable'] == 'true')){
					$postdata['voice_mail_enable'] = 'false';
				}
				if(!($postdata['attach_file'] =='false' || $postdata['attach_file'] == 'true')){
					$postdata['attach_file'] = 'false';
				}
				if(!($postdata['local_after_email'] =='false' || $postdata['local_after_email'] == 'true')){
					$postdata['local_after_email'] = 'false';
				}
				if(!($postdata['send_all_message'] =='false' || $postdata['send_all_message'] == 'true')){
					$postdata['send_all_message'] = 'false';
				}
				$digits = 5;
				$random_password = rand(pow(10, $digits - 1), pow(10, $digits) - 1);
				$sipdevice_array = array (
					'username' => $postdata['username'],
					'sip_profile_id' => $postdata ['sip_profile_id'],
					"reseller_id" => $this->accountinfo['type'] == '1' ? $postdata['id'] : $postdata ['reseller_id'],
					'accountid' => $postdata['accountid'],
					'dir_params' => json_encode(array(
						"password"=>  $password ,
						'vm-enabled' => $postdata['voice_mail_enable'] ,
						"vm-password"=> $random_password,
						"vm-mailto"=> $postdata['mailto'],
						"vm-attach-file"=>$postdata['attach_file'],
						"vm-keep-local-after-email"=> $postdata['local_after_email'],
						"vm-email-all-messages"=>$postdata['send_all_message']
					)),
					"dir_vars"=>json_encode(array(
						'effective_caller_id_name' => $postdata['caller_name'],
						'effective_caller_id_number' => $postdata['caller_number'],
					)),
					'codec' => $postdata['codec'],
					'status' => isset($postdata ['status']) ? $postdata ['status'] : '0',
					'creation_date'=>gmdate('Y-m-d H:i:s'),
					'last_modified_date'=>gmdate('Y-m-d H:i:s'),
					'codec' => 'PCMU,PCMA',
					'call_waiting' => '0',
					'id_sip_external' => $postdata['id_sip_external'] ? $postdata['id_sip_external'] : '0'
				);
				$this->db->insert("sip_devices",$sipdevice_array);
				$last_id = $this->db->insert_id ();
				$final_array = $this->accountinfo;
				$final_array['sip_user_name'] = $sipdevice_array['number'];
				$final_array['password'] = $password;
				$final_array['id'] = $postdata['accountid'];
				$final_array['status_code'] = 306;
				$this->common->mail_to_users('create_sip_device',$final_array);	
				$sipdevice_array['sipdevice_id'] = (string)$last_id;
				unset($sipdevice_array['id']);
				$sipdevice_array['dir_params'] = json_decode($sipdevice_array['dir_params'],true);
				$decoded_pass =  $this->common->decode($sipdevice_array['dir_params']['password']);
				//$sipdevice_array['dir_params']['password'] = $this->common->encrypt($decoded_pass);
				$sipdevice_array['dir_params'] = json_encode($sipdevice_array['dir_params']);
				$sipdevice_array['creation_date'] = $this->common->convert_GMT_to('','',$sipdevice_array['creation_date'],$this->accountinfo['timezone_id']);
				$sipdevice_array['last_modified_date'] = $this->common->convert_GMT_to('','',$sipdevice_array['last_modified_date'],$this->accountinfo['timezone_id']);
				// END
			}

			$queryDids = $this->db->get_where('dids', array('number' => $postdata['username']));

			if($queryDids->num_rows() == 0){
					$insert_product_did_array = array(
						'name' => $postdata['username'],
						'country_id' => 28,
						'product_category' => 4,
						'buy_cost' => 0,
						'price' => 0,
						'setup_fee' => 0,
						'can_resell' => 0,
						'commission' => 0,
						'billing_type' => 1,
						'billing_days' => 28,
						'free_minutes' => 0,
						'applicable_for' => 0,
						'apply_on_existing_account' => 0,
						'apply_on_rategroups' => '',
						'destination_rategroups' => '',
						'destination_countries' => '',
						'destination_calltypes' => '',
						'release_no_balance' => 0,
						'can_purchase' => 0,
						'status' => 0,
						'is_deleted' => 0,
						'created_by' => 1,
						'reseller_id' => 0,
						'creation_date' => gmdate("Y-m-d H:i:s"),
						'last_modified_date' => gmdate("Y-m-d H:i:s")
				);
			}

			$this->db->insert("products", $insert_product_did_array);
			$product_did_id = $this->common->get_field_name('id','products',array('name' => $postdata['username']));

			$did_add_array = array (
				'number' => $postdata['username'],
				'accountid' => $postdata['accountid'],
				'status' => '0',
				'extensions' => $type == 'ip' ? $postdata['destination_ip'] : $postdata['username'],
				'call_type' => $type == 'ip' ? 2 : 0,
				'product_id' => $product_did_id,
				'leg_timeout' => !empty($postdata['ring_timeout']) ? $postdata['ring_timeout'] : '60',
			);

			$this->db->insert("dids",$did_add_array);
			$did_add_array['did_id'] = (string)$this->db->insert_id();
			$account_id =  $postdata['accountid'];
			$created_by_accountinfo = array(
			    'id'     => '1'
			);
			$productdata['product_id'] = $product_did_id;
			$productdata['create_invoice'] = "false";
			$confirm_oder = $this->order->confirm_order($productdata, $account_id, $created_by_accountinfo);

			if($type == 'ip'){
				$this->response ( array (
					'status'=>true,
					'data' => $did_add_array,
					'success' => $this->lang->line( 'did_ip_created' )
				), 200 );
			}

			$this->response ( array (
				'status'=>true,
				'data' => $sipdevice_array,
				'success' => $this->lang->line( 'sipdevice_created' )
			), 200 );
		}
	}

	function _sip_devices_delete(){
		$postdata = $this->postdata;
		if($this->form_validation->required($postdata['sipdevice_id'] == '')){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'require_sip_id' )
			), 400 );
		}
		if(!$this->form_validation->numeric_with_comma($postdata['sipdevice_id'])){
			$this->response ( array (
				'status' => false,
				'success' =>  $this->lang->line ('valid_sip_id')  
			), 400 );
		}

		$get_device = $this->common->get_field_name('username', 'sip_devices',array('id' => $postdata['sipdevice_id']));
		$did_info = (array) $this->db->get_where("dids", "number = $get_device")->result_array();
		$did_details = $did_info[0];
		$this->api_log->write_log ( 'get_device : ', json_encode($get_device));		
		$this->api_log->write_log ( 'did_info : ', json_encode($did_details['product_id']));

		$accountinfo = $this->accountinfo;
		$this->did_number_release($did_details, $accountinfo, 'remove');

		$category_name = '';
		$acc_id = '';
		$order_items_id = '';
		$order_id = '';
		$did_delete = array();
		$product_category_details = array();
		$product_category_details_result = array();
		$product_category_details = $this->db_model->getSelect("name,product_category", "products", array(
			"id" => $did_details['product_id']
		));

		if ($product_category_details->num_rows > 0) {
			$product_category_details_result = $product_category_details->result_array()[0];

			$did_delete['product_name'] = $product_category_details_result['name'];

			$category_name = $this->common->get_field_name("name", "category", array(
				"id" => $product_category_details_result['product_category']
			));
			$acc_id = $this->common->get_field_name("accountid", "order_items", array(
				"product_id" => $did_details['product_id']
			));
			$order_items_id = $this->common->get_field_name("order_id", "order_items", array(
				"product_id" => $did_details['product_id']
			));
			$order_id = $this->common->get_field_name("order_id", "orders", array(
				"id" => $order_items_id
			));
			
			$did_delete['category_name'] = $category_name;
			$did_delete['next_billing_date'] = gmdate('Y-m-d H:i:s');
			$acc_info_result = array();
			$did_delete['order_id'] = $order_id;
			$acc_info = $this->db_model->getSelect("id,number,first_name,last_name,company_name,email,reseller_id", "accounts", array(
				"id" => $acc_id
			));

			if ($acc_info->num_rows > 0) {
				$acc_info_result = $acc_info->result_array()[0];
				$final_array = array_merge($acc_info_result, $did_delete);
				$this->common->mail_to_users('product_release', $final_array);
			}
		}
		$this->db->where("id = " . $did_details['product_id']);
		$this->db->delete('products');
		$this->db->where(array(
			"id" => $did_details['id']
		));
		$this->db->delete('dids');

		$where = array();
		if($this->accountinfo['type'] == '1'){
			$where = array('reseller_id' => $postdata['id']);
		}
		$this->db->where("id IN (".$postdata['sipdevice_id'].") ");
		$delete_sip_info = $this->db->delete('sip_devices',$where);
		$delete_sip_info = $this->db->affected_rows($delete_sip_info ); 
		if($delete_sip_info != '0'){

			$this->response ( array (
				'status'=>true,
				'success' => $this->lang->line( 'sipdevice_deleted' )
			), 200 );
		}else{
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line( 'sipdevice_not_found' )
			), 400 );
		}
	}

	function _sip_devices_update(){
		$postdata = $this->postdata;
		$type = isset($postdata['type']) && $postdata['type'] != '' ? strtolower($postdata['type']) : 'registration';
		if(!in_array($type, array('ip','registration'))){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'invalid_device_type' )
			), 400 );
		}

		if($type == 'ip'){
			if(empty($postdata['sipdevice_id'])){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'require_sip_id' )
				), 400 );
			}
			if($this->accountinfo['type'] == 1){
				$reseller_account_ids = array();
				$reseller_accounts = $this->db->select('id')->get_where('accounts', array('reseller_id' => $this->accountinfo['id']))->result_array();
				foreach($reseller_accounts as $ra){
					$reseller_account_ids[] = $ra['id'];
				}
				$this->db->where_in('accountid', !empty($reseller_account_ids) ? $reseller_account_ids : array(0));
			}
			$didinfo = (array)$this->db->get_where ( 'dids', array('id' => $postdata['sipdevice_id'], 'call_type' => 2) )->first_row();
			if(empty($didinfo)){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'sipdevice_not_found' )
				), 400 );
			}
			if(!empty($postdata['ring_timeout']) && !$this->form_validation->integer($postdata['ring_timeout'])){
				$this->response ( array (
					'status' => false,
					'error'  => $this->lang->line ( 'enter_leg_timeout' )
				), 400 );
			}
			$did_update_array = array (
				'extensions'         => !empty($postdata['destination_ip']) ? $postdata['destination_ip'] : $didinfo['extensions'],
				'number'         => !empty($postdata['number']) ? $postdata['number'] : $didinfo['number'],
				'call_type'          => 2,
				'last_modified_date' => gmdate('Y-m-d H:i:s')
			);
			if(!empty($postdata['ring_timeout'])){
				$did_update_array['leg_timeout'] = $postdata['ring_timeout'];
			}
			if(isset($postdata['status']) && ($postdata['status'] == '0' || $postdata['status'] == '1')){
				$did_update_array['status'] = $postdata['status'];
			}
			$this->db->where ( 'id', $didinfo['id'] );
			$this->db->update ( 'dids', $did_update_array );
			
			if(!empty($postdata['number'])){
				$product_update_array['name'] = $postdata['number'];
				$this->db->where ( 'id', $didinfo['product_id'] );
			    $this->db->update ( 'products', $product_update_array );
			}
			
			
			$did_update_array['did_id'] = (string)$didinfo['id'];
			$did_update_array['number'] = $didinfo['number'];
			$did_update_array['last_modified_date'] = $this->common->convert_GMT_to('','',$did_update_array['last_modified_date'],$this->accountinfo['timezone_id']);
			$this->response ( array (
				'status'=>true,
				'data' => $did_update_array,
				'success' => $this->lang->line( 'did_ip_updated' )
			), 200 );
		}

		if (isset($postdata['reseller_id'])) {
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ( 'reseller_update_not_allowed' ) 
			), 400 );	
		}

		if (isset($postdata['number'])) {
			$selectSipDevices = $this->db->get_where('sip_devices', array('username' => $postdata['number']));
			if($selectSipDevices->num_rows() != 0){
				$row = $selectSipDevices->row();
				if($row->id != $postdata['sipdevice_id']){
					$this->response (array (
						'status' => false,
						'error' => $this->lang->line ( 'duplicate_sip_device' ) 
					), 400 );
				}
			}
		}

		if($this->form_validation->required($postdata['sipdevice_id'] == '')){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ( 'require_sip_id' ) 
			), 400 );
		}else{
			#$sipdeviceinfo = (array)$this->db->get_where ("sip_devices",array("id"=>$postdata['sipdevice_id'],'accountid'=>$postdata['accountid']))->first_row();
			$sipdeviceinfo = (array)$this->db->get_where ("sip_devices",array("id"=>$postdata['sipdevice_id']))->first_row();
			if(empty($sipdeviceinfo)){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'sipdevice_not_found' )
				), 400 );
			}

			$queryDidsUpdate = $this->common->get_field_name('did_id','view_devices', array('sip_device_id' => $postdata['sipdevice_id']));
			if(!empty($queryDidsUpdate)) {
			$did_update_array = array (
				'number' => !empty($postdata['number']) ? $postdata['number'] : $sipdeviceinfo['username'],
				'extensions' => !empty($postdata['number']) ? $postdata['number'] : $sipdeviceinfo['username']
			);
			
			if (!empty($postdata['ring_timeout'])){
				$did_update_array['leg_timeout'] = $postdata['ring_timeout'];
			}

			$this->db->where("id", $queryDidsUpdate);
			$this->db->update("dids",$did_update_array);

			}
			$vars = json_decode($sipdeviceinfo['dir_vars'],true);
			$vars_new = json_decode($sipdeviceinfo['dir_params'], true);
			if(!($postdata['voice_mail'] =='false' || $postdata['voice_mail'] == 'true')){
				$postdata['voice_mail'] = 'true';
			}
			if(!($postdata['attach_file'] =='false' || $postdata['attach_file'] == 'true')){
				$postdata['attach_file'] = 'true';
			}
			if(!($postdata['local_after_email'] =='false' || $postdata['local_after_email'] == 'true')){
				$postdata['local_after_email'] = 'true';
			}
			if(!($postdata['send_all_message'] =='false' || $postdata['send_all_message'] == 'true')){
				$postdata['send_all_message'] = 'true';
			}

			$update_array = array(
				"status" => isset($postdata['status'])?$postdata['status']:$sipdeviceinfo['status'],
				"username" => !empty($postdata['number']) ? $postdata['number'] : $sipdeviceinfo['username'],
				"accountid" => !empty($postdata['accountid']) ? $postdata['accountid'] : $sipdeviceinfo['accountid'],
				'dir_params' => json_encode(array(
					"password" => isset($postdata['password']) && !empty($postdata['password']) ? $postdata['password'] :$vars_new['password'],
					"vm-enabled" => isset($postdata['voice_mail']) && !empty($postdata['voice_mail']) ? $postdata['voice_mail']:$vars_new['vm-enabled'],
					"vm-password" => isset($postdata['voicemail_password']) && !empty($postdata['voicemail_password']) ?$postdata['voicemail_password']:$vars_new['vm-password'],
					"vm-mailto" => isset($postdata['mailto']) && !empty($postdata['mailto']) ? $postdata['mailto'] :$vars_new['vm-mailto'],
					"vm-attach-file" => isset($postdata['attach_file']) && !empty($postdata['attach_file'])?$postdata['attach_file']:$vars_new['vm-attach-file'],
					"vm-keep-local-after-email" => isset($postdata['local_after_email']) && !empty($postdata['local_after_email'])?$postdata['local_after_email']:$vars_new['vm-keep-local-after-email'],
					"vm-email-all-messages" => isset($postdata['send_all_message']) && !empty($postdata['send_all_message'])?$postdata['send_all_message']:$vars_new['vm-email-all-messages']
				)),
				"dir_vars"=>json_encode(array(
					'effective_caller_id_name' => isset($postdata['caller_name']) && !empty($postdata['caller_name'])?$postdata['caller_name']:$vars['effective_caller_id_name'],
					'effective_caller_id_number' => isset($postdata['caller_number']) && !empty($postdata['caller_number'])?$postdata['caller_number']:$vars['effective_caller_id_number']
				)),
				'last_modified_date'=>gmdate('Y-m-d H:i:s')
			);
			$this->db->where ( 'id', $this->postdata ['sipdevice_id'] );
			$this->db->update ( 'sip_devices', $update_array );
			$update_array['dir_params'] = json_decode($update_array['dir_params'],true);
			$decoded_pass = $this->common->decode($update_array['dir_params']['password']);
			$update_array['dir_params']['password'] = $this->common->encrypt($decoded_pass);
			$this->response ( array (
				'status'=>true,
				'data' => $update_array,
				'success' => "SIP Device updated sucessfully." 
			), 200 );
		}
	}

	function _sip_devices_read() {
		$postdata = $this->postdata;
		if($this->form_validation->required($postdata['sipdevice_id'] == '')){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'require_sip_id' )
			), 400 );
		}else{
			$sipdeviceinfo = (array)$this->db->get_where ("sip_devices",array("id"=>$postdata['sipdevice_id']))->first_row();
		    if(empty($sipdeviceinfo)){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'sipdevice_not_found' )
				), 400 );
	        }else{
	        	$dir_params = json_decode($sipdeviceinfo['dir_params'],true);
				$this->api_log->write_log ( 'DIR Params : ', json_encode($dir_params));
	        	$dir_vars = json_decode($sipdeviceinfo['dir_vars'],true);
	        	$sipdeviceinfo['password'] = $dir_params['password'];
	        	$sipdeviceinfo['vm-enabled'] = $dir_params['vm-enabled'];
	        	$sipdeviceinfo['effective_caller_id_name'] = $dir_vars['effective_caller_id_name'];
	        	$sipdeviceinfo['effective_caller_id_number'] = $dir_vars['effective_caller_id_number'];
	        	$sipdeviceinfo['status'] = $sipdeviceinfo['status'] == '0' ? 'Active' : 'Inactive'  ;
	        	$sipdeviceinfo['vm-enabled'] = $dir_params['vm-enabled'];
	        	$sipdeviceinfo['vm-password'] = $dir_params['vm-password'];
	        	$sipdeviceinfo['vm-attach-file'] = $dir_params['vm-attach-file'];
	        	$sipdeviceinfo['vm-mailto'] = $dir_params['vm-mailto'];
	        	$sipdeviceinfo['vm-email-all-messages'] = $dir_params['vm-email-all-messages'];
	        	$sipdeviceinfo['vm-keep-local-after-email'] = $dir_params['vm-keep-local-after-email'];
	        	unset($sipdeviceinfo['id'],$sipdeviceinfo['reseller_id'],$sipdeviceinfo['accountid'],$sipdeviceinfo['sip_profile_id']);
	        	unset($sipdeviceinfo['call_waiting'],$sipdeviceinfo['last_modified_date'],$sipdeviceinfo['creation_date'],$sipdeviceinfo['dir_params'],$sipdeviceinfo['dir_vars']);
	        	$this->response ( array (
					'status'=>true,
					'data' => $sipdeviceinfo,
					'success' => $this->lang->line( "read_sipdevice" ) 
				), 200 );
	        }
		}
	}

	function did_number_release($did_info, $accountinfo, $action){
        if ($this->session->userdata['userlevel_logintype'] == '-1' || $this->session->userdata['userlevel_logintype'] == '2' ) {
            $did_update_array = array(
                "accountid" => 0,
                "parent_id" => 0,
                "call_type" => 0,
                "extensions" => "",
                "always" => 0,
                "always_destination" => "",
                "user_busy" => 0,
                "user_busy_destination" => "",
                "user_not_registered" => 0,
                "user_not_registered_destination" => "",
                "no_answer" => 0,
                "no_answer_destination" => "",
                "call_type_vm_flag" => 1,
                "failover_call_type" => 1,
                "always_vm_flag" => 1,
                "user_busy_vm_flag" => 1,
                "user_not_registered_vm_flag" => 1,
                "no_answer_vm_flag" => 1,
                "failover_extensions" => ""
            );
            $order_where = array(
                "is_terminated" => 0,
                "product_id" => $did_info['product_id']
            );
            
            if ($did_info['accountid'] > 0) {
                unset($did_update_array['parent_id']);
                $order_where['accountid']  =  $did_info['accountid'];       
            }
        } else {
            if ($action == 'release') {
                $did_update_array = array(
                    "accountid" => 0,
                    "call_type" => 0,
                    "extensions" => "",
                    "always" => 0,
                    "always_destination" => "",
                    "user_busy" => 0,
                    "user_busy_destination" => "",
                    "user_not_registered" => 0,
                    "user_not_registered_destination" => "",
                    "no_answer" => 0,
                    "no_answer_destination" => "",
                    "call_type_vm_flag" => 1,
                    "failover_call_type" => 1,
                    "always_vm_flag" => 1,
                    "user_busy_vm_flag" => 1,
                    "user_not_registered_vm_flag" => 1,
                    "no_answer_vm_flag" => 1,
                    "failover_extensions" => ""
                );
            } else {
                $did_update_array = array(
                    "accountid" => 0,
                    "parent_id" => 0,
                    "call_type" => 0,
                    "extensions" => "",
                    "always" => 0,
                    "always_destination" => "",
                    "user_busy" => 0,
                    "user_busy_destination" => "",
                    "user_not_registered" => 0,
                    "user_not_registered_destination" => "",
                    "no_answer" => 0,
                    "no_answer_destination" => "",
                    "call_type_vm_flag" => 1,
                    "failover_call_type" => 1,
                    "always_vm_flag" => 1,
                    "user_busy_vm_flag" => 1,
                    "user_not_registered_vm_flag" => 1,
                    "no_answer_vm_flag" => 1,
                    "failover_extensions" => ""
                );
            }
            $order_where = array(
                "is_terminated" => 0,
                "product_id" => $did_info['product_id'],
                "accountid" => $did_info['accountid']
            );
        }
        if($did_info['parent_id'] > 0 && $did_info['accountid'] == 0){
            
            $this->db->delete("reseller_products",array("product_id" => $did_info['product_id']));
        }
        $this->db->where(array(
            "product_id" => $did_info['product_id']
        ));
        $this->db->update("dids", $did_update_array);

        $order_update_array = array(
            "is_terminated" => 1,
            "termination_date" => gmdate('Y-m-d H:i:s'),
            "termination_note" => "DID(" . $did_info['number'] . ") has been released by " . $accountinfo['number'] . "( " . $accountinfo['first_name'] . " " . $accountinfo['last_name'] . ") "
        );
        $this->db->where($order_where);
        $this->db->update("order_items", $order_update_array);
        return true;
    }
}

?>
