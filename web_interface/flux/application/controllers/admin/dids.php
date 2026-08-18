<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negocios
//
// Copyright (C) 2026 Flux Telecom
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


require APPPATH . '/controllers/common/account.php';

class Dids extends Account
{
    protected $postdata = "";

    function __construct()
    {
        parent::__construct();
        $this->load->model('common_model');
        $this->load->model('db_model');
        $this->load->library('Form_validation');
        $this->load->library('flux/order');
        $this->load->model("did/Did_model", 'did_model');
        $this->accountinfo = $this->get_account_info();
        if ($this->accountinfo['type'] != -1 && $this->accountinfo['type'] != 2) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('error_invalid_key')
            ), 400);
        }
        $rawinfo = $this->post();
        $this->postdata = array();
        foreach ($rawinfo as $key => $value) {
            $this->postdata[$key] = $this->_xss_clean($value, TRUE);
        }
    }

    public function index()
    {
        $accountid = $this->accountinfo['id'];
        $where = array('id' => $accountid, 'deleted' => 0, 'status' => 0);
        $this->db->where($where);
        $accountinfo = (array) $this->db->get('accounts')->first_row();

        if (empty($accountinfo) || !isset($accountinfo)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('account_not_found')
            ), 400);
        }
        $accountinfo = $this->_authorize_account($accountinfo, true, true);
        $function = isset($this->postdata['action']) ? $this->postdata['action'] : '';
        if ($function != '') {
            $function = '_' . $function;
            if ((int) method_exists($this, $function) > 0) {
                $this->$function();
            } else {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('unknown_method')
                ), 400);
            }
        } else {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('unknown_method')
            ), 400);
        }
    }

    private function _apply_did_filters($object_where_params)
    {
        if (empty($object_where_params) || !is_array($object_where_params)) {
            return;
        }

        foreach ($object_where_params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            switch ($key) {
                case 'number':
                    $this->db->where('dids.number', $value);
                    break;

                case 'destination':
                case 'extensions':
                    $this->db->where('dids.extensions', $value);
                    break;

                case 'destination_ip':
                    $this->db->where('dids.call_type', 2);
                    $this->db->where('dids.extensions', $value);
                    break;

                case 'type':
                    $type_map = array('ip' => 2, 'registration' => 0);
                    if (isset($type_map[$value])) {
                        $this->db->where('dids.call_type', $type_map[$value]);
                    }
                    break;

                case 'accountid':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('enter_valid_accountid')
                        ), 400);
                    }
                    $this->db->where('dids.accountid', (int) $value);
                    break;

                case 'reseller_id':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('valid_reseller_id')
                        ), 400);
                    }
                    $this->db->where('dids.parent_id', (int) $value);
                    break;

                case 'status':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('valid_status')
                        ), 400);
                    }
                    $this->db->where('dids.status', (int) $value);
                    break;

                case 'call_type':
                    $this->db->where('did_call_types.call_type', $value);
                    break;

                case 'country_id':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('valid_country_id')
                        ), 400);
                    }
                    $this->db->where('dids.country_id', (int) $value);
                    break;
                    
                case 'reverse_rate':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('reverse_rate_numeric')
                        ), 400);
                    }
                    $this->db->where('dids.reverse_rate', (int) $value);
                    break;
                case 'rate_group':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('enter_rategroup_id')
                        ), 400);
                    }
                    $this->db->where('dids.rate_group', (int) $value);
                    break;
                case 'province':
                    $this->db->where('dids.province', $value);
                    break;

                case 'city':
                    $this->db->where('dids.city', $value);
                    break;

                case 'username':
                    $this->db->where('sip_devices.username', $value);
                    break;

                case 'sip_profile_id':
                    if (!$this->form_validation->integer($value)) {
                        $this->response(array(
                            'status'  => false,
                            'success' => $this->lang->line('valid_sip_profile_id')
                        ), 400);
                    }
                    $this->db->where('sip_devices.sip_profile_id', (int) $value);
                    break;
            }
        }
    }

    function _list()
    {
        if (empty($this->postdata['end_limit']) || empty($this->postdata['start_limit'])) {
            if (!($this->postdata['start_limit'] == '0' || $this->postdata['end_limit'] == '0')) {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('error_param_missing') . " integer:end_limit,integer:start_limit"
                ), 400);
            } else {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('number_greater_zero')
                ), 400);
            }
        }
        if (!($this->postdata['start_limit'] < $this->postdata['end_limit'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('valid_start_limit')
            ), 400);
        }

        $object_where_params = $this->postdata['object_where_params'];

        $start         = $this->postdata['start_limit'] - 1;
        $limit         = $this->postdata['end_limit'];
        $no_of_records = (int) $limit - (int) $start;

        if(is_numeric($object_where_params['reseller_id']) && $object_where_params['reseller_id'] == 1){
        $object_where_params['reseller_id'] = 0;
        }

        $this->db->select('COUNT(DISTINCT dids.id) AS total', FALSE);
        $this->db->from('dids');
        $this->db->join('sip_devices',    'sip_devices.accountid = dids.accountid AND sip_devices.username = dids.extensions', 'left');
        $this->db->join('did_call_types', 'did_call_types.call_type_code = dids.call_type',                                   'left');
        $this->_apply_did_filters($object_where_params);
        $total_count = (int) $this->db->get()->row()->total;

        $this->db->select(
            "dids.id              AS did_id_new,
             dids.product_id      AS id,
             dids.product_id      AS productid,
             dids.id              AS did_id,
             dids.number,
             dids.status,
             dids.country_id,
             dids.accountid,
             dids.parent_id,
             dids.cost,
             dids.setup,
             dids.monthlycost,
             dids.maxchannels,
             dids.leg_timeout,
             dids.init_inc,
             dids.inc,
             dids.call_type,
             dids.last_modified_date,
             dids.province,
             dids.city,
             dids.reverse_rate,
             dids.rate_group,
             CASE WHEN dids.call_type = 2  THEN dids.extensions ELSE NULL END AS destination_ip,
             CASE WHEN dids.call_type != 2 THEN dids.extensions ELSE NULL END AS extensions,
             accounts.number        AS account_number,
             accounts.first_name,
             accounts.last_name,
             accounts.company_name,
             accounts.tax_number,
             reseller_account.first_name   AS reseller_first_name,
             reseller_account.last_name    AS reseller_last_name,
             reseller_account.number       AS reseller_number,
             reseller_account.company_name AS reseller_company_name,
             sip_devices.id                AS sip_device_id,
             sip_devices.username,
             sip_devices.status            AS sip_device_status,
             sip_devices.dir_vars,
             sip_devices.dir_params,
             sip_devices.codec,
             sip_devices.sip_profile_id,
             sip_devices.reseller_id       AS sip_reseller_id,
             sip_devices.accountid         AS sip_accountid,
             sip_devices.id_sip_external,
             sip_devices.creation_date     AS sip_creation_date,
             sip_devices.last_modified_date AS sip_last_modified_date,
             sip_profiles.name             AS sip_profile_name,
             did_call_types.call_type      AS call_type_label,
             products.billing_type,
             products.billing_days",
            FALSE
        );

        $this->db->from('dids');
        $this->db->join('accounts',                     'accounts.id = dids.accountid',                                                     'left');
        $this->db->join('accounts AS reseller_account', 'reseller_account.id = dids.parent_id',                                             'left');
        $this->db->join('sip_devices',                  'sip_devices.accountid = dids.accountid AND sip_devices.username = dids.extensions', 'left');
        $this->db->join('sip_profiles',                 'sip_profiles.id = sip_devices.sip_profile_id',                                     'left');
        $this->db->join('did_call_types',               'did_call_types.call_type_code = dids.call_type',                                   'left');
        $this->db->join('products',                     'products.id = dids.product_id',                                                    'left');
        $this->_apply_did_filters($object_where_params);
        $this->db->group_by('dids.id');
        $this->db->order_by('dids.id', 'DESC');
        $this->db->limit($no_of_records, $start);

        $dids_array  = $this->db->get();
        $dids_arrays = $dids_array->result_array();

        foreach ($dids_arrays as $key => $dids_array_value) {
            $raw_accountid = (int) $dids_array_value['accountid'];
            $raw_parent_id = (int) $dids_array_value['parent_id'];
            $raw_status    = (int) $dids_array_value['status'];
            $raw_call_type = (int) $dids_array_value['call_type'];
            $raw_reverse_rate = (int) $dids_array_value['reverse_rate'];
            $raw_rate_group = (int) $dids_array_value['rate_group'];

            $dids_arrays[$key]['product_id'] = $dids_arrays[$key]['id'];
            $dids_arrays[$key]['country_id'] = $this->common->get_field_name_country_camel("country", "countrycode", $dids_arrays[$key]['country_id']);



            $dids_arrays[$key]['call_type'] = $dids_arrays[$key]['call_type_label'];
            unset($dids_arrays[$key]['call_type_label']);

            if ($raw_accountid == 0 && $raw_parent_id == 0 && $raw_status == 0) {
                $dids_arrays[$key]['is_purchased'] = "Assign Number";
            } elseif ($raw_accountid != 0 && $raw_parent_id == 0 && $raw_status == 0) {
                $dids_arrays[$key]['is_purchased'] = "Release(C)";
            } elseif ($raw_accountid == 0 && $raw_parent_id != 0 && $raw_status == 0) {
                $dids_arrays[$key]['is_purchased'] = "Release(R)";
            } elseif ($raw_accountid != 0 && $raw_parent_id != 0 && $raw_status == 0) {
                $dids_arrays[$key]['is_purchased'] = "Release(C)";
            } else {
                $dids_arrays[$key]['is_purchased'] = "Inactive";
            }

            if ($dids_arrays[$key]['billing_type'] == 0) {
                $dids_arrays[$key]['billing_type'] = "One Time";
            } elseif ($dids_arrays[$key]['billing_type'] == 1) {
                $dids_arrays[$key]['billing_type'] = "Recurring";
            } else {
                $dids_arrays[$key]['billing_type'] = "Recurring Monthly";
            }

            if ($raw_status == 0) {
                $dids_arrays[$key]['status'] = "Active";
            } elseif ($raw_status == 1) {
                $dids_arrays[$key]['status'] = "Inactive";
            } else {
                $dids_arrays[$key]['status'] = "On Hold";
            }

            if ($raw_parent_id == 0) {
                $dids_arrays[$key]['reseller_id'] = "Admin";
            } else {
                $dids_arrays[$key]['reseller_id'] = trim(
                    $dids_arrays[$key]['reseller_first_name']   . ' ' .
                    $dids_arrays[$key]['reseller_last_name']    . ' ' .
                    $dids_arrays[$key]['reseller_number']       . ' ' .
                    $dids_arrays[$key]['reseller_company_name']
                );
            }
            if ($raw_accountid == 0) {
                $dids_arrays[$key]['accountid'] = '';
            } else {
                $dids_arrays[$key]['company'] = trim(
                    $dids_arrays[$key]['first_name']     . ' ' .
                    $dids_arrays[$key]['last_name']      . ' ' .
                    $dids_arrays[$key]['account_number'] . ' ' .
                    $dids_arrays[$key]['company_name']
                );
                $dids_arrays[$key]['accountid'] = $raw_accountid;
            }

            if ($raw_call_type === 2) {

                $dids_arrays[$key]['type'] = 'ip';
                unset($dids_arrays[$key]['extensions']);

            } elseif ($raw_call_type === 0) {

                $dids_arrays[$key]['type'] = 'registration';
                unset($dids_arrays[$key]['destination_ip']);

                if (!empty($dids_arrays[$key]['sip_device_id'])) {
                    $dir_params_decoded = !empty($dids_arrays[$key]['dir_params'])
                        ? json_decode($dids_arrays[$key]['dir_params'], true)
                        : array();

                    $dir_vars_decoded = !empty($dids_arrays[$key]['dir_vars'])
                        ? json_decode($dids_arrays[$key]['dir_vars'], true)
                        : array();

                    $sip_device = array(
                        'id'                 => $dids_arrays[$key]['sip_device_id'],
                        'username'           => $dids_arrays[$key]['username'],
                        'accountid'          => $dids_arrays[$key]['sip_accountid'],
                        'codec'              => $dids_arrays[$key]['codec'],
                        'status'             => $dids_arrays[$key]['sip_device_status'] == '1' ? 'Inactive' : 'Active',
                        'sip_profile_name'   => $dids_arrays[$key]['sip_profile_name'],
                        'id_sip_external'    => $dids_arrays[$key]['id_sip_external'],
                        'creation_date'      => $this->common->convert_GMT_to('', '', $dids_arrays[$key]['sip_creation_date'],      $this->accountinfo['timezone_id']),
                        'last_modified_date' => $this->common->convert_GMT_to('', '', $dids_arrays[$key]['sip_last_modified_date'], $this->accountinfo['timezone_id']),
                    );

                    if ($dids_arrays[$key]['sip_reseller_id'] != '0' && $this->accountinfo['type'] != '1') {
                        $sip_device['reseller_id'] = $dids_arrays[$key]['sip_reseller_id'];
                    }

                    $sip_device = array_merge($sip_device, $dir_params_decoded, $dir_vars_decoded);
                    $dids_arrays[$key]['sip_device'] = $sip_device;
                }

            } else {

                $dids_arrays[$key]['type'] = 'other';
                unset($dids_arrays[$key]['destination_ip']);

            }
            
            if ($raw_reverse_rate == 0) {
                $dids_arrays[$key]['reverse_rate'] = "Active";
            } else {
                $dids_arrays[$key]['reverse_rate'] = "Inactive";
                unset($dids_arrays[$key]['rate_group']);
            }

            unset(
                $dids_arrays[$key]['sip_device_id'],
                $dids_arrays[$key]['sip_device_status'],
                $dids_arrays[$key]['dir_vars'],
                $dids_arrays[$key]['dir_params'],
                $dids_arrays[$key]['codec'],
                $dids_arrays[$key]['sip_profile_id'],
                $dids_arrays[$key]['sip_reseller_id'],
                $dids_arrays[$key]['sip_accountid'],
                $dids_arrays[$key]['id_sip_external'],
                $dids_arrays[$key]['sip_creation_date'],
                $dids_arrays[$key]['sip_last_modified_date'],
                $dids_arrays[$key]['sip_profile_name'],
                $dids_arrays[$key]['username']
            );
            unset(
                $dids_arrays[$key]['maxchannels'],
                $dids_arrays[$key]['leg_timeout'],
                $dids_arrays[$key]['parent_id'],
                $dids_arrays[$key]['setup'],
                $dids_arrays[$key]['last_modified_date'],
                $dids_arrays[$key]['inc'],
                $dids_arrays[$key]['init_inc'],
                $dids_arrays[$key]['did_id_new'],
                $dids_arrays[$key]['id'],
                $dids_arrays[$key]['productid'],
                $dids_arrays[$key]['first_name'],
                $dids_arrays[$key]['last_name'],
                $dids_arrays[$key]['account_number'],
                $dids_arrays[$key]['company_name'],
                $dids_arrays[$key]['reseller_first_name'],
                $dids_arrays[$key]['reseller_last_name'],
                $dids_arrays[$key]['reseller_number'],
                $dids_arrays[$key]['reseller_company_name']
            );
        }

        if (empty($dids_arrays)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line("no_records_found")
            ), 200);
        } else {
            $this->response(array(
                'total_count' => $total_count,
                'data'        => $dids_arrays,
                'success'     => $this->lang->line("did_list_info")
            ), 200);
        }
    }

    function _create()
    {
        if ($this->form_validation->required($this->postdata['did_number']) == '') {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('did_number_required')
            ), 400);
        }
        if ($this->form_validation->required($this->postdata['accountid']) == '') {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('accountid_required')
            ), 400);
        }
        
        if ($this->postdata['billing_type'] != "" && $this->form_validation->greater_than($this->postdata['billing_type'],'1')) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('invalid_billing_type_entry')
            ), 400);
        }
        if ($this->postdata['did_number'] != "" && !$this->form_validation->numeric($this->postdata['did_number'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_did_number')
            ), 400);
        }
        if ($this->postdata['did_number'] != "" && !$this->form_validation->is_unique_did_api($this->postdata['did_number'], 'products')) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('did_number_already_exists')
            ), 400);
        }
        $product_add_array['name'] = $this->postdata['did_number'];

        if ($this->form_validation->required($this->postdata['monthly_fee']) == '') {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('monthly_fee_required')
            ), 400);
        }
        if (!is_numeric($this->postdata['accountid']) && $this->postdata['accountid'] != "") {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_accountid')
            ), 400);
        }
        $accounts_data = $this->db_model->getSelect("*", "accounts", array("id" => $this->postdata['accountid'], "reseller_id" => 0, "type" => 0, "status" => 0, "deleted" => 0))->row_array();
        if (empty($accounts_data) || $accounts_data == "") {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('account_not_found')
            ), 400);
        }
        if ($this->form_validation->required($this->postdata['billing_days']) == '') {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('billing_days_required')
            ), 400);
        }

        if (isset($this->postdata['provider_id']) && $this->postdata['provider_id'] !== '') {
            $provider_id = $this->db_model->getSelect("*", "accounts", array(
                "id"      => $this->postdata['provider_id'],
                "status"  => 0,
                "type"    => 3,
                "deleted" => 0
            ))->row_array();
            if (empty($provider_id)) {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('provider_id_not_found')
                ), 400);
            }
            $did_add_array['provider_id'] = $this->postdata['provider_id'];
        } else {
            $did_add_array['provider_id'] = 3;
        }

        if ($this->postdata['country_id'] != "" && !$this->form_validation->numeric($this->postdata['country_id'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('valid_country_id')
            ), 400);
        }
        $product_add_array['country_id'] = isset($this->postdata['country_id']) && $this->postdata['country_id'] != ""
            ? $this->postdata['country_id']
            : 28;

        if ($this->postdata['city'] != '' && !$this->form_validation->min_length($this->postdata['city'], 1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('enter_city')
            ), 400);
        }
        if ($this->postdata['province'] != '' && !$this->form_validation->min_length($this->postdata['province'], 1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('enter_province')
            ), 400);
        }
        $did_add_array['city']     = $this->postdata['city'];
        $did_add_array['province'] = $this->postdata['province'];
        
        $product_add_array['product_category'] = 4;

        if ($this->postdata['buy_cost'] != "" && !$this->form_validation->numeric($this->postdata['buy_cost'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_buy_cost')
            ), 400);
        }
        if ($this->postdata['buy_cost'] != "" && !$this->form_validation->max_length($this->postdata['buy_cost'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_buy_cost')
            ), 400);
        }
        if ($this->postdata['buy_cost'] != "" && !$this->form_validation->greater_than($this->postdata['buy_cost'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_buy_cost')
            ), 400);
        }
        $product_add_array['buy_cost'] = isset($this->postdata['buy_cost']) ? $this->postdata['buy_cost'] : "0.00";

        if ($this->postdata['status'] != "" && !$this->form_validation->numeric($this->postdata['status'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_retired')
            ), 400);
        }
        $product_add_array['status'] = isset($this->postdata['status']) && $this->postdata['status'] != ""
            ? $this->postdata['status']
            : 0;

        if ($this->postdata['connection_cost'] != "" && !$this->form_validation->max_length($this->postdata['connection_cost'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_connectcost')
            ), 400);
        }
        if ($this->postdata['connection_cost'] != "" && !$this->form_validation->greater_than($this->postdata['connection_cost'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_connectcost')
            ), 400);
        }
        $did_add_array['connectcost'] = isset($this->postdata['connection_cost']) ? $this->postdata['connection_cost'] : "0";

        if ($this->postdata['grace_time'] != "" && !$this->form_validation->max_length($this->postdata['grace_time'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_includedseconds')
            ), 400);
        }
        if ($this->postdata['grace_time'] != "" && !$this->form_validation->greater_than($this->postdata['grace_time'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_includedseconds')
            ), 400);
        }
        $did_add_array['includedseconds'] = isset($this->postdata['grace_time']) ? $this->postdata['grace_time'] : 0;

        if ($this->postdata['cost_min'] != "" && !$this->form_validation->max_length($this->postdata['cost_min'], 10)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_cost_min')
            ), 400);
        }
        if ($this->postdata['cost_min'] != "" && !$this->form_validation->greater_than($this->postdata['cost_min'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_cost_min')
            ), 400);
        }
        $did_add_array['cost'] = isset($this->postdata['cost_min']) ? $this->postdata['cost_min'] : "0.00";

        if ($this->postdata['initial_increment'] != "" && !$this->form_validation->max_length($this->postdata['initial_increment'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_initial_increment')
            ), 400);
        }
        if ($this->postdata['initial_increment'] != "" && !$this->form_validation->greater_than($this->postdata['initial_increment'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_initial_increment')
            ), 400);
        }
        if ($this->postdata['initial_increment'] != "" && !$this->form_validation->integer($this->postdata['initial_increment'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_initial_increment')
            ), 400);
        }
        $did_add_array['init_inc'] = isset($this->postdata['initial_increment']) ? $this->postdata['initial_increment'] : "0";

        if ($this->postdata['increment'] != "" && !$this->form_validation->max_length($this->postdata['increment'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_increment')
            ), 400);
        }
        if ($this->postdata['increment'] != "" && !$this->form_validation->greater_than($this->postdata['increment'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_increment')
            ), 400);
        }
        if ($this->postdata['increment'] != "" && !$this->form_validation->integer($this->postdata['increment'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_increment')
            ), 400);
        }
        $did_add_array['inc'] = isset($this->postdata['increment']) ? $this->postdata['increment'] : "0";

        if ($this->postdata['setup_fee'] != "" && !$this->form_validation->numeric($this->postdata['setup_fee'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_setup_fee')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != "" && !$this->form_validation->max_length($this->postdata['setup_fee'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_setup_fee')
            ), 400);
        }
        if ($this->postdata['setup_fee'] != "" && !$this->form_validation->greater_than($this->postdata['setup_fee'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_setup_fee')
            ), 400);
        }
        $product_add_array['setup_fee'] = isset($this->postdata['setup_fee']) ? $this->postdata['setup_fee'] : "0.00";

        if ($this->postdata['monthly_fee'] != "" && !$this->form_validation->numeric($this->postdata['monthly_fee'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_price')
            ), 400);
        }
        if ($this->postdata['monthly_fee'] != "" && !$this->form_validation->max_length($this->postdata['monthly_fee'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_price')
            ), 400);
        }
        if ($this->postdata['monthly_fee'] != "" && !$this->form_validation->greater_than($this->postdata['monthly_fee'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_price')
            ), 400);
        }
        $product_add_array['price'] = isset($this->postdata['monthly_fee']) ? $this->postdata['monthly_fee'] : "0.00";

        if ($this->postdata['call_timeout'] != "" && !$this->form_validation->max_length($this->postdata['call_timeout'], 15)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_call_timeout')
            ), 400);
        }
        if ($this->postdata['call_timeout'] != "" && !$this->form_validation->greater_than($this->postdata['call_timeout'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_call_timeout')
            ), 400);
        }
        if ($this->postdata['call_timeout'] != "" && !$this->form_validation->integer($this->postdata['call_timeout'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_call_timeout')
            ), 400);
        }
        $did_add_array['leg_timeout'] = isset($this->postdata['call_timeout']) && $this->postdata['call_timeout'] != ""
            ? $this->postdata['call_timeout']
            : 0;

        if (!isset($this->postdata['billing_type']) || $this->postdata['billing_type'] === null || $this->postdata['billing_type'] === '') {
            $this->postdata['billing_type'] = 1;
        }

        if (!$this->form_validation->numeric($this->postdata['billing_type'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_billing_type')
            ), 400);
        }
        if (!$this->form_validation->integer($this->postdata['billing_type'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_billing_type')
            ), 400);
        }
        $product_add_array['billing_type'] = (int) $this->postdata['billing_type'];

        if ($this->postdata['billing_days'] != "" && !$this->form_validation->numeric($this->postdata['billing_days'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('numeric_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->max_length($this->postdata['billing_days'], 3)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->greater_than($this->postdata['billing_days'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_billing_days')
            ), 400);
        }
        if ($this->postdata['billing_days'] != "" && !$this->form_validation->integer($this->postdata['billing_days'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_billing_days')
            ), 400);
        }
        $product_add_array['billing_days'] = ($this->postdata['billing_type'] != 2)
            ? $this->postdata['billing_days']
            : 28;

        if ($this->postdata['concurrent_calls'] != "" && !$this->form_validation->max_length($this->postdata['concurrent_calls'], 10)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('max_concurrent_calls')
            ), 400);
        }
        if ($this->postdata['concurrent_calls'] != "" && !$this->form_validation->greater_than($this->postdata['concurrent_calls'], -1)) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('min_concurrent_calls')
            ), 400);
        }
        if ($this->postdata['concurrent_calls'] != "" && !$this->form_validation->integer($this->postdata['concurrent_calls'])) {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('integer_concurrent_calls')
            ), 400);
        }

        if (isset($this->postdata['reverse_rate']) && $this->postdata['reverse_rate'] !== '') {
            if (!$this->form_validation->integer($this->postdata['reverse_rate'])) {
                $this->response(array('status' => false, 'error' => $this->lang->line('reverse_rate_numeric')), 400);
            }
            $did_add_array['reverse_rate'] = (int) $this->postdata['reverse_rate'];
        
            if ((int) $this->postdata['reverse_rate'] === 0) {
                if (!isset($this->postdata['rate_group']) || $this->postdata['rate_group'] === '') {
                    $this->response(array('status' => false, 'error' => $this->lang->line('enter_rategroup_id')), 400);
                }
                if (!$this->form_validation->integer($this->postdata['rate_group'])) {
                    $this->response(array('status' => false, 'error' => $this->lang->line('enter_rategroup_id')), 400);
                }
                $rate_group_data = $this->db_model->getSelect("*", "pricelists", array("id" => (int) $this->postdata['rate_group'], "status" => 0))->row_array();
                if (!empty($rate_group_data)) {
                    $did_add_array['rate_group'] = $rate_group_data['id'];
                } 
                elseif (!empty($accounts_data['pricelist_id'])) {
                    $did_add_array['rate_group'] = (int) $accounts_data['pricelist_id'];
                } else {
                    $this->response(array('status' => false, 'error' => $this->lang->line('rategroup_not_found')), 400);
                }
            } else {
                $did_add_array['rate_group'] = 0;
            }
        } else {
            $did_add_array['reverse_rate'] = 1;
            $did_add_array['rate_group']   = 0;
        }
        
        $type = isset($this->postdata['type']) ? trim($this->postdata['type']) : '';
        $did_add_array['city'] = isset($this->postdata['city']) ? $this->postdata['city'] : '';
        $did_add_array['province'] = isset($this->postdata['province']) ? $this->postdata['province'] : '';

        if ($type === 'ip') {
            if ($this->form_validation->required($this->postdata['destination_ip']) == '') {
                $this->response(array(
                    'status' => false,
                    'error'  => $this->lang->line('destination_ip_required')
                ), 400);
            }
            $did_add_array['call_type']  = 2;
            $did_add_array['extensions'] = trim($this->postdata['destination_ip']);

        } elseif ($type === 'registration') {
            $username = $this->postdata['did_number'];
            $existing = $this->db_model->getSelect("id", "sip_devices", array(
                "username" => $username
            ))->row_array();

            if (!empty($existing)) {
                $username = $this->common->find_uniq_rendno('10', '', '');
            }

            $did_add_array['call_type']  = 0;
            $did_add_array['extensions'] = $username;

        } else {
            $did_add_array['call_type']  = 0;
            $did_add_array['extensions'] = '';
        }

        $product_add_array['creation_date']      = gmdate("Y-m-d H:i:s");
        $product_add_array['last_modified_date'] = gmdate("Y-m-d H:i:s");
        $product_add_array['created_by']         = $this->accountinfo['id'];
        $product_add_array['reseller_id']        = $this->accountinfo['reseller_id'];
        $this->db->insert("products", $product_add_array);
        $last_id = $this->db->insert_id();

        $did_add_array['maxchannels']        = isset($this->postdata['concurrent_calls']) ? $this->postdata['concurrent_calls'] : "0";
        $did_add_array['number']             = $product_add_array['name'];
        $did_add_array['status']             = $product_add_array['status'];
        $did_add_array['setup']              = $product_add_array['setup_fee'];
        $did_add_array['monthlycost']        = $product_add_array['price'];
        $did_add_array['country_id']         = $product_add_array['country_id'];
        $did_add_array['last_modified_date'] = gmdate("Y-m-d H:i:s");
        $did_add_array['product_id']         = $last_id;
        $this->db->insert("dids", $did_add_array);
        $last_id_did = $this->db->insert_id();

        $sip_device_info = null;
        if ($type === 'registration' && !empty($last_id_did)) {
            $password    = $this->common->generate_password();
            $digits      = 5;
            $vm_password = rand(pow(10, $digits - 1), pow(10, $digits) - 1);

            $sip_device_array = array(
                'username'           => $username,
                'sip_profile_id'     => 1,
                'reseller_id'        => $this->accountinfo['reseller_id'],
                'accountid'          => $this->postdata['accountid'],
                'dir_params'         => json_encode(array(
                    'password'                  => $password,
                    'vm-enabled'                => 'false',
                    'vm-password'               => $vm_password,
                    'vm-mailto'                 => '',
                    'vm-attach-file'            => 'false',
                    'vm-keep-local-after-email' => 'false',
                    'vm-email-all-messages'     => 'false',
                )),
                'dir_vars'           => json_encode(array(
                    'effective_caller_id_name'   => $username,
                    'effective_caller_id_number' => $did_add_array['number'],
                )),
                'codec'              => 'PCMU,PCMA',
                'status'             => 0,
                'creation_date'      => gmdate("Y-m-d H:i:s"),
                'last_modified_date' => gmdate("Y-m-d H:i:s"),
                'call_waiting'       => 0,
                'id_sip_external'    => 0,
            );
            $this->db->insert("sip_devices", $sip_device_array);
            $last_sip_id = $this->db->insert_id();

            if (!empty($last_sip_id)) {
                $final_array                  = $this->accountinfo;
                $final_array['sip_user_name'] = $username;
                $final_array['password']      = $password;
                $final_array['id']            = 0;
                $final_array['status_code']   = 306;
                $this->common->mail_to_users('create_sip_device', $final_array);

                $sip_device_info                       = $this->db_model->getSelect("*", "sip_devices", array("id" => $last_sip_id))->row_array();
                $sip_device_info['sipdevice_id']       = (string) $last_sip_id;
                $sip_device_info['dir_params']         = json_decode($sip_device_info['dir_params'], true);
                $sip_device_info['dir_vars']           = json_decode($sip_device_info['dir_vars'], true);
                $sip_device_info['creation_date']      = $this->common->convert_GMT_to('', '', $sip_device_info['creation_date'],      $this->accountinfo['timezone_id']);
                $sip_device_info['last_modified_date'] = $this->common->convert_GMT_to('', '', $sip_device_info['last_modified_date'], $this->accountinfo['timezone_id']);
            }
        }

        if (!empty($last_id_did)) {

            if ($accounts_data != "") {
                $did_result = $this->did_billing_process($accounts_data, $this->postdata['accountid'], $last_id_did);
                if ($did_result[0] == "SUCCESS") {
                    $add_array['invoice_type']      = "debit";
                    $add_array['payment_by']        = "Account Balance";
                    $add_array['charge_type']       = "DID";
                    $add_array['is_update_balance'] = "true";
                    $add_array['is_parent_billing'] = "false";
                    $add_array['product_id']        = $last_id;
                    $add_array['create_invoice']    = "true";
                    $order_id = $this->order->confirm_order($add_array, $this->postdata['accountid'], $this->accountinfo);
                    if ($order_id > 0) {
                        $this->db->where("id", $last_id);
                        $this->db->update("dids", array(
                            "accountid" => $this->postdata['accountid']
                        ));
                    } else {
                        $this->response(array(
                            'status' => false,
                            'error'  => $this->lang->line('something_wrong')
                        ), 400);
                    }
                }
            }

                $this->db->select(
                    "dids.id              AS did_id_new,
                     dids.product_id      AS id,
                     dids.product_id      AS productid,
                     dids.id              AS did_id,
                     dids.number,
                     dids.status,
                     dids.country_id,
                     dids.accountid,
                     dids.parent_id,
                     dids.cost,
                     dids.setup,
                     dids.monthlycost,
                     dids.maxchannels,
                     dids.leg_timeout,
                     dids.init_inc,
                     dids.inc,
                     dids.call_type,
                     dids.last_modified_date,
                     dids.province,
                     dids.city,
                     dids.reverse_rate,
                     dids.rate_group,
                     CASE WHEN dids.call_type = 2  THEN dids.extensions ELSE NULL END AS destination_ip,
                     CASE WHEN dids.call_type != 2 THEN dids.extensions ELSE NULL END AS extensions,
                     accounts.number        AS account_number,
                     accounts.first_name,
                     accounts.last_name,
                     accounts.company_name,
                     accounts.tax_number,
                     reseller_account.first_name   AS reseller_first_name,
                     reseller_account.last_name    AS reseller_last_name,
                     reseller_account.number       AS reseller_number,
                     reseller_account.company_name AS reseller_company_name,
                     sip_devices.id                AS sip_device_id,
                     sip_devices.username,
                     sip_devices.status            AS sip_device_status,
                     sip_devices.dir_vars,
                     sip_devices.dir_params,
                     sip_devices.codec,
                     sip_devices.sip_profile_id,
                     sip_devices.reseller_id       AS sip_reseller_id,
                     sip_devices.accountid         AS sip_accountid,
                     sip_devices.id_sip_external,
                     sip_devices.creation_date     AS sip_creation_date,
                     sip_devices.last_modified_date AS sip_last_modified_date,
                     sip_profiles.name             AS sip_profile_name,
                     did_call_types.call_type      AS call_type_label,
                     products.billing_type,
                     products.billing_days",
                    FALSE
                );
                $this->db->from('dids');
                $this->db->join('accounts',                     'accounts.id = dids.accountid',                                                     'left');
                $this->db->join('accounts AS reseller_account', 'reseller_account.id = dids.parent_id',                                             'left');
                $this->db->join('sip_devices',                  'sip_devices.accountid = dids.accountid AND sip_devices.username = dids.extensions', 'left');
                $this->db->join('sip_profiles',                 'sip_profiles.id = sip_devices.sip_profile_id',                                     'left');
                $this->db->join('did_call_types',               'did_call_types.call_type_code = dids.call_type',                                   'left');
                $this->db->join('products',                     'products.id = dids.product_id',                                                    'left');
                $this->db->where('dids.id', $last_id_did);
                $did_info = $this->db->get()->row_array();
        
                if (empty($did_info)) {
                    $this->response(array(
                        'status' => false,
                        'error'  => $this->lang->line('did_not_found')
                    ), 400);
                }
        
                $raw_accountid    = (int) $did_info['accountid'];
                $raw_parent_id    = (int) $did_info['parent_id'];
                $raw_status       = (int) $did_info['status'];
                $raw_call_type    = (int) $did_info['call_type'];
                $raw_reverse_rate = (int) $did_info['reverse_rate'];
        
                $did_info['product_id'] = $did_info['id'];
                $did_info['country_id'] = $this->common->get_field_name_country_camel("country", "countrycode", $did_info['country_id']);
        
                $did_info['call_type'] = $did_info['call_type_label'];
                unset($did_info['call_type_label']);
        
                if ($raw_accountid == 0 && $raw_parent_id == 0 && $raw_status == 0) {
                    $did_info['is_purchased'] = "Assign Number";
                } elseif ($raw_accountid != 0 && $raw_parent_id == 0 && $raw_status == 0) {
                    $did_info['is_purchased'] = "Release(C)";
                } elseif ($raw_accountid == 0 && $raw_parent_id != 0 && $raw_status == 0) {
                    $did_info['is_purchased'] = "Release(R)";
                } elseif ($raw_accountid != 0 && $raw_parent_id != 0 && $raw_status == 0) {
                    $did_info['is_purchased'] = "Release(C)";
                } else {
                    $did_info['is_purchased'] = "Inactive";
                }
        
                if ($did_info['billing_type'] == 0) {
                    $did_info['billing_type'] = "One Time";
                } elseif ($did_info['billing_type'] == 1) {
                    $did_info['billing_type'] = "Recurring";
                } else {
                    $did_info['billing_type'] = "Recurring";
                }
        
                if ($raw_status == 0) {
                    $did_info['status'] = "Active";
                } elseif ($raw_status == 1) {
                    $did_info['status'] = "Inactive";
                } else {
                    $did_info['status'] = "On Hold";
                }
        
                if ($raw_parent_id == 0) {
                    $did_info['reseller_id'] = "Admin";
                } else {
                    $did_info['reseller_id'] = trim(
                        $did_info['reseller_first_name']   . ' ' .
                        $did_info['reseller_last_name']    . ' ' .
                        $did_info['reseller_number']       . ' ' .
                        $did_info['reseller_company_name']
                    );
                }
        
                if ($raw_accountid == 0) {
                    $did_info['accountid'] = '';
                } else {
                    $did_info['company'] = trim(
                        $did_info['first_name']     . ' ' .
                        $did_info['last_name']      . ' ' .
                        $did_info['account_number'] . ' ' .
                        $did_info['company_name']
                    );
                    $did_info['accountid'] = $raw_accountid;
                }
        
                if ($raw_call_type === 2) {
        
                    $did_info['type'] = 'ip';
                    unset($did_info['extensions']);
        
                } elseif ($raw_call_type === 0) {
        
                    $did_info['type'] = 'registration';
                    unset($did_info['destination_ip']);
        
                    if (!empty($did_info['sip_device_id'])) {
                        $dir_params_decoded = !empty($did_info['dir_params'])
                            ? json_decode($did_info['dir_params'], true)
                            : array();
        
                        $dir_vars_decoded = !empty($did_info['dir_vars'])
                            ? json_decode($did_info['dir_vars'], true)
                            : array();
        
                        $sip_device = array(
                            'id'                 => $did_info['sip_device_id'],
                            'username'           => $did_info['username'],
                            'accountid'          => $did_info['sip_accountid'],
                            'codec'              => $did_info['codec'],
                            'status'             => $did_info['sip_device_status'] == '1' ? 'Inactive' : 'Active',
                            'sip_profile_name'   => $did_info['sip_profile_name'],
                            'id_sip_external'    => $did_info['id_sip_external'],
                            'creation_date'      => $this->common->convert_GMT_to('', '', $did_info['sip_creation_date'],      $this->accountinfo['timezone_id']),
                            'last_modified_date' => $this->common->convert_GMT_to('', '', $did_info['sip_last_modified_date'], $this->accountinfo['timezone_id']),
                        );
        
                        if ($did_info['sip_reseller_id'] != '0' && $this->accountinfo['type'] != '1') {
                            $sip_device['reseller_id'] = $did_info['sip_reseller_id'];
                        }
        
                        $sip_device = array_merge($sip_device, $dir_params_decoded, $dir_vars_decoded);
                        $did_info['sip_device'] = $sip_device;
                    }
        
                } else {
        
                    $did_info['type'] = 'other';
                    unset($did_info['destination_ip']);
        
                }
        
                if ($raw_reverse_rate == 0) {
                    $did_info['reverse_rate'] = "Active";
                    $did_info['rate_group']   = $this->common->get_field_name('name', "pricelists", array(
                        'id' => $did_info['rate_group']
                    ));
                } else {
                    $did_info['reverse_rate'] = "Inactive";
                    unset($did_info['rate_group']);
                }
        
                unset(
                    $did_info['sip_device_id'],
                    $did_info['sip_device_status'],
                    $did_info['dir_vars'],
                    $did_info['dir_params'],
                    $did_info['codec'],
                    $did_info['sip_profile_id'],
                    $did_info['sip_reseller_id'],
                    $did_info['sip_accountid'],
                    $did_info['id_sip_external'],
                    $did_info['sip_creation_date'],
                    $did_info['sip_last_modified_date'],
                    $did_info['sip_profile_name'],
                    $did_info['username']
                );
                unset(
                    $did_info['maxchannels'],
                    $did_info['leg_timeout'],
                    $did_info['parent_id'],
                    $did_info['setup'],
                    $did_info['last_modified_date'],
                    $did_info['inc'],
                    $did_info['init_inc'],
                    $did_info['did_id_new'],
                    $did_info['id'],
                    $did_info['productid'],
                    $did_info['first_name'],
                    $did_info['last_name'],
                    $did_info['account_number'],
                    $did_info['company_name'],
                    $did_info['reseller_first_name'],
                    $did_info['reseller_last_name'],
                    $did_info['reseller_number'],
                    $did_info['reseller_company_name']
                );
        
                $this->response(array(
                    'status'  => true,
                    'data'    => $did_info,
                    'success' => $this->lang->line('did_create')
                ), 200);

        } 
        else {
            $this->response(array(
                'status' => false,
                'error'  => $this->lang->line('did_not_found')
            ), 400);
        }
    }
    
	function _update()
	{
		if (!isset($this->postdata['did_id']) || $this->postdata['did_id'] == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_param_missing') . " integer:did_id"
			), 400);
		}
		if (!$this->form_validation->numeric($this->postdata['did_id'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('numeric_did_id')
			), 400);
		}
	
		$dids_data = $this->db_model->getSelect(
			"*", "dids", array("id" => (int) $this->postdata['did_id'])
		)->row_array();
	
		if (empty($dids_data)) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('did_not_found')
			), 400);
		}
	
		$did_id     = $dids_data['id'];
		$product_id = $dids_data['product_id'];
	
		$did_update_array     = array();
		$product_update_array = array();
	
		if (isset($this->postdata['status']) && $this->postdata['status'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['status'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_retired')
				), 400);
			}
			$did_update_array['status']     = (int) $this->postdata['status'];
			$product_update_array['status'] = (int) $this->postdata['status'];
		}
	
		if (isset($this->postdata['country_id']) && $this->postdata['country_id'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['country_id'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('valid_country_id')
				), 400);
			}
			$did_update_array['country_id']     = (int) $this->postdata['country_id'];
			$product_update_array['country_id'] = (int) $this->postdata['country_id'];
		}
		if ($this->postdata['billing_type'] != "" && $this->form_validation->greater_than($this->postdata['billing_type'],'1')) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('invalid_billing_type_entry')
			), 400);
		}
		if (isset($this->postdata['city']) && $this->postdata['city'] !== '') {
			if ($this->form_validation->alpha($this->postdata['city']) != '1') {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('enter_city')
				), 400);
			}
			$did_update_array['city'] = $this->postdata['city'];
		}
	
		if (isset($this->postdata['province']) && $this->postdata['province'] !== '') {
			if ($this->form_validation->alpha($this->postdata['province']) != '1') {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('enter_province')
				), 400);
			}
			$did_update_array['province'] = $this->postdata['province'];
		}
	
		if (isset($this->postdata['buy_cost']) && $this->postdata['buy_cost'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['buy_cost'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_buy_cost')
				), 400);
			}
			if (!$this->form_validation->max_length($this->postdata['buy_cost'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_buy_cost')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['buy_cost'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_buy_cost')
				), 400);
			}
			$product_update_array['buy_cost'] = $this->postdata['buy_cost'];
		}
	
		if (isset($this->postdata['connection_cost']) && $this->postdata['connection_cost'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['connection_cost'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_connectcost')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['connection_cost'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_connectcost')
				), 400);
			}
			$did_update_array['connectcost'] = $this->postdata['connection_cost'];
		}
	
		if (isset($this->postdata['grace_time']) && $this->postdata['grace_time'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['grace_time'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_includedseconds')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['grace_time'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_includedseconds')
				), 400);
			}
			$did_update_array['includedseconds'] = $this->postdata['grace_time'];
		}
	
		if (isset($this->postdata['cost_min']) && $this->postdata['cost_min'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['cost_min'], 10)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_cost_min')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['cost_min'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_cost_min')
				), 400);
			}
			$did_update_array['cost'] = $this->postdata['cost_min'];
		}
	
		if (isset($this->postdata['initial_increment']) && $this->postdata['initial_increment'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['initial_increment'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_initial_increment')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['initial_increment'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_initial_increment')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['initial_increment'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_initial_increment')
				), 400);
			}
			$did_update_array['init_inc'] = $this->postdata['initial_increment'];
		}
	
		if (isset($this->postdata['increment']) && $this->postdata['increment'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['increment'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_increment')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['increment'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_increment')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['increment'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_increment')
				), 400);
			}
			$did_update_array['inc'] = $this->postdata['increment'];
		}
	
		if (isset($this->postdata['setup_fee']) && $this->postdata['setup_fee'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['setup_fee'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_setup_fee')
				), 400);
			}
			if (!$this->form_validation->max_length($this->postdata['setup_fee'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_setup_fee')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['setup_fee'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_setup_fee')
				), 400);
			}
			$did_update_array['setup']         = $this->postdata['setup_fee'];
			$product_update_array['setup_fee'] = $this->postdata['setup_fee'];
		}
	
		if (isset($this->postdata['monthly_fee']) && $this->postdata['monthly_fee'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['monthly_fee'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_price')
				), 400);
			}
			if (!$this->form_validation->max_length($this->postdata['monthly_fee'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_price')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['monthly_fee'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_price')
				), 400);
			}
			$did_update_array['monthlycost'] = $this->postdata['monthly_fee'];
			$product_update_array['price']   = $this->postdata['monthly_fee'];
		}
	
		if (isset($this->postdata['call_timeout']) && $this->postdata['call_timeout'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['call_timeout'], 15)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_call_timeout')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['call_timeout'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_call_timeout')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['call_timeout'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_call_timeout')
				), 400);
			}
			$did_update_array['leg_timeout'] = (int) $this->postdata['call_timeout'];
		}
		if (isset($this->postdata['billing_type']) && $this->postdata['billing_type'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['billing_type'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_billing_type')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['billing_type'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_billing_type')
				), 400);
			}
			$product_update_array['billing_type'] = (int) $this->postdata['billing_type'];
		}
		if (isset($this->postdata['billing_days']) && $this->postdata['billing_days'] !== '') {
			if (!$this->form_validation->numeric($this->postdata['billing_days'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('numeric_billing_days')
				), 400);
			}
			if (!$this->form_validation->max_length($this->postdata['billing_days'], 3)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_billing_days')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['billing_days'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_billing_days')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['billing_days'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_billing_days')
				), 400);
			}
			$billing_type_effective = isset($product_update_array['billing_type'])
				? $product_update_array['billing_type']
				: (int) $this->db_model->getSelect("billing_type", "products", array("id" => $product_id))->row()->billing_type;
	
			$product_update_array['billing_days'] = ($billing_type_effective != 2)
				? (int) $this->postdata['billing_days']
				: 28;
		}
	
		if (isset($this->postdata['concurrent_calls']) && $this->postdata['concurrent_calls'] !== '') {
			if (!$this->form_validation->max_length($this->postdata['concurrent_calls'], 10)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('max_concurrent_calls')
				), 400);
			}
			if (!$this->form_validation->greater_than($this->postdata['concurrent_calls'], -1)) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('min_concurrent_calls')
				), 400);
			}
			if (!$this->form_validation->integer($this->postdata['concurrent_calls'])) {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('integer_concurrent_calls')
				), 400);
			}
			$did_update_array['maxchannels'] = (int) $this->postdata['concurrent_calls'];
		}
		
		if (isset($this->postdata['reverse_rate']) && $this->postdata['reverse_rate'] !== '') {
		    if (!$this->form_validation->integer($this->postdata['reverse_rate'])) {
		        $this->response(array('status' => false, 'error' => $this->lang->line('reverse_rate_numeric')), 400);
		    }
		    $did_update_array['reverse_rate'] = (int) $this->postdata['reverse_rate'];
		
		    if ((int) $this->postdata['reverse_rate'] === 0) {
		        if (!isset($this->postdata['rate_group']) || $this->postdata['rate_group'] === '') {
		            $this->response(array('status' => false, 'error' => $this->lang->line('enter_rategroup_id')), 400);
		        }
		        if (!$this->form_validation->integer($this->postdata['rate_group'])) {
		            $this->response(array('status' => false, 'error' => $this->lang->line('enter_rategroup_id')), 400);
		        }
		        $rate_group_data = $this->db_model->getSelect("*", "pricelists", array("id" => (int) $this->postdata['rate_group'], "status" => 0))->row_array();
		        if (!empty($rate_group_data)) {
		            $did_update_array['rate_group'] = $rate_group_data['id'];
		        } 
		        elseif (!empty($dids_data['accountid'])) {
		            $account_pricelist = $this->db_model->getSelect("pricelist_id", "accounts", array("id" => (int) $dids_data['accountid']))->row_array();
		            if (!empty($account_pricelist['pricelist_id'])) {
		                $did_update_array['rate_group'] = (int) $account_pricelist['pricelist_id'];
		            } 
		            else {
		                $this->response(array('status' => false, 'error' => $this->lang->line('rategroup_not_found')), 400);
		            }
		        } 
		        else {
		            $this->response(array('status' => false, 'error' => $this->lang->line('rategroup_not_found')), 400);
		        }
		    } 
		    else {
		        $did_update_array['rate_group'] = 0;
		    }
		}
		
		$type = isset($this->postdata['type']) ? trim($this->postdata['type']) : '';
	
		if ($type !== '') {
			if ($type === 'ip') {
				if (!isset($this->postdata['destination_ip']) || trim($this->postdata['destination_ip']) == '') {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('destination_ip_required')
					), 400);
				}
	
				if ((int) $dids_data['call_type'] === 0 && $dids_data['extensions'] != '') {
					$this->db->where('username', $dids_data['extensions']);
					$this->db->update('sip_devices', array('status' => 1));
				}
	
				$did_update_array['call_type']  = 2;
				$did_update_array['extensions'] = trim($this->postdata['destination_ip']);
	
			} elseif ($type === 'registration') {
				if (!isset($this->postdata['sipdevice_id']) || trim($this->postdata['sipdevice_id']) == '') {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('sipdevice_id_required')
					), 400);
				}
				if (!$this->form_validation->integer($this->postdata['sipdevice_id'])) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('numeric_did_id')
					), 400);
				}
	
				$sip_device = $this->db_model->getSelect("*", "sip_devices", array(
					"id"        => (int) $this->postdata['sipdevice_id'],
					"accountid" => (int) $dids_data['accountid'],
					"status"    => 0
				))->row_array();
	
				if (empty($sip_device)) {
					$this->response(array(
						'status' => false,
						'error'  => $this->lang->line('sip_device_not_found')
					), 400);
				}
	
				$did_update_array['call_type']  = 0;
				$did_update_array['extensions'] = $sip_device['username'];
	
			} else {
				$this->response(array(
					'status' => false,
					'error'  => $this->lang->line('invalid_call_type')
				), 400);
			}
		}
	
		if (empty($did_update_array) && empty($product_update_array)) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_param_missing') . " no updatable fields provided"
			), 400);
		}
	
		$did_update_array['last_modified_date'] = gmdate("Y-m-d H:i:s");
	
		$this->db->where("id", $did_id);
		$this->db->update("dids", $did_update_array);
	
		if (!empty($product_update_array)) {
			$product_update_array['last_modified_date'] = gmdate("Y-m-d H:i:s");
			$this->db->where("id", $product_id);
			$this->db->update("products", $product_update_array);
		}

		$this->db->select(
			"dids.id              AS did_id_new,
			 dids.product_id      AS id,
			 dids.product_id      AS productid,
			 dids.id              AS did_id,
			 dids.number,
			 dids.status,
			 dids.country_id,
			 dids.accountid,
			 dids.parent_id,
			 dids.cost,
			 dids.setup,
			 dids.monthlycost,
			 dids.maxchannels,
			 dids.leg_timeout,
			 dids.init_inc,
			 dids.inc,
			 dids.call_type,
			 dids.last_modified_date,
			 dids.province,
			 dids.city,
			 dids.reverse_rate,
			 dids.rate_group,
			 CASE WHEN dids.call_type = 2  THEN dids.extensions ELSE NULL END AS destination_ip,
			 CASE WHEN dids.call_type != 2 THEN dids.extensions ELSE NULL END AS extensions,
			 accounts.number        AS account_number,
			 accounts.first_name,
			 accounts.last_name,
			 accounts.company_name,
			 accounts.tax_number,
			 reseller_account.first_name   AS reseller_first_name,
			 reseller_account.last_name    AS reseller_last_name,
			 reseller_account.number       AS reseller_number,
			 reseller_account.company_name AS reseller_company_name,
			 sip_devices.id                AS sip_device_id,
			 sip_devices.username,
			 sip_devices.status            AS sip_device_status,
			 sip_devices.dir_vars,
			 sip_devices.dir_params,
			 sip_devices.codec,
			 sip_devices.sip_profile_id,
			 sip_devices.reseller_id       AS sip_reseller_id,
			 sip_devices.accountid         AS sip_accountid,
			 sip_devices.id_sip_external,
			 sip_devices.creation_date     AS sip_creation_date,
			 sip_devices.last_modified_date AS sip_last_modified_date,
			 sip_profiles.name             AS sip_profile_name,
			 did_call_types.call_type      AS call_type_label,
			 products.billing_type,
			 products.billing_days",
			FALSE
		);
		$this->db->from('dids');
		$this->db->join('accounts',                     'accounts.id = dids.accountid',                                                     'left');
		$this->db->join('accounts AS reseller_account', 'reseller_account.id = dids.parent_id',                                             'left');
		$this->db->join('sip_devices',                  'sip_devices.accountid = dids.accountid AND sip_devices.username = dids.extensions', 'left');
		$this->db->join('sip_profiles',                 'sip_profiles.id = sip_devices.sip_profile_id',                                     'left');
		$this->db->join('did_call_types',               'did_call_types.call_type_code = dids.call_type',                                   'left');
		$this->db->join('products',                     'products.id = dids.product_id',                                                    'left');
		$this->db->where('dids.id', $did_id);
		$did_info = $this->db->get()->row_array();
	
		if (empty($did_info)) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('did_not_found')
			), 400);
		}
	
		$raw_accountid = (int) $did_info['accountid'];
		$raw_parent_id = (int) $did_info['parent_id'];
		$raw_status    = (int) $did_info['status'];
		$raw_call_type = (int) $did_info['call_type'];
		$raw_reverse_rate = (int) $did_info['reverse_rate'];
		
		if ($raw_reverse_rate === 0) {
		    $did_info['reverse_rate'] = "Active";
		} else {
		    $did_info['reverse_rate'] = "Inactive";
		    unset($did_info['rate_group']);
		}
		
		$did_info['product_id'] = $did_info['id'];
		$did_info['country_id'] = $this->common->get_field_name_country_camel("country", "countrycode", $did_info['country_id']);
	
		$did_info['call_type'] = $did_info['call_type_label'];
		unset($did_info['call_type_label']);
	
		if ($raw_accountid == 0 && $raw_parent_id == 0 && $raw_status == 0) {
			$did_info['is_purchased'] = "Assign Number";
		} elseif ($raw_accountid != 0 && $raw_parent_id == 0 && $raw_status == 0) {
			$did_info['is_purchased'] = "Release(C)";
		} elseif ($raw_accountid == 0 && $raw_parent_id != 0 && $raw_status == 0) {
			$did_info['is_purchased'] = "Release(R)";
		} elseif ($raw_accountid != 0 && $raw_parent_id != 0 && $raw_status == 0) {
			$did_info['is_purchased'] = "Release(C)";
		} else {
			$did_info['is_purchased'] = "Inactive";
		}
	
		if ($did_info['billing_type'] == 0) {
			$did_info['billing_type'] = "One Time";
		} elseif ($did_info['billing_type'] == 1) {
			$did_info['billing_type'] = "Recurring";
		} else {
			$did_info['billing_type'] = "Recurring";
		}
	
		if ($raw_status == 0) {
			$did_info['status'] = "Active";
		} elseif ($raw_status == 1) {
			$did_info['status'] = "Inactive";
		} else {
			$did_info['status'] = "On Hold";
		}
	
		if ($raw_parent_id == 0) {
			$did_info['reseller_id'] = "Admin";
		} else {
			$did_info['reseller_id'] = trim(
				$did_info['reseller_first_name']   . ' ' .
				$did_info['reseller_last_name']    . ' ' .
				$did_info['reseller_number']       . ' ' .
				$did_info['reseller_company_name']
			);
		}
		if ($raw_accountid == 0) {
			$did_info['accountid'] = '';
		} else {
			$did_info['company'] = trim(
				$did_info['first_name']     . ' ' .
				$did_info['last_name']      . ' ' .
				$did_info['account_number'] . ' ' .
				$did_info['company_name']
			);
			$did_info['accountid'] = $raw_accountid;
		}
	
		if ($raw_call_type === 2) {
	
			$did_info['type'] = 'ip';
			unset($did_info['extensions']);
	
		} elseif ($raw_call_type === 0) {
	
			$did_info['type'] = 'registration';
			unset($did_info['destination_ip']);
	
			if (!empty($did_info['sip_device_id'])) {
				$dir_params_decoded = !empty($did_info['dir_params'])
					? json_decode($did_info['dir_params'], true)
					: array();
	
				$dir_vars_decoded = !empty($did_info['dir_vars'])
					? json_decode($did_info['dir_vars'], true)
					: array();
	
				$sip_device = array(
					'id'                 => $did_info['sip_device_id'],
					'username'           => $did_info['username'],
					'accountid'          => $did_info['sip_accountid'],
					'codec'              => $did_info['codec'],
					'status'             => $did_info['sip_device_status'] == '1' ? 'Inactive' : 'Active',
					'sip_profile_name'   => $did_info['sip_profile_name'],
					'id_sip_external'    => $did_info['id_sip_external'],
					'creation_date'      => $this->common->convert_GMT_to('', '', $did_info['sip_creation_date'],      $this->accountinfo['timezone_id']),
					'last_modified_date' => $this->common->convert_GMT_to('', '', $did_info['sip_last_modified_date'], $this->accountinfo['timezone_id']),
				);
	
				if ($did_info['sip_reseller_id'] != '0' && $this->accountinfo['type'] != '1') {
					$sip_device['reseller_id'] = $did_info['sip_reseller_id'];
				}
	
				$sip_device            = array_merge($sip_device, $dir_params_decoded, $dir_vars_decoded);
				$did_info['sip_device'] = $sip_device;
			}
	
		} else {
	
			$did_info['type'] = 'other';
			unset($did_info['destination_ip']);
	
		}
	
		unset(
			$did_info['sip_device_id'],
			$did_info['sip_device_status'],
			$did_info['dir_vars'],
			$did_info['dir_params'],
			$did_info['codec'],
			$did_info['sip_profile_id'],
			$did_info['sip_reseller_id'],
			$did_info['sip_accountid'],
			$did_info['id_sip_external'],
			$did_info['sip_creation_date'],
			$did_info['sip_last_modified_date'],
			$did_info['sip_profile_name'],
			$did_info['username']
		);
		unset(
			$did_info['maxchannels'],
			$did_info['leg_timeout'],
			$did_info['parent_id'],
			$did_info['setup'],
			$did_info['last_modified_date'],
			$did_info['inc'],
			$did_info['init_inc'],
			$did_info['did_id_new'],
			$did_info['id'],
			$did_info['productid'],
			$did_info['first_name'],
			$did_info['last_name'],
			$did_info['account_number'],
			$did_info['company_name'],
			$did_info['reseller_first_name'],
			$did_info['reseller_last_name'],
			$did_info['reseller_number'],
			$did_info['reseller_company_name']
		);
	
		$this->response(array(
			'status'  => true,
			'data'    => $did_info,
			'success' => $this->lang->line('did_update')
		), 200);
	}

	function _assign()
	{
		if(!$this->form_validation->required($this->postdata['did_id']) ){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('did_id_required' ) 
			 ), 400 );
		}
		if(!$this->form_validation->required($this->postdata['accountid']) ){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('accountid_required' ) 
			), 400 );
		}
		if(!is_numeric($this->postdata['did_id']) && $this->postdata['did_id'] != ""){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('numeric_did_id') 
			), 400 );
		}
		if(!is_numeric($this->postdata['accountid']) && $this->postdata['accountid'] != ""){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('numeric_accountid') 
			), 400 );
		}
		$accounts_data = $this->db_model->getSelect ("*", "accounts", array ("id" => $this->postdata['accountid'] , "reseller_id" => 0, "type" => 0,"status" => 0,"deleted" => 0))->row_array();
		if (empty($accounts_data) || $accounts_data == "") {
				$this->response(array(
					'status' =>false,
					'error' => $this->lang->line('account_not_found')
				), 400);
		} 
		$dids_data = $this->db_model->getSelect ("*", "dids", array ("product_id" => $this->postdata['did_id'] , "accountid" => 0,"parent_id" => 0, "status" => 0))->row_array();
		 if (empty($dids_data) || $dids_data == "") {
				$this->response(array(
					'status' =>false,
					'error' => $this->lang->line('did_not_found')
				), 400);
		}
		if($accounts_data != "" && $dids_data != ""){
			$did_id = $this->common->get_field_name("id","dids",array("product_id"=>$this->postdata['did_id']));
			$did_result = $this->did_billing_process($accounts_data, $this->postdata['accountid'], $did_id);
			if($did_result[0] == "INSUFFIECIENT_BALANCE"){
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line("insufficient_balance")

				), 200);
			}
			if ($did_result[0] == "SUCCESS") {
				$add_array['invoice_type'] = "debit";
				$add_array['payment_by'] = "Account Balance";
				$add_array['charge_type'] = "DID";
				$add_array['is_update_balance'] = "true";
				$add_array['is_parent_billing'] = "false";
				$add_array['product_id'] = $this->postdata['did_id'];
				$add_array['create_invoice'] = "true";
				$order_id = $this->order->confirm_order($add_array, $this->postdata['accountid'], $this->accountinfo);
				if ($order_id > 0) {
						$this->db->where("product_id", $this->postdata['did_id']);
						$this->db->update("dids", array(
							"accountid" =>$this->postdata['accountid']
						));
						$this->response(array(
							'status' => true,
							'error' => $this->lang->line("did_assigned")
						), 200);
				}
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line('something_wrong')
				), 400);
			}
			$this->response(array(
					'status' => true,
					'error' => $this->lang->line("did_assigned")
			), 200);
		}
		else{
			$this->response(array(
				'status' =>false,
				'error' => $this->lang->line('did_not_found')
			), 400);
		}
	}
	
	function _assign_reseller()
	{
		if(!$this->form_validation->required($this->postdata['did_id']) ){
			  $this->response ( array (
				  'status' => false,
				  'error' => $this->lang->line ('did_id_required' ) 
			   ), 400 );
		  }
		if(!$this->form_validation->required($this->postdata['reseller_id']) ){
			$this->response ( array (
				'status' => false,
				'error' => $this->lang->line ('required_reseller_id' ) 
			 ), 400 );
		}else{
			$account_info = $this->db_model->getSelect("*", " accounts", array('id' => $this->postdata['reseller_id'],'type' => 1,'deleted' => 0))->row_array();
			if(empty($account_info)){
				$this->response ( array (
					'status' => false,
					'error' => $this->lang->line ('reseller_not_found') 
				), 400 );
			}
		}
		if(!is_numeric($this->postdata['did_id']) && $this->postdata['did_id'] != ""){
			  $this->response ( array (
				  'status' => false,
				  'error' => $this->lang->line ('numeric_did_id') 
			  ), 400 );
		}
		if($this->postdata['account_id'] != ""){
			$account_info = $this->db_model->getSelect("*", " accounts", array('id' => $this->postdata['account_id'],'reseller_id'=>$this->postdata['reseller_id'],'type' => 0,'deleted' => 0))->row_array();
			if(empty($account_info)){
				$this->response ( array (
					'status' => false,
					'error' => $this->lang->line ('account_not_found') 
				), 400 );
			}
		}
		  if(!is_numeric($this->postdata['price']) && $this->postdata['price'] != ""){
			  $this->response ( array (
				  'status' => false,
				  'error' => $this->lang->line ('numeric_price') 
			  ), 400 );
		  }
		  if(!is_numeric($this->postdata['setup_fee']) && $this->postdata['setup_fee'] != ""){
			  $this->response ( array (
				  'status' => false,
				  'error' => $this->lang->line ('numeric_setup_fee') 
			  ), 400 );
		  }
		if($this->postdata['account_id'] == ""){
			$where = array('parent_id' => $this->accountinfo['reseller_id'], 'accountid' => 0, 'status' => 0,'product_id'=> $this->postdata['did_id']);
		}else{
			$where = array('parent_id' => $this->postdata['reseller_id'], 'accountid' => 0, 'status' => 0,'product_id'=> $this->postdata['did_id']);
		}
		if ($this->accountinfo['reseller_id'] > 0) {
			$where = array( 
				'reseller_products.account_id' => $this->accountinfo['reseller_id'],
				'dids.status' => 0,
				'dids.parent_id' => $this->accountinfo['reseller_id'],
				'dids.accountid' => 0
			);
		  $buy_did_list = $this->db_model->getJionQuery('dids', 'dids.product_id as id,dids.number,,dids.country_id,dids.cost,dids.call_type,dids.leg_timeout,dids.maxchannels,dids.extensions,reseller_products.buy_cost,reseller_products.setup_fee,
  reseller_products.price,reseller_products.billing_type,(CASE WHEN reseller_products.billing_type = 2 THEN "Monthly" ELSE reseller_products.billing_days END) as billing_days,dids.province,dids.city
  ,reseller_products.product_id', $where, 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', "", "", 'DESC', 'dids.id');
		  }
		  else{
			$buy_did_list = $this->db_model->select("*,product_id as productid,product_id as id", "dids", $where, "dids.id", "desc", "", "");
		  }
		  $dids_arrays = $buy_did_list->row_array();
		if($dids_arrays['id'] != $this->postdata['did_id'] || $dids_arrays == ""){
			if($this->postdata['account_id'] != ""){
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line("did_not_optin")
				), 400);
			}else{
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line("did_not_found")
				), 400);
			}
		}
		  $add_array['price'] = $this->postdata['price'];
		  $add_array['setup_fee'] = $this->postdata['setup_fee'];
		  if($this->postdata['did_id'] != '' && $this->postdata['did_id'] != 0) {
				$did_id = $this->common->get_field_name("id", "dids", array(
					"product_id" => $this->postdata['did_id']
				));
				$did_product_id = $this->db_model->getSelect("*", " dids", array(
						'product_id' => $this->postdata['did_id'],
						'status' => 0
				))->row_array();
				$did_result = $this->did_billing_process($account_info, $account_info['id'], $did_id, '', $add_array);
				  if ($did_result[0] == "INSUFFIECIENT_BALANCE") {
					  $this->response(array(
						  'status' => false,
						  'error' => $this->lang->line("insufficient_balance")
					  ), 400);
				  }
				$product_info = $this->db_model->getSelect("*", " products", array(
					'id' => $did_product_id['product_id'],
					'status' => 0
				));
				if ($did_result[0] == "SUCCESS") {
				  if($this->postdata['account_id'] != ""){
					$add_array['invoice_type'] = "debit";
					$add_array['payment_by'] = "Account Balance";
					$add_array['charge_type'] = "DID";
					$add_array['is_update_balance'] = "true";
					$add_array['is_parent_billing'] = "false";
					$add_array['product_id'] = $this->postdata['did_id'];
					$product_info = $this->db_model->getSelect("*", "reseller_products", array(
						'product_id' => $did_product_id['product_id'],
						'account_id' => $this->postdata['reseller_id']
					))->row_array();
					$add_array['price'] = $product_info['price'];
					$add_array['setup_fee'] = $product_info['setup_fee'];
				  }else{
					if ($product_info->num_rows > 0) {
						$product_info = $product_info->result_array()[0];
						$add_array['billing_type'] = $product_info['billing_type'];
						$add_array['billing_days'] = $product_info['billing_days'];
						$add_array['commission'] = $product_info['commission'];
						$add_array['free_minutes'] = isset($product_info['free_minutes']) ? $product_info['free_minutes'] : 0;
						if (($this->accountinfo['reseller_id'] > 0 || $this->accountinfo['type'] == 1) && $this->accountinfo['is_distributor'] == 0) {
							$add_array['buy_cost'] = $this->common_model->add_calculate_currency($did_product_id['cost'], "", '', false, false);
						} else {
							$add_array['buy_cost'] = $product_info['buy_cost'];
						}
						if ($account_info['is_distributor'] == 0) {
							$add_array['price'] = isset($this->postdata['price']) && $this->postdata['price'] != "" ? $this->common_model->add_calculate_currency($this->postdata['price'], "", '', false, false) : $this->common_model->add_calculate_currency($product_info['price'], "", '', false, false);
							$add_array['setup_fee'] = isset($this->postdata['setup_fee']) && $this->postdata['setup_fee'] != "" ? $this->common_model->add_calculate_currency($this->postdata['setup_fee'], "", '', false, false) : $this->common_model->add_calculate_currency($product_info['setup_fee'], "", '', false, false);
						} else {
							$add_array['price'] = $product_info['price'];
							$add_array['setup_fee'] = $product_info['setup_fee'];
						}
						$query = "INSERT INTO reseller_products (product_id, account_id, reseller_id, buy_cost,commission, setup_fee, price, free_minutes, billing_type, billing_days, status, is_optin,is_owner,optin_date) VALUES(" . $did_product_id['product_id'] . "," . $account_info['id'] . "," . $account_info['reseller_id'] . "," . $add_array['buy_cost'] . "," . $add_array['commission'] . "," . $add_array['setup_fee'] . "," . $add_array['price'] . "," . $add_array['free_minutes'] . "," . $add_array['billing_type'] . "," . $add_array['billing_days'] . ", 0, 0, 1, '" . gmdate('Y-m-d H:i:s') . "') ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), account_id = VALUES(account_id), reseller_id = VALUES(reseller_id),buy_cost = VALUES(buy_cost),commission = VALUES(commission), setup_fee = VALUES(setup_fee), price = VALUES(price), free_minutes = VALUES(free_minutes), billing_type = VALUES(billing_type), billing_days = VALUES(billing_days), status = VALUES(status), is_optin = VALUES(is_optin), is_owner = VALUES(is_owner), optin_date = VALUES(optin_date)";
						$query = $this->db->query($query);
						$add_array['is_parent_billing'] = 'false';
						$add_array['payment_by'] = "Account Balance";
						$add_array['product_id'] = $this->postdata['did_id'];
					}
				}
					$add_array['create_invoice'] = "true";
					$order_id = $this->order->confirm_order($add_array, $account_info['id'], $account_info);
					if ($order_id > 0) {
						if($this->postdata['account_id'] != ""){
							$this->db->where("product_id", $this->postdata['did_id']);
							$did_update = $this->db->update("dids", array(
								"accountid" =>$this->postdata['account_id']
							));
							$this->response(array(
								'status' => true,
								'error' => $this->lang->line("did_assigned")
							), 200);
						}else{  
							$this->db->where("product_id", $this->postdata['did_id']);
							$did_update = $this->db->update("dids", array(
								"parent_id" => $account_info['id']
							));
							if($did_update == 1){
								$this->response(array(
									'status' => true,
									'success' => $this->lang->line("did_optin")
								), 200);
							}else{
								$this->response(array(
									'status' => false,
									'error' => $this->lang->line('something_wrong')
								), 400);
							}
						}
					}else{
					$this->response(array(
						'status' => false,
						'error' => $this->lang->line('something_wrong')
					), 400);
				}
				$this->response(array(
					'status' => false,
					'error' => $this->lang->line('something_wrong')
				), 400);
		}
	}else{
		$this->response(array(
		  'status' => false,
		  'error' => $this->lang->line("did_not_found")
		), 400);
		}
	}

	function _release()
	{
		if (!isset($this->postdata['did_id']) || $this->postdata['did_id'] == '') {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('error_param_missing') . " integer:did_id"
			), 400);
		}
		if (!$this->form_validation->numeric($this->postdata['did_id'])) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('numeric_did_id')
			), 400);
		}

		$did_info = $this->db_model->getSelect(
			"*", "dids", array("id" => (int) $this->postdata['did_id'])
		)->row_array();

		if (empty($did_info)) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('did_not_found')
			), 400);
		}

		if ((int) $did_info['accountid'] === 0) {
			$this->response(array(
				'status' => false,
				'error'  => $this->lang->line('did_not_assigned')
			), 400);
		}

		$this->did_model->did_number_release($did_info, $this->accountinfo, 'release');

		$this->response(array(
			'status'  => true,
			'success' => $this->lang->line('did_release')
		), 200);
	}

    function did_billing_process($request_from, $accountid, $did, $skip_balance_check = false, $extra_info = '')
    {
        $account     = $this->db_model->getSelect("*", "accounts", array('id' => $accountid));
        $accountinfo = (array) $account->first_row();

        $currency_name = $this->common->get_field_name('currency', "currency", array(
            'id' => $accountinfo['currency_id']
        ));

        $didinfo = $this->did_model->get_did_by_number($did);

        if ($request_from['type'] == 1 && $accountinfo['reseller_id'] > 0) {
            if ($didinfo['parent_id'] > 0 && $didinfo['accountid'] > 0) {
                return array("NOT_AVAL_FOR_PURCHASE", "This DID is already purchased by someone.");
            } else {
                $reseller_pricing_query = $this->db_model->getJionQuery('dids', 'dids.id,dids.number,dids.cost,dids.inc,dids.call_type,dids.extensions,dids.connectcost,dids.includedseconds,reseller_products.buy_cost,reseller_products.commission,reseller_products.setup_fee,reseller_products.price,reseller_products.billing_type,reseller_products.billing_days,reseller_products.status,reseller_products.billing_days,reseller_products.billing_type,dids.last_modified_date,dids.product_id', array('dids.number' => $didinfo['number']), 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', '', '', '', '');
                if (!empty($reseller_pricing_query)) {
                    $reseller_pricing_result    = (array) $reseller_pricing_query->first_row();
                    $didinfo['call_type']       = ($reseller_pricing_result['call_type'] > 0) ? $reseller_pricing_result['call_type'] : 0;
                    $didinfo['extensions']      = ($reseller_pricing_result['extensions'] > 0) ? $reseller_pricing_result['extensions'] : '';
                    $didinfo['setup_fee']       = ($reseller_pricing_result['setup_fee'] > 0) ? $reseller_pricing_result['setup_fee'] : 0;
                    $didinfo['price']           = ($reseller_pricing_result['price'] > 0) ? $reseller_pricing_result['price'] : 0;
                    $didinfo['connectcost']     = ($reseller_pricing_result['connectcost'] > 0) ? $reseller_pricing_result['connectcost'] : 0;
                    $didinfo['includedseconds'] = ($reseller_pricing_result['includedseconds'] > 0) ? $reseller_pricing_result['includedseconds'] : 0;
                    $didinfo['cost']            = ($reseller_pricing_result['cost'] > 0) ? $reseller_pricing_result['cost'] : 0;
                    $didinfo['inc']             = ($reseller_pricing_result['inc'] > 0) ? $reseller_pricing_result['inc'] : 0;
                    $didinfo['billing_days']    = $reseller_pricing_result['billing_days'];
                    $didinfo['billing_type']    = $reseller_pricing_result['billing_type'];
                }
            }
        }
        if ($request_from['type'] == 1 && $accountinfo['reseller_id'] == 0) {
            if ($didinfo['parent_id'] > 0 && $didinfo['accountid'] > 0) {
                return array("NOT_AVAL_FOR_PURCHASE", "This DID is already purchased by someone.");
            } else {
                $reseller_pricing_query = $this->db_model->getJionQuery('dids', 'dids.id,dids.number,dids.cost,dids.inc,dids.call_type,dids.extensions,dids.connectcost,dids.includedseconds,products.buy_cost,products.commission,products.setup_fee,products.price,products.billing_type,products.billing_days,products.status,dids.last_modified_date,dids.product_id', array('dids.number' => $didinfo['number']), 'products', 'dids.product_id=products.id', 'inner', '', '', '', '');
                if (!empty($reseller_pricing_query)) {
                    $reseller_pricing_result    = (array) $reseller_pricing_query->first_row();
                    $didinfo['call_type']       = ($reseller_pricing_result['call_type'] > 0) ? $reseller_pricing_result['call_type'] : 0;
                    $didinfo['extensions']      = ($reseller_pricing_result['extensions'] > 0) ? $reseller_pricing_result['extensions'] : '';
                    $didinfo['setup_fee']       = ($reseller_pricing_result['setup_fee'] > 0) ? $reseller_pricing_result['setup_fee'] : 0;
                    $didinfo['price']           = ($reseller_pricing_result['price'] > 0) ? $reseller_pricing_result['price'] : 0;
                    $didinfo['connectcost']     = ($reseller_pricing_result['connectcost'] > 0) ? $reseller_pricing_result['connectcost'] : 0;
                    $didinfo['includedseconds'] = ($reseller_pricing_result['includedseconds'] > 0) ? $reseller_pricing_result['includedseconds'] : 0;
                    $didinfo['cost']            = ($reseller_pricing_result['cost'] > 0) ? $reseller_pricing_result['cost'] : 0;
                    $didinfo['inc']             = ($reseller_pricing_result['inc'] > 0) ? $reseller_pricing_result['inc'] : 0;
                    $didinfo['billing_days']    = $reseller_pricing_result['billing_days'];
                    $didinfo['billing_type']    = $reseller_pricing_result['billing_type'];
                }
            }
        }
        if ($accountinfo['reseller_id'] > 0 && $accountinfo['type'] == 0) {
            if ($didinfo['accountid'] > 0) {
                return array("NOT_AVAL_FOR_PURCHASE", "This DID is already purchased by someone.");
            } else {
                $customer_result_array      = $this->db_model->getJionQuery('dids', 'dids.id,dids.number,dids.cost,dids.inc,dids.call_type,dids.extensions,dids.connectcost,dids.includedseconds,reseller_products.buy_cost,reseller_products.commission,reseller_products.setup_fee,reseller_products.price,reseller_products.billing_type,reseller_products.billing_days,reseller_products.status,dids.last_modified_date,dids.product_id', array('dids.number' => $didinfo['number'], 'reseller_products.account_id' => $accountinfo['reseller_id']), 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', '', '', '', '');
                $customer_result_array      = (array) $customer_result_array->first_row();
                $didinfo['call_type']       = $customer_result_array['call_type'];
                $didinfo['extensions']      = $customer_result_array['extensions'];
                $didinfo['setup_fee']       = $customer_result_array['setup_fee'];
                $didinfo['price']           = $customer_result_array['price'];
                $didinfo['connectcost']     = $customer_result_array['connectcost'];
                $didinfo['includedseconds'] = $customer_result_array['includedseconds'];
                $didinfo['cost']            = $customer_result_array['cost'];
                $didinfo['inc']             = $customer_result_array['inc'];
                $didinfo['billing_days']    = $customer_result_array['billing_days'];
                $didinfo['billing_type']    = $customer_result_array['billing_type'];
            }
        }
        if ($accountinfo['reseller_id'] == 0 && $accountinfo['type'] == 0) {
            if ($didinfo['accountid'] > 0) {
                return array("NOT_AVAL_FOR_PURCHASE", "This DID is already purchased by someone.");
            } else {
                $did_info_arr               = $this->db_model->getJionQuery('dids', 'dids.id,dids.number,dids.cost,dids.inc,dids.call_type,dids.extensions,dids.connectcost,dids.includedseconds,products.buy_cost,products.commission,products.setup_fee,products.price,products.billing_type,products.billing_days,products.status,dids.last_modified_date,dids.product_id', array('dids.number' => $didinfo['number']), 'products', 'dids.product_id=products.id', 'inner', '', '', '', '');
                $did_info_arr               = (array) $did_info_arr->first_row();
                $didinfo['call_type']       = $did_info_arr['call_type'];
                $didinfo['extensions']      = $did_info_arr['extensions'];
                $didinfo['setup_fee']       = $did_info_arr['setup_fee'];
                $didinfo['price']           = $did_info_arr['price'];
                $didinfo['connectcost']     = $did_info_arr['connectcost'];
                $didinfo['includedseconds'] = $did_info_arr['includedseconds'];
                $didinfo['cost']            = $did_info_arr['cost'];
                $didinfo['inc']             = $did_info_arr['inc'];
                $didinfo['billing_days']    = $did_info_arr['billing_days'];
                $didinfo['billing_type']    = $did_info_arr['billing_type'];
            }
        }

        $available_bal                  = $this->db_model->get_available_bal($accountinfo);
        $accountinfo['did_number']      = $didinfo['number'];
        $accountinfo['did_country_id']  = $didinfo['country_id'];
        $accountinfo['did_setup']       = $this->common_model->calculate_currency($didinfo['setup_fee'], '', $currency_name, true, true);
        $accountinfo['did_monthlycost'] = $this->common_model->calculate_currency($didinfo['price'], '', $currency_name, true, true);
        $accountinfo['did_maxchannels'] = $didinfo['maxchannels'];

        $account_balance = $accountinfo['posttoexternal'] == 1 ? $accountinfo['credit_limit'] - ($accountinfo['balance']) : $accountinfo['balance'];
        $account_balance = $this->common_model->calculate_currency($account_balance, '', $currency_name, true, false);

        $didinfo["setup_fee"] = (isset($extra_info['setup_fee']) && $extra_info['setup_fee'] > 0) ? $extra_info['setup_fee'] : $didinfo["setup_fee"];
        $didinfo["price"]     = (isset($extra_info['price']) && $extra_info['price'] > 0) ? $extra_info['price'] : $didinfo["price"];

        if ($account_balance >= ($didinfo["setup_fee"] + $didinfo["price"]) || $skip_balance_check == true) {
            if ($request_from['type'] == 2 || ($request_from['type'] == 1 && $request_from['id']) == $accountinfo['id']) {
                $field_name = $accountinfo['type'] == '1' ? "parent_id" : 'accountid';
            } elseif ($request_from['type'] == 1) {
                $field_name = $accountinfo['type'] == 1 ? "parent_id" : 'accountid';
                if ($accountinfo['type'] == 0 || $accountinfo['type'] == 3) {
                    $this->db_model->update("dids", array(
                        "accountid" => $accountinfo['id']
                    ), array(
                        "id" => $didinfo['id']
                    ));
                }
            } elseif ($request_from['type'] == 0 || $request_from['type'] == 4 || $request_from['type'] == 3) {
                $this->db_model->update("dids", array(
                    "accountid" => $accountinfo['id']
                ), array(
                    "id" => $didinfo['id']
                ));
            }
            $next_bill_date = gmdate('Y-m-d H:i:s');
            $today          = gmdate("d");

            if ($today <= 28 && $didinfo['billing_type'] == "2") {
                $next_bill_date = gmdate("Y-m-d H:i:s", strtotime($next_bill_date . "+" . (1) . " months"));
                $next_bill_date = date("Y-m-d H:i:s", strtotime($next_bill_date));
            }
            if ($today > 28 && $didinfo['billing_type'] == "2") {
                $first_of_month = strtotime(gmdate("Y-m-1 H:i:s"));
                $days_of_month  = $this->common->days_in_next_month();
                if ($today == 29 && $today <= $days_of_month) {
                    $next_bill_date = gmdate("Y-m-29 H:i:s", strtotime("+" . (1) . " months", $first_of_month));
                } elseif ($today == 30 && $today <= $days_of_month) {
                    $next_bill_date = gmdate("Y-m-30 H:i:s", strtotime("+" . (1) . " months", $first_of_month));
                } else {
                    $next_bill_date = gmdate("Y-m-" . $days_of_month . " H:i:s", strtotime("+" . (1) . " months", $first_of_month));
                }
            }
            if ($didinfo['billing_type'] == 0) {
                $next_bill_date = ($didinfo['billing_days'] == 0) ? gmdate('Y-m-d H:i:s', strtotime('+10 years')) : gmdate("Y-m-d H:i:s", strtotime("+" . ($didinfo['billing_days']) . " days"));
            }
            if ($didinfo['billing_type'] == 1) {
                $next_bill_date = ($didinfo['billing_days'] == 0) ? gmdate('Y-m-d H:i:s', strtotime('+10 years')) : gmdate("Y-m-d H:i:s", strtotime("+" . ($didinfo['billing_days']) . " days"));
            }
            unset($didinfo['id']);
            $final_array                      = array_merge($accountinfo, $didinfo);
            $final_array['next_billing_date'] = $next_bill_date;
            $final_array['name']              = $final_array['number'];
            $final_array['category_name']     = "DID";
            $final_array['payment_by']        = "Account Balance";
            $final_array['quantity']          = 1;
            $final_array['price']             = $final_array['price'];
            $final_array['setup_fee']         = $final_array['setup_fee'];
            $final_array['total_price']       = ($final_array['setup_fee'] + $final_array['price']) * ($final_array['quantity']);
            $final_array['price']             = ($final_array['setup_fee'] + $final_array['price']);
            $this->common->mail_to_users('product_purchase', $final_array);
            return array("SUCCESS", "DID Purchased Successfully.");
        } else {
            return array("INSUFFIECIENT_BALANCE", "Insufficient fund to purchase this DID.");
        }
    }

}