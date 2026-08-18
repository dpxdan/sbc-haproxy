<?php
defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . '/controllers/common/account.php';
class Orders extends Account
{
	protected $postdata = "";
	
	function __construct()
	{
		parent::__construct();
		$this->load->model('common_model');
		$this->load->library('common');
		$this->load->model('db_model');
		$this->load->model('Flux_common');
		$this->load->library('flux_log');
		$this->load->library('Form_validation');
		$this->load->library('flux/payment');
		$this->load->library ( 'flux/order' );
		$rawinfo = $this->post();
		$this->accountinfo = $this->get_account_info(); 
		if($this->accountinfo['type'] != '-1'  && $this->accountinfo ['type'] != '2'  && $this->accountinfo ['type'] != '1' ){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'error_invalid_key' )
			), 400 );
		}
		foreach ($rawinfo as $key => $value) {
			$this->postdata[$key] = $this->_xss_clean($value, TRUE);
		}
	}
	
	public function index()
	{
	    $accountid = $this->accountinfo ['id'];
	    $where = array('id'=>$accountid,'deleted'=>0,'status'=>0);
	    $this->db->where($where);
	    $accountinfo = (array)$this->db->get('accounts')->first_row();
	
	    if(empty($accountinfo) || !isset($accountinfo)){
	        $this->response ( array (
	            'status'  => false,
	            'error'   => $this->lang->line ( 'account_not_found' )
	        ), 400 );
	    }
	    $accountinfo = $this->_authorize_account ( $accountinfo,true,true);
	    $function = isset ( $this->postdata ['action'] ) ? $this->postdata ['action'] : '';
	    if ($function != '') {
	        $function = '_' . $function;
	        if (( int ) method_exists ( $this, $function ) > 0) {
	            $this->$function ();
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
	
	private function _reseller_list()
	{
		$this->_list();
	}
	
	private function _list()
	{
		if (empty($this->postdata['end_limit']) || empty($this->postdata['start_limit']) ){
			if(!( $this->postdata['start_limit'] == '	0' || $this->postdata['end_limit'] == '0' )){
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
		$start = $this->postdata['start_limit']-1;
		$limit = $this->postdata['end_limit'];
		$no_of_records = (int)$limit - (int)$start;
		$object_where_params = $this->postdata['object_where_params'];
		if(!empty($object_where_params['from_date']) || !empty($object_where_params['to_date'])  ){
			$from_dates = DateTime::createFromFormat("Y-m-d H:i:s", $object_where_params['from_date']);
	       	$to_dates = DateTime::createFromFormat("Y-m-d H:i:s", $object_where_params['to_date']);
	       	if(empty($from_dates) || empty($to_dates)){
	       		$this->response ( array (
						'status' => false,
						'error' => $this->lang->line('invalid_date_format')
				), 400 );
	       	}
	       	else{
	       		if($this->postdata['action'] != 'reseller_orders_list'){
	       			$object_where_params_date['order_items.billing_date >='] = $this->timezone->convert_to_GMT_new ( $object_where_params['from_date'], '1' , $this->accountinfo['timezone_id']);
					$object_where_params_date['order_items.billing_date <='] = $this->timezone->convert_to_GMT_new ( $object_where_params['to_date'], '1',$this->accountinfo['timezone_id']);
	       		}
	       		else{
	       			$object_where_params_date['order_items.billing_date >='] =  $object_where_params['from_date'];
					$object_where_params_date['order_items.billing_date <='] =  $object_where_params['to_date'];
	       		}
				$this->db->where($object_where_params_date);
	       	}
		}
		unset($object_where_params['to_date'],$object_where_params['from_date']);
		foreach($object_where_params as $object_where_key => $object_where_value) {
			if($object_where_value != '') {	
				if(isset($object_where_key['accountid']) || $object_where_key == 'accountid'){
					$this->db->where('orders.accountid', $object_where_value);
				}
				if(isset($object_where_key['id']) || $object_where_key == 'id'){
					$this->db->where('orders.id', $object_where_value);
				}
				if(isset($object_where_key['order_id']) || $object_where_key == 'order_id'){
					$this->db->where('orders.id', $object_where_value);
				}
				if(isset($object_where_key['order_number']) || $object_where_key == 'order_number'){
					$this->db->where('orders.order_id', $object_where_value);
				}
				if(isset($object_where_key['category_name']) || $object_where_key == 'category_name'){
					$this->db->where('category.name', $object_where_value);
				}
				if(isset($object_where_key['product_name']) || $object_where_key == 'product_name'){
					$this->db->where('products.name', $object_where_value);
				}
				if(isset($object_where_key['setup_fee']) || $object_where_key == 'setup_fee'){
					$this->db->where('order_items.setup_fee', $object_where_value);
				}
				if(isset($object_where_key['price']) || $object_where_key == 'price'){
					$this->db->where('order_items.price', $object_where_value);
				}
				if(isset($object_where_key['reseller_id']) || $object_where_key == 'reseller_id'){
					$this->db->where('order_items.reseller_id', $object_where_value);
				}
				else{
					$where[$object_where_key] = $object_where_value;
				}
			}
		}
		if(!empty($where)) {
		    unset($where['accountid'],$where['id'],$where['order_id'],$where['category_name'],$where['product_name'],$where['setup_fee'],$where['price']);	
			$this->db->where($where);
		}
		if(!empty($like_array)) {
			$this->db->where($like_array); 
		}
		if($this->accountinfo['type'] == '1' && $this->postdata['action'] != 'reseller_orders_list'){
			$this->db->where('reseller_id', $this->postdata['id']); 
		}
		if($this->postdata['action'] == 'reseller_orders_list'){
			$this->db->where('accountid', $this->postdata['id']); 
		}

					$this->db->select('orders.id,orders.order_id as order_number,orders.order_date,orders.payment_gateway,(CASE WHEN order_items.`is_terminated`=0 THEN CONCAT("Ativo") ELSE CONCAT("Inativo") END) AS order_status,orders.payment_status,orders.reseller_id,orders.accountid,order_items.billing_date,order_items.termination_date,order_items.next_billing_date,order_items.product_id,order_items.setup_fee,order_items.price,products.name as product_name,category.name as category_name');
					$this->db->join('order_items', 'order_items.order_id = orders.id', 'inner');
					$this->db->join('products', 'products.id = order_items.product_id', 'left');
					$this->db->join('category', 'category.id = products.product_category', 'left');
					$this->db->order_by('order_items.billing_date', 'desc');
					$result = $this->db->get('orders');
					if (empty($result)) {
			         $this->response ( array (
						'status' => true,
						'success' => $this->lang->line( "no_records_found" )
					), 200 );
					}
					else {		
					$orders_info = $result->result_array();
					$count = $result->num_rows();
}
		foreach ($orders_info as $key => $orders_value) {
			$orders_value['billing_date'] = $this->timezone->convert_to_GMT_new($orders_value['billing_date'],'1',$this->accountinfo['timezone_id']);
			$ordersinfo[] =$orders_value;
		}
    	if (!empty($ordersinfo)) {
			$this->response ( array (
				'status' => true,
				'total_count' => $count,
				'data' => $ordersinfo,
				'success' => $this->lang->line( "orders_list" )
			), 200 );
        }
        else{
			$this->response ( array (
				'status' => true,
				'data' => array(),
				'success' => $this->lang->line( "no_records_found" )
			), 200 );
		}
	}
	
	private function _read()
	{
		$postdata = $this->postdata;
		$object_where_params = $this->postdata['object_where_params'];
		if (empty($object_where_params['order_id']) || !isset($object_where_params['order_id'])) {
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ( 'error_param_missing' ) . " integer:order_id"
			), 400 );
		}
		else{
			
			$where = array('id' => $object_where_params['order_id']);
			$this->db->limit(1, '');
			$this->db->select('id,order_id as order_number,order_date,billing_date,next_billing_date,termination_date,order_status,reseller_id,accountid,company,product_type,product_name,product_id,includedseconds,is_terminated,order_price,product_price');			
			$this->db->where($where);
			$result = $this->db->get('view_status_pedidos');
			$orderinfo = $result->result_array();
			$new_array = array();
	       	if(empty($orderinfo)){
				$this->response ( array (
					'status'  => false,
					'error'   => $this->lang->line ( 'order_not_found' )
				), 400 );
	        }
						
			foreach ($orderinfo as $key => $value) {
			$value['id'] = $value['id'];
			$value['order_number'] = $value['order_number'];
			$value['order_date'] = $value['order_date'];
			$value['billing_date'] = $value['billing_date'];
			$value['next_billing_date'] = $value['next_billing_date'];
			$value['termination_date'] = $value['termination_date'];
			$value['order_status'] = $value['order_status'];
			$value['reseller_id'] = $value['reseller_id'];						
			$value['accountid'] = $value['accountid'];
			$value['company'] = $value['company'];
			$value['product_type'] = $value['product_type'];
			$value['product_name'] = $value['product_name'];
			$value['product_id'] = $value['product_id'];						
			$value['includedseconds'] = $value['includedseconds'];
			$value['is_terminated'] = $value['is_terminated'] == '0' ? 'Active' : 'Inactive'  ;
			$value['order_price'] = $value['order_price'];
			$value['product_price'] = $value['product_price'];
			if($value['reseller_id'] == '0'){
			unset($value['reseller_id']);
            }
            if($value['product_type'] == 'DID'){
			unset($value['includedseconds']);
            }
            if($value['termination_date'] == '0000-00-00 00:00:00'){
			unset($value['termination_date'],$value['is_terminated']);
            }
			$new_array[] = $value;
		}
			$this->response ( array (
				'status' => true,
				'data' => $new_array,
				'success' => $this->lang->line( "read_order" )
			), 200 );			
		}
	}
	
	private function _create()
    {
        $postdata = $this->postdata;
        $object_where_params = $this->postdata['object_where_params'];
        if (empty($object_where_params['product_id']) || !isset($object_where_params['product_id']) || empty($object_where_params['accountid']) || !isset($object_where_params['accountid'])) {
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ( 'error_param_missing' ) . " integer:product_id:account_id"
			), 400 );
		}
		else {
        $where = array('id' => $object_where_params['product_id'], 'status' => 0);
        $this->db->select('id as product_id,name as product_name,product_category as category,price,free_minutes,setup_fee,billing_type,billing_days,commission,reseller_id');
        $this->db->where($where);
        $result = $this->db->get('products');
        $ProductData = $result->result_array();
        
        if($ProductData == ""){
            $this->response ( array (
                'status' => false,
                'error' => $this->lang->line ('product_not_found') ), 400 );            
        }
        else {        
        $ProductData_list = $this->db_model->getSelect("*", "products", array(
            "id" => $object_where_params['product_id']
        ))->result_array()[0];
        }
        if($ProductData_list != ""){
            $ProductData = array_merge($ProductData,$ProductData_list);
        }
        $account_id = $object_where_params['accountid'];
        $accountinfo = $this->accountinfo;        
        if (! empty($ProductData) && isset($ProductData)) {
                $customer_data = array();
                $customer_data = $this->db_model->getSelect("*", "accounts", array(
                    "id" => $object_where_params['accountid'],
                    "status" => 0,
                    "deleted" => 0,
                    "type" => 0
                ));
                if ($customer_data->num_rows > 0) {
                    $customer_data = $customer_data->result_array()[0];
                }
		        $quantity = (isset($object_where_params['quantity']) && $object_where_params['quantity'] > 1)?$object_where_params['quantity']:1;
                $total_amt = (($ProductData_list['price'] + $ProductData_list['setup_fee']) * $quantity);

                $account_balance = $customer_data['posttoexternal'] == 1 ? $customer_data['credit_limit'] + ($customer_data['balance']) : $customer_data['balance'];
                if ($account_balance >= $total_amt) {
                    $ProductData['invoice_type'] = ($ProductData_list['product_category'] == 3) ? "credit" : "debit";
                    $ProductData['next_billing_date'] = ($ProductData_list['billing_days'] == 0) ? gmdate('Y-m-d 23:59:59', strtotime('+10 years')) : gmdate("Y-m-d 23:59:59", strtotime("+" . ($ProductData_list['billing_days'] - 1) . " days"));
                    $ProductData['create_invoice'] = "true";
                    $ProductData['product_id'] = $object_where_params['product_id'];
                    $ProductData['payment_by'] = 0;
                    $last_id = $this->order->confirm_order($ProductData, $account_id, $accountinfo);
                    if (! empty($customer_data) && $last_id != '' && $customer_data['notifications'] == 0) {
                        $ProductData['payment_by'] = ($ProductData['payment_by'] == 0) ? "Account Balance" : "Account Balance";
                        $ProductData['category'] = $this->common->get_field_name("name", "category", array(
                            "id" => $ProductData_list['product_category']
                        ));                        
                        $final_array = array_merge($customer_data, $ProductData_list);
                       $final_array['quantity'] = (isset($ProductData['quantity']) && $ProductData['quantity'] > 1)?$ProductData['quantity']:1;
                        $final_array['price'] = ($ProductData['setup_fee'] + $ProductData['price']);
                        $final_array['total_price'] = ($ProductData['setup_fee'] + $ProductData['price']) * (isset($ProductData['quantity']) ? $ProductData['quantity'] : 1);
                        $final_array['total_price_amount'] = ($ProductData['setup_fee'] + $ProductData['price']);
                        $final_array['category_name'] = $ProductData['category'];
			            $final_array['name'] = $ProductData_list['name'];
			            $final_array['payment_by'] = $ProductData['payment_by'];
			            $final_array['next_billing_date'] = ($ProductData_list['billing_days'] == 0) ? gmdate('Y-m-d 23:59:59', strtotime('+10 years')) : gmdate("Y-m-d 23:59:59", strtotime("+" . ($ProductData_list['billing_days'] - 1) . " days"));
			            $final_array['id'] = $account_id;
                        $this->common->mail_to_users('product_purchase', $final_array);
                    }
                    $orderinfo = $this->db_model->getSelect("*",'view_status_pedidos', array('id' => $last_id))->row_array();
                    $where = array('id' => $last_id);
					$this->db->limit(1, '');
					$this->db->select('id,order_id as order_item_id,order_date,billing_date,next_billing_date,termination_date,order_status,reseller_id,accountid,company,product_type,product_name,product_id,includedseconds,is_terminated,order_price,product_price');			
					$this->db->where($where);
					$result = $this->db->get('view_status_pedidos');
					$orderinfo = $result->result_array();
					$new_array = array();
                   
                    foreach ($orderinfo as $key => $value) {
					$value['id'] = $value['id'];
					$value['order_item_id'] = $value['order_item_id'];
					$value['order_date'] = $value['order_date'];
					$value['billing_date'] = $value['billing_date'];
					$value['next_billing_date'] = $value['next_billing_date'];
					$value['termination_date'] = $value['termination_date'];
					$value['order_status'] = $value['order_status'];
					$value['reseller_id'] = $value['reseller_id'];						
					$value['accountid'] = $value['accountid'];
					$value['company'] = $value['company'];
					$value['product_type'] = $value['product_type'];
					$value['product_name'] = $value['product_name'];
					$value['product_id'] = $value['product_id'];						
					$value['includedseconds'] = $value['includedseconds'];
					$value['is_terminated'] = $value['is_terminated'] == '0' ? 'Active' : 'Inactive'  ;
					$value['order_price'] = $value['order_price'];
					$value['product_price'] = $value['product_price'];
					if($value['reseller_id'] == '0'){
					unset($value['reseller_id']);
					}
					if($value['product_type'] == 'DID'){
					unset($value['includedseconds']);
					}
					if($value['termination_date'] == '0000-00-00 00:00:00'){
					unset($value['termination_date'],$value['is_terminated']);
					}
					$new_array[] = $value;
				}
					$this->response ( array (
						'status' => true,
						'id'   => $last_id,
						'data' => $new_array,
						'success' => $this->lang->line( "create_order_success" )
					), 200 );	

                } 
                else {                    
			    $this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('insufficient_balance') ), 400 );                                                                    
                }
            } 
        else {
				$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('product_not_found') ), 400 );            
				}
            }
        }
		
	private function _delete()
	{
		$postdata = $this->postdata;
		if($this->form_validation->required($postdata['order_id'] ) == ''){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ( 'enter_order_id' ) 
			), 400 );
		}
		else{
			if(!$this->form_validation->numeric_with_comma($postdata['order_id'])){
				$this->response ( array (
					'status' => false,
					'success' =>  $this->lang->line ('enter_valid_order_id')  
				), 400 );
			}
			$orderinfo = $this->db->get('order_items')->result_array();
			if(empty($orderinfo)){
			$this->response ( array (
				'status'  => false,
				'error'   => $this->lang->line ( 'order_not_found' )
			), 400 );
			}
			else {
			$termination_date = $this->common->convert_GMT_to('','',gmdate('Y-m-d H:i:s'),$this->accountinfo['timezone_id']);
			$this->common->update_data ( "order_items", array (
					"order_id" => $postdata['order_id']
			), array (
					"is_terminated" => 1,
					'termination_date'=>$termination_date,
					'termination_note'=>'Product has been released'
			) );
			$this->common->update_data ( "counters", array (
					"package_id" => $postdata['order_id']
			), array (
					"status" => 0
			) );								
			$order_array = array(
				"order_id" => $postdata['order_id'],
				"termination_date" => $termination_date
			);							
			$this->response ( array (
				'status'=> true,
				'data' => $order_array,
				'success' => $this->lang->line('order_deleted') 
			), 200 );
			}
		}
	}     
    
		
}