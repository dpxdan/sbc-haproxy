<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negocios
//
// Copyright (C) 2025 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// FluxSBC Version 4.2 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . '/controllers/common/account.php';
class Invoices extends Account
{
	protected $postdata = "";

	function __construct()
	{
		parent::__construct();
		$this->load->library('common');
		$this->load->model('db_model');
		$this->load->model('common_model');
		$this->load->model('Flux_common');
		$this->load->library('Form_validation');
		$this->load->library('flux_log');
		$this->load->library('flux/payment');
		$this->load->model('invoice_model');
		$rawinfo = $this->post();
		$this->accountinfo = $this->get_account_info();
		if($this->accountinfo['type'] != '-1'  && $this->accountinfo ['type'] != '2' && $this->accountinfo ['type'] != '0' && $this->accountinfo ['type'] != '6' && $this->accountinfo ['type'] != '1' ){
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
		if($this->accountinfo['type'] == '-1'){

			$where = array('id' => $this->accountinfo['id'] , 'type' => 1);
		}
		else{
			$type = array(-1,2);
			$where = array('id'=>$accountid,'deleted'=>0,'status'=>0);
		}
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
		}
		else {
			$this->response ( array (
				'status'=> false,
				'error' => $this->lang->line ( 'unknown_method' )
			), 400 );
		}
	}

	private function _customer_invoices()
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
		$from_currency = Common_model::$global_config['system_config']['base_currency'];
		$to_currency = $this->common->get_field_name('currency', 'currency', $this->accountinfo['currency_id']);
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
	       	}else{
	       		$object_where_params_date['generate_date >='] = $this->timezone->convert_to_GMT_new ( $object_where_params['from_date'], '1' , $this->accountinfo['timezone_id']);
				 $object_where_params_date['generate_date <='] = $this->timezone->convert_to_GMT_new ( $object_where_params['to_date'], '1',$this->accountinfo['timezone_id']);
				$this->db->where($object_where_params_date);
	       	}
		}
		unset($object_where_params['to_date'],$object_where_params['from_date'],$object_where_params['display_records']);
		foreach($object_where_params as $object_where_key => $object_where_value) {
			if($object_where_value != '') {
				if(isset($object_where_key) && $object_where_key == 'destination'){
					$this->db->where('notes', $object_where_params['destination'] );
				}
				if(isset($object_where_key) && $object_where_key == 'duration'){
					$duration = explode(':', $object_where_params['duration']);
                    if (isset($duration[0]) && isset($duration[1])) {
                        if (is_numeric($duration[0]) && is_numeric($duration[1])) {
                            $object_where_params['duration'] = (60 * $duration[0]) + $duration[1];
                        }
                    }
					$this->db->where('billseconds' , $object_where_params['duration']);
				}
				if(isset($object_where_key) && $object_where_key == 'code'){
					$this->db->like('pattern', '^'.$object_where_params['code'] );
				}
				if(isset($object_where_key) && $object_where_key == 'reseller_id'){
					$this->db->where('reseller_id', $object_where_params['reseller_id'] );
				}
				if(isset($object_where_key) && $object_where_key == 'accountid'){
					$this->db->where('accountid', $object_where_params['accountid'] );
				}
				$where[$object_where_key] = $object_where_value;
			}
		}
		if(!empty($where)){
			unset($where['destination'],$where['code'],$where['duration'],$where['reseller_id'],$where['accountid']);
			$this->db->like($where, $object_where_params );
		}
	 	if ($this->accountinfo['type'] == '1') {
			$this->db->where('reseller_id', $this->postdata['id']);
	 	}
		$this->db->where_in('generate_type',array(0,1));
		$this->db->order_by("generate_date", "desc");
		$this->db->limit($no_of_records, $start);
        $this->db->select('*');
        $result = $this->db->get('view_new_invoices');
        $count = $result -> num_rows();
        $invoices_info = $result->result_array();
		foreach ($invoices_info as $key => $invoices_value) {

            $invoices_value['generate_date'] = $this->common->convert_GMT_to('','',$invoices_value['generate_date'],$this->accountinfo['timezone_id']);
            $invoices_value['from_date'] = $this->common->convert_GMT_to('','',$invoices_value['from_date'],$this->accountinfo['timezone_id']);
            $invoices_value['to_date'] = $this->common->convert_GMT_to('','',$invoices_value['to_date'],$this->accountinfo['timezone_id']);
            $invoices_value['due_date'] = $this->common->convert_GMT_to('','',$invoices_value['due_date'],$this->accountinfo['timezone_id']);
            $invoices_value['debit'] = $this->common_model->calculate_currency_customer($invoices_value['debit'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['credit'] = $this->common_model->calculate_currency_customer($invoices_value['credit'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['invoice_total'] = $this->common_model->calculate_currency_customer($invoices_value['invoice_total'],$from_currency,$to_currency,true,true)." ".$to_currency;
			$invoicesinfo[] =$invoices_value;
		}
    	if (!empty($invoicesinfo)) {
			$this->response ( array (
				'status' => true,
				'total_count' => $count,
				'data' => $invoicesinfo,
				'success' => $this->lang->line( "invoices_list" )
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

	private function _customer_billing_details()
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

			if (empty($object_where_params['from_date']) || empty($object_where_params['to_date'])) {
					$this->response(array(
							'status' => false,
							'error'  => $this->lang->line('error_param_missing') . " from_date, to_date"
					), 400);
					return;
			}

			$from_date_obj = DateTime::createFromFormat("Y-m-d H:i:s", $object_where_params['from_date']);
			$to_date_obj   = DateTime::createFromFormat("Y-m-d H:i:s", $object_where_params['to_date']);
			if (!$from_date_obj || !$to_date_obj) {
					$this->response(array(
							'status' => false,
							'error'  => $this->lang->line('invalid_date_format')
					), 400);
					return;
			}
            if (!empty($object_where_params['reseller_id'])) {
            $reseller_id = $object_where_params['reseller_id'];
            }
			$account_ids = [];
			if (!empty($object_where_params['accountid'])) {
					$account_ids = is_array($object_where_params['accountid'])
							? $object_where_params['accountid']
							: [$object_where_params['accountid']];
			} 
			else {
					$rows = $this->db->select('id')->from('accounts')->where('type','0')->where('status', '0')->where('deleted', '0')->order_by('id', 'desc')->limit($no_of_records, $start)->get()->result_array();
					$account_ids = array_column($rows, 'id');
			}

			$from_date_gmt = $this->timezone->convert_to_GMT_new(
					$object_where_params['from_date'],
					'1',
					$this->accountinfo['timezone_id']
			);
			$to_date_gmt = $this->timezone->convert_to_GMT_new(
					$object_where_params['to_date'],
					'1',
					$this->accountinfo['timezone_id']
			);

			$from_currency = Common_model::$global_config['system_config']['base_currency'];
			$to_currency   = $this->common->get_field_name('currency', 'currency', $this->accountinfo['currency_id']);

			$all_accounts_data = [];

			try {
					foreach ($account_ids as $account_id) {

							$this->db->select('id, number, first_name, last_name, company_name, email,reseller_id');
							$this->db->from('accounts');
							$this->db->where('id', $account_id);
							$this->db->order_by('id', 'desc');
							$this->db->limit($no_of_records, $start);							
							if ($this->accountinfo['type'] == '1') {
									$this->db->where('reseller_id', $this->postdata['id']);
							}
							if (!empty($object_where_params['reseller_id'])) {
							$reseller_id = $object_where_params['reseller_id'];
							}
							
							$account_query = $this->db->get();
							if ($account_query->num_rows() === 0) {
									continue;
							}
							$account_details = $account_query->row_array();

							$this->db->select('oi.id, oi.order_id, oi.product_id, p.name as product_name, oi.free_minutes, oi.price, oi.is_terminated, oi.termination_date');
							$this->db->from('order_items as oi');
							$this->db->join('products as p', 'oi.product_id = p.id', 'left');
							$this->db->join('orders as o', 'oi.order_id = o.id', 'left');
							$this->db->where('oi.accountid', $account_id);
							$this->db->where('oi.is_terminated', '0');
							$this->db->where('p.product_category', '1');
							$this->db->where('oi.billing_date >=', $from_date_gmt);
//							$this->db->where('o.order_date <=', $to_date_gmt);
							$this->db->where('oi.termination_date', '0000-00-00 00:00:00');
							$this->db->order_by('oi.billing_date', 'desc');
							$all_plans = $this->db->get()->result_array();

							$total_plan_cost     = 0.0;
							$total_exceeded_cost = 0.0;
							$plan_billing_details = [];

							foreach ($all_plans as $plan) {
									$package_id = $plan['order_id'];
									$total_plan_cost += (float)$plan['price'];

									$this->db->select_sum('billseconds', 'seconds_for_this_plan');
									$this->db->from('cdrs');
									$this->db->where('accountid', $account_id);
									$this->db->where('package_id', $package_id);
									$this->db->where('callstart >=', $from_date_gmt);
									$this->db->where('callstart <=', $to_date_gmt);
									$this->db->where('debit >', '0.0000');
									$seconds_for_plan_result = $this->db->get()->row();
									$seconds_consumed = $seconds_for_plan_result ? (int)$seconds_for_plan_result->seconds_for_this_plan : 0;

									$free_seconds_for_plan    = (int)$plan['free_minutes'] * 60;
									$exceeded_seconds_for_plan = max(0, $seconds_consumed - $free_seconds_for_plan);

									$cost_per_exceeded_second = 0.005;
									$exceeded_cost_for_plan   = $exceeded_seconds_for_plan * $cost_per_exceeded_second;
									$total_exceeded_cost     += $exceeded_cost_for_plan;

									$detail = array(
											'order_item_id'   => $package_id,
											'product_id'      => $plan['product_id'],
											'product_name'    => $plan['product_name'] ?: 'Plano não encontrado',
											'status'          => ((int)$plan['is_terminated'] === 1) ? 'Terminated' : 'Active',
											'price'           => $this->common_model->calculate_currency_customer($plan['price'], $from_currency, $to_currency, true, true) . " " . $to_currency,
											'minutes_consumed'=> sprintf('%02d:%02d', floor($seconds_consumed / 60), $seconds_consumed % 60),
											'free_minutes'    => (int)$plan['free_minutes'],
											'exceeded_minutes'=> sprintf('%02d:%02d', floor($exceeded_seconds_for_plan / 60), $exceeded_seconds_for_plan % 60),
											'exceeded_debit'  => $this->common_model->calculate_currency_customer($exceeded_cost_for_plan, $from_currency, $to_currency, true, true) . " " . $to_currency,
									);

									if ((int)$plan['is_terminated'] === 1) {
											$detail['termination_date'] = $this->common->convert_GMT_to('', '', $plan['termination_date'], $this->accountinfo['timezone_id']);
									}

									$plan_billing_details[] = $detail;
							}

							$this->db->select_sum('debit', 'total_cost_outside_plan');
							$this->db->from('cdrs');
							$this->db->where('accountid', $account_id);
							$this->db->where('package_id', 0);
							$this->db->where('callstart >=', $from_date_gmt);
							$this->db->where('callstart <=', $to_date_gmt);
							$this->db->where('debit >', '0.0000');
							$cost_outside_plan_result = $this->db->get()->row();
							$total_cost_outside_plan  = $cost_outside_plan_result ? (float)$cost_outside_plan_result->total_cost_outside_plan : 0.0;

							$total_billing_amount = $total_plan_cost + $total_exceeded_cost + $total_cost_outside_plan;

							$response_data = array(
									'account_details' => $account_details,
									'usage_period' => array(
											'from_date' => $from_date_gmt,
											'to_date'   => $to_date_gmt
									),
									'billing_summary' => array(
											'plans_total_debit'      => $this->common_model->calculate_currency_customer($total_plan_cost, $from_currency, $to_currency, true, true) . " " . $to_currency,
											'total_exceeded_debit'   => $this->common_model->calculate_currency_customer($total_exceeded_cost, $from_currency, $to_currency, true, true) . " " . $to_currency,
											'calls_outside_plan_debit'=> $this->common_model->calculate_currency_customer($total_cost_outside_plan, $from_currency, $to_currency, true, true) . " " . $to_currency,
											'total_amount'           => $this->common_model->calculate_currency_customer($total_billing_amount, $from_currency, $to_currency, true, true) . " " . $to_currency
									),
									'details_per_plan' => $plan_billing_details
							);

							$all_accounts_data[] = $response_data;
					}

					if (count($all_accounts_data) === 1 && !is_array($object_where_params['accountid'])) {
							$all_accounts_data = $all_accounts_data[0];
					}

					$this->response(array(
							'status'  => true,
							'data'    => $all_accounts_data,
							'success' => $this->lang->line("invoices_list_information")
					), 200);

			} catch (Exception $e) {
					$this->response(array(
							'status' => false,
							'error'  => 'Ocorreu um erro ao processar a solicitação: ' . $e->getMessage()
					), 500);
			}
	}

	private function _provider_invoices()
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
		$from_currency = Common_model::$global_config['system_config']['base_currency'];
		$to_currency = $this->common->get_field_name('currency', 'currency', $this->accountinfo['currency_id']);
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
	       	}else{
	       		$object_where_params_date['generate_date >='] = $this->timezone->convert_to_GMT_new ( $object_where_params['from_date'], '1' , $this->accountinfo['timezone_id']);
				 $object_where_params_date['generate_date <='] = $this->timezone->convert_to_GMT_new ( $object_where_params['to_date'], '1',$this->accountinfo['timezone_id']);
				$this->db->where($object_where_params_date);
	       	}
		}
		unset($object_where_params['to_date'],$object_where_params['from_date'],$object_where_params['display_records']);
		foreach($object_where_params as $object_where_key => $object_where_value) {
			if($object_where_value != '') {
				if(isset($object_where_key) && $object_where_key == 'destination'){
					$this->db->where('notes', $object_where_params['destination'] );
				}
				if(isset($object_where_key) && $object_where_key == 'duration'){
					$duration = explode(':', $object_where_params['duration']);
                    if (isset($duration[0]) && isset($duration[1])) {
                        if (is_numeric($duration[0]) && is_numeric($duration[1])) {
                            $object_where_params['duration'] = (60 * $duration[0]) + $duration[1];
                        }
                    }
					$this->db->where('billseconds' , $object_where_params['duration']);
				}
				if(isset($object_where_key) && $object_where_key == 'code'){
					$this->db->like('pattern', '^'.$object_where_params['code'] );
				}
				if(isset($object_where_key) && $object_where_key == 'provider_id'){
				$this->db->where('accountid', $object_where_params['provider_id'] );
				}
				$where[$object_where_key] = $object_where_value;
			}
		}
		if(!empty($where)){
			unset($where['destination'],$where['code'],$where['duration'],$where['provider_id']);
			$this->db->like($where, $object_where_params );
		}
	 	if ($this->accountinfo['type'] == '1') {
			$this->db->where('provider_id', $this->postdata['id']);
		}
		$this->db->where_in('generate_type',array(0,1));
		$this->db->order_by("generate_date", "desc");
		$this->db->limit($no_of_records, $start);
        $this->db->select('*');
        $result = $this->db->get('view_new_invoices');
        $count = $result -> num_rows();
        $invoices_info = $result->result_array();
		foreach ($invoices_info as $key => $invoices_value) {

            $invoices_value['generate_date'] = $this->common->convert_GMT_to('','',$invoices_value['generate_date'],$this->accountinfo['timezone_id']);
            $invoices_value['from_date'] = $this->common->convert_GMT_to('','',$invoices_value['from_date'],$this->accountinfo['timezone_id']);
            $invoices_value['to_date'] = $this->common->convert_GMT_to('','',$invoices_value['to_date'],$this->accountinfo['timezone_id']);
            $invoices_value['due_date'] = $this->common->convert_GMT_to('','',$invoices_value['due_date'],$this->accountinfo['timezone_id']);
            $invoices_value['debit'] = $this->common_model->calculate_currency_customer($invoices_value['debit'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['credit'] = $this->common_model->calculate_currency_customer($invoices_value['credit'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['invoice_total'] = $this->common_model->calculate_currency_customer($invoices_value['invoice_total'],$from_currency,$to_currency,true,true)." ".$to_currency;
			$invoicesinfo[] =$invoices_value;
		}
    	if (!empty($invoicesinfo)) {
			$this->response ( array (
				'status' => true,
				'total_count' => $count,
				'data' => $invoicesinfo,
				'success' => $this->lang->line( "provider_invoices_list" )
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

	private function _reseller_invoices_list()
	{
		$this->_reseller_invoices();
	}

	private function _reseller_invoices()
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
		$from_currency = Common_model::$global_config['system_config']['base_currency'];
		$to_currency = $this->common->get_field_name('currency', 'currency', $this->accountinfo['currency_id']);
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
	       	}else{
	       		$object_where_params_date['generate_date >='] = $this->timezone->convert_to_GMT_new ( $object_where_params['from_date'], '1' , $this->accountinfo['timezone_id']);
				 $object_where_params_date['generate_date <='] = $this->timezone->convert_to_GMT_new ( $object_where_params['to_date'], '1',$this->accountinfo['timezone_id']);
				$this->db->where($object_where_params_date);
	       	}
		}
		unset($object_where_params['to_date'],$object_where_params['from_date'],$object_where_params['display_records']);
		foreach($object_where_params as $object_where_key => $object_where_value) {
			if($object_where_value != '') {
				if(isset($object_where_key) && $object_where_key == 'destination'){
					$this->db->where('notes', $object_where_params['destination'] );
				}
				if(isset($object_where_key) && $object_where_key == 'duration'){
					$this->db->where('billseconds' , $object_where_params['duration']);
				}
				if(isset($object_where_key) && $object_where_key == 'code'){
					$this->db->where('pattern', '^'.$object_where_params['code'] );
				}
				$where[$object_where_key] = $object_where_value;
			}
		}
		if(!empty($where)){
			unset($where['destination'],$where['code'],$where['duration']);
			$this->db->where($where, $object_where_params );
		}
		if($this->accountinfo['type'] == '1' && $this->postdata['action'] != 'reseller_invoices_list'){
			$where = array(
                "reseller_id" => $this->postdata['id'],
                "accountid <>" => $this->postdata['id']
            );
			$this->db->where($where);
		}
		$this->db->order_by("generate_date", "desc");
		$this->db->limit($no_of_records, $start);
		if($this->postdata['action'] != 'reseller_invoices_list'){
			 $this->db->select('*');
		}
		else{
			$this->db->where('accountid', $this->postdata['id']);
			$this->db->select('*');
		}
 		$result = $this->db->get('view_new_invoices');
		$count = $result -> num_rows();
		$reseller_invoices_info = $result->result_array();
		foreach ($reseller_invoices_info as $key => $invoices_value) {

            $show_seconds = $this->postdata['object_where_params']['display_records'] == 'minutes' || $this->postdata['object_where_params']['display_records'] == 'seconds' ? $this->postdata['object_where_params']['display_records'] : 'minutes';
            $invoices_value['duration'] = ($show_seconds == 'minutes') ? ($invoices_value['billseconds'] > 0) ? sprintf('%02d', $invoices_value['billseconds'] / 60) . ":" . sprintf('%02d', $invoices_value['billseconds'] % 60) : "00:00" : $invoices_value['billseconds'];
            $invoices_value['generate_date'] = $this->common->convert_GMT_to('','',$invoices_value['generate_date'],$this->accountinfo['timezone_id']);
            $invoices_value['debit'] = $this->common_model->calculate_currency_customer($invoices_value['debit'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['cost'] = $this->common_model->calculate_currency_customer($invoices_value['cost'],$from_currency,$to_currency,true,true)." ".$to_currency;
            $invoices_value['country_id'] = $this->common->get_field_name('country','countrycode',array('id' => $invoices_value['country_id'])) ;
            $invoices_value['pricelist_id'] = $this->common->get_field_name('name','pricelists',array('id' => $invoices_value['pricelist_id'])) ;
            $invoices_value['trunk_id'] = $this->common->get_field_name('name','trunks',array('id' => $invoices_value['trunk_id'])) ;
            $invoices_value['destination'] = $invoices_value['notes'] ;
            $invoices_value['code'] =  preg_replace('/[^\d+0-9]/', '',  $invoices_value['pattern']);
            if( $this->postdata['action'] == 'reseller_invoices_list'){
            	unset($invoices_value['cost'],$invoices_value['accountid'],$invoices_value['country_id'],$invoices_value['trunk_id'],$invoices_value['pricelist_id'],$invoices_value['code']);
            }
            unset($invoices_value['notes'],$invoices_value['billseconds'],$invoices_value['pattern'],$invoices_value['notes'],$invoices_value['is_recording']);
			$reseller_invoices_info[] =$invoices_value;
		}
    	if (!empty($reseller_invoices_info)) {
			$this->response ( array (
				'status' => true,
				'total_count' => $count,
				'data' => $reseller_invoices_info,
				'success' => $this->lang->line( "invoices_list" )
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

}
