<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ****************************************************************
 * IMPORTANT!! : This is API for Customer CURD Operation : IMPORTANT!!
 * ****************************************************************
 *
 * ===================================================
 * API Expected parameters :
 * ===================================================
 * Integer : start_limit (Start limit for customer list)
 * Integer : end_limit (End limit for customer list)
 * Integer : accountid (Unique accountid for each customer)
 * String : first_name (Customer name)
 * Integer : number (Unique account number for each customer)
 * String : email (Unique email id for each customer)
 * Integer : country_id (Unique Country id)
 * Integer : timezone_id (Unique Timezome id)
 * Integer : currency_id (Unique Currency id)
 * Integer : pricelist_id (Rategroup id)
 *
 * ===================================================
 * API Possible actions : create,read,update,delete,list,batch_update
 * ===================================================
 * mandatory fields;
 * create : number,email,first_name
 * read : accountid
 * update : accountid
 * delete : accountid
 * list : end_limit
 * batch_update :
 *
 * ===================================================
 * API URL
 * ===================================================
 *
 */

require APPPATH . '/controllers/common/account.php';

class Customer extends Account {

	protected $postdata  = "";
	protected $accountinfo = "";

	function __construct() {
		parent::__construct();
		$this->load->model('Common_model');
		$this->load->library('common');
		$this->load->model('db_model');
		$this->load->library('flux/order');
		$this->load->library('Form_validation');
		$this->accountinfo = $this->get_account_info();
		if ($this->accountinfo['type'] > 10) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_invalid_key'),
			), 400);
		}
		$rawinfo      = $this->post();
		$this->postdata = array();
		foreach ($rawinfo as $key => $value) {
			$this->postdata[$key] = $this->_xss_clean($value, TRUE);
		}
		$this->postdata['client_ip'] = $_SERVER['SERVER_ADDR'];
		$this->reseller_allow = "true";
	}

	public function index() {
		$function  = isset($this->postdata['action']) ? $this->postdata['action'] : '';
		$accountid = $this->postdata['id'];
		$where     = array('status' => 0);
		if (
			$this->accountinfo['type'] == -1 ||
			$this->accountinfo['type'] == 5  ||
			$this->accountinfo['type'] == 2  ||
			$this->accountinfo['type'] == 4  ||
			$this->accountinfo['type'] == 6
		) {
			$this->db->where_in('type', array(3, 0));
		} else {
			$where = array('reseller_id' => $this->accountinfo['id'], 'type' => 0);
		}
		$this->db->where($where);
		$accountinfo = (array) $this->db->get('accounts')->first_row();
		if (empty($accountinfo) || !isset($accountinfo)) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('account_not_found'),
			), 500);
		}
		$accountinfo = $this->_authorize_account($this->accountinfo, true, true);
		if ($function != '') {
			$function   = '_' . $function;
			$function_2 = '_customer' . $function;
			$this->api_log->write_log('Function : ', json_encode($function));
			if ((int) method_exists($this, $function) > 0) {
				$this->$function();
			} else if ((int) method_exists($this, $function_2) > 0) {
				$this->$function_2();
			} else {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('unknown_method'),
				), 400);
			}
		} else {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('unknown_method'),
			), 400);
		}
	}

	function _customer_list() {
		if (!isset($this->postdata['end_limit']) || !isset($this->postdata['start_limit'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_param_missing') . " integer:end_limit,integer:start_limit",
			), 400);
		} else {
			if ($this->postdata['start_limit'] <= 0 || $this->postdata['end_limit'] <= 0) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('number_greater_zero'),
				), 400);
			}

			if (!($this->postdata['start_limit'] < $this->postdata['end_limit'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_start_limit'),
				), 400);
			}

			$start               = $this->postdata['start_limit'] - 1;
			$limit               = $this->postdata['end_limit'];
			$object_where_params = $this->postdata['object_where_params'];

			if ($object_where_params['type'] != '') {
				if (!($object_where_params['type'] == '0' || $object_where_params['type'] == '3')) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_type'),
					), 400);
				}
			}
			if (isset($object_where_params['tax_number']) && $object_where_params['tax_number'] != '') {
				$object_where_params['tax_number'] = preg_replace('/\D+/', '', (string) $object_where_params['tax_number']);
				$len = strlen($object_where_params['tax_number']);
				if ($len !== 14) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_doc'),
					), 400);
				}
			}
			$currency_id = $this->common->get_field_name('currency', 'currency', array('id' => $this->accountinfo['currency_id']));
			$where       = '';
			foreach ($object_where_params as $object_where_key => $object_where_value) {
				if ($object_where_value != '') {
					$where = $object_where_key . ' = "' . $object_where_value . '" AND ';
					if (!empty($where)) {
						$where = rtrim($where, "AND ");
						$this->db->where($where);
					}
				}
			}

			$no_of_records = (int) $limit - (int) $start;
			if (
				$this->accountinfo['type'] == -1 ||
				$this->accountinfo['type'] == 2  ||
				$this->accountinfo['type'] == 4  ||
				$this->accountinfo['type'] == 5  ||
				$this->accountinfo['type'] == 6
			) {
				$this->db->where_in('type', array(0, 3));
			} else {
				$where = array('reseller_id' => $this->accountinfo['id'], 'type' => 0);
				$this->db->where($where);
			}

			$query = $this->db
				->limit($no_of_records, $start)
				->order_by('id', 'desc')
				->select('id,id as accountid, number,first_name,timezone_id,last_name,company_name,email,reseller_id,telephone_1,postal_code,province,city,country_id,pricelist_id,posttoexternal,currency_id,balance,credit_limit,first_used,expiry,localization_id,creation,status,permission_id,tax_number,invoice_day')
				->where('deleted', '0')
				->get('accounts');

			$count = $query->num_rows();
			$query = $query->result_array();

			if ($count > 0) {
				foreach ($query as $key => $value) {
					$value['id']             = $value['id'];
					$value['accountid']      = $value['accountid'];
					$value['first_name']     = $value['first_name'];
					$value['last_name']      = $value['last_name'];
					$value['company_name']   = $value['company_name'];
					$value['country']        = $this->common->get_field_name('nicename', 'countrycode', array('id' => $value['country_id']));
					$value['reseller_id']    = $value['reseller_id'] != null ? $value['reseller_id'] : '0';
					$value['pricelist_id']   = $this->common->get_field_name('name', 'pricelists', array('id' => $value['pricelist_id']));
					$value['localization_id'] = $this->common->get_field_name('name', 'localization', array('id' => $value['localization_id']));
					$value['creation']       = $this->common->convert_GMT_to('', '', $value['creation'], $this->accountinfo['timezone_id']);
					$value['expiry']         = $this->common->convert_GMT_to('', '', $value['expiry'], $this->accountinfo['timezone_id']);
					$value['balance']        = $this->Common_model->to_calculate_currency($value['balance'], '', $currency_id);
					$value['posttoexternal'] = $value['posttoexternal'] == 0 ? "Prepaid" : "Postpaid";
					$value['tax_number']     = $value['tax_number'];
					$value['invoice_day']     = $value['invoice_day'];
					unset($value['timezone_id']);
					$value['permission_id']  = $this->common->get_field_name('name', 'permissions', array('id' => $value['permission_id']));
					$insert_array            = $this->_token($value['id'], "e", $value);
					$value['token']          = $insert_array['token'];
					unset($value['id']);
					$new_array[] = $value;
				}
				$this->response(array(
					'total_count' => $count,
					'data'        => $new_array,
					'success'     => $this->lang->line("customer_list_information"),
				), 200);
			} else {
				$this->response(array(
					'total_count' => 0,
					'data'        => $new_array,
					'success'     => $this->lang->line("no_records_found"),
				), 200);
			}
		}
	}

	function _customer_read() {
		$postdata    = $this->postdata;
		$currency_id = $this->common->get_field_name('currency', 'currency', array('id' => $this->accountinfo['currency_id']));
		if (empty($postdata['accountid']) || !isset($postdata['accountid'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_param_missing') . " integer:accountid",
			), 400);
		} else {
			$where = array('id' => $postdata['accountid'], 'deleted' => 0, 'status' => 0);
			$this->db->select('*');
			if (
				$this->accountinfo['type'] == -1 ||
				$this->accountinfo['type'] == 2  ||
				$this->accountinfo['type'] == 5  ||
				$this->accountinfo['type'] == 6  ||
				$this->accountinfo['type'] == 4
			) {
				$this->db->where_in('type', array(0, 3));
			} else {
				$where = array('reseller_id' => $postdata['id'], 'id' => $postdata['accountid'], 'deleted' => 0, 'status' => 0);
			}
			$this->db->where($where);
			$accountinfo = (array) $this->db->get("accounts")->first_row();
			if (empty($accountinfo)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('account_not_found'),
				), 400);
			}

			$this->db->select('taxes_id');
			$this->db->where('accountid', $accountinfo['id']);
			$tax_id     = (array) $this->db->get('taxes_to_accounts')->first_row();
			$read_array = array(
				"accountid"            => $accountinfo['id'],
				"number"               => $accountinfo['number'],
				"reseller_id"          => $accountinfo['reseller_id'],
				"pin"                  => $accountinfo['pin'],
				"email"                => $accountinfo['email'],
				"first_used"           => $this->common->convert_GMT_to('', '', $accountinfo['first_used'], $this->accountinfo['timezone_id']),
				"validfordays"         => $accountinfo['validfordays'],
				"expiry"               => $this->common->convert_GMT_to('', '', $accountinfo['expiry'], $this->accountinfo['timezone_id']),
				"status"               => $accountinfo['status'],
				"maxchannels"          => $accountinfo['maxchannels'],
				"cps"                  => $accountinfo['cps'],
				"creation"             => $this->common->convert_GMT_to('', '', $accountinfo['creation'], $this->accountinfo['timezone_id']),
				"balance"              => $this->Common_model->to_calculate_currency($accountinfo['balance'], '', $currency_id),
				"localization_id"      => $accountinfo['localization_id'],
				"local_call"           => $accountinfo['local_call'],
				"is_recording"         => $accountinfo['is_recording'],
				"allow_ip_management"  => $accountinfo['allow_ip_management'],
				"notifications"        => $accountinfo['notifications'],
				"paypal_permission"    => $accountinfo['paypal_permission'],
				"first_name"           => $accountinfo['first_name'],
				"last_name"            => $accountinfo['last_name'],
				"company_name"         => $accountinfo['company_name'],
				"telephone_1"          => $accountinfo['telephone_1'],
				"telephone_2"          => $accountinfo['telephone_2'],
				"notification_email"   => $accountinfo['notification_email'],
				"address_1"            => $accountinfo['address_1'],
				"address_2"            => $accountinfo['address_2'],
				"city"                 => $accountinfo['city'],
				"province"             => $accountinfo['province'],
				"notify_credit_limit"  => $accountinfo['notify_credit_limit'],
				"notify_flag"          => $accountinfo['notify_flag'],
				"notify_email"         => $accountinfo['notify_email'],
				"language_id"          => $accountinfo['language_id'],
				"invoice_day"          => $accountinfo['invoice_day'],
				"invoice_interval"     => $accountinfo['invoice_interval'],
				"invoice_note"         => $accountinfo['invoice_note'],
				"local_call_cost"      => $accountinfo['local_call_cost'],
				"charge_per_min"       => $accountinfo['charge_per_min'],
				"postal_code"          => $accountinfo['postal_code'],
				"country_id"           => $accountinfo['country_id'],
				"timezone_id"          => $accountinfo['timezone_id'],
				"currency_id"          => $accountinfo['currency_id'],
				"posttoexternal"       => $accountinfo['posttoexternal'],
				"credit_limit"         => $accountinfo['credit_limit'],
				"pricelist_id"         => $accountinfo['pricelist_id'],
				"sweep_id"             => $accountinfo['sweep_id'],
				"tax_id"               => $tax_id['taxes_id'],
				"tax_number"           => $accountinfo['tax_number'],
				"reference"            => $accountinfo['reference'],
			);
			$this->response(array(
				'status'  => true,
				'data'    => $read_array,
				'success' => $this->lang->line("read_customer"),
			), 200);
		}
	}

	function _customer_create() {
		$postdata = $this->postdata;
		if ($this->accountinfo['type'] == 1) {
			$postdata['reseller_id'] = $this->accountinfo['id'];
		} else {
			if ($postdata['reseller_id'] == '' || $postdata['reseller_id'] == 0) {
				$postdata['reseller_id'] = 0;
			} else {
				if ($postdata['reseller_id'] > 0) {
					$postdata['reseller_id'] = $this->common->get_field_name('id', 'accounts', array('id' => $postdata['reseller_id'], 'type' => 1, 'deleted' => 0));
				}
				if ($postdata['reseller_id'] == '') {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_reseller_id'),
					), 400);
				}
			}
		}

		if ($this->form_validation->required($postdata['first_name']) == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('enter_first_name'),
			), 400);
		}

		if (empty($postdata['email']) || $postdata['email'] == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('require_email'),
			), 400);
		} else {
			if (!filter_var($postdata['email'], FILTER_VALIDATE_EMAIL)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('invalid_email_format'),
				), 400);
			}
			$this->db->where('deleted', 0);
			$this->db->where(array("email" => $postdata['email']));
			$check_email = $this->db->get('accounts')->num_rows();
			if ($check_email > 0) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('email_already_used'),
				), 400);
			}
		}

		if (isset($postdata['tax_number']) && $postdata['tax_number'] != '') {
			$postdata['tax_number'] = preg_replace('/\D+/', '', (string) $postdata['tax_number']);
			$len = strlen($postdata['tax_number']);
			if ($len >= 15 || $len <= 11) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_doc'),
				), 400);
			}
			$this->db->where(array("tax_number" => $postdata['tax_number']));
			$check_tax_number = $this->db->get('accounts')->num_rows();
			if ($check_tax_number > 0) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('tax_number_already_used'),
				), 400);
			}
		}

		if ($postdata['pricelist_id'] == '' || !isset($postdata['pricelist_id'])) {
			if ($this->form_validation->required($postdata['pricelist_id']) == '') {
				$postdata['pricelist_id'] = 1;
			}
		} else {
			$postdata['pricelist_id'] = $this->common->get_field_name('id', 'pricelists', array('id' => $postdata['pricelist_id'], 'status' => 0));
		}

		if (empty($postdata['pricelist_id'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('valid_pricelist_id'),
			), 400);
		}

		if ($this->accountinfo['type'] == 1) {
			if ($this->form_validation->required($postdata['telephone_1']) == '') {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('enter_telephone'),
				), 400);
			}
			if ($postdata['currency_id'] == '' || !isset($postdata['currency_id'])) {
				$postdata['currency_id'] = $this->common->get_field_name('id', 'currency', array('currency' => 'BRL'));
			} else {
				$postdata['currency_id'] = $this->common->get_field_name('id', 'currency', array('id' => $postdata['currency_id']));
				if (empty($postdata['currency_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('enter_currency'),
					), 400);
				}
			}
			if ($postdata['country_id'] == '' || !isset($postdata['country_id'])) {
				$postdata['country_id'] = $this->common->get_field_name('id', 'countrycode', array('country' => 'BRAZIL'));
			} else {
				$postdata['country_id'] = $this->common->get_field_name('id', 'countrycode', array('id' => $postdata['country_id']));
				if (empty($postdata['country_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_country_id'),
					), 400);
				}
			}
			if ($postdata['timezone_id'] == '' || !isset($postdata['timezone_id'])) {
				$postdata['timezone_id'] = $this->common->get_field_name('id', 'timezone', array('timezone_name' => 'America/Sao_Paulo'));
			} else {
				$postdata['timezone_id'] = $this->common->get_field_name('id', 'timezone', array('id' => $postdata['timezone_id']));
				if (empty($postdata['timezone_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_timezone_id'),
					), 400);
				}
			}
			if (!is_numeric($postdata['balance']) && $postdata['balance'] != '') {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_balance'),
				), 400);
			}
			if ($postdata['pricelist_id'] == '' || !isset($postdata['pricelist_id'])) {
				if ($this->form_validation->required($postdata['pricelist_id']) == '') {
					if (empty($postdata['pricelist_id'])) {
						$this->response(array(
							'status' => false,
							'error'  => $this->lang->line('require_pricelist_id'),
						), 400);
					}
				}
			} else {
				$postdata['pricelist_id'] = $this->common->get_field_name('id', 'pricelists', array('id' => $postdata['pricelist_id'], 'reseller_id' => $postdata['id']));
			}
			if (empty($postdata['pricelist_id'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_pricelist_id'),
				), 400);
			}
		}

		
		$config = isset(Common_model::$global_config['system_config']['card_length'])
		    ? Common_model::$global_config['system_config']['card_length']
		    : '';
		
		if ($config != '') {
			$uname = $this->common->find_uniq_rendno_customer(Common_model::$global_config['system_config']['card_length'], 'number ', 'accounts');
		} else {
			$uname = $this->common->find_uniq_rendno_customer(10, 'number ', 'accounts');
		}

		$pin_generate = Common_model::$global_config['system_config']['generate_pin'];
		
		$pin_length = isset(Common_model::$global_config['system_config']['pin_length'])
		    ? Common_model::$global_config['system_config']['pin_length']
		    : 6;
		
		if ($pin_generate == 0) {
			$pin        = ($pin_length < 6) ? 6 : $pin_length;
			$pin_number = $this->common->find_uniq_rendno_customer($pin, 'number', 'accounts');
		}

		if ($postdata['password'] == '') {
			$postdata['password'] = $this->common->generate_password();
		}
		$encoded_password = $this->common->encode($postdata['password']);

		if ($postdata['sweep_id'] == '' || $postdata['sweep_id'] >= '3' || $postdata['sweep_id'] == '1') {
			$sweep_id = 2;
		} else {
			$sweep_id = $postdata['sweep_id'];
		}

		if (isset($postdata['notify_email']) && !empty($postdata['notify_email']) && (!filter_var($postdata['notify_email'], FILTER_VALIDATE_EMAIL))) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('invalid_email_format'),
			), 400);
		}

		$current_date = gmdate("Y-m-d H:i:s");
		if (!($postdata['invoice_day'] == -1 || $postdata['invoice_day'] <= 30)) {
			$postdata['invoice_day'] = 1;
		}

		if ($this->accountinfo['type'] != '1') {
			$currency_id      = Common_model::$global_config['system_config']['base_currency'];
			$currency_info    = (array) $this->db->get_where("currency", array("currency" => $currency_id))->first_row();
			$country_id       = Common_model::$global_config['system_config']['country'];
			$timezone_id      = Common_model::$global_config['system_config']['default_timezone'];
			$localization_id  = Common_model::$global_config['system_config']['localization_id'];
			if (empty($postdata['pricelist_id'])) {
				$pricelist_id = Common_model::$global_config['system_config']['default_signup_rategroup'];
			} else {
				$pricelist_id = $this->common->get_field_name('id', 'pricelists', array('id' => $postdata['pricelist_id'], 'reseller_id' => $postdata['reseller_id']));
				if (empty($pricelist_id)) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('rategroup_not_found'),
					), 400);
				}
			}
		}

		if ($postdata['posttoexternal'] == '') {
			$postdata['posttoexternal'] = '1';
		}

		if (!($postdata['posttoexternal'] == 0 || $postdata['posttoexternal'] == 1)) {
			$postdata['posttoexternal'] = '0';
		}

		if ((!isset($postdata['credit_limit']) || $postdata['credit_limit'] == '') && $postdata['posttoexternal'] == 1) {
			$postdata['credit_limit'] = 10000.00;
		}

		if (isset($postdata['credit_limit']) && $postdata['credit_limit'] != '' && !is_numeric($postdata['credit_limit'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('valid_credit_limit'),
			), 400);
		}

		if (isset($postdata['tax_number']) && $postdata['tax_number'] != '' && !is_numeric($postdata['tax_number'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('valid_tax_id'),
			), 400);
		}
		if (!is_numeric($postdata['maxchannels']) && $postdata['maxchannels'] != '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('valid_maxchannels'),
			), 400);
		}
		$type                         = $this->postdata['action'] == 'provider_create' ? '3' : '0';
		
		$postdata['notification_email'] = $postdata['action'] == 'provider_create'
		    ? $postdata['email']
		    : (isset($postdata['notification_email']) ? $postdata['notification_email'] : '');
		
		$currency_id                  = Common_model::$global_config['system_config']['base_currency'];
		$currency_info                = (array) $this->db->get_where("currency", array("currency" => $currency_id))->first_row();

		$insert_array = array(
			"number"               => $uname,
			"password"             => $encoded_password,
			"email"                => $postdata['email'],
			"pin"                  => $pin_number,
			"currency_id"          => isset($currency_info['id']) ? $currency_info['id'] : $postdata['currency_id'],
			"type"                 => $type,
			"country_id"           => isset($country_id) ? $country_id : $postdata['country_id'],
			"timezone_id"          => isset($timezone_id) ? $timezone_id : $postdata['timezone_id'],
			"cps"                  => isset($postdata['cps']) ? $postdata['cps'] : Common_model::$global_config['system_config']['cps'],
			"reseller_id"          => $postdata['reseller_id'],
			"company_name"         => isset($postdata['company_name']) ? $postdata['company_name'] : '',
			"first_name"           => $postdata['first_name'],
			"last_name"            => isset($postdata['last_name']) ? $postdata['last_name'] : '',
			"telephone_1"          => isset($postdata['telephone_1']) ? $postdata['telephone_1'] : '',
			"maxchannels"          => isset($postdata['maxchannels']) ? $postdata['maxchannels'] : Common_model::$global_config['system_config']['maxchannels'],
			"permission_id"        => isset($postdata['permission_id']) ? $postdata['permission_id'] : '0',
			"local_call"           => Common_model::$global_config['system_config']['local_call'],
			"localization_id"      => isset($localization_id) ? $localization_id : '',
			"is_distributor"       => isset($postdata['is_distributor']) ? $postdata['is_distributor'] : '1',
			"std_cid_translation"  => isset($postdata['std_cid_translation']) ? $postdata['std_cid_translation'] : '',
			"did_cid_translation"  => isset($postdata['did_cid_translation']) ? $postdata['did_cid_translation'] : '',
			"address_1"            => isset($postdata['address_1']) ? $postdata['address_1'] : '',
			"address_2"            => isset($postdata['address_2']) ? $postdata['address_2'] : '',
			"city"                 => isset($postdata['city']) ? $postdata['city'] : '',
			"province"             => isset($postdata['province']) ? $postdata['province'] : '',
			"postal_code"          => isset($postdata['postal_code']) ? $postdata['postal_code'] : '',
			"posttoexternal"       => isset($postdata['posttoexternal']) ? $postdata['posttoexternal'] : '1',
			"credit_limit"         => isset($postdata['credit_limit']) ? $postdata['credit_limit'] : '',
			"sweep_id"             => isset($sweep_id) ? $sweep_id : '',
			"language_id"          => isset($postdata['language_id']) ? $postdata['language_id'] : '0',
			"dialed_modify"        => isset($postdata['dialed_modify']) ? $postdata['dialed_modify'] : '',
			"invoice_interval"     => isset($postdata['invoice_interval']) ? $postdata['invoice_interval'] : '0',
			"invoice_note"         => isset($postdata['invoice_note']) ? $postdata['invoice_note'] : '',
			"generate_invoice"     => Common_model::$global_config['system_config']['generate_invoice'],
			"deleted"              => isset($postdata['deleted']) ? $postdata['deleted'] : '0',
			"notification_email"   => isset($postdata['notification_email']) ? $postdata['notification_email'] : '',
			"last_bill_date"       => "0000-00-00 00:00:00",
			"inuse"                => isset($postdata['inuse']) ? $postdata['inuse'] : '0',
			"invoice_day"          => isset($postdata['invoice_day']) ? $postdata['invoice_day'] : '1',
			"commission_rate"      => isset($postdata['commission_rate']) ? $postdata['commission_rate'] : '0',
			"reference"            => isset($postdata['reference']) ? $postdata['reference'] : '',
			"notifications"        => Common_model::$global_config['system_config']['notifications'],
			"is_recording"         => Common_model::$global_config['system_config']['is_recording'],
			"charge_per_min"       => Common_model::$global_config['system_config']['charge_per_min'],
			"paypal_permission"    => Common_model::$global_config['system_config']['paypal_permission'],
			"notify_flag"          => Common_model::$global_config['system_config']['notify_flag'],
			"tax_number"           => isset($postdata['tax_number']) ? $postdata['tax_number'] : '',
			"pricelist_id"         => isset($pricelist_id) ? $pricelist_id : $postdata['pricelist_id'],
			"creation"             => gmdate('Y-m-d H:i:s'),
			"first_used"           => "0000-00-00 00:00:00",
			"sip_device_flag"      => isset($postdata['sip_device_flag']) ? $postdata['sip_device_flag'] : Common_model::$global_config['system_config']['create_sipdevice']
		);

		$this->load->library("flux/signup_lib");
		$last_id     = $this->signup_lib->create_account($insert_array);
		$accountinfo = $this->db_model->getSelect("*", 'accounts', array('id' => $last_id))->row_array();

		if (!empty($accountinfo)) {
			//vinculando pacote ao customer
			$created_by_accountinfo = array(
			    'id'     => '1'
			);
			if (isset($postdata['product_id']) && $postdata['product_id'] != '') {
				$productdata['product_id']      = $postdata['product_id'];
				$account_id                     = $last_id;
				$productdata['create_invoice']  = "true";
				$productdata['payment_by']  		= 0;
				$confirm_order = $this->order->confirm_order($productdata, $account_id, $created_by_accountinfo);
				if ($confirm_order == '') {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('product_assign_error'),
					), 400);
				} else {					
					$this->db->select('
					    order_items.id as order_item_id,
					    orders.order_id as order_number,
					    orders.order_date,
					    orders.payment_status,
					    orders.reseller_id,
					    orders.accountid,
					    order_items.billing_date,
					    order_items.termination_date,
					    order_items.next_billing_date,
					    order_items.product_id,
					    order_items.setup_fee,
					    order_items.price,
					    products.name as product_name,
					    category.name as category_name
					');
					$this->db->from('orders');
					$this->db->join('order_items', 'order_items.order_id = orders.id', 'inner');
					$this->db->join('products', 'products.id = order_items.product_id', 'left');
					$this->db->join('category', 'category.id = products.product_category', 'left');
					$this->db->where('order_items.order_id', $confirm_order);
					$this->db->order_by('order_items.billing_date', 'desc');

					$result = $this->db->get();

					if (!$result || $result->num_rows() === 0) {
						$this->response(array(
							'status'  => true,
							'success' => $this->lang->line('no_records_found'),
						), 200);
					}

					$orders_info = $result->result_array();
					$ordersinfo  = array();

					foreach ($orders_info as $orders_value) {
						$orders_value['billing_date'] = $this->timezone->convert_to_GMT_new(
							$orders_value['billing_date'],
							'1',
							$this->accountinfo['timezone_id']
						);
						$ordersinfo[] = $orders_value;
					}
				}
			}

			$accountinfo['product_id'] = isset($productdata['product_id']) && $productdata['product_id'] != '' ? $productdata['product_id'] : '0';

			if ($postdata['action'] != 'provider_create') {
				if (isset($ordersinfo) && $ordersinfo != '') {
					$this->response(array(
						'status'        => true,
						'data'          => $accountinfo,
						'order_details' => $ordersinfo,
						'success'       => $this->lang->line('create_customer_success'),
					), 200);
				} else {
					$this->response(array(
						'status'  => true,
						'data'    => $accountinfo,
						'success' => $this->lang->line('create_customer_success'),
					), 200);
				}
			} else {
				$this->response(array(
					'status'  => true,
					'data'    => $accountinfo,
					'success' => $this->lang->line('create_provider_success'),
				), 200);
			}
		} else {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('something_wrong_contact_admin'),
			), 400);
		}
	}

	function _customer_delete() {
		$postdata = $this->postdata;
		if ($this->form_validation->required($postdata['accountid']) == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('enter_account_id'),
			), 400);
		} else {
			if (!$this->form_validation->numeric_with_comma($postdata['accountid'])) {
				$this->response(array(
					'status'  => false,
					'success' => $this->lang->line('enter_valid_accountid'),
				), 400);
			}
			if ($this->accountinfo['type'] == 1) {
				$this->db->where('reseller_id', $postdata['id']);
				$this->db->where_in('id', $postdata['accountid']);
			} else {
				$this->db->where_in('id', $postdata['accountid']);
			}
			$accountinfo = $this->db->get('accounts')->result_array();
			if (empty($accountinfo)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('account_not_found'),
				), 400);
			}
			$where = array('type' => 0);
			if ($this->accountinfo['type'] == 1) {
				$where['reseller_id'] = $this->accountinfo['id'];
			}
			$this->db->where($where);
			$this->db->where("id IN (" . $postdata['accountid'] . ") ");
			$delete_user_info = $this->db->update("accounts", array("deleted" => "1"));
			$last_edit_id     = $this->db->affected_rows();
			if ($last_edit_id > 0) {
				$this->db->where("accountid IN (" . $postdata['accountid'] . ") ");
				$delete_sip_info = $this->db->delete("sip_devices", '');
			}
			if ($last_edit_id == 0) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('account_not_found'),
				), 400);
			} else {
				$this->response(array(
					'status'  => true,
					'success' => $this->lang->line('account_deleted'),
				), 200);
			}
		}
	}

	function _customer_update() {
		$postdata = $this->postdata;

		if ($this->form_validation->required($postdata['accountid']) == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('enter_account_id'),
			), 400);
		} else {
			$customerinfo = (array) $this->db->get_where("accounts", array("id" => $postdata['accountid'], "deleted" => "0"))->first_row();
			if (empty($customerinfo)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('account_not_found'),
				), 400);
			}

			if (!isset($postdata['currency_id']) || $postdata['currency_id'] == '') {
				$postdata['currency_id'] = $this->common->get_field_name('id', 'currency', array('currency' => 'BRL'));
			} else {
				$postdata['currency_id'] = $this->common->get_field_name('id', 'currency', array('id' => $postdata['currency_id']));
				if (empty($postdata['currency_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('enter_currency'),
					), 400);
				}
			}

			if (!isset($postdata['country_id']) || $postdata['country_id'] == '') {
				$postdata['country_id'] = $this->common->get_field_name('id', 'countrycode', array('country' => 'BRAZIL'));
			} else {
				$postdata['country_id'] = $this->common->get_field_name('id', 'countrycode', array('id' => $postdata['country_id']));
				if (empty($postdata['country_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_country_id'),
					), 400);
				}
			}

			if (!isset($postdata['timezone_id']) || $postdata['timezone_id'] == '') {
				$postdata['timezone_id'] = $this->common->get_field_name('id', 'timezone', array('timezone_name' => 'America/Sao_Paulo'));
			} else {
				$postdata['timezone_id'] = $this->common->get_field_name('id', 'timezone', array('id' => $postdata['timezone_id']));
				if (empty($postdata['timezone_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_timezone_id'),
					), 400);
				}
			}
			
			

			if (isset($postdata['balance']) && $postdata['balance'] != '' && !is_numeric($postdata['balance'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_balance'),
				), 400);
			}
			if (isset($postdata['maxchannels']) && !is_numeric($postdata['maxchannels'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_maxchannels'),
				), 400);
			}
			if (isset($postdata['cps']) && !is_numeric($postdata['cps'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_cps'),
				), 400);
			}
			
			if (!isset($postdata['pricelist_id']) || $postdata['pricelist_id'] == '') {
				$postdata['pricelist_id'] = $this->common->get_field_name('pricelist_id', 'accounts', array('id' => $postdata['accountid']));
			} else {
				$postdata['pricelist_id'] = $this->common->get_field_name('id', 'pricelists', array('id' => $postdata['pricelist_id'], 'status' => 0));
				if (empty($postdata['pricelist_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('rategroup_not_found'),
					), 400);
				}
			}
			

			if (isset($postdata['tax_number']) && $postdata['tax_number'] != '') {
				$postdata['tax_number'] = preg_replace('/\D+/', '', (string) $postdata['tax_number']);
				$len = strlen($postdata['tax_number']);
				if ($len !== 14) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('valid_doc'),
					), 400);
				}
				$this->db->where_not_in('id', $postdata['accountid']);
				$this->db->where(array("tax_number" => $postdata['tax_number']));
				$check_tax_number = $this->db->get('accounts')->num_rows();
				if ($check_tax_number > 0) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('tax_number_already_used'),
					), 400);
				}
			}

			//validação de pacotes vinculados ao account
			$account_id             = $postdata['accountid'];
			$created_by_accountinfo = array(
			    'id'     => '1'
			);
			if (isset($postdata['product_id']) && $postdata['product_id'] != '') {
				$package              = $this->db->get_where("packages_view", array('accountid' => $postdata['accountid'], 'is_terminated' => '0'))->result_array();
				$productIdsInPackage  = array_column($package, 'product_id');

				if (empty($package)) {
					$productdata['product_id']     = $postdata['product_id'];
					$productdata['create_invoice'] = "true";
					$confirm_order = $this->order->confirm_order($productdata, $account_id, $created_by_accountinfo);
				}

				foreach ($package as $key) {
					$this->api_log->write_log('Package ID: ', json_encode($key['product_id']));
					if ($key['product_id'] != $postdata['product_id']) {
						$update_counter = array("status" => "0");
						$this->db->where('product_id', $key['product_id']);
						$this->db->where('accountid', $this->postdata['accountid']);
						$this->db->update('counters', $update_counter);
						$update_order = array(
							"is_terminated"    => "1",
							"termination_date" => date("Y-m-d H:i:s"),
							"termination_note" => "Product terminated by customer_update (API)",
						);
						$this->db->where('product_id', $key['product_id']);
						$this->db->where('accountid', $this->postdata['accountid']);
						$this->db->update('order_items', $update_order);
					} else if (!in_array($postdata['product_id'], $productIdsInPackage)) {
						$productdata['product_id']     = $postdata['product_id'];
						$productdata['create_invoice'] = "true";
						$confirm_order = $this->order->confirm_order($productdata, $account_id, $created_by_accountinfo);
					}
				}
			}

			$update_array = array(
			    "status"           => isset($postdata['status'])           ? $postdata['status']           : $customerinfo['status'],
			    "first_name"       => isset($postdata['first_name'])       ? $postdata['first_name']       : $customerinfo['first_name'],
			    "last_name"        => isset($postdata['last_name'])        ? $postdata['last_name']        : $customerinfo['last_name'],
			    "email"            => isset($postdata['email'])            ? $postdata['email']            : $customerinfo['email'],
			    "company_name"     => isset($postdata['company_name'])     ? $postdata['company_name']     : $customerinfo['company_name'],
			    "country_id"       => isset($postdata['country_id'])       ? $postdata['country_id']       : $customerinfo['country_id'],
			    "timezone_id"      => isset($postdata['timezone_id'])      ? $postdata['timezone_id']      : $customerinfo['timezone_id'],
			    "currency_id"      => isset($currency_info['id'])          ? $currency_info['id']          : $postdata['currency_id'],
			    "address_1"        => isset($postdata['address_1'])        ? $postdata['address_1']        : $customerinfo['address_1'],
			    "city"             => isset($postdata['city'])             ? $postdata['city']             : $customerinfo['city'],
			    "province"         => isset($postdata['province'])         ? $postdata['province']         : $customerinfo['province'],
			    "postal_code"      => isset($postdata['postal_code'])      ? $postdata['postal_code']      : $customerinfo['postal_code'],
			    "telephone_1"      => isset($postdata['telephone_1'])      ? $postdata['telephone_1']      : $customerinfo['telephone_1'],
			    "permission_id"    => isset($postdata['permission_id'])    ? $postdata['permission_id']    : $customerinfo['permission_id'],
			    "balance"          => isset($postdata['balance'])          ? $postdata['balance']          : $customerinfo['balance'],
			    "tax_number"       => isset($postdata['tax_number'])       ? $postdata['tax_number']       : $customerinfo['tax_number'],
			    "credit_limit"     => isset($postdata['credit_limit'])     ? $postdata['credit_limit']     : $customerinfo['credit_limit'],
			    "invoice_day"      => isset($postdata['invoice_day'])      ? $postdata['invoice_day']      : $customerinfo['invoice_day'],
			    "generate_invoice" => isset($postdata['generate_invoice']) ? $postdata['generate_invoice'] : $customerinfo['generate_invoice'],
			    "notify_email"     => isset($postdata['notify_email'])     ? $postdata['notify_email']     : $customerinfo['notify_email'],
			    "pricelist_id"     => isset($postdata['pricelist_id'])     ? $postdata['pricelist_id']     : $customerinfo['pricelist_id'],
			    "maxchannels"      => isset($postdata['maxchannels'])      ? $postdata['maxchannels']      : $customerinfo['maxchannels'],
			    "cps"              => isset($postdata['cps'])              ? $postdata['cps']              : $customerinfo['cps'],
			);
			$this->db->where('id', $this->postdata['accountid']);
			$this->db->update('accounts', $update_array);
			$this->response(array(
				'status'  => true,
				'data'    => $update_array,
				'success' => $this->lang->line('account_update_success'),
			), 200);
		}
	}

	function _product_add() {
	    $postdata = $this->postdata;
	
	    if (empty($postdata['accountid'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('enter_account_id'),
	        ), 400);
	    }
	
	    if (empty($postdata['product_id'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('error_param_missing') . ' integer:product_id',
	        ), 400);
	    }
	
	    $productdata = array(
	        'product_id'     => $postdata['product_id'],
	        'create_invoice' => 'true',
	        'payment_by'     => 0
	    );
	
	    $account_id             = $postdata['accountid'];
	    $created_by_accountinfo = array(
	        'id'     => $postdata['id']
	    );
	
	    $confirm_order = $this->order->confirm_order(
	        $productdata,
	        $account_id,
	        $created_by_accountinfo
	    );
	
	    if (empty($confirm_order)) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('product_assign_error'),
	        ), 400);
	    }
	    $this->db->select('
	        order_items.id as order_item_id,
	        orders.order_id as order_number,
	        orders.order_date,
	        orders.payment_status,
	        orders.reseller_id,
	        orders.accountid,
	        order_items.billing_date,
	        order_items.termination_date,
	        order_items.next_billing_date,
	        order_items.product_id,
	        order_items.setup_fee,
	        order_items.price,
	        products.name as product_name,
	        category.name as category_name
	    ');
	    $this->db->from('orders');
	    $this->db->join('order_items', 'order_items.order_id = orders.id', 'inner');
	    $this->db->join('products', 'products.id = order_items.product_id', 'left');
	    $this->db->join('category', 'category.id = products.product_category', 'left');
	    $this->db->where('order_items.order_id', $confirm_order);
	    $this->db->order_by('order_items.billing_date', 'desc');
	
	    $result = $this->db->get();
	
	    if (!$result || $result->num_rows() === 0) {
	        $this->response(array(
	            'status'  => true,
	            'success' => $this->lang->line('no_records_found'),
	        ), 200);
	    }
	
	    $orders_info = $result->result_array();
	    $ordersinfo  = array();
	
	    foreach ($orders_info as $orders_value) {
	        $orders_value['billing_date'] = $this->timezone->convert_to_GMT_new(
	            $orders_value['billing_date'],
	            '1',
	            $this->accountinfo['timezone_id']
	        );
	
	        $ordersinfo[] = $orders_value;
	    }
	
	    $this->db->select('id, number, first_name, last_name, company_name, email, reseller_id');
	    $this->db->from('accounts');
	    $this->db->where('id', $account_id);
	    $this->db->order_by('id', 'desc');
	
	    $account_query   = $this->db->get();
	    $account_details = $account_query->row_array();
	
	    $response_data = array(
	        'account_details' => $account_details,
	        'order_details'   => $ordersinfo,
	    );
	
	    $this->response(array(
	        'status'  => true,
	        'data'    => $response_data,
	        'success' => $this->lang->line('product_assign_success'),
	    ), 200);
	}

	function _product_release() {
	    $postdata = $this->postdata;

	    if (empty($postdata['accountid'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('enter_account_id'),
	        ), 400);
	    }

	    if (!$this->form_validation->numeric($postdata['accountid'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('numeric_accountid'),
	        ), 400);
	    }

	    if (empty($postdata['order_item_id'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('error_param_missing') . " integer:order_item_id",
	        ), 400);
	    }

	    if (!$this->form_validation->numeric($postdata['order_item_id'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('numeric_order_item_id'),
	        ), 400);
	    }

	    $accountinfo = $this->db_model->getSelect(
	        'id', 'accounts', array('id' => $postdata['accountid'], 'deleted' => '0')
	    )->row_array();

	    if (empty($accountinfo)) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('account_not_found'),
	        ), 400);
	    }

	    $this->db->select('
	        order_items.id as order_item_id,
	        order_items.order_id,
	        order_items.product_id,
	        order_items.accountid,
	        order_items.is_terminated,
	        products.name as product_name
	    ');
	    $this->db->from('order_items');
	    $this->db->join('orders', 'orders.id = order_items.order_id', 'inner');
	    $this->db->join('products', 'products.id = order_items.product_id', 'left');
	    $this->db->where('order_items.id', (int) $postdata['order_item_id']);
	    $this->db->where('orders.accountid', (int) $postdata['accountid']);
	    $order_item_info = $this->db->get()->row_array();

	    if (empty($order_item_info)) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('order_item_not_found'),
	        ), 400);
	    }

	    if ((int) $order_item_info['is_terminated'] === 1) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('product_already_released'),
	        ), 400);
	    }

	    $update_order = array(
	        'is_terminated'    => '1',
	        'termination_date' => date('Y-m-d H:i:s'),
	        'termination_note' => 'Product terminated by product_release (API)',
	    );
	    $this->db->where('id', $order_item_info['order_item_id']);
	    $this->db->update('order_items', $update_order);

	    $this->db->where('accountid', $order_item_info['accountid']);
	    $this->db->where('product_id', $order_item_info['product_id']);
	    $this->db->where('package_id', $order_item_info['order_id']);
	    $this->db->update('counters', array('status' => '0'));

	    $this->response(array(
	        'status'  => true,
	        'data'    => array(
	            'order_item_id'    => $order_item_info['order_item_id'],
	            'accountid'        => $order_item_info['accountid'],
	            'product_id'       => $order_item_info['product_id'],
	            'product_name'     => $order_item_info['product_name'],
	            'termination_date' => $update_order['termination_date'],
	        ),
	        'success' => $this->lang->line('product_release_success'),
	    ), 200);
	}

	function _customer_products_list() {
	    $postdata = $this->postdata;

	    if (empty($postdata['accountid'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('enter_account_id'),
	        ), 400);
	    }

	    if (!$this->form_validation->numeric($postdata['accountid'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('numeric_accountid'),
	        ), 400);
	    }

	    if (!isset($postdata['end_limit']) || !isset($postdata['start_limit'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('error_param_missing') . " integer:end_limit,integer:start_limit",
	        ), 400);
	    }

	    if ($postdata['start_limit'] <= 0 || $postdata['end_limit'] <= 0) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('number_greater_zero'),
	        ), 400);
	    }

	    if (!($postdata['start_limit'] < $postdata['end_limit'])) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('valid_start_limit'),
	        ), 400);
	    }

	    $account_id  = $postdata['accountid'];
	    $accountinfo = $this->db_model->getSelect(
	        'id,number,first_name,last_name,company_name,email,reseller_id',
	        'accounts',
	        array('id' => $account_id, 'deleted' => '0')
	    )->row_array();

	    if (empty($accountinfo)) {
	        $this->response(array(
	            'status' => false,
	            'error'  => $this->lang->line('account_not_found'),
	        ), 400);
	    }

	    $start = $postdata['start_limit'] - 1;
	    $limit = (int) $postdata['end_limit'] - (int) $start;

	    $this->db->select('
	        order_items.id as order_item_id,
	        orders.order_id as order_number,
	        orders.order_date,
	        orders.payment_status,
	        orders.reseller_id,
	        orders.accountid,
	        order_items.billing_date,
	        order_items.termination_date,
	        order_items.next_billing_date,
	        order_items.is_terminated,
	        order_items.product_id,
	        order_items.setup_fee,
	        order_items.price,
	        products.name as product_name,
	        category.name as category_name
	    ');
	    $this->db->from('orders');
	    $this->db->join('order_items', 'order_items.order_id = orders.id', 'inner');
	    $this->db->join('products', 'products.id = order_items.product_id', 'left');
	    $this->db->join('category', 'category.id = products.product_category', 'left');
	    $this->db->where('orders.accountid', $account_id);

	    if (empty($postdata['include_terminated']) || $postdata['include_terminated'] != '1') {
	        $this->db->where('order_items.is_terminated', '0');
	    }

	    $this->db->order_by('order_items.billing_date', 'desc');
	    $this->db->limit($limit, $start);

	    $result      = $this->db->get();
	    $orders_info = $result->result_array();
	    $count       = $result->num_rows();

	    if (empty($orders_info)) {
	        $this->response(array(
	            'status'      => false,
	            'error'     => $this->lang->line('no_records_found'),
	        ), 200);
	    }

	    foreach ($orders_info as $key => $orders_value) {
	        $orders_value['billing_date'] = $this->timezone->convert_to_GMT_new(
	            $orders_value['billing_date'],
	            '1',
	            $this->accountinfo['timezone_id']
	        );
	        $orders_info[$key] = $orders_value;
	    }

	    $this->response(array(
	        'total_count'     => $count,
	        'account_details' => $accountinfo,
	        'data'            => $orders_info,
	        'success'         => $this->lang->line('customer_products_list_information'),
	    ), 200);
	}

}