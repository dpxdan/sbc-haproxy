<?php

require APPPATH . '/controllers/common/account.php';

class Product extends Account
{

    protected $postdata = "";

    function __construct()
    {
        parent::__construct();
        $this->load->model('common_model');
        $this->load->model('db_model');
        $this->load->library('Form_validation');
        $this->load->library('flux_log');
        $this->load->library('flux/order');
        $this->accountinfo = $this->get_account_info();
        if ($this->accountinfo['type'] != -1 && $this->accountinfo['type'] != 1 && $this->accountinfo['type'] != 2)
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('error_invalid_key')
            ), 400);
        }
        $rawinfo = $this->post();
        $this->postdata = array();
        foreach ($rawinfo as $key => $value)
        {
            $this->postdata[$key] = $this->_xss_clean($value, TRUE);
        }
    }

    public function index()
    {
        $function = isset($this->postdata['action']) ? $this->postdata['action'] : '';
        $this->api_log->write_log('API URL : ', base_url() . "" . $_SERVER['REQUEST_URI']);
        $this->api_log->write_log('Params : ', json_encode($this->postdata));
        if ($function != '')
        {
            $function = 'product_' . $function;
            if ((int)method_exists($this, $function) > 0)
            {
                $this->$function();
            }
            else
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('unknown_method')
                ), 500);
            }
        }
        else
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('unknown_method')
            ), 400);
        }
        die;
    }

    function product_optin()
    {
        if ($this->accountinfo['type'] == '1')
        {
            if (!isset($this->postdata['product_id']) || $this->postdata['product_id'] == '')
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('product_not_found')
                ), 400);
            }
            $reseller_products_info = $this->db_model->getSelect("*", "reseller_products", array("product_id" => $this->postdata['product_id'], "account_id" => $this->accountinfo['id'], "reseller_id" => $this->accountinfo['reseller_id']))->first_row();
            if (!empty($reseller_products_info))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('product_already_optin')
                ), 400);
            }
            if ($this->accountinfo['reseller_id'] > 0)
            {
                $products_info = $this->db_model->getSelect("*", "products", array("id" => $this->postdata['product_id'], "status" => 0))->row_array();
                if ($products_info['product_category'] == 1 || $products_info['product_category'] == 2)
                {
                    $this->db->where(array('product_id' => $this->postdata['product_id'], 'is_owner' => 0));
                    $product_data = (array)$this->db->get('reseller_products')->first_row();
                    //print_r($this->db->last_query());die;
                }
            }
            else
            {
                $this->db->where(array('id' => $this->postdata['product_id'], 'can_resell' => 0, 'product_category' => 1, 'status' => 0));
                $this->db->or_where('product_category', 2);
                $product_data = (array)$this->db->get('products')->first_row();
            }
            if (empty($product_data))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('product_not_found')
                ), 400);
            }
            if ($product_data['can_resell'] == 0)
            {
                if ($this->accountinfo['is_distributor'] == 0)
                {
                    $insert_array['setup_fee'] = isset($this->postdata['setup_fee']) && $this->postdata['setup_fee'] != "" ? $this->postdata['setup_fee'] : $product_data['setup_fee'];
                    $insert_array['price'] = isset($this->postdata['setup_fee']) && $this->postdata['price'] != "" ? $this->postdata['price'] : $product_data['price'];
                }
                else
                {
                    $insert_array['setup_fee'] = $product_data['setup_fee'];
                    $insert_array['price'] = $product_data['price'];
                }
                $insert_array['product_id'] = $this->postdata['product_id'];
                $insert_array['account_id'] = $this->accountinfo['id'];
                $insert_array['reseller_id'] = $this->accountinfo['reseller_id'] > 0 ? $this->accountinfo['reseller_id'] : 0;
                $insert_array['is_owner'] = 1;
                $insert_array['is_optin'] = 0;
                $insert_array['optin_date'] = gmdate("Y-m-d H:i:s");
                $insert_array['status'] = $product_data['status'];
                $insert_array['free_minutes'] = $product_data['free_minutes'];
                $insert_array['commission'] = $product_data['commission'];
                $insert_array['modified_date'] =  gmdate("Y-m-d H:i:s");
                $insert_array['country_id'] = $product_data['country_id'];
                $insert_array['buy_cost'] = $product_data['buy_cost'];
                $insert_array['billing_days'] = $product_data['billing_days'];
                $insert_array['billing_type'] = $product_data['billing_type'];
                $this->db->insert("reseller_products", $insert_array);
            }
        }
        if (!empty($insert_array))
        {
            $this->response(array(
                'status' => true,
                'data' => $insert_array,
                'success' => $this->lang->line('product_optin')
            ), 200);
        }
        else
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('product_not_found')
            ), 400);
        }
    }

    function product_list()
    {
        $currency_id = $this->common->get_field_name('currency', 'currency', array('id' => $this->accountinfo['currency_id']));
        if (empty($this->postdata['end_limit']) || empty($this->postdata['start_limit']))
        {
            if (!($this->postdata['start_limit'] == '0' || $this->postdata['end_limit'] == '0'))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('error_param_missing') . " integer:end_limit,integer:start_limit"
                ), 400);
            }
            else
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('number_greater_zero')
                ), 400);
            }
        }
        if (!($this->postdata['start_limit'] < $this->postdata['end_limit']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('valid_start_limit')
            ), 400);
        }
        $object_where_params = $this->postdata['object_where_params'];
        $where = array();
        foreach ($object_where_params as $object_where_key => $object_where_value)
        {
            if ($object_where_value != '')
            {
                if (isset($object_where_key) && $object_where_key == 'pattern')
                {
                    $this->db->like('pattern', '^' . $object_where_params['pattern'] . '.*');
                }
                else
                {
                    if ($object_where_key == 'country_id' && $object_where_value != "")
                    {
                        if (!$this->form_validation->integer($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('invalid_country')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'buy_cost' && $object_where_value != "")
                    {
                        if (!$this->form_validation->greater_than($object_where_value, -1))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('enter_correct_buy_cost')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'setup_fee' && $object_where_value != "")
                    {
                        if (!$this->form_validation->numeric($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('enter_setup_fee')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'price' && $object_where_value != "")
                    {
                        if (!$this->form_validation->numeric($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('numeric_price')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'commission' && $object_where_value != "")
                    {
                        if (!$this->form_validation->numeric($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('numeric_commision')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'billing_days' && $object_where_value != "")
                    {
                        if (!$this->form_validation->integer($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('numeric_billing_days')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'billing_type' && $object_where_value != "")
                    {
                        if (!$this->form_validation->integer($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('enter_correct_billing_type')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'free_minutes' && $object_where_value != "")
                    {
                        if (!$this->form_validation->integer($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('numeric_free_minutes')
                            ), 400);
                        }
                    }
                    if ($object_where_key == 'status' && $object_where_value != "")
                    {
                        if (!$this->form_validation->integer($object_where_value))
                        {
                            $this->response(array(
                                'status' => false,
                                'success' =>  $this->lang->line('valid_status')
                            ), 400);
                        }
                    }
                    $where[$object_where_key] = $object_where_value;
                }
            }
        }
        if (isset($where['product_category']) && $where['product_category'] != "")
        {
            $product_category = $this->common->get_field_name('id', 'category', array('id' => $where['product_category']));
            if ($product_category == "")
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('invalid_product_category')
                ), 400);
            }
        }
        $categoryinfo = $this->db_model->getSelect("GROUP_CONCAT('''',id,'''') as id", "category", "code NOT IN ('REFILL','DID')");
        if (!empty($where))
        {
            $this->db->where($where);
        }
        $start = $this->postdata['start_limit'] - 1;
        $limit = $this->postdata['end_limit'];
        $no_of_records = (int)$limit - (int)$start;
        $this->db->limit($no_of_records, $start);
        if ($this->accountinfo['type'] == 1)
        {
            if ($categoryinfo->num_rows > 0)
            {
                $categoryinfo = $categoryinfo->result_array()[0]['id'];
                $this->db->where("product_category IN (" . $categoryinfo . ")", NULL, false);
            }
            $temp_where = "(reseller_products.is_optin = 0 OR reseller_products.is_owner=0)";
            $this->db->where($temp_where);
            $tmp_where = "(reseller_products.status = 0 OR reseller_products.status =1)";
            $this->db->where($tmp_where);
            $str_where = "(products.status = 0 OR reseller_products.is_owner=0)";
            $this->db->where($str_where);
            $this->db->where('reseller_products.account_id', $this->accountinfo['id']);
            $available_product = $this->db_model->getJionQuery('products', 'products.id,products.name,products.product_category,products.country_id,reseller_products.status as reseller_status,reseller_products.buy_cost,reseller_products.reseller_id,products.commission,reseller_products.setup_fee,reseller_products.price,reseller_products.billing_type,(CASE WHEN reseller_products.billing_type = 2 THEN "Monthly" ELSE reseller_products.billing_days END) as billing_days,reseller_products.free_minutes,products.status,products.last_modified_date,reseller_products.product_id', array('products.is_deleted' => 0), 'reseller_products', 'products.id=reseller_products.product_id', 'inner', '', '', '', '');
        }
        else
        {
            $this->db->order_by("id", "DESC");
            $where = array("is_deleted" => "0", "product_category <>" => 4);
            $available_product = $this->db_model->select("id,name,product_category,country_id,buy_cost,reseller_id,commission,setup_fee,price,billing_type,billing_days,free_minutes,status,(CASE WHEN billing_type = 2 THEN 'Monthly' ELSE  billing_days END) as billing_days", "products", $where, "", "", '', '');
        }
        $available_products = $available_product->result_array();
        $count = $available_product->num_rows();

        if (empty($available_products))
        {
            $this->response(array(
                'total_count' => 0,
                'data' => $available_products,
                'error' => $this->lang->line('no_records_found')
            ), 200);
        }
        else
        {
            foreach ($available_products as $key => $value)
            {
                if ($this->accountinfo['type'] == 1)
                {
                    unset($value['status']);
                    unset($value['commission']);
                }
                $available_products[$key] = $value;
                if ($this->accountinfo['type'] != 1)
                {
                    $available_products[$key]['retired'] = $available_products[$key]['status'];
                }
                if ($this->accountinfo['type'] != 1)
                {
                    $available_products[$key]['reseller_id'] = $available_products[$key]['reseller_id'];
                    unset($available_products[$key]['last_modified_date']);
                }

                $available_products[$key]['country_name'] = $this->common->get_field_name_country_camel("country", "countrycode", $value['country_id']);
                $available_products[$key]['product_category'] = $this->common->get_field_name("name", "category", $value['product_category']);
                $available_products[$key]['buy_cost'] = $this->common_model->to_calculate_currency($value['buy_cost'], '', $currency_id);
                $available_products[$key]['setup_fee'] = $this->common_model->to_calculate_currency($value['setup_fee'], '', $currency_id);
                $available_products[$key]['price'] = $this->common_model->to_calculate_currency($value['price'], '', $currency_id);
                $available_products[$key]['reseller_id'] = $available_products[$key]['reseller_id'];
                $available_products[$key]['billing_type'] = $this->common->get_renewal_type_category_list('billing_type', 'billing_type', $value['billing_type']);
                $available_products[$key]['status'] = $value['status'] == 0 ? "Available" : "Disabled";
                if ($available_products[$key]['reseller_id'] == '0')
                {
                    unset($available_products[$key]['reseller_id']);
                }
                unset($available_products[$key]['country_id']);
            }
            $this->response(array(
                'total_count' => $count,
                'data' => $available_products,
                'success' => $this->lang->line("product_list_information")

            ), 200);
        }
    }

    function product_read()
    {
        $postdata    = $this->postdata;
        $currency_id = $this->common->get_field_name('currency', 'currency', array('id' => $this->accountinfo['currency_id']));

        if (empty($postdata['product_id']) || !isset($postdata['product_id']))
        {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('error_param_missing') . ' integer:product_id'
            ), 400);
        }
        else
        {
            $where = array(
                'id'         => $postdata['product_id'],
                'is_deleted' => 0
            );

            if (
                $this->accountinfo['type'] != -1 && $this->accountinfo['type'] != 2 &&
                $this->accountinfo['type'] != 4 && $this->accountinfo['type'] != 5 &&
                $this->accountinfo['type'] != 6
            )
            {
                $where['reseller_id'] = $postdata['id'];
            }

            $this->db->select('*');
            $this->db->where($where);
            $product = (array)$this->db->get('products')->first_row();

            if (empty($product))
            {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('product_not_found')
                ), 400);
            }
            else
            {
                $raw_status = (int) $product['status'];

                $read_array = array(
                    'id'                        => $product['id'],
                    'name'                      => $product['name'],
                    'description'               => $product['description'],
                    'product_category'          => $this->common->get_field_name('name', 'category', $product['product_category']),
                    'country_name'              => $this->common->get_field_name_country_camel('country', 'countrycode', $product['country_id']),
                    'buy_cost'                  => $this->common_model->to_calculate_currency($product['buy_cost'],  '', $currency_id),
                    'price'                     => $this->common_model->to_calculate_currency($product['price'],     '', $currency_id),
                    'setup_fee'                 => $this->common_model->to_calculate_currency($product['setup_fee'], '', $currency_id),
                    'commission'                => $product['commission'],
                    'billing_type'              => $this->common->get_renewal_type_category_list('billing_type', 'billing_type', $product['billing_type']),
                    'billing_days'              => $product['billing_days'],
                    'free_minutes'              => $product['free_minutes'],
                    'can_resell'                => $product['can_resell'],
                    'can_purchase'              => $product['can_purchase'],
                    'applicable_for'            => $product['applicable_for'],
                    'apply_on_existing_account' => $product['apply_on_existing_account'],
                    'apply_on_rategroups'       => $product['apply_on_rategroups'],
                    'destination_rategroups'    => $product['destination_rategroups'],
                    'destination_countries'     => $product['destination_countries'],
                    'destination_calltypes'     => $product['destination_calltypes'],
                    'release_no_balance'        => $product['release_no_balance'],
                    'status'                    => $raw_status == 0 ? 'Available' : 'Disabled',
                    'reseller_id'               => $product['reseller_id'],
                    'created_by'                => $product['created_by'],
                    'creation_date'             => $product['creation_date'],
                    'last_modified_date'        => $product['last_modified_date'],
                );

                if ($this->accountinfo['type'] != 1)
                {
                    $read_array['retired'] = $raw_status;
                }

                if ($this->accountinfo['type'] == 1)
                {
                    unset($read_array['commission']);
                }

                if ($read_array['reseller_id'] == '0')
                {
                    unset($read_array['reseller_id']);
                }

                if ($product['product_category'] == 4)
                {
                    $this->db->select(
                        "dids.id                        AS did_id,
						 dids.product_id,
						 dids.number,
						 dids.status,
						 dids.country_id,
						 dids.accountid,
						 dids.parent_id,
						 dids.cost,
						 dids.monthlycost,
						 dids.call_type,
						 dids.extensions,
						 dids.reverse_rate,
						 dids.rate_group,
						 accounts.number                AS account_number,
						 accounts.first_name,
						 accounts.last_name,
						 accounts.company_name,
						 reseller_account.first_name    AS reseller_first_name,
						 reseller_account.last_name     AS reseller_last_name,
						 reseller_account.number        AS reseller_number,
						 reseller_account.company_name  AS reseller_company_name,
						 sip_devices.id                 AS sip_device_id,
						 sip_devices.username,
						 sip_devices.status             AS sip_device_status,
						 sip_devices.dir_vars,
						 sip_devices.dir_params,
						 sip_devices.codec,
						 sip_devices.reseller_id        AS sip_reseller_id,
						 sip_devices.accountid          AS sip_accountid,
						 sip_devices.id_sip_external,
						 sip_devices.creation_date      AS sip_creation_date,
						 sip_devices.last_modified_date AS sip_last_modified_date,
						 sip_profiles.name              AS sip_profile_name,
						 did_call_types.call_type       AS call_type_label",
                        FALSE
                    );
                    $this->db->from('dids');
                    $this->db->join('accounts',                     'accounts.id = dids.accountid',                                                      'left');
                    $this->db->join('accounts AS reseller_account', 'reseller_account.id = dids.parent_id',                                              'left');
                    $this->db->join('sip_devices',                  'sip_devices.accountid = dids.accountid AND sip_devices.username = dids.extensions', 'left');
                    $this->db->join('sip_profiles',                 'sip_profiles.id = sip_devices.sip_profile_id',                                      'left');
                    $this->db->join('did_call_types',               'did_call_types.call_type_code = dids.call_type',                                    'left');
                    $this->db->where('dids.product_id', $product['id']);
                    $did_row = (array)$this->db->get()->first_row();

                    if (!empty($did_row))
                    {
                        $raw_accountid    = (int) $did_row['accountid'];
                        $raw_parent_id    = (int) $did_row['parent_id'];
                        $raw_did_status   = (int) $did_row['status'];
                        $raw_call_type    = (int) $did_row['call_type'];
                        $raw_reverse_rate = (int) $did_row['reverse_rate'];

                        if ($raw_accountid == 0 && $raw_parent_id == 0 && $raw_did_status == 0)
                        {
                            $is_purchased = 'Assign Number';
                        }
                        elseif ($raw_accountid == 0 && $raw_parent_id != 0 && $raw_did_status == 0)
                        {
                            $is_purchased = 'Release(R)';
                        }
                        elseif ($raw_did_status != 0)
                        {
                            $is_purchased = 'Inactive';
                        }
                        else
                        {
                            $is_purchased = 'Release(C)';
                        }

                        if ($raw_did_status == 0)
                        {
                            $status_label = 'Active';
                        }
                        elseif ($raw_did_status == 1)
                        {
                            $status_label = 'Inactive';
                        }
                        else
                        {
                            $status_label = 'On Hold';
                        }

                        if ($product['billing_type'] == 0)
                        {
                            $billing_type_label = 'One Time';
                        }
                        elseif ($product['billing_type'] == 1)
                        {
                            $billing_type_label = 'Recurring';
                        }
                        else
                        {
                            $billing_type_label = 'Recurring Monthly';
                        }

                        if ($raw_parent_id == 0)
                        {
                            $reseller_label = 'Admin';
                        }
                        else
                        {
                            $reseller_label = trim(
                                $did_row['reseller_first_name']  . ' ' .
                                    $did_row['reseller_last_name']   . ' ' .
                                    $did_row['reseller_number']      . ' ' .
                                    $did_row['reseller_company_name']
                            );
                        }

                        $did_array = array(
                            'did_id'       => $did_row['did_id'],
                            'product_id'   => $did_row['product_id'],
                            'number'       => $did_row['number'],
                            'status'       => $status_label,
                            'country_id'   => $this->common->get_field_name_country_camel('country', 'countrycode', $did_row['country_id']),
                            'accountid'    => ($raw_accountid == 0) ? '' : $raw_accountid,
                            'cost'         => $did_row['cost'],
                            'monthlycost'  => $did_row['monthlycost'],
                            'call_type'    => $did_row['call_type_label'],
                            'reverse_rate' => ($raw_reverse_rate == 0) ? 'Active' : 'Inactive',
                            'is_purchased' => $is_purchased,
                            'billing_type' => $billing_type_label,
                            'billing_days' => $product['billing_days'],
                            'reseller_id'  => $reseller_label,
                        );

                        if ($raw_accountid != 0)
                        {
                            $did_array['company'] = trim(
                                $did_row['first_name']     . ' ' .
                                    $did_row['last_name']      . ' ' .
                                    $did_row['account_number'] . ' ' .
                                    $did_row['company_name']
                            );
                        }

                        if ($raw_reverse_rate == 0)
                        {
                            $did_array['rate_group'] = (int) $did_row['rate_group'];
                        }

                        if ($raw_call_type === 2)
                        {
                            $did_array['type']           = 'ip';
                            $did_array['destination_ip'] = $did_row['extensions'];
                        }
                        elseif ($raw_call_type === 0)
                        {
                            $did_array['type']       = 'registration';
                            $did_array['extensions'] = $did_row['extensions'];

                            if (!empty($did_row['sip_device_id']))
                            {
                                $dir_params_decoded = !empty($did_row['dir_params'])
                                    ? json_decode($did_row['dir_params'], true)
                                    : array();
                                $dir_vars_decoded = !empty($did_row['dir_vars'])
                                    ? json_decode($did_row['dir_vars'], true)
                                    : array();

                                $sip_device = array(
                                    'id'                 => $did_row['sip_device_id'],
                                    'username'           => $did_row['username'],
                                    'accountid'          => $did_row['sip_accountid'],
                                    'codec'              => $did_row['codec'],
                                    'status'             => $did_row['sip_device_status'] == '1' ? 'Inactive' : 'Active',
                                    'sip_profile_name'   => $did_row['sip_profile_name'],
                                    'id_sip_external'    => $did_row['id_sip_external'],
                                    'creation_date'      => $this->common->convert_GMT_to('', '', $did_row['sip_creation_date'],      $this->accountinfo['timezone_id']),
                                    'last_modified_date' => $this->common->convert_GMT_to('', '', $did_row['sip_last_modified_date'], $this->accountinfo['timezone_id']),
                                );

                                if ($did_row['sip_reseller_id'] != '0' && $this->accountinfo['type'] != '1')
                                {
                                    $sip_device['reseller_id'] = $did_row['sip_reseller_id'];
                                }

                                $did_array['sip_device'] = array_merge($sip_device, $dir_params_decoded, $dir_vars_decoded);
                            }
                        }
                        else
                        {
                            $did_array['type']       = 'other';
                            $did_array['extensions'] = $did_row['extensions'];
                        }

                        $read_array['did'] = $did_array;
                    }
                }

                if ($product['reseller_id'] > 0)
                {
                    $this->db->select('*');
                    $this->db->where('product_id', $product['id']);
                    $this->db->where('account_id', $product['reseller_id']);
                    $reseller_product = (array)$this->db->get('reseller_products')->first_row();

                    if (!empty($reseller_product))
                    {
                        $read_array['reseller_product'] = array(
                            'id'            => $reseller_product['id'],
                            'product_id'    => $reseller_product['product_id'],
                            'account_id'    => $reseller_product['account_id'],
                            'reseller_id'   => $reseller_product['reseller_id'],
                            'country_id'    => $reseller_product['country_id'],
                            'status'        => $reseller_product['status'],
                            'buy_cost'      => $reseller_product['buy_cost'],
                            'price'         => $reseller_product['price'],
                            'free_minutes'  => $reseller_product['free_minutes'],
                            'commission'    => $reseller_product['commission'],
                            'setup_fee'     => $reseller_product['setup_fee'],
                            'billing_days'  => $reseller_product['billing_days'],
                            'billing_type'  => $reseller_product['billing_type'],
                            'is_owner'      => $reseller_product['is_owner'],
                            'is_optin'      => $reseller_product['is_optin'],
                            'optin_date'    => $reseller_product['optin_date'],
                            'modified_date' => $reseller_product['modified_date'],
                        );
                    }
                }

                $this->response(array(
                    'status'  => true,
                    'data'    => $read_array,
                    'success' => $this->lang->line('read_product')
                ), 200);
            }
        }
    }

    function product_create()
    {
        if (!$this->form_validation->required($this->postdata['product_category']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('required_product_category')
            ), 400);
        }
        $product_category =  $this->common->get_field_name('id', 'category', array('id' => $this->postdata['product_category']));
        if (empty($product_category))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('invalid_product_category')
            ), 400);
        }
        // Package
        if ($this->postdata['product_category'] == 1)
        {
            $this->product_validation($this->postdata);
            $postdata = $this->product_package($this->postdata);
        }
        // Refill
        if ($this->postdata['product_category'] == 3)
        {
            $this->product_validation($this->postdata);
            $postdata = $this->product_refill($this->postdata);
        }
        // DID
        if ($this->postdata['product_category'] == 4)
        {
            $this->product_validation($this->postdata);
            $postdata = $this->product_did($this->postdata);
        }
        $this->db->insert("products", $postdata);
        $last_id = $this->db->insert_id();
        if ($this->postdata['email_notify'] == 1)
        {
            $postdata['email_notify'] = $this->postdata['email_notify'];
        }
        if ($this->postdata['apply_on_existing_account'] != "" && $this->postdata['apply_on_existing_account'] == 1)
        {
            $this->assign_product_to_exiting_account($postdata, $last_id);
        }
        if ($this->postdata['product_category'] == 4)
        {
            $did_array = array(
                'number' => $this->postdata['product_name'],
                'accountid' => 0,
                'parent_id' => 0,
                'connectcost' => $this->postdata['connectcost'],
                'includedseconds' => $this->postdata['includedseconds'],
                'monthlycost' => $this->postdata['monthly_fee'],
                'cost' => $this->postdata['cost_min'],
                'init_inc' => $this->postdata['init_inc'],
                'inc' => $this->postdata['inc'],
                'extensions' => '',
                'status' => $this->postdata['status'],
                'provider_id' => $this->postdata['provider_id'],
                'country_id' => $this->postdata['country_id'],
                'province' => $this->postdata['province'],
                'city' => $this->postdata['city'],
                'setup' => $this->postdata['setup_fee'],
                'maxchannels' => $this->postdata['maxchannels'],
                'call_type' => 0,
                'leg_timeout' => $this->postdata['call_timeout'],
                'product_id' => $last_id,
                'always' =>  0,
                'always_destination' =>  '',
                'user_busy' =>  0,
                'user_busy_destination' => '',
                'user_not_registered' => 0,
                'user_not_registered_destination' => '',
                'no_answer' => 0,
                'no_answer_destination' => '',
                'failover_extensions' => '',
                'failover_call_type' => 1,
                'always_vm_flag' => 1,
                'user_busy_vm_flag' => 1,
                'user_not_registered_vm_flag' => 1,
                'no_answer_vm_flag' => 1,
                'call_type_vm_flag' => 1,
                'last_modified_date' => gmdate("Y-m-d H:i:s")
            );
        }
        if ($last_id != "")
        {
            if ($this->postdata['product_category'] == 4)
            {
                $this->db->insert("dids", $did_array);
            }
            $postdata['creation_date'] = $this->common->convert_GMT_to('', '', $postdata['creation_date'], $this->accountinfo['timezone_id']);
            $postdata['last_modified_date'] = $this->common->convert_GMT_to('', '', $postdata['last_modified_date'], $this->accountinfo['timezone_id']);
            $this->response(array(
                'status' => true,
                'data' => $postdata,
                'success' => $this->lang->line('product_create')
            ), 200);
        }
    }

    function product_package($postdata)
    {
        $this->postdata = $postdata;
		if($postdata['country_id'] == '' || !isset($postdata['country_id'])){
		$postdata['country_id'] =  $this->common->get_field_name('id','countrycode', array('country' => 'BRAZIL'));
		}
		else{
		$postdata['country_id'] = $this->common->get_field_name('id','countrycode',array('id'=>$postdata['country_id']));
		if(empty($postdata['country_id'])){
		$this->response ( array (
			'status' => false,
			'error' => $this->lang->line('valid_country_id')
		), 400 );
		}
		}
        if (!$this->form_validation->required($this->postdata['country_id']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('require_country_id')
            ), 400);
        }
        else
        {
            $country_id =  $this->common->get_field_name('id', 'countrycode', array('id' => $this->postdata['country_id']));
            if (empty($country_id) && $country_id == "")
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('invalid_country')
                ), 400);
            }
        }
        if ($this->postdata['product_buy_cost'] != "")
        {
            if (!$this->form_validation->numeric($this->postdata['product_buy_cost']))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('numeric_buy_cost')
                ), 400);
            }
            if (!$this->form_validation->max_length($this->postdata['product_buy_cost'], 15))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('max_buy_cost')
                ), 400);
            }
            if (!$this->form_validation->greater_than($this->postdata['product_buy_cost'], -1))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('min_buy_cost')
                ), 400);
            }
        }

        $this->postdata['can_purchase'] = $this->postdata['can_purchase'] == "1" ? $this->postdata['can_purchase'] : 0;
        $this->postdata['can_resell'] = $this->postdata['can_resell'] == "1" ? $this->postdata['can_resell'] : 0;
        if ($this->postdata['commission'] != '' && !$this->form_validation->numeric($this->postdata['commission']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_commision')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != '' && !$this->form_validation->numeric($this->postdata['setup_fee']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        $this->postdata['billing_type'] = $this->postdata['billing_type'] == "1" ? $this->postdata['billing_type'] : 0;
        if (!$this->form_validation->required($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('billing_days_required')
            ), 400);
        }
        if (!$this->form_validation->numeric($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->max_length($this->postdata['billing_days'], 3))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('max_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->greater_than($this->postdata['billing_days'], -1))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('min_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->integer($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('integer_billing_days')
            ), 400);
        }
        $this->postdata['apply_on_existing_account'] = $this->postdata['apply_on_existing_account'] == "0" ? $this->postdata['apply_on_existing_account'] : 1;
        if (!$this->form_validation->required($this->postdata['free_minutes']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('free_minutes_required')
            ), 400);
        }
        if (!$this->form_validation->numeric($this->postdata['free_minutes']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('integer_charge_type')
            ), 400);
        }
        $this->postdata['release_no_balance'] = $this->postdata['release_no_balance'] == "0" ? $this->postdata['release_no_balance'] : 1;
        $this->postdata['status'] = $this->postdata['status'] == "1" ? $this->postdata['status'] : 0;
        $this->postdata['applicable_for'] = $this->postdata['applicable_for'] == "1" ? $this->postdata['applicable_for'] : $this->postdata['applicable_for'] == "0" ? $this->postdata['applicable_for'] : "1";
        if ($this->postdata['apply_on_rategroups'] != "")
        {
            $explode_ids = explode(',', $this->postdata['apply_on_rategroups']);
            $this->db->where_in('id', $explode_ids);
            $available_rategroups = $this->db_model->getSelect("*", "pricelists", array("status" => 0, "reseller_id" => 0))->result_array();
            unset($this->postdata['apply_on_rategroups']);
            foreach ($available_rategroups as $key => $value)
            {
                if (in_array($value['id'], $explode_ids) != FALSE)
                {
                    $this->postdata['apply_on_rategroups'] .= $value['id'] . ',';
                }
            }
            $this->postdata['apply_on_rategroups'] =  rtrim($this->postdata['apply_on_rategroups'], ',');
        }
        if ($this->postdata['commission'] != "")
        {
            if (!$this->form_validation->numeric($this->postdata['commission']))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('numeric_commision')
                ), 400);
            }
            if (!$this->form_validation->max_length($this->postdata['commission'], 15))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('max_commision')
                ), 400);
            }
            if (!$this->form_validation->greater_than($this->postdata['commission'], -1))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('min_commision')
                ), 400);
            }
        }
        $add_array['commission'] = isset($this->postdata['commission']) ? $this->postdata['commission'] : 0;
        $insert_array = array(
            'name' => $this->postdata['product_name'],
            'country_id' => $this->postdata['country_id'],
            'description' => $this->postdata['product_description'],
            'buy_cost' => $this->postdata['product_buy_cost'],
            'product_category' => $this->postdata['product_category'],
            'price' => $this->postdata['price'],
            'setup_fee' => $this->postdata['setup_fee'],
            'can_resell' => $this->postdata['can_resell'],
            'commission' => $this->postdata['commission'],
            'billing_type' => $this->postdata['billing_type'],
            'billing_days' => $this->postdata['billing_days'],
            'free_minutes' => $this->postdata['free_minutes'],
            'applicable_for' => $this->postdata['applicable_for'],
            'apply_on_existing_account' => $this->postdata['apply_on_existing_account'],
            'apply_on_rategroups' => $this->postdata['apply_on_rategroups'],
            'release_no_balance' => $this->postdata['release_no_balance'],
            'can_purchase' => $this->postdata['can_purchase'],
            'status' => $this->postdata['status'],
            'is_deleted' => 0,
            'reseller_id' => $this->accountinfo['reseller_id'],
            'created_by' => $this->accountinfo['id'],
            'creation_date' => gmdate("Y-m-d H:i:s"),
            'last_modified_date' => gmdate("Y-m-d H:i:s"),
        );
        return $insert_array;
    }

    function assign_product_to_exiting_account($productinfo, $product_id)
    {
        $productinfo['product_id'] = $product_id;
        $accountinfo = $this->accountinfo;
        $reseller_id = $accountinfo['type'] == 1 ? $accountinfo['id'] : 0;
        if ($productinfo['apply_on_existing_account'] == 1 && $productinfo['release_no_balance'] == 1 && (isset($productinfo['apply_on_rategroups']) && $productinfo['apply_on_rategroups'] > 0))
        {
            $this->db->select("*");
            $this->db->from("accounts");
            $this->db->where(array("status" => 0, "deleted" => 0, "type" => 0, "reseller_id" => $reseller_id));
            $this->db->where_in("pricelist_id", $productinfo['apply_on_rategroups']);
            $account_info = $this->db->get();
            if ($account_info->num_rows > 0)
            {
                $account_info = $account_info->result_array();
                foreach ($account_info as $key => $account)
                {
                    $customer_data = $this->db_model->getSelect("*", "accounts", array("id" => $account['id'], "status" => 0, "deleted" => 0, "type" => 0));
                    $productinfo['payment_by'] = "Account Balance";
                    $last_id = $this->order->confirm_order($productinfo, $account['id'], $accountinfo);
                    if ($customer_data->num_rows > 0)
                    {
                        $customer_data = $customer_data->result_array()[0];
                        if ((isset($productinfo['email_notify']) && $productinfo['email_notify']  == 1) && $last_id > 0)
                        {
                            $productinfo['product_category'] = ($productinfo['product_category'] == 1) ? "Package" : (($productinfo['product_category'] == 4)  ? "DID" : "Package");
                            $productinfo['next_billing_date'] = ($productinfo['billing_days'] == 0) ? gmdate('Y-m-d 23:59:59', strtotime('+10 years')) : gmdate("Y-m-d 23:59:59", strtotime("+" . ($productinfo['billing_days'] - 1) . " days"));
                            $final_array = array_merge($customer_data, $productinfo);
                            if (isset($productinfo['product_category']) && $productinfo['product_category'] == 2)
                            {
                                $final_array['quantity'] = isset($productinfo['quantity']) ? $productinfo['quantity'] : 1;
                            }
                            else
                            {
                                $final_array['quantity'] = 1;
                            }
                            $final_array['category_name'] = gettext($productinfo['product_category']);
                            $final_array['price'] = ($productinfo['setup_fee'] + $productinfo['price']);
                            $final_array['total_price'] = ($productinfo['setup_fee'] + $productinfo['price']) * ($final_array['quantity']);
                            $final_array['total_price_amount'] = ($productinfo['setup_fee'] + $productinfo['price']);
                            $this->common->mail_to_users("product_purchase", $final_array);
                        }
                    }
                }
            }
            return true;
        }
        else
        {
            if ($productinfo['apply_on_existing_account'] == 0 && $productinfo['release_no_balance'] == 0 && (isset($productinfo['apply_on_rategroups']) && $productinfo['apply_on_rategroups'] > 0))
            {
                $total_amt = $productinfo['price'] + $productinfo['setup_fee'];
                $this->db->select("*");
                $this->db->from("accounts");
                $this->db->where(array("status" => 0, "deleted" => 0, "type" => 0, "reseller_id" => $reseller_id));
                $this->db->where_in("pricelist_id", $productinfo['apply_on_rategroups']);
                $account_info = $this->db->get();

                if ($account_info->num_rows > 0)
                {
                    $account_info = $account_info->result_array();
                    foreach ($account_info as $key => $account)
                    {

                        $customer_data = $this->db_model->getSelect("*", "accounts", array("id" => $account['id'], "status" => 0, "deleted" => 0, "type" => 0));
                        if ($customer_data->num_rows > 0)
                        {
                            $customer_data = $customer_data->result_array()[0];
                        }

                        $account_balance = $account['posttoexternal'] == 1 ? $account['credit_limit'] - ($account['balance']) : $account['balance'];
                        if ($account_balance >= $total_amt)
                        {
                            $productinfo['payment_by'] = "Account Balance";
                            $last_id = $this->order->confirm_order($productinfo, $account['id'], $accountinfo);

                            if (!empty($customer_data) && isset($productinfo['email_notify']) && $productinfo['email_notify'] == 1  && $last_id  > 0)
                            {
                                $productinfo['product_category'] = ($productinfo['product_category'] == 1) ? "PACKAGE" : (($productinfo['product_category'] == 2)  ? "SUBSCRIPTION" : "DID");
                                $productinfo['next_billing_date'] = ($productinfo['billing_days'] = 0) ? gmdate('Y-m-d 23:59:59', strtotime('+10 years')) : gmdate("Y-m-d 23:59:59", strtotime("+" . ($productinfo['billing_days'] - 1) . " days"));
                                $final_array = array_merge($customer_data, $productinfo);
                                if (isset($productinfo['product_category']) && $productinfo['product_category'] == 2)
                                {
                                    $final_array['quantity'] = isset($productinfo['quantity']) ? $productinfo['quantity'] : 1;
                                }
                                else
                                {
                                    $final_array['quantity'] = 1;
                                }
                                $final_array['category_name'] = $productinfo['product_category'];
                                $final_array['price'] = ($productinfo['setup_fee'] + $productinfo['price']);
                                $final_array['total_price'] = ($productinfo['setup_fee'] + $productinfo['price']) * ($final_array['quantity']);
                                $final_array['total_price_amount'] = ($productinfo['setup_fee'] + $productinfo['price']);
                                $this->common->mail_to_users("product_purchase", $final_array);
                            }
                        }
                    }
                }
            }
        }
    }

    function product_optin_list()
    {
        $currency_id = $this->common->get_field_name('currency', 'currency', array('id' => $this->accountinfo['currency_id']));
        if (!isset($this->postdata['start_limit']) || $this->postdata['start_limit'] == "" || !isset($this->postdata['end_limit']) || $this->postdata['end_limit'] == "")
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('error_param_missing')
            ), 400);
        }
        else
        {
            if ($this->postdata['start_limit'] <= 0 || $this->postdata['end_limit'] <= 0)
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('number_greater_zero')
                ), 400);
            }
            $start = $this->postdata['start_limit'] - 1;
            $limit = $this->postdata['end_limit'];
            $object_where_params = $this->postdata['object_where_params'];
            $where = '';
            foreach ($object_where_params as $object_where_key => $object_where_value)
            {
                if ($object_where_value != '')
                {
                    $where = $object_where_key . ' = "' . $object_where_value . '" AND ';
                }
            }
            if (!empty($where))
            {
                $where = rtrim($where, "AND ");
                $this->db->where($where);
            }
            $no_of_records = (int)$limit - (int)$start;

            if ($this->accountinfo['type'] == 1)
            {
                $categoryinfo = $this->db_model->getSelect("GROUP_CONCAT('''',id,'''') as id", "category", "code NOT IN ('REFILL','DID')");
                if ($categoryinfo->num_rows > 0)
                {
                    $categoryinfo = $categoryinfo->result_array()[0]['id'];
                }
                if ($this->accountinfo["reseller_id"] > 0)
                {

                    $this->db->where("product_id NOT IN (select CONCAT(product_id) from reseller_products where is_owner = 1 and is_optin = 0 and account_id = " . $this->accountinfo['id'] . " )");
                    $this->db->where("product_category IN (" . $categoryinfo . ")", NULL, false);

                    $optin_list = $this->db_model->getJionQuery('products', 'products.id,products.name,products.product_category,products.country_id,reseller_products.status as reseller_status,reseller_products.buy_cost,reseller_products.reseller_id,products.commission,reseller_products.setup_fee,reseller_products.price as
buycost,reseller_products.price,reseller_products.billing_type,(CASE WHEN reseller_products.billing_type = 2 THEN "Monthly" ELSE reseller_products.billing_days END) as billing_days,reseller_products.free_minutes,products.status,products.last_modified_date,reseller_products.product_id', array('products.status' => 0, 'products.is_deleted' => 0, 'reseller_products.status' => 0, 'products.can_resell' => 0, 'products.can_purchase' => 0, 'reseller_products.account_id' => $this->accountinfo['reseller_id']), 'reseller_products', 'products.id=reseller_products.product_id', 'inner', '', '', '', '');
                }
                else
                {
                    $this->db->where("product_category IN (" . $categoryinfo . ")", NULL, false);
                    $this->db->where("id NOT IN (select product_id from reseller_products where is_optin = 0 and account_id = " . $this->accountinfo["id"] . " )");
                    $optin_list = $this->db_model->select("*,price as buycst,(CASE WHEN billing_type = 2 THEN 'Monthly' ELSE billing_days END) as billing_days", "products", array("status" => 0, "reseller_id" => 0, "can_resell" => 0, "can_purchase" => 0, 'products.is_deleted' => 0), "id", "ASC", '', '', "");
                }
            }
            $optin_lists = $optin_list->result_array();
            $count = $optin_list->num_rows();

            if (empty($optin_lists))
            {
                $this->response(array(
                    'total_count' => 0,
                    'data' => $optin_lists,
                    'error' => $this->lang->line('no_records_found')
                ), 200);
            }
            else
            {
                foreach ($optin_lists as $key => $value)
                {
                    if ($this->accountinfo['type'] == 1)
                    {
                        unset($value['status']);
                        unset($value['commission']);
                    }
                    $optin_lists[$key] = $value;
                    if ($this->accountinfo['type'] != 1)
                    {
                        $optin_lists[$key]['reseller_id'] = $value['reseller_id'];
                        unset($optin_lists[$key]['last_modified_date']);
                    }

                    $optin_lists[$key]['country_id'] = $this->common->get_field_name_country_camel("country", "countrycode", $value['country_id']);
                    $optin_lists[$key]['product_category'] = $this->common->get_field_name("name", "category", $value['product_category']);
                    $optin_lists[$key]['buy_cost'] = $this->common_model->to_calculate_currency($value['buy_cost'], '', $currency_id);
                    $optin_lists[$key]['setup_fee'] = $this->common_model->to_calculate_currency($value['setup_fee'], '', $currency_id);
                    $optin_lists[$key]['price'] = $this->common_model->to_calculate_currency($value['price'], '', $currency_id);
                    $optin_lists[$key]['billing_type'] = $this->common->get_renewal_type_category_list('billing_type', 'billing_type', $value['billing_type']);
                }
                $this->response(array(
                    'total_count' => $count,
                    'data' => $optin_lists,
                    'success' => $this->lang->line("product_list_information")

                ), 200);
            }
        }
    }

    function product_delete()
    {
        if (!isset($this->postdata['product_id']) || $this->postdata['product_id'] == '')
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('error_param_missing') . "integer:product_id"
            ), 400);
        }
        if (!$this->form_validation->numeric_with_comma($this->postdata['product_id']))
        {
            $this->response(array(
                'status' => false,
                'success' =>  $this->lang->line('valid_product_id')
            ), 400);
        }
        $product_id = $this->postdata['product_id'];
        unset($this->postdata['action']);

        $product_info = (array)$this->db_model->getSelect("*", "products", $where)->row_array();

        if (!empty($product_info))
        {

            if ($product_id != '')
            {
                $where = $this->db->where("id IN (" . $this->postdata['product_id'] . ") ");

                $product_id = $this->accountinfo['reseller_id'] > 0 ? $this->accountinfo['reseller_id'] : 0;
                $accountid = $this->accountinfo['type'] == 1 ? $this->accountinfo['id'] : 0;
                if ($this->accountinfo['type'] == 1 || $this->accountinfo['type'] == 5)
                {
                    $where = $this->db->where("product_id IN (" . $this->postdata['product_id'] . ")");
                    $product_info = (array)$this->db->get_where("reseller_products", $where)->result_array();
                    foreach ($product_info as $key => $value)
                    {
                        if ($value['is_owner'] == 0)
                        {
                            $this->db->where("id", $value['product_id']);
                            $this->db->update("products", array("is_deleted" => 1));
                            $affected_rows = $this->db->affected_rows();
                            $this->db->where("id", $value['id']);
                            $this->db->update("reseller_products", array("is_optin" => 1));
                        }
                        else
                        {
                            $this->db->update("reseller_products", array("is_optin" => 1));
                        }
                    }
                }
                else
                {
                    $product_info = (array)$this->db->get_where("products", $where)->result_array();
                    foreach ($product_info as $key => $value)
                    {
                        $this->db->where("id", $value['id']);
                        if ($this->accountinfo['type'] != 2)
                        {
                            $this->db->where("created_by", $this->accountinfo['id']);
                        }
                        $this->db->update("products", array("is_deleted" => 1));
                        $affected_rows = $this->db->affected_rows();
                        $this->db->where("product_id", $value['id']);
                        $this->db->update("reseller_products", array("is_optin" => 1, "modified_date" => gmdate("Y-m-d H:i:s")));
                    }
                }
            }
            if ($affected_rows == 0)
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('product_not_found')
                ), 400);
            }
            $this->response(array(
                'status' => true,
                'success' => $this->lang->line('product_delete')
            ), 200);
        }
        else
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('product_not_found')
            ), 400);
        }
    }

    function product_refill($postdata)
    {
        $this->postdata = $postdata;
        $insert_array = array(
            'name' => $this->postdata['product_name'],
            'country_id' => 0,
            'description' => $this->postdata['product_description'],
            'buy_cost' => 0,
            'product_category' => $this->postdata['product_category'],
            'price' => $this->postdata['price'],
            'setup_fee' => 0,
            'can_resell' => 0,
            'commission' => 0,
            'billing_type' => 0,
            'billing_days' => 0,
            'free_minutes' => 0,
            'applicable_for' => 0,
            'apply_on_existing_account' => 0,
            'release_no_balance' => 0,
            'can_purchase' => 0,
            'status' => $this->postdata['status'],
            'is_deleted' => 0,
            'created_by' => 1,
            'reseller_id' => $this->accountinfo['reseller_id'],
            'creation_date' => gmdate("Y-m-d H:i:s"),
            'last_modified_date' => gmdate("Y-m-d H:i:s"),
        );
        return $insert_array;
    }

    function product_validation($postdata)
    {
        $this->postdata = $postdata;
        if (!$this->form_validation->required($this->postdata['product_name']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('name_required')
            ), 400);
        }
        $this->postdata['status'] = $this->postdata['status'] == "1" ? $this->postdata['status'] : 0;
        if (!$this->form_validation->required($this->postdata['price']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_price')
            ), 400);
        }
        if (!$this->form_validation->numeric($this->postdata['price']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_valid_price')
            ), 400);
        }
    }

    function product_did($postdata)
    {
        $this->postdata = $postdata;
        if (!$this->form_validation->numeric($this->postdata['product_name']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_product_name')
            ), 400);
        }
        $did_id = $this->common->get_field_name('id', 'dids', array('number' => $postdata['product_name']));
        if ($did_id != "")
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('unique_did_number')
            ), 400);
        }
        if ($this->postdata['product_buy_cost'] != "")
        {
            if (!$this->form_validation->numeric($this->postdata['product_buy_cost']))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('numeric_buy_cost')
                ), 400);
            }
            if (!$this->form_validation->max_length($this->postdata['product_buy_cost'], 15))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('max_buy_cost')
                ), 400);
            }
            if (!$this->form_validation->greater_than($this->postdata['product_buy_cost'], -1))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('min_buy_cost')
                ), 400);
            }
        }
        if ($this->postdata['setup_fee'] != "")
        {
            if (!$this->form_validation->numeric($this->postdata['setup_fee']))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('numeric_setup_fee')
                ), 400);
            }
            if (!$this->form_validation->max_length($this->postdata['setup_fee'], 15))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('max_setup_fee')
                ), 400);
            }
            if (!$this->form_validation->greater_than($this->postdata['setup_fee'], -1))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('min_setup_fee')
                ), 400);
            }
        }
        $this->postdata['status'] = $this->postdata['status'] == 1 ? $this->postdata['status'] : 0;
        $this->postdata['billing_type'] = $this->postdata['billing_type'] == 1 ? $this->postdata['billing_type'] : 0;
        if (!$this->form_validation->required($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('billing_days_required')
            ), 400);
        }
        if (!$this->form_validation->numeric($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->max_length($this->postdata['billing_days'], 3))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('max_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->greater_than($this->postdata['billing_days'], -1))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('min_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->integer($this->postdata['billing_days']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('integer_billing_days')
            ), 400);
        }
        if ($this->postdata['connectcost'] != ""  && !$this->form_validation->numeric($this->postdata['connectcost']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_correct_correctcost')
            ), 400);
        }
        if ($this->postdata['cost_min'] != ""  && !$this->form_validation->numeric($this->postdata['cost_min']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_correct_cost_min')
            ), 400);
        }
        if ($this->postdata['includedseconds'] != ""  && !$this->form_validation->numeric($this->postdata['includedseconds']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_correct_includedseconds')
            ), 400);
        }
        if ($this->postdata['init_inc'] != ""  && !$this->form_validation->numeric($this->postdata['init_inc']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('initially_increment_number')
            ), 400);
        }
        if ($this->postdata['inc'] != ""  && !$this->form_validation->numeric($this->postdata['inc']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('inc_number')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != ""  && !$this->form_validation->numeric($this->postdata['setup_fee']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != ""  && !$this->form_validation->numeric($this->postdata['setup_fee']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != ""  && !$this->form_validation->numeric($this->postdata['setup_fee']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != ""  && !$this->form_validation->numeric($this->postdata['setup_fee']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        if ($this->postdata['leg_timeout'] != ""  && !$this->form_validation->numeric($this->postdata['leg_timeout']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_leg_timeout')
            ), 400);
        }
        if (!$this->form_validation->required($this->postdata['provider_id']))
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('enter_provider_id')
            ), 400);
        }
        $provider_id = $this->common->get_field_name('id', 'accounts', array('id' => $this->postdata['provider_id'], 'type' => 3, 'status' => 0));
        if ($provider_id == "")
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('provider_id_not_found')
            ), 400);
        }
        $insert_array = array(
            'name' => $this->postdata['product_name'],
            'country_id' => $this->postdata['country_id'],
            'product_category' => $this->postdata['product_category'],
            'buy_cost' => $this->postdata['product_buy_cost'],
            'price' => $this->postdata['price'],
            'setup_fee' => $this->postdata['setup_fee'],
            'can_resell' => 0,
            'commission' => 0,
            'billing_type' => $this->postdata['billing_type'],
            'billing_days' => $this->postdata['billing_days'],
            'free_minutes' => 0,
            'applicable_for' => 0,
            'apply_on_existing_account' => 0,
            'apply_on_rategroups' => '',
            'destination_rategroups' => '',
            'destination_countries' => '',
            'destination_calltypes' => '',
            'release_no_balance' => 0,
            'can_purchase' => 0,
            'status' => $this->postdata['status'],
            'is_deleted' => 0,
            'created_by' => 1,
            'reseller_id' => $this->accountinfo['reseller_id'],
            'creation_date' => gmdate("Y-m-d H:i:s"),
            'last_modified_date' => gmdate("Y-m-d H:i:s")
        );
        return $insert_array;
    }

    function product_update()
    {
        $postdata = $this->postdata;

        if ($this->form_validation->required($postdata['product_id']) == '')
        {
            $this->response(array(
                'status' => false,
                'error' => $this->lang->line('error_param_missing') . "integer:product_id"
            ), 400);
        }
        else
        {
            $productinfo = (array) $this->db->get_where("products", array("id" => $postdata['product_id']))->first_row();
            if (empty($productinfo))
            {
                $this->response(array(
                    'status' => false,
                    'error' => $this->lang->line('product_not_found'),
                ), 400);
            }
            $update_array = array(
                "name" => isset($postdata['product_name']) ? $postdata['product_name'] : $productinfo['product_name'],
                "description" => isset($postdata['description']) ? $postdata['description'] : $productinfo['description'],
                "buy_cost" => isset($postdata['buy_cost']) ? $postdata['buy_cost'] : $productinfo['buy_cost'],
                "product_category" => isset($postdata['product_category']) ? $postdata['product_category'] : $productinfo['product_category'],
                "price" => isset($postdata['price']) ? $postdata['price'] : $productinfo['price'],
                "setup_fee" => isset($postdata['setup_fee']) ? $postdata['setup_fee'] : $productinfo['setup_fee'],
                "can_resell" => isset($postdata['can_resell']) ? $postdata['can_resell'] : $productinfo['can_resell'],
                "billing_type" => isset($postdata['billing_type']) ? $postdata['billing_type'] : $productinfo['billing_type'],
                "billing_days" => isset($postdata['billing_days']) ? $postdata['billing_days'] : $productinfo['billing_days'],
                "free_minutes" => isset($postdata['free_minutes']) ? $postdata['free_minutes'] : $productinfo['free_minutes'],
                "applicable_for" => isset($postdata['applicable_for']) ? $postdata['applicable_for'] : $productinfo['applicable_for'],
                "apply_on_existing_account" => isset($postdata['apply_on_existing_account']) ? $postdata['apply_on_existing_account'] : $productinfo['apply_on_existing_account'],
                "apply_on_rategroups" => isset($postdata['apply_on_rategroups']) ? $postdata['apply_on_rategroups'] : $productinfo['apply_on_rategroups'],
                "release_no_balance" => isset($postdata['release_no_balance']) ? $postdata['release_no_balance'] : $productinfo['release_no_balance'],
                "can_purchase" => isset($postdata['can_purchase']) ? $postdata['can_purchase'] : $productinfo['can_purchase'],
                "status" => isset($postdata['status']) ? $postdata['status'] : $productinfo['status'],

            );
            $this->db->where('id', $this->postdata['product_id']);
            $this->db->update('products', $update_array);
            $this->response(array(
                'status' => true,
                'data' => $update_array,
                'success' => "Product updated sucessfully.",
            ), 200);
        }
    }
}
?>