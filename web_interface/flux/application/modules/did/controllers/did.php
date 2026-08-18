<?php

// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
//
// Copyright (C) 2021 Flux Telecom
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
class DID extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper('template_inheritance');
        $this->load->library('session');
        $this->load->library('did_form');
        $this->load->library('flux/form', 'did_form');
        $this->load->library('flux/permission');
        $this->load->model('did_model');
        $this->load->library('csvreader');
        $this->load->library('did_lib');
        $this->load->library('flux/order');
        $this->load->library('FLUX_Sms');
        $this->load->library('flux_log');
        $this->load->config('did');

        if ($this->session->userdata('user_login') == false) {
            redirect(base_url() . '/flux/login');
        }
    }

    public function did_list()
    {
        $this->session->set_userdata('advance_search', 0);
        $data['app_name'] = 'Flux Telecom - Unindo pessoas e negócios | Manage DIDs | DIDS';
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('DIDs');
        $this->session->set_userdata('did_search', 0);
        $data['search_flag'] = true;
        $data['batch_update_flag'] = true;
        if ($this->session->userdata('logintype') == 2 || $this->session->userdata('logintype') == '-1') {
            $data["grid_buttons"] = $this->did_form->build_grid_buttons();
        } elseif ($this->session->userdata('logintype') == 1) {
            $data["grid_buttons"] = $this->did_form->build_grid_buttons_reseller();
        } else {
            $data["grid_buttons"] = json_encode(array());
        }
        if ($this->session->userdata['userlevel_logintype'] == '1') {
            $data['grid_fields'] = $this->did_form->build_did_list_for_reseller_login();
            $data['form_search'] = $this->form->build_serach_form($this->did_form->get_search_did_form_for_reseller());
            $data['form_batch_update'] = $this->form->build_batchupdate_form($this->did_form->did_batch_update_form());
        } else {
            $data['grid_fields'] = $this->did_form->build_did_list_for_admin();
            $data['form_search'] = $this->form->build_serach_form($this->did_form->get_search_did_form());
            $data['form_batch_update'] = $this->form->build_batchupdate_form($this->did_form->did_batch_update_form());
        }
        $this->load->view('view_did_list', $data);
    }

    public function did_list_json()
    {
        $json_data = array();
        $count_all = $this->did_model->getdid_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->did_model->getdid_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        if ($this->session->userdata['userlevel_logintype'] == '1') {
            $grid_fields = json_decode($this->did_form->build_did_list_for_reseller_login());
        } else {
            $grid_fields = json_decode($this->did_form->build_did_list_for_admin());
        }
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    public function did_available_list()
    {
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('Buy DIDs');
        $data['search_flag'] = true;
        $this->session->set_userdata('did_search', 0);
        $data['grid_fields'] = $this->did_form->build_did_list_for_available_dids();
        $data['form_search'] = $this->form->build_serach_form($this->did_form->get_available_search_did_form());
        $this->load->view('view_available_dids_list', $data);
    }

    public function did_available_list_json()
    {
        $json_data = array();
        $count_all = $this->did_model->getavailable_did_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->did_model->getavailable_did_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        $grid_fields = json_decode($this->did_form->build_did_list_for_available_dids());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    public function did_available_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);
        if ($this->input->post('advance_search', true) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            if (isset($action['country_id']) && $action['country_id'] != '') {
                $action['dids.country_id'] = $action['country_id'];
                unset($action['country_id']);
            }
            $this->session->set_userdata('did_available_list_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'did/did_available_list/');
        }
    }

    public function did_available_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('did_available_list_search', "");
    }

    public function did_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);
        if ($this->input->post('advance_search', true) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $accountinfo = $this->session->userdata('accountinfo');

            if ($accountinfo['reseller_id'] == 0) {
                if (isset($action['number']['number']) && $action['number']['number'] != '') {
                    $action['dids.number']['dids.number'] = $action['number']['number'];
                    $action['dids.number']['dids.number-string'] = $action['number']['number-string'];
                    unset($action['number']);
                }
                if (isset($action['country_id']) && $action['country_id'] != '') {
                    $action['dids.country_id'] = $action['country_id'];
                    unset($action['country_id']);
                }
                if (isset($action['province']) && $action['province'] != '') {
                    $action['dids.province'] = $action['province'];
                    $action['dids.province']['dids.province'] = $action['province']['province'];
                    $action['dids.province']['dids.province-string'] = $action['province']['province-string'];
                    unset($action['province']);
                    unset($action['dids.province']['province']);
                    unset($action['dids.province']['province-string']);
                }
                if (isset($action['city']) && $action['city'] != '') {
                    $action['dids.city'] = $action['city'];
                    $action['dids.city']['dids.city'] = $action['city']['city'];
                    $action['dids.city']['dids.city-string'] = $action['city']['city-string'];
                    unset($action['city']);
                    unset($action['dids.city']['city']);
                    unset($action['dids.city']['city-string']);
                }
                if (isset($action['cost']) && $action['cost'] != '') {
                    $action['dids.cost'] = $action['cost'];
                    $action['dids.cost']['dids.cost'] = $action['cost']['cost'];
                    $action['dids.cost']['dids.cost-integer'] = $action['cost']['cost-integer'];
                    unset($action['cost']);
                    unset($action['dids.cost']['cost']);
                    unset($action['dids.cost']['cost-integer']);
                }
                if (isset($action['accountid']) && $action['accountid'] != '') {
                    $action['dids.accountid'] = $action['accountid'];
                    unset($action['accountid']);
                }
                if (isset($action['call_type']) && $action['call_type'] != '') {
                    $action['dids.call_type'] = $action['call_type'];
                    unset($action['call_type']);
                }
                if (isset($action['extensions']) && $action['extensions'] != '') {
                    $action['dids.extensions'] = $action['extensions'];
                    $action['dids.extensions']['dids.extensions'] = $action['extensions']['extensions'];
                    $action['dids.extensions']['dids.extensions-string'] = $action['extensions']['extensions-string'];
                    unset($action['extensions']);
                    unset($action['dids.extensions']['extensions']);
                    unset($action['dids.extensions']['extensions-string']);
                }
            } else {

                if (isset($action['number']['number']) && $action['number']['number'] != '') {
                    $action['dids.number']['dids.number'] = $action['number']['number'];
                    $action['dids.number']['dids.number-string'] = $action['number']['number-string'];
                    unset($action['number']);
                }
                if (isset($action['country_id']) && $action['country_id'] != '') {
                    $action['view_dids_reseller.country_id'] = $action['country_id'];
                    unset($action['country_id']);
                }
                if (isset($action['province']) && $action['province'] != '') {
                    $action['view_dids_reseller.province'] = $action['province'];
                    $action['view_dids_reseller.province']['view_dids_reseller.province'] = $action['province']['province'];
                    $action['view_dids_reseller.province']['view_dids_reseller.province-string'] = $action['province']['province-string'];
                    unset($action['province']);
                    unset($action['view_dids_reseller.province']['province']);
                    unset($action['view_dids_reseller.province']['province-string']);
                }
                if (isset($action['city']) && $action['city'] != '') {
                    $action['view_dids_reseller.city'] = $action['city'];
                    $action['view_dids_reseller.city']['view_dids_reseller.city'] = $action['city']['city'];
                    $action['view_dids_reseller.city']['view_dids_reseller.city-string'] = $action['city']['city-string'];
                    unset($action['city']);
                    unset($action['view_dids_reseller.city']['city']);
                    unset($action['view_dids_reseller.city']['city-string']);
                }
                if (isset($action['accountid']) && $action['accountid'] != '') {
                    $action['view_dids_reseller.buyer_accountid'] = $action['accountid'];
                    unset($action['accountid']);
                }
                if (isset($action['cost']) && $action['cost'] != '') {
                    $action['view_dids_reseller.cost'] = $action['cost'];
                    $action['view_dids_reseller.cost']['view_dids_reseller.cost'] = $action['cost']['cost'];
                    $action['view_dids_reseller.cost']['view_dids_reseller.cost-integer'] = $action['cost']['cost-integer'];
                    unset($action['cost']);
                    unset($action['view_dids_reseller.cost']['cost']);
                    unset($action['view_dids_reseller.cost']['cost-integer']);
                }

                if (isset($action['setup']) && $action['setup'] != '') {
                    $action['view_dids_reseller.setup_fee'] = $action['setup'];
                    $action['view_dids_reseller.setup_fee']['view_dids_reseller.setup_fee'] = $action['setup']['setup'];
                    $action['view_dids_reseller.setup_fee']['view_dids_reseller.setup_fee-integer'] = $action['setup']['setup-integer'];
                    unset($action['setup']);
                    unset($action['view_dids_reseller.setup_fee']['setup']);
                    unset($action['view_dids_reseller.setup_fee']['setup-integer']);
                }

                if (isset($action['monthlycost']) && $action['monthlycost'] != '') {
                    $action['view_dids_reseller.price'] = $action['monthlycost'];
                    $action['view_dids_reseller.price']['view_dids_reseller.price'] = $action['monthlycost']['monthlycost'];
                    $action['view_dids_reseller.price']['view_dids_reseller.price-integer'] = $action['monthlycost']['monthlycost-integer'];
                    unset($action['monthlycost']);
                    unset($action['view_dids_reseller.price']['price']);
                    unset($action['view_dids_reseller.price']['price-integer']);
                }

                if (isset($action['call_type']) && $action['call_type'] != '') {
                    $action['view_dids_reseller.call_type'] = $action['call_type'];
                    unset($action['call_type']);
                }
                if (isset($action['extensions']) && $action['extensions'] != '') {
                    $action['view_dids_reseller.extensions'] = $action['extensions'];
                    $action['view_dids_reseller.extensions']['view_dids_reseller.extensions'] = $action['extensions']['extensions'];
                    $action['view_dids_reseller.extensions']['view_dids_reseller.extensions-string'] = $action['extensions']['extensions-string'];
                    unset($action['extensions']);
                    unset($action['view_dids_reseller.extensions']['extensions']);
                    unset($action['view_dids_reseller.extensions']['extensions-string']);
                }
            }
            $this->session->set_userdata('did_list_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'did/did_list/');
        }
    }

    public function did_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('did_search', "");
    }

    public function did_available_purchase($number)
    {
        $accountinfo = $this->session->userdata('accountinfo');
        if ($number != '') {
            $data['currency'] = $this->common->get_field_name("currency", "currency", array(
                "id" => $accountinfo['currency_id']
            ));
            if ($accountinfo['reseller_id'] > 0) {
                $did_info = $this->db_model->getJionQuery('dids', 'dids.id,dids.number as name,reseller_products.buy_cost,reseller_products.commission,reseller_products.setup_fee,reseller_products.price,reseller_products.billing_type,reseller_products.billing_days,reseller_products.status,dids.last_modified_date,dids.product_id', array(
                    'dids.number' => $number,
                    'reseller_products.account_id' => $accountinfo['reseller_id']
                ), 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', '', '', '', '');
            } else {
                $did_info = $this->db_model->getJionQuery('dids', 'dids.id,products.name,products.buy_cost,products.commission,products.setup_fee,products.price,products.billing_type,products.billing_days,products.status,dids.last_modified_date,dids.product_id', array(
                    'dids.number' => $number
                ), 'products', 'dids.product_id=products.id', 'inner', '', '', '', '');
            }
            if ($did_info->num_rows > 0) {
                $data['did_info'] = $did_info->result_array()[0];
                $data['accountinfo'] = $accountinfo;
                $this->load->view("view_edit_available_dids_purchase", $data);
            }
        }
        redirect(base_url() . 'did/did_available_list/');
    }

    public function did_resellerdid_save()
    {
        $add_array = $this->input->post();

        $productid = $this->input->post('product_id');
        $accountinfo = $this->session->userdata("accountinfo");
        if ($productid != '' && $productid != 0) {
            $did_id = $this->common->get_field_name("id", "dids", array(
                "product_id" => $productid
            ));
            $did_result = $this->did_lib->did_billing_process($this->session->userdata, $accountinfo['id'], $did_id, '', $add_array);
            if ($did_result[0] == "SUCCESS") {
                $product_info = $this->db_model->getSelect("*", " products", array(
                    'id' => $productid,
                    'status' => 0
                ));
                if ($product_info->num_rows > 0) {
                    $product_info = $product_info->result_array()[0];
                    $add_array['billing_type'] = $product_info['billing_type'];
                    $add_array['billing_days'] = $product_info['billing_days'];
                    $add_array['commission'] = $product_info['commission'];
                    $add_array['free_minutes'] = isset($product_info['free_minutes']) ? $product_info['free_minutes'] : 0;
                    if (($accountinfo['reseller_id'] > 0 || $accountinfo['type'] == 1) && $accountinfo['is_distributor'] == 0) {
                        $add_array['buy_cost'] = $this->common_model->add_calculate_currency($add_array['product_buy_cost'], "", '', false, false);
                    } else {
                        $add_array['buy_cost'] = $product_info['buy_cost'];
                    }

                    if ($accountinfo['is_distributor'] == 0) {
                        $add_array['price'] = isset($add_array['price']) ? $this->common_model->add_calculate_currency($add_array['price'], "", '', false, false) : $this->common_model->add_calculate_currency($product_info['price'], "", '', false, false);
                        $add_array['setup_fee'] = isset($add_array['setup_fee']) ? $this->common_model->add_calculate_currency($add_array['setup_fee'], "", '', false, false) : $this->common_model->add_calculate_currency($product_info['setup_fee'], "", '', false, false);
                    } else {
                        $add_array['price'] = $product_info['price'];
                        $add_array['setup_fee'] = $product_info['setup_fee'];
                    }

                    $query = "INSERT INTO reseller_products (product_id, account_id, reseller_id, buy_cost,commission, setup_fee, price, free_minutes, billing_type, billing_days, status, is_optin,is_owner,optin_date)
		VALUES($productid," . $accountinfo['id'] . "," . $accountinfo['reseller_id'] . "," . $add_array['buy_cost'] . "," . $add_array['commission'] . "," . $add_array['setup_fee'] . "," . $add_array['price'] . "," . $add_array['free_minutes'] . "," . $add_array['billing_type'] . "," . $add_array['billing_days'] . ", 0, 0, 1, '" . gmdate('Y-m-d H:i:s') . "') ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), account_id = VALUES(account_id), reseller_id = VALUES(reseller_id),buy_cost = VALUES(buy_cost),commission = VALUES(commission), setup_fee = VALUES(setup_fee), price = VALUES(price), free_minutes = VALUES(free_minutes), billing_type = VALUES(billing_type), billing_days = VALUES(billing_days), status = VALUES(status), is_optin = VALUES(is_optin), is_owner = VALUES(is_owner), optin_date = VALUES(optin_date)";
                    $query = $this->db->query($query);
                    $add_array['is_parent_billing'] = 'false';
                    $add_array['payment_by'] = "Account Balance";
                    $add_array['create_invoice'] = "false";
                    $order_id = $this->order->confirm_order($add_array, $accountinfo['id'], $accountinfo);

                    if ($order_id != '') {
                        $this->db->where("product_id", $productid);
                        $this->db->update("dids", array(
                            "parent_id" => $accountinfo['id']
                        ));
                    }
                    $flux_flash_message_type = ($did_result[0] == "SUCCESS") ? "flux_errormsg" : "flux_notification";
                    $this->session->set_flashdata($flux_flash_message_type, gettext($did_result[1]));
                }
            } else {

                $flux_flash_message_type = ($did_result[0] == "SUCCESS") ? "flux_errormsg" : "flux_notification";
                $this->session->set_flashdata($flux_flash_message_type, gettext($did_result[1]));
            }
            redirect(base_url() . 'did/did_available_list/');
        } else {
            $this->session->set_flashdata('flux_notification', gettext('Product Not Found!'));
            redirect(base_url() . 'did/did_available_list/');
        }
    }

    public function did_assgin_reseller($id)
    {
        $accountinfo = $this->session->userdata('accountinfo');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $data['accounts_list'] = $this->db_model->build_concat_select_dropdown("id,first_name,number", "accounts", "", array(
                "reseller_id" => $accountinfo['id'],
                "type" => 0,
                "status" => 0,
                "deleted" => 0
            ), "");
        } else {
            $data['accounts_list'] = $this->db_model->build_concat_select_dropdown("id,first_name,number", "accounts", "", array(
                "reseller_id" => 0,
                "type" => 0,
                "status" => 0,
                "deleted" => 0
            ), "");
        }
        $data['number'] = $this->common->get_field_name("number", "dids", array(
            "id" => $id,
            "status" => 0
        ));
        if ($data['number'] != '') {
            $data['product_id'] = $this->common->get_field_name("product_id", "dids", array(
                "id" => $id,
                "status" => 0
            ));
            $this->load->view("view_reseller_did_assign", $data);
        }
    }

    public function did_assign_number()
    {
        $add_array = $this->input->post();
        if (! empty($add_array) && $add_array['accountid'] > 0) {
            $accountinfo = $this->session->userdata("accountinfo");
            $add_array['is_parent_billing'] = 'false';
            $add_array['payment_by'] = 0;
            $account_info = $this->db_model->getSelect("*", "accounts", array(
                "id" => $add_array["accountid"],
                "status" => 0,
                "deleted" => 0
            ));
            if ($account_info->num_rows > 0) {
                $accountdata = $account_info->result_array()[0];
                $balance = $accountdata['posttoexternal'] == 1 ? $accountdata['credit_limit'] - ($accountdata['balance']) : $accountdata['balance'];
                if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
                    $where = array(
                        'product_id' => $add_array['product_id']
                    );
                    $product_info = (array) $this->db->get_where("reseller_products", $where)->result_array()[0];
                } else {
                    $where = array(
                        'id' => $add_array['product_id']
                    );
                    $product_info = (array) $this->db->get_where("products", $where)->result_array()[0];
                }
                if (! empty($product_info)) {
                    $total_amt = $product_info['price'] + $product_info['setup_fee'];

                    $total_amt = $this->common->convert_to_currency('', '', $total_amt);
                    $did_id = $this->common->get_field_name("id", "dids", array("product_id" => $add_array['product_id']));
                    $this->load->library('did_lib');
                    $accountdata['logintype'] = $accountdata['type'];
                    $did_result = $this->did_lib->did_billing_process($accountdata, $add_array["accountid"], $did_id);
                    if ($balance >= $total_amt) {
                        $add_array['invoice_type'] = "debit";
                        $add_array['payment_by'] = "Account Balance";
                        $add_array['charge_type'] = "DID";
                        $add_array['is_update_balance'] = "true";
                        $add_array['create_invoice'] = "false";
                        $order_id = $this->order->confirm_order($add_array, $add_array['accountid'], $accountinfo);
                        if ($order_id > 0) {
                            $this->db->where("product_id", $add_array['product_id']);
                            $this->db->update("dids", array(
                                "accountid" => $add_array['accountid']
                            ));
                            $this->session->set_flashdata('flux_errormsg', gettext('DID Assign Successfully!'));
                        }
                    } else {
                        $this->session->set_flashdata('flux_notification', gettext('Insufficient balance to assign DID!'));
                        redirect(base_url() . 'did/did_list/');
                    }
                }
            } else {
                $this->session->set_flashdata('flux_notification', gettext('Account Not Found!'));
                redirect(base_url() . 'did/did_list/');
            }
        }
        redirect(base_url() . 'did/did_list/');
    }

    public function did_list_release($id)
    {
        $accountinfo = $this->session->userdata('accountinfo');
        $where = array(
            'id' => $id
        );
        $did_info = (array) $this->db->get_where("dids", $where)->result_array()[0];
        if ($did_info['accountid'] > 0) {
            $where = array(
            'id' => $did_info['accountid']
            );
        } else {
            $where = array(
            'id' => $did_info['parent_id']
            );
        }
        $accountinfo = (array) $this->db->get_where("accounts", $where)->result_array()[0];
        $this->did_model->did_number_release($did_info, $accountinfo, 'release');
        $did_info['product_name'] = $did_info['number'];

        $final_array = array_merge($did_info, $accountinfo);
        $final_array['id'] = $accountinfo['id'];
        $final_array['next_billing_date'] = gmdate('Y-m-d H:i:s');
        $this->did_lib->did_release($final_array);
        $this->session->set_flashdata('flux_errormsg', gettext('DID Released Successfully!'));
        redirect(base_url() . 'did/did_list/');
    }

    public function did_delete_multiple()
    {
        $ids = $this->input->post("selected_ids", true);
        $did_info_details = array();
        $accountinfo = $this->session->userdata('accountinfo');
        $where = "product_id IN ($ids)";
        $did_info = (array) $this->db->get_where("dids", $where)->result_array();
        foreach ($did_info as $key => $value) {
            $this->did_model->did_number_release($value, $accountinfo, 'remove');
            if ($this->session->userdata['userlevel_logintype'] == '1' || $this->session->userdata['userlevel_logintype'] == '5') {
                if ($accountinfo['reseller_id'] > 0) {
                    $this->db->where($where);
                    $this->db->where('reseller_id', $accountinfo['reseller_id']);
                    $this->db->where('account_id', $accountinfo['id']);
                    $this->db->delete("reseller_products");
                    $this->db->where($where);
                    $this->db->update("dids", array(
                        'parent_id' => $accountinfo['reseller_id']
                    ));
                } else {
                    $this->db->where($where);
                    $this->db->where('account_id', $accountinfo['id']);
                    $this->db->delete("reseller_products");
                }
            } else {

                if ($this->session->userdata['userlevel_logintype'] == '-1' || $this->session->userdata['userlevel_logintype'] == '2') {
                    $category_name = '';
                    $acc_id = '';
                    $order_items_id = '';
                    $order_id = '';
                    $did_delete = array();
                    $product_category_details = array();
                    $product_category_details_result = array();
                    $product_category_details = $this->db_model->getSelect("name,product_category", "products", array(
                        "id" => $value['product_id']
                    ));

                    if ($product_category_details->num_rows > 0) {
                        $product_category_details_result = $product_category_details->result_array()[0];

                        $did_delete['product_name'] = $product_category_details_result['name'];

                        $category_name = $this->common->get_field_name("name", "category", array(
                            "id" => $product_category_details_result['product_category']
                        ));
                        $acc_id = $this->common->get_field_name("accountid", "order_items", array(
                            "product_id" => $value['product_id']
                        ));
                        $order_items_id = $this->common->get_field_name("order_id", "order_items", array(
                            "product_id" => $value['product_id']
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
                    $whr = "id IN ($ids)";
                    $this->db->where($whr);
                    $this->db->delete('products');
                    $this->db->where(array(
                        "id" => $value['id']
                    ));
                    $this->db->delete('dids');
                }
            }
        }
        echo "DIDs";
    }

    public function did_forward($id = '')
    {
        if ($this->session->userdata('logintype') == 1) {
            $query = $this->db_model->getSelect("*", "reseller_products", array(
                'product_id' => $id
            ));
            $result = $query->result_array();
            $qry_did = $this->db_model->getSelect("*", "dids", array(
                'product_id' => $id
            ));
            $qry_result = $qry_did->result_array();
            $id = $qry_result[0]['id'];
        }
        $did_forward = $this->did_model->did_forward($id);
        $data['page_title'] = gettext('DID Destination'). ' (' . $did_forward[0]['number'] . ')';
        $data['id'] = $id;
        $data['call_type'] = $did_forward[0]['call_type'];
        $data['extensions'] = $did_forward[0]['extensions'];
        $data['call_type_vm_flag'] = $did_forward[0]['call_type_vm_flag'];
        $data['logtype'] = $this->session->userdata('logintype');        
        
        // sip_profiles ----------------------------------------------------------------
        $sip_profile_result          = $this->db_model->getSelect('*', 'sip_profiles', array('status' => 0));
        $data['sip_profile']         = ($sip_profile_result->num_rows() > 0)
                                           ? $sip_profile_result->result_array()
                                           : array();
        $data['current_sip_profile'] = isset($did_forward[0]['sip_profile_id'])
                                           ? $did_forward[0]['sip_profile_id']
                                           : '';
        // -----------------------------------------------------------------------------
        
        
        $addon_info = $this->db_model->getSelect("*", "addons", array(
            "package_name" => "ringgroup"
        ));
        if ($addon_info->num_rows() == 0) {
            $this->load->view('view_did_forward', $data);
        } else {
            $this->load->view('view_did_custom_forward', $data);
        }
        // End
    }

    public function did_local_change($call_type_code = "", $did_id = "", $extensions = "")
    {
        $accountid = $this->common->get_field_name("accountid", "dids", array("id" => $did_id));
        $whr = array("accountid" => $accountid,'status' => 0);
        $did_local = $this->db_model->getSelect('*', 'sip_devices', $whr);
        if ($did_local->num_rows() > 0) {
            $did_local_data = $did_local->result_array();
            $did_local_arr = array();
            foreach ($did_local_data as $value) {
                $did_local_arr[$value['username']] =  $value['username'];
            }
            $did_local_info = array("name" => "extensions" ,"id" => "extensions_id", "class" => "col-md-6 extensions_set");
            echo form_dropdown($did_local_info, $did_local_arr, $extensions);
        } else {
            echo '<select class="col-md-12 form-control form-control-lg selectpicker extensions_set" id="extensions_id" name="extensions"><option>--</option></select>';
        }
    }

    public function did_forward_save()
    {
        $add_array = $this->input->post();
        $did_forward = $this->did_model->update_did_forward($add_array);
        $this->session->set_flashdata('flux_errormsg', gettext('DID forwading set sucessfully!'));
        if ($this->session->userdata('logintype') == 0 || $this->session->userdata('logintype') == 3) {
            redirect(base_url() . 'user/user_didlist/');
        } else {
            redirect(base_url() . 'did/did_list/');
        }
    }

    public function customer_did($accountid, $accounttype)
    {
        $json_data = array();
        $instant_search = $this->session->userdata('left_panel_search_' . $accounttype . '_did');
        $account_arr = (array) $this->db->get_where('accounts', array(
            "id" => $accountid
        ))->first_row();
        $field_name = $accounttype == "reseller" ? "parent_id" : 'accountid';

        if ($account_arr['reseller_id'] != 0) {

            if ($accounttype == 'reseller') {
                $like_str = "";
                $like_str = ! empty($instant_search) ? "(dids.number like '%$instant_search%'
					OR  dids.init_inc like '%$instant_search%'
					OR  dids.inc like '%$instant_search%'
					OR  dids.cost like '%$instant_search%'
					OR  dids.includedseconds like '%$instant_search%'
					OR  reseller_products.setup_fee like '%$instant_search%'
					OR  reseller_products.price like '%$instant_search%'
					OR  dids.connectcost like '%$instant_search%'
					OR  dids.province like '%$instant_search%'
					OR  dids.city like '%$instant_search%'
					    )" : null;
                $this->db->where('dids.accountid', $accountid);
                $this->db->where('dids.parent_id', $account_arr['reseller_id']);
                $this->db->where('dids.status', 0);
                if (! empty($like_str)) {
                    $this->db->where($like_str);
                }
                $count_result = $this->db_model->getJionQueryCount('dids', 'dids.id,dids.product_id,dids.number,reseller_products.buy_cost,reseller_products.commission,reseller_products.price,reseller_products.billing_type,reseller_products.setup_fee,reseller_products.billing_days,reseller_products.product_id', 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', "", "", 'DESC', 'dids.id');
                $paging_data = $this->form->load_grid_config($count_result['count'], $_GET['rp'], $_GET['page']);
                $json_data = $paging_data["json_paging"];
                $query = $this->db_model->getJionQuery('dids', 'dids.id,dids.product_id,dids.number,reseller_products.buy_cost,reseller_products.commission,reseller_products.price,reseller_products.billing_type,reseller_products.setup_fee,reseller_products.billing_days,reseller_products.product_id', 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', $paging_data["paging"]["page_no"], $paging_data["paging"]["start"], 'DESC', 'dids.id');
            } else {
                $str1 = '';
                $like_str1 = ! empty($instant_search) ? "(dids.number like '%$instant_search%'
                                                    OR dids.inc like '%$instant_search%'
                                                    OR dids.cost like '%$instant_search%'
                                                    OR dids.includedseconds like '%$instant_search%'
                                                    OR reseller_products.setup_fee like '%$instant_search%'
                                                    OR reseller_products.price like '%$instant_search%'
                                                    OR dids.connectcost like '%$instant_search%'
                                                    OR dids.province like '%$instant_search%'
						    OR dids.city like '%$instant_search%'
                                                        )" : null;

                $this->db->where('dids.accountid', $accountid);
                $this->db->where('dids.parent_id', $account_arr['reseller_id']);
                $this->db->where('dids.status', 0);
                if (! empty($like_str1)) {
                    $str1 = $like_str1;
                    $this->db->where($str1);
                }
                $count_result = (array) $this->db->query('SELECT  COUNT(*) as count,  reseller_products.setup_fee, reseller_products.price FROM dids  INNER JOIN reseller_products  ON dids.product_id = reseller_products.product_id')->first_row();


                $paging_data = $this->form->load_grid_config($count_result['count'], $_GET['rp'], $_GET['page']);
                $json_data = $paging_data["json_paging"];

                $query = $this->db_model->getJionQuery('dids', 'dids.id as did_id,dids.product_id as id,dids.number,dids.cost,dids.inc,dids.country_id,dids.call_type,dids.extensions,dids.connectcost,dids.init_inc,dids.includedseconds,reseller_products.buy_cost,reseller_products.commission,reseller_products.setup_fee,reseller_products.price,reseller_products.billing_type,reseller_products.billing_days,reseller_products.status,dids.last_modified_date,dids.city,dids.province', '', 'reseller_products', 'dids.product_id=reseller_products.product_id', 'inner', $paging_data["paging"]["page_no"], $paging_data["paging"]["start"], 'DESC', 'dids.id');

            }
        } else {
            $like_str = ! empty($instant_search) ? "(dids.number like '%$instant_search%'
                                                    OR dids.inc like '%$instant_search%'
                                                    OR dids.cost like '%$instant_search%'
                                                    OR dids.includedseconds like '%$instant_search%'
                                                    OR dids.setup like '%$instant_search%'
                                                    OR dids.monthlycost like '%$instant_search%'
                                                    OR dids.connectcost like '%$instant_search%'
                                                    OR  dids.province like '%$instant_search%'
						    OR  dids.city like '%$instant_search%'
                                                        )" : null;
            if (! empty($like_str)) {
                $this->db->where($like_str);
            }
            $where = array(
                $field_name => $accountid
            );

            $count_all = $this->db_model->getJionQueryCount('dids', 'dids.id as did_id,dids.product_id as id,dids.number,dids.cost,dids.inc,dids.country_id,dids.call_type,dids.extensions,dids.connectcost,dids.init_inc,dids.includedseconds,products.buy_cost,products.commission,products.setup_fee,products.price,products.billing_type,products.billing_days,products.status,dids.last_modified_date,dids.city,dids.province', $where, 'products', 'dids.product_id=products.id', 'inner', "", "", 'DESC', 'dids.id');
            $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
            $json_data = $paging_data["json_paging"];
            if (! empty($like_str)) {
                $this->db->where($like_str);
            }

            $query = $this->db_model->getJionQuery('dids', 'dids.id as did_id,dids.product_id as id,dids.number,dids.cost,dids.inc,dids.country_id,dids.call_type,dids.extensions,dids.connectcost,dids.init_inc,dids.includedseconds,products.buy_cost,products.commission,products.setup_fee,products.price,products.billing_type,products.billing_days,products.status,dids.last_modified_date,dids.city,dids.province', $where, 'products', 'dids.product_id=products.id', 'inner', $paging_data["paging"]["page_no"], $paging_data["paging"]["start"], 'DESC', 'dids.id');


        }
        $did_grid_fields = json_decode($this->did_form->build_did_list_for_customer($accountid, $accounttype));
        $json_data['rows'] = $this->form->build_grid($query, $did_grid_fields);
        echo json_encode($json_data);
    }

    public function did_download_sample_file($file_name)
    {
        $this->load->helper('download');
        $full_path = "./assets/Rates_File/" . $file_name . ".csv";
        ob_clean();
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false
            )
        );
        $file = file_get_contents($full_path, false, stream_context_create($arrContextOptions));
        force_download(gettext("samplefile.csv"), $file);
    }

    public function did_import()
    {
        $data['page_title'] = gettext('Import DIDs Using Field Mapper');
        $this->session->set_userdata('import_did_rate_csv', "");
        $error_data = $this->session->userdata('import_did_csv_error');
        $full_path = $this->config->item('rates-file-path');
        if (file_exists($full_path . $error_data) && $error_data != "") {
            unlink($full_path . $error_data);
            $this->session->set_userdata('import_did_csv_error', "");
        }
        $data['provider_dropdown'] = form_dropdown(
            'provider_id',
            $this->db_model->build_concat_select_dropdown("id,first_name,number", " accounts", "where_arr", array(
                "type"    => "3",
                "status"  => "0",
                "deleted" => "0",
            )),
            ''
        );
        $this->load->view('view_import_did_mapper', $data);
    }

    public function did_preview_file()
    {
        $invalid_flag = false;
        $error = null;
        $data['page_title'] = gettext('Import DIDs Using Field Mapper');
        $data['mapto_fields'] = $this->config->item('DIDs-mapper-fields');
        if (isset($_FILES['didimport']['name']) && $_FILES['didimport']['name'] != "") {
            $segments = explode(".", $_FILES['didimport']['name']);
            if (count($segments) !== 2) {
                $invalid_flag = true;
                $error = gettext("File Uploading Fail Please Try Again");
            } else {
                list($txt, $ext) = $segments;
                if ($ext == "csv" && $_FILES['didimport']['size'] > 0) {
                    $upload_error = $_FILES['didimport']['error'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $_FILES["didimport"]["tmp_name"]);
                    $acceptable_mime_types = array(
                        'application/csv',
                        'application/x-csv',
                        'text/csv',
                        'text/comma-separated-values',
                        'text/x-comma-separated-values',
                        'text/tab-separated-values',
                        'text/plain',
                    );
                    if (! in_array($mime_type, $acceptable_mime_types)) {
                        $invalid_flag = true;
                        $error = gettext("Invalid file format : Only CSV file allows to import records(Can't import empty file)");
                    } elseif ($upload_error == 0) {
                        $uploadedFile = $_FILES["didimport"]["tmp_name"];
                        $file_data = $this->common->csv_to_array($uploadedFile);
                        if (! empty($file_data)) {
                            $field_select = array_combine(array_keys($file_data[0]), array_keys($file_data[0]));
                            $full_path = $this->config->item('rates-file-path');
                            $actual_file_name = "FluxSBC-DIDs-import" . date("Y-m-d H:i:s") . "." . $ext;
                            $actual_file_name = str_replace(' ', '-', $actual_file_name);
                            $actual_file_name = str_replace(':', '-', $actual_file_name);
                            if (move_uploaded_file($uploadedFile, $full_path . $actual_file_name)) {
                                array_unshift($file_data, $field_select);
                                $data['csv_tmp_data'] = $file_data;
                                $data['file_data'] = $field_select;
                                $data['page_title'] = gettext('Map CSV to DIDs');
                                $this->session->set_userdata('import_did_rate_csv', $actual_file_name);
                                $data['post_array'] = serialize($this->input->post());
                                $data['mapper_array'] = $this->_create_did_mapper_array();
                            } else {
                                $invalid_flag = true;
                                $error = gettext("File Uploading Fail Please Try Again");
                            }
                        } else {
                            $invalid_flag = true;
                            $error = gettext("Invalid file format : Only CSV file allows to import records(Can't import empty file)");
                        }
                    } else {
                        $invalid_flag = true;
                        $error = gettext("File Uploading Fail Please Try Again");
                    }
                } else {
                    $invalid_flag = true;
                    $error = gettext("Invalid file format : Only CSV file allows to import records(Can't import empty file)");
                }
            }
        } else {
            $invalid_flag = true;
            $error = gettext("Please Select  File.");
        }

        if ($invalid_flag) {
            $data['error'] = $error;
            $data['provider_dropdown'] = form_dropdown(
                'provider_id',
                $this->db_model->build_concat_select_dropdown("id,first_name,number", " accounts", "where_arr", array(
                    "type"    => "3",
                    "status"  => "0",
                    "deleted" => "0",
                )),
                $this->input->post('provider_id')
            );
        }
        $this->load->view('view_import_did_mapper', $data);
    }

    private function _create_did_mapper_array()
    {
        $custom_config_array = $this->config->item('DIDs-mapper-fields');
        $custom_array = array();
        foreach ($custom_config_array as $section => $fields) {
            foreach ($fields as $label => $value) {
                $params_arr = array(
                    'id'    => $value,
                    'class' => $value,
                    'name'  => $value,
                );
                $custom_array[$value] = str_replace('col-md-5', 'col-md-12', form_input($params_arr));
            }
        }
        return $custom_array;
    }

    public function did_import_file()
    {
        ini_set('max_execution_time', 0);
        $add_array = $this->input->post();
        $default_fields = isset($add_array['post_array']) ? unserialize($add_array['post_array']) : array();
        $provider_id = ! empty($default_fields['provider_id']) ? $default_fields['provider_id'] : 0;
        $new_final_arr = array();
        $invalid_array = array();
        $new_final_arr_key = $this->config->item('DID-rates-field');
        $accountinfo = $this->session->userdata('accountinfo');
        $reseller_id = $accountinfo['type'] == 1 ? $accountinfo['id'] : 0;
        $full_path = $this->config->item('rates-file-path');
        $did_file_name = $this->session->userdata('import_did_rate_csv');
        $csv_tmp_data = $this->common->csv_to_array($full_path . $did_file_name);
        $flag = false;
        $i = 0;
        $number_arr = array();
        $reseller_array = array();
        $final_reseller_array = array();
        $resolve = function ($key) use (&$add_array, &$csv_data_ref) {
            $sel = isset($add_array[$key . '-select']) ? $add_array[$key . '-select'] : '';
            if (! empty($sel) && isset($csv_data_ref[$sel]) && $csv_data_ref[$sel] !== '') {
                return $csv_data_ref[$sel];
            }
            return isset($add_array[$key]) ? $add_array[$key] : '';
        };
        foreach ($csv_tmp_data as $key => $csv_data) {
            $csv_data_ref = $csv_data;
            $row = array(
                'number'          => $resolve('number'),
                'country_id'      => $resolve('country_id'),
                'city'            => $resolve('city'),
                'province'        => $resolve('province'),
                'accountid'       => $resolve('accountid'),
                'cost'            => $resolve('cost'),
                'init_inc'        => $resolve('init_inc'),
                'inc'             => $resolve('inc'),
                'setup'           => $resolve('setup'),
                'monthlycost'     => $resolve('monthlycost'),
                'call_type'       => $resolve('call_type'),
                'extensions'      => $resolve('extensions'),
                'connectcost'     => $resolve('connectcost'),
                'includedseconds' => $resolve('includedseconds'),
                'leg_timeout'     => $resolve('leg_timeout'),
                'billing_days'    => $resolve('billing_days'),
            );
            $csv_data = $row;
            if (isset($csv_data['number']) && $csv_data['number'] != '') {
                $str = null;
                if (! empty($csv_data['call_type'])) {
                    $call_type = $this->common->get_field_name("call_type_code", "did_call_types", array(
                        "call_type" => $csv_data['call_type']
                    ));
                } else {
                    $call_type = '0';
                }
                $csv_data['accountid'] = isset($csv_data['accountid']) ? $csv_data['accountid'] : 0;
                $csv_data['country_id'] = isset($csv_data['country_id']) ? $csv_data['country_id'] : 0;
                $csv_data['city'] = isset($csv_data['city']) ? $csv_data['city'] : '';
                $csv_data['province'] = isset($csv_data['province']) ? $csv_data['province'] : '';
                $csv_data['provider_id'] = ($provider_id > 0) ? $provider_id : 0;
                $csv_data['call_type'] = $call_type;
                $csv_data['extensions'] = isset($csv_data['extensions']) ? $csv_data['extensions'] : '';
                $csv_data['includedseconds'] = isset($csv_data['includedseconds']) ? $csv_data['includedseconds'] : 0;
                $csv_data['cost'] = ! empty($csv_data['cost']) && is_numeric($csv_data['cost']) && $csv_data['cost'] ? $csv_data['cost'] : 0;
                $csv_data['setup'] = ! empty($csv_data['setup']) && is_numeric($csv_data['setup']) && $csv_data['setup'] > 0 ? $csv_data['setup'] : 0;
                $csv_data['monthlycost'] = ! empty($csv_data['monthlycost']) && is_numeric($csv_data['monthlycost']) && $csv_data['monthlycost'] > 0 ? $csv_data['monthlycost'] : 0;
                $csv_data['connectcost'] = ! empty($csv_data['connectcost']) && is_numeric($csv_data['connectcost']) && $csv_data['connectcost'] > 0 ? $csv_data['connectcost'] : 0;
                $csv_data['inc'] = isset($csv_data['inc']) ? $csv_data['inc'] : 0;
                $csv_data['leg_timeout'] = ! empty($csv_data['leg_timeout']) && is_numeric($csv_data['leg_timeout']) ? (int) $csv_data['leg_timeout'] : 30;
                $csv_data['billing_days'] = ! empty($csv_data['billing_days']) && is_numeric($csv_data['billing_days']) ? (int) $csv_data['billing_days'] : 0;
                $str = $this->data_validate($csv_data);
                if ($str != "") {
                    $invalid_array[$i] = $csv_data;
                    $invalid_array[$i]['error'] = $str;
                } else {
                    if (! empty($csv_data['country_id'])) {
                        if (! in_array($csv_data['number'], $number_arr)) {
                            $number_count = $this->db_model->countQuery('id', 'dids', array(
                                'number' => $csv_data['number']
                            ));
                            if ($number_count > 0) {
                                $invalid_array[$i] = $csv_data;
                                $invalid_array[$i]['error'] = 'Duplicate DID found from database';
                            } else {
                                if ($csv_data['accountid'] > 0) {
                                    $this->db->where('type IN(0,1,3)');
                                    $this->db->where('reseller_id', 0);
                                    $this->db->where('deleted', 0);
                                    $this->db->where('status', 0);
                                    $account_info = (array) $this->db->get_where('accounts', array(
                                        "number" => $csv_data['accountid']
                                    ))->first_row();
                                    if ($account_info) {
                                        $account_balance = $this->db_model->get_available_bal($account_info);
                                        $setup = $this->common_model->add_calculate_currency($csv_data['setup'], '', '', false, false);

                                        if ($account_balance >= $setup) {
                                            $field_name = $account_info['type'] == 1 ? 'parent_id' : 'accountid';
                                            $currency_name = $this->common->get_field_name('currency', "currency", array(
                                                'id' => $account_info['currency_id']
                                            ));
                                            $csv_data['monthlycost'] = $this->common_model->add_calculate_currency($csv_data['monthlycost'], '', '', false, false);
                                            $csv_data['cost'] = $this->common_model->add_calculate_currency($csv_data['cost'], '', '', false, false);
                                            $csv_data['connectcost'] = $this->common_model->add_calculate_currency($csv_data['connectcost'], '', '', false, false);
                                            $csv_data['setup'] = $setup;
                                            $csv_data[$field_name] = $account_info['id'];

                                            $available_bal = $this->db_model->update_balance($csv_data["setup"], $account_info['id'], "debit");
                                            $account_info['did_number'] = $csv_data['number'];
                                            $account_info['did_country_id'] = $csv_data['country_id'];
                                            $account_info['did_setup'] = $this->common_model->calculate_currency($csv_data['setup'], '', $currency_name, true, true);
                                            $account_info['did_monthlycost'] = $this->common_model->calculate_currency($csv_data['monthlycost'], '', $currency_name, true, true);
                                            $account_info['did_maxchannels'] = "0";
                                            $csv_data['country_id'] = $this->common->get_field_name('id', 'countrycode', array(
                                                "country" => $csv_data['country_id']
                                            ));
                                            if ($account_info['type'] == 1) {
                                                $reseller_array = $csv_data;
                                                $reseller_array['note'] = $csv_data['number'];
                                                $reseller_array['reseller_id'] = $account_info['id'];
                                                $reseller_array['parent_id'] = $account_info['reseller_id'];

                                                unset($reseller_array['number'], $csv_data['accountid'], $reseller_array['accountid'], $reseller_array['country_id'], $reseller_array['init_inc']);
                                                $csv_data['accountid'] = 0;
                                                $final_reseller_array[$i] = $reseller_array;
                                            } else {
                                                $csv_data['parent_id'] = 0;
                                            }

                                            $new_final_arr[$i] = $csv_data;
                                        } else {
                                            $invalid_array[$i] = $csv_data;
                                            $invalid_array[$i]['error'] = gettext('Account have not sufficient amount to purchase this DID.');
                                        }
                                    } else {
                                        $invalid_array[$i] = $csv_data;
                                        $invalid_array[$i]['error'] = gettext('Account not found or assign to invalid account');
                                    }
                                } else {
                                    $csv_data['setup'] = $this->common_model->add_calculate_currency($csv_data['setup'], '', '', false, false);
                                    $csv_data['monthlycost'] = $this->common_model->add_calculate_currency($csv_data['monthlycost'], '', '', false, false);
                                    $csv_data['cost'] = $this->common_model->add_calculate_currency($csv_data['cost'], '', '', false, false);
                                    $csv_data['connectcost'] = $this->common_model->add_calculate_currency($csv_data['connectcost'], '', '', false, false);
                                    $csv_data['accountid'] = 0;
                                    $csv_data['country_id'] = $this->common->get_field_name('id', 'countrycode', array(
                                        "country" => $csv_data['country_id']
                                    ));
                                    $new_final_arr[$i] = $csv_data;
                                }
                            }
                        } else {
                            $invalid_array[$i] = $csv_data;
                            $invalid_array[$i]['error'] = gettext('Duplicate DID found from import file.');
                        }
                    } else {
                        $invalid_array[$i] = $csv_data;
                        $invalid_array[$i]['error'] = gettext('Country Not Found from import file.');
                    }
                }
                $number_arr[] = $csv_data['number'];
            }
            $i++;
        }
        if (! empty($new_final_arr)) {
            $result = $this->did_model->bulk_insert_dids($new_final_arr);
        }

        unlink($full_path . $did_file_name);
        $count = count($invalid_array);
        if ($count > 0) {
            $session_id = "-1";
            $fp = fopen($full_path . $session_id . '.csv', 'w');
            foreach ($new_final_arr_key as $key => $value) {
                $custom_array[0][$key] = ucfirst($key);
            }
            $custom_array[0]['error'] = "Error";
            $invalid_array = array_merge($custom_array, $invalid_array);
            foreach ($invalid_array as $err_data) {
                fputcsv($fp, $err_data);
            }
            fclose($fp);
            $this->session->set_userdata('import_did_csv_error', $session_id . ".csv");
            $data["error"] = $invalid_array;
            $data['provider_id'] = $provider_id;
            $data['import_record_count'] = count($new_final_arr) + count($reseller_array);
            $data['failure_count'] = count($invalid_array) - 1;
            $data['page_title'] = gettext('DID Import Error');
            $this->load->view('view_import_error', $data);
        } else {
            $this->session->set_flashdata('flux_errormsg', gettext('Total').' ' . count($new_final_arr) .' '. gettext('DIDs Imported Successfully!'));
            redirect(base_url() . "did/did_list/");
        }
    }

    public function data_validate($csvdata)
    {
        $str = null;
        $alpha_regex = "/^[a-z ,.'-]+$/i";
        $alpha_numeric_regex = "/^[a-z0-9 ,.'-]+$/i";
        $email_regex = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/";
        $str .= $csvdata['number'] != '' ? null : 'Number,';
        $str = rtrim($str, ',');
        if (! $str) {
            $str .= is_numeric($csvdata['number']) ? null : 'Number,';
            $str .= ! empty($csvdata['connectcost']) && is_numeric($csvdata['connectcost']) ? null : (empty($csvdata['connectcost']) ? null : 'Connect Cost,');
            $str .= ! empty($csvdata['includedseconds']) && is_numeric($csvdata['includedseconds']) ? null : (empty($csvdata['includedseconds']) ? null : 'Included Seconds,');
            if ($str) {
                $str = rtrim($str, ',');
                $error_field = explode(',', $str);
                $count = count($error_field);
                $str .= $count > 1 ? ' are not valid' : ' is not Valid';
                return $str;
            } else {
                return false;
            }
        } else {
            $str = rtrim($str, ',');
            $error_field = explode(',', $str);
            $count = count($error_field);
            $str .= $count > 1 ? ' are required' : ' is Required';
            return $str;
        }
    }

    public function did_error_download()
    {
        $this->load->helper('download');
        $error_data = $this->session->userdata('import_did_csv_error');
        $full_path = $this->config->item('rates-file-path');
        ob_clean();
        $data = file_get_contents($full_path . $error_data);
        force_download("error_did_rates.csv", $data);
    }

    public function did_export_data_xls()
    {
        $account_info = $accountinfo = $this->session->userdata('accountinfo');
        $currency_id = $account_info['currency_id'];
        $currency = $this->common->get_field_name('currency', 'currency', $currency_id);
        $query = $this->did_model->getdid_list(true, '0', '10000000');
        ob_clean();
        $outbound_array[] = array(
            gettext("DID"),
            gettext("Country"),
            gettext("Account"),
            gettext("Per Minute Cost") . "(" . $currency . ")",
            gettext("Initial Increment"),
            gettext("Increment"),
            gettext("Setup Fee") . "(" . $currency . ")",
            gettext("Monthly Fee") . "(" . $currency . ")",
            gettext("Call Type"),
            gettext("Destination"),
            gettext("Status"),
            gettext("Modified Date"),
            gettext("Is Purchased?")
        );
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $outbound_array[] = array(
                    $row['number'],
                    $this->common->get_field_name("country", "countrycode", $row['country_id']),
                    $this->common->get_field_name("number", "accounts", $row['accountid']),
                    $this->common_model->calculate_currency($row['cost'], '', '', true, false),
                    $row['init_inc'],
                    $row['inc'],
                    $this->common_model->calculate_currency($row['setup'], '', '', true, false),
                    $this->common_model->calculate_currency($row['monthlycost'], '', '', true, false),
                    $this->common->get_call_type("", "", $row['call_type']),
                    $row['extensions'],
                    $this->common->get_status('export', '', $row['status']),
                    $row['last_modified_date'],
                    $this->common->check_did_avl_export($row['number'])
                );
            }
        }
        $this->load->helper('csv');
        array_to_csv($outbound_array, 'DIDs_' . date("Y-m-d") . '.csv');
    }
    public function did_batch_update()
    {
        $batch_update_arr = $this->input->post();

        $destination_error = $this->validate_batch_destination($batch_update_arr);
        if ($destination_error != '') {
            echo json_encode(array(
                    "error_msg" => $destination_error,
                ));
            exit();
        }

        $result           = $this->did_model->did_batch_update($batch_update_arr);
        echo json_encode(array(
                "SUCCESS" => gettext("DID Batch Updated Successfully!"),
            ));
        exit();
    }

    public function did_batch_destination_change($call_type_code = "")
    {
        echo $this->build_batch_destination_field($call_type_code);
        exit();
    }

    private function build_batch_destination_field($call_type_code)
    {
        $field_info = array(
            "name"  => "extensions[extensions]",
            "id"    => "extensions",
            "class" => "extensions_set",
        );

        if ($call_type_code === '0' || $call_type_code === '5') {
            $options = $this->get_batch_extension_options();
        } elseif ($call_type_code === '7') {
            $options = $this->get_batch_ringgroup_options();
        } else {
            return '<input type="text" name="extensions[extensions]" id="extensions" value="" '
                . 'maxlength="180" size="20" class="text field extensions_set col-md-12 form-control form-control-lg">';
        }

        $options = array('' => gettext('--Select--')) + $options;

        return form_dropdown($field_info, $options, '');
    }


    private function get_batch_search_accountids()
    {
        if ($this->session->userdata('advance_search') != 1) {
            return null;
        }

        $accountinfo = $this->session->userdata('accountinfo');
        if (isset($accountinfo['type']) && ($accountinfo['type'] == 1 || $accountinfo['type'] == 5)) {
            $this->db->where('parent_id', $accountinfo['id']);
        }
        $this->db_model->build_search('did_list_search');
        $this->db->select('accountid');
        $this->db->distinct();
        $query = $this->db->get('dids');

        $accountids = array();
        foreach ($query->result_array() as $row) {
            if ($row['accountid'] > 0) {
                $accountids[] = $row['accountid'];
            }
        }
        return $accountids;
    }

    private function get_batch_scope_where()
    {
        $where       = array('status' => 0);
        $accountinfo = $this->session->userdata('accountinfo');
        if (isset($accountinfo['type']) && ($accountinfo['type'] == 1 || $accountinfo['type'] == 5)) {
            $where['reseller_id'] = $accountinfo['id'];
        }
        return $where;
    }

    private function get_batch_destination_query($table)
    {
        $accountids = $this->get_batch_search_accountids();
        if (is_array($accountids)) {
            if (empty($accountids)) {
                return false;
            }
            $this->db->where_in('accountid', $accountids);
        }
        $this->db->where($this->get_batch_scope_where());
        return $this->db->get($table);
    }

    private function get_batch_extension_options()
    {
        $options = array();
        $query   = $this->get_batch_destination_query('sip_devices');
        if ($query === false) {
            return $options;
        }
        foreach ($query->result_array() as $row) {
            $options[html_escape($row['username'])] = html_escape($row['username']);
        }
        return $options;
    }

    private function get_batch_ringgroup_options()
    {
        $options = array();
        if (!$this->db->table_exists('pbx_ringgroup')) {
            return $options;
        }

        $query = $this->get_batch_destination_query('pbx_ringgroup');
        if ($query === false) {
            return $options;
        }
        foreach ($query->result_array() as $row) {
            $options[$row['id']] = html_escape($row['name']);
        }
        return $options;
    }

    private function batch_destination_exists($table, $field, $value)
    {
        if ($table == 'pbx_ringgroup' && !$this->db->table_exists('pbx_ringgroup')) {
            return false;
        }

        $accountids = $this->get_batch_search_accountids();
        if (is_array($accountids)) {
            if (empty($accountids)) {
                return false;
            }
            $this->db->where_in('accountid', $accountids);
        }
        $this->db->where($this->get_batch_scope_where());
        $this->db->where($field, $value);
        return $this->db->count_all_results($table) > 0;
    }

    private function validate_batch_destination($post)
    {
        if (!isset($post['extensions']['operator']) || $post['extensions']['operator'] != '2') {
            return '';
        }

        $destination = isset($post['extensions']['extensions']) ? trim($post['extensions']['extensions']) : '';

        if ($destination == '') {
            return gettext('Enter the destination or set the operator to Preserve.');
        }
        if (mb_strlen($destination) > 180) {
            return gettext('The destination must be at most 180 characters long.');
        }

        $call_type = isset($post['call_type']['call_type']) ? (string) $post['call_type']['call_type'] : '';

        switch ($call_type) {
            case '0':
            case '5':
                return $this->batch_destination_exists('sip_devices', 'username', $destination)
                    ? ''
                    : gettext('Invalid destination: the selected extension was not found.');
            case '7':
                return $this->batch_destination_exists('pbx_ringgroup', 'id', $destination)
                    ? ''
                    : gettext('Invalid destination: the selected ring group was not found.');
            case '1':
                return preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+(:\d{1,5})?$/', $destination)
                    ? ''
                    : gettext('Invalid destination: use the format number@host or number@host:port.');
            case '2':
                return $this->is_valid_ip_destination($destination)
                    ? ''
                    : gettext('Invalid destination: enter an IP address or IP:port.');
            case '4':
                return preg_match('/^\+?[0-9]+$/', $destination)
                    ? ''
                    : gettext('Invalid destination: enter digits only, optionally starting with +.');
            default:
                return '';
        }
    }

    private function is_valid_ip_destination($destination)
    {
        $parts = explode(':', $destination);
        if (count($parts) > 2) {
            return false;
        }
        if (count($parts) == 2) {
            if (!preg_match('/^\d{1,5}$/', $parts[1]) || (int) $parts[1] < 1 || (int) $parts[1] > 65535) {
                return false;
            }
        }
        return filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}
?>

