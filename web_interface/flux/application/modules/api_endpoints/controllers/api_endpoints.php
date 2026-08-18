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
class api_endpoints extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->helper('template_inheritance');
        $this->load->library('api_endpoints_form');
        $this->load->library('flux/form');
        $this->load->library('flux_log');
        $this->load->library('FLUX_Sms');
        $this->load->helper(array('form', 'url'));
        $this->load->model('common_model');
        $this->load->library('session');
        $this->load->helper('form');
        $this->load->model('api_endpoints_model');
        $this->load->model('api_model');
        $this->load->model('Flux_common');
        $this->load->library('flux/permission');
        
        if ($this->session->userdata('user_login') == FALSE)
            redirect(base_url() . '/flux/login');
    }
    
    function api_endpoints_list()
    {
        $data['page_title'] = gettext('API Endpoints');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields'] = $this->api_endpoints_form->build_api_endpoints_list_for_admin();
        $data["grid_buttons"] = $this->api_endpoints_form->build_grid_buttons();
        $data['form_search'] = $this->form->build_serach_form($this->api_endpoints_form->get_api_endpoints_search_form());
        $this->load->view('view_api_endpoints_list', $data);
    }
    
    function api_endpoints_list_json()
    {
        $json_data = array();
        $count_all = $this->api_endpoints_model->api_endpoints_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->api_endpoints_model->api_endpoints_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);        
        $permissioninfo = $this->session->userdata('permissioninfo');                
        foreach ($query as $key => $value) {
        
            $checkbox = array(
                '<input type="checkbox" name="chkAll" id=' . $value['id'] . ' class="ace chkRefNos" onclick="clickchkbox(' . $value['id'] . ')" value=' . $value['id'] . '><lable class="lbl"></lable>'
            );
            $account_data = $this->session->userdata("accountinfo");
            $base_url = base_url().'api_endpoints/api_endpoints_edit/'.$value['id'];
            $edit_permission="<a href='/api_endpoints/api_endpoints_edit/" . $value['id'] . "' style='cursor:pointer;color:#3b3280' title='".gettext('Edit Endpoint')." - ".$value['endpoint_name']."'>" . $value['endpoint_name'] . "</a>";
            
            $current_row = array(
                $checkbox,
                $edit_permission,
                $this->common->get_field_name('partner_name', '`api_partners', array(
                    'id' => $value['partner_id']
                )),
                $value['endpoint_url'],               
                $this->common->convert_GMT_to('', '', $value['last_modified_date']),
                $this->common->get_status('status', 'api_endpoints', $value),
		        $this->get_buttons_api_endpoints ( $value ['id'] ) 
            );
            $json_data['rows'][] = array(
                'cell' => $current_row
            );
        }
        echo json_encode($json_data);        
        
    }

    function partners_endpoints_list()
    {
        $accountinfo = $this->session->userdata("accountinfo");
        $account_arr = (array) $this->db->get_where("accounts", array(
            "id" => $accountinfo['id'],
            "deleted" => "0",
            "status" => "0"
        ))->first_row();
        if (empty($account_arr)) {
            $this->session->sess_destroy();
            $this->load->helper('cookie');
            set_cookie('post_info', json_encode("text"), '20');
            redirect(base_url() . "login/");
        }
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('Partners Endpoints');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields'] = $this->api_endpoints_form->build_partners_endpoints_list_for_admin();
        $data["grid_buttons"] = $this->api_endpoints_form->build_partners_grid_buttons();
        $data['form_search'] = $this->form->build_serach_form($this->api_endpoints_form->get_partners_endpoints_search_form());
        $this->load->view('view_partners_endpoints_list', $data);
    }
    
    function partners_endpoints_list_json()
    {
        $json_data = array();
        $count_all = $this->api_endpoints_model->getpartners_endpoints_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->api_endpoints_model->getpartners_endpoints_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        $grid_fields = json_decode($this->api_endpoints_form->build_partners_endpoints_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }
        
    function partners_list()
    {
        $accountinfo = $this->session->userdata("accountinfo");
        $account_arr = (array) $this->db->get_where("accounts", array(
            "id" => $accountinfo['id'],
            "deleted" => "0",
            "status" => "0"
        ))->first_row();
        if (empty($account_arr)) {
            $this->session->sess_destroy();
            $this->load->helper('cookie');
            set_cookie('post_info', json_encode("text"), '20');
            redirect(base_url() . "login/");
        }
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('Partners List');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields'] = $this->api_endpoints_form->build_partners_list_for_admin();
        $data["grid_buttons"] = $this->api_endpoints_form->build_partner_grid_buttons();
        $data['form_search'] = $this->form->build_serach_form($this->api_endpoints_form->get_partners_search_form());
        $this->load->view('view_partners_list', $data);
    }
    
    function partners_list_json()
    {
        $json_data = array();
        $count_all = $this->api_endpoints_model->getpartners_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->api_endpoints_model->getpartners_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        $grid_fields = json_decode($this->api_endpoints_form->build_partners_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    function partners_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);
    
        if ($this->input->post('advance_search', TRUE) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('partners_list_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'api_endpoints/partners_list/');
        }
    }
    
    function partners_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('account_search', "");
    }
    
    function partners_endpoints_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);
    
        if ($this->input->post('advance_search', TRUE) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('partners_endpoints_list_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'api_endpoints/partners_endpoints_list/');
        }
    }
    
    function partners_endpoints_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('account_search', "");
    }

	function partners_add($type = "")
	{
		$data['username'] = $this->session->userdata('user_name');
		$data['flag'] = 'create';
		$data['page_title'] = gettext('Create Partner');
		$data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_form_fields(), '');
		$this->load->view('view_partners_add_edit', $data);
	}

	function partners_edit($edit_id = '')
	{
		$accountinfo = $this->session->userdata('accountinfo');
		if ($accountinfo['type'] == - 1 || $accountinfo['type'] == 2) {
			$data['page_title'] = gettext('Edit Partner');
			$where = array(
				'id' => $edit_id
			);
			$account = $this->db_model->getSelect("*", "api_partners", $where);
			foreach ($account->result_array() as $key => $value) {
				$edit_data = $value;
			}
			$data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_form_fields($edit_id), $edit_data);
			$this->load->view('view_partners_add_edit', $data);
		} else {
			$this->session->set_flashdata('flux_notification', gettext('Permission Denied!'));
			redirect(base_url() . 'api_endpoints/partners_list/');
			exit();
		}
	}

    function partners_save($add_array = false) 
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$add_array = $this->input->post();
			$current_date = gmdate("Y-m-d H:i:s");
			$partner_name = $add_array['partner_name'];
			$data['partner_name'] = $partner_name;			
			$data['edit_id']     = $add_array['id'];
		$data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_form_fields($add_array['id']), $add_array);
		if ($add_array['id'] != '') {
				$data['page_title'] = gettext('Edit '.$partner_name);
			if ($this->form_validation->run() == FALSE) {
				$data['validation_errors'] = validation_errors();
			} 
			else {
				$this->api_endpoints_model->edit_partners($add_array, $add_array['id']);
					$this->session->set_flashdata('flux_errormsg', $add_array["partner_name"].' '.gettext('Updated successfully!'));

					redirect(base_url().'api_endpoints/partners_list/');
				exit();
			}
				$data["account_data"]["0"] = $add_array;
				$this->load->view('view_partners_add_edit', $data);
			} 
		else {
				$data['page_title'] = gettext('Create API Partner');
			if ($this->form_validation->run() == FALSE) {
				$data['validation_errors'] = validation_errors();
			} 
			else {
					$last_id = $this->api_endpoints_model->add_partners($add_array);
					$this->session->set_flashdata('flux_errormsg', gettext('Partner'). ' '.$add_array["partner_name"].' '.gettext('Added Successfully!'));

					redirect(base_url().'api_endpoints/partners_list/');
				exit();
			}
				$this->load->view('view_partners_add_edit', $data);
			}
		} 
		else {
			redirect(base_url().'api_endpoints/partners_list/');
		}
	}

	function partners_delete_multiple()
	{
	    $ids = $this->input->post("selected_ids", true);
	    $where = "id IN ($ids)";
	    $this->db->where($where);
	    echo $this->db->delete("api_partners");
	}

    function partners_endpoints_add($type = "")
    {
        $data['username'] = $this->session->userdata('user_name');
        $data['flag'] = 'create';
        $data['page_title'] = gettext('Create Partner Endpoint');
        $data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_endpoints_form_fields(), '');
        $this->load->view('view_partners_endpoints_add_edit', $data);
    }

    function partners_endpoints_edit($edit_id = '')
    {
        $accountinfo = $this->session->userdata('accountinfo');
        if ($accountinfo['type'] == - 1 || $accountinfo['type'] == 2) {
            $data['page_title'] = gettext('Edit Partner Endpoint');
            $where = array(
                'id' => $edit_id
            );
            $account = $this->db_model->getSelect("*", "endpoints", $where);
            foreach ($account->result_array() as $key => $value) {
                $edit_data = $value;
            }
            $data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_endpoints_form_fields($edit_id), $edit_data);
            $this->load->view('view_partners_endpoints_add_edit', $data);
        } else {
            $this->session->set_flashdata('flux_notification', gettext('Permission Denied!'));
            redirect(base_url() . 'api_endpoints/partners_endpoints_list/');
            exit();
        }
    }

	function partners_endpoints_save()
	{
		$add_array = $this->input->post();
		$data['form'] = $this->form->build_form($this->api_endpoints_form->get_partners_endpoints_form_fields($add_array['id']), $add_array);
		if ($add_array['id'] != '') {
			$data['page_title'] = gettext('Edit Partner Endpoint');
			if ($this->form_validation->run() == FALSE) {
				$data['validation_errors'] = validation_errors();
				echo $data['validation_errors'];
				exit();
			} 
			else {
//                $data['product_rate_group'] = $this->db_model->build_dropdown("id,name", "pricelists", "", $where_arr);
				$this->api_endpoints_model->edit_partners_endpoints($add_array, $add_array['id']);
				echo json_encode(array(
					"SUCCESS" => $add_array["nome"] .' '. gettext("Enpoint Updated Successfully!")
				));
				exit();
			}
		} 
		else {
			$data['page_title'] = gettext('Partner Endpoint Details');
			if ($this->form_validation->run() == FALSE) {
				$data['validation_errors'] = validation_errors();
				echo $data['validation_errors'];
				exit();
			} else {
				$this->api_endpoints_model->add_partners_endpoints($add_array);
				echo json_encode(array(
					"SUCCESS" => $add_array["nome"] .' '. gettext("Enpoint Added Successfully!")
				));
				exit();
			}
		}
	}

	function partners_endpoints_delete_multiple()
	{
	    $ids = $this->input->post("selected_ids", true);
	    $where = "id IN ($ids)";
	    $this->db->where($where);
	    echo $this->db->delete("endpoints");
	}

    function api_endpoints_add($type = "")
    {
        $data['username'] = $this->session->userdata('user_name');
        $data['flag'] = 'create';
        $data['page_title'] = gettext('Create API Endpoint');
        $data['form'] = $this->form->build_form($this->api_endpoints_form->get_api_endpoints_form_fields(), '');
        $this->load->view('view_api_endpoints_add_edit', $data);
    }

    function api_endpoints_edit($edit_id = '')
    {
        $accountinfo = $this->session->userdata('accountinfo');
        if ($accountinfo['type'] == - 1 || $accountinfo['type'] == 2) {
            $data['page_title'] = gettext('Edit API Endpoint');
            $where = array(
                'id' => $edit_id
            );
            $account = $this->db_model->getSelect("*", "api_endpoints", $where);
            foreach ($account->result_array() as $key => $value) {
                $edit_data = $value;
            }
            $data['form'] = $this->form->build_form($this->api_endpoints_form->get_api_endpoints_form_fields($edit_id), $edit_data);
            $this->load->view('view_api_endpoints_add_edit', $data);
        } else {
            $this->session->set_flashdata('flux_notification', gettext('Permission Denied!'));
            redirect(base_url() . 'api_endpoints/api_endpoints_list/');
            exit();
        }
    }

    function api_endpoints_save($add_array = false) 
    {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $add_array = $this->input->post();
			$current_date = gmdate("Y-m-d H:i:s");
			$endpoint_name = $add_array['endpoint_name'];
			$data['endpoint_name'] = $endpoint_name;			
			$data['edit_id']     = $add_array['id'];
        $data['form'] = $this->form->build_form($this->api_endpoints_form->get_api_endpoints_form_fields($add_array['id']), $add_array);
        if ($add_array['id'] != '') {
				$data['page_title'] = gettext('Edit '.$endpoint_name);
            if ($this->form_validation->run() == FALSE) {
                $data['validation_errors'] = validation_errors();
            } 
            else {
                $this->api_endpoints_model->edit_api_endpoints($add_array, $add_array['id']);
					$this->session->set_flashdata('flux_errormsg', $add_array["endpoint_name"].' '.gettext('Updated successfully!'));

				redirect(base_url().'api_endpoints/api_endpoints_list/');
				exit();
            }
				$data["account_data"]["0"] = $add_array;
				$this->load->view('view_api_endpoints_add_edit', $data);
			} else {
				$data['page_title'] = gettext('Create API Endpoint');
            if ($this->form_validation->run() == FALSE) {
                $data['validation_errors'] = validation_errors();
            } else {
					$last_id = $this->api_endpoints_model->add_api_endpoints($add_array);
					$this->session->set_flashdata('flux_errormsg', $add_array["endpoint_name"].' '.gettext('Added Successfully!'));

				redirect(base_url().'api_endpoints/api_endpoints_list/');
				exit();
            }
				$this->load->view('view_api_endpoints_add_edit', $data);
			}
		} else {
			redirect(base_url().'api_endpoints/api_endpoints_list/');
        }
    }

    function api_endpoints_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);

        if ($this->input->post('advance_search', TRUE) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('api_endpoints_list_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'api_endpoints/api_endpoints_list/');
        }
    }

    function api_endpoints_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('account_search', "");
    }

    function api_endpoints_remove($id)
    {
        $this->api_endpoints_model->remove_accessnumber($id);
        $this->db->delete("api_endpoints", array(
            "access_number" => $id
        ));
        $this->session->set_flashdata('flux_notification', gettext('Accessnumber Removed Successfully!'));
        redirect(base_url() . 'api_endpoints/api_endpoints_list/');
    }

    function api_endpoints_delete_multiple()
    {
        $ids = $this->input->post("selected_ids", true);
        $where = "id IN ($ids)";
        $this->db->where($where);
        echo $this->db->delete("api_endpoints");
    }
    
    function set_force_endpoint($endpointid, $partnerid)
    {
        foreach ($partnerid as $id) {
            $endpoint_arr = array(
                "partner_id" => $id,
                "endpoint_id" => $endpointid
            );
            $this->db->insert("endpoints", $endpoint_arr);
        }
    }
    
    function endpoint_set_force_partner($add_array, $endpoints_id)
    {
        $partner_id = explode(",", $add_array['partner_id']);
        $partner_count = count($partner_id);
        foreach ($partner_id as $key => $value) {
            if ($value != 0) {
                $insert_array = array(
                    "endpoint_id" => $endpoints_id,
//                    "pricelist_id" => 0,
                    "partner_id" => $value
//                    "percentage" => $percentage[$key]
                );
                $this->db->insert("endpoints", $insert_array);
            }
        }
    }
        
    function api_test_form($edit_id = '')
    {
        $data['page_title'] = gettext('Test API Endpoint');
        $accountinfo = $this->session->userdata ( "accountinfo" );        
		$add_array = $this->db_model->getSelect ( "*", " api_endpoints", array ('id' => $edit_id));
		
		
		
		if ($add_array->num_rows > 0) {
			$endpoint_info = ( array ) $add_array->first_row ();
			$data['endpoint_info']=$endpoint_info;
			$data['edit_id']=$edit_id;
			$data['partner_id']=$endpoint_info['partner_id'];
			$data['endpoint_auth']=$endpoint_info['endpoint_auth'];

			$partner = $this->common->get_field_name("partner_name","api_partners",array("id"=>$endpoint_info['partner_id']));
			$where_arr = array("partner_id"=>$endpoint_info['partner_id'],"status"=>0);
			$data['destination_endpoints'] = $this->db_model->build_dropdown_reseller("id,nome", "endpoints", "where_arr",  $where_arr);
			$data['partner_name']=$partner;
			$data['accountinfo']=$accountinfo;
            $data['session_data'] = json_encode($data);	
			$this->load->view ( 'view_api_request', $data);
		} 		
    }
    
    function get_endpoint_base_url()
    {
        $this->permission->check_web_record_permission($edit_id, 'api_endpoints', "api_endpoints/api_activity_list/");
        $endpoint_id = $this->input->post('endpoint_id');
//        $this->load->database();
        $query = $this->db->get_where('endpoints', ['id' => $endpoint_id]);
        if ($query->num_rows()) {
            $row = $query->row();
            $base_url = $row->base_url;
            $last_segment = end(explode('/', rtrim($base_url, '/'))); // exemplo: retorna 'cdr'
            $qtype = $last_segment . '.id';
    
            $response = [
                'qtype' => $qtype,
                'body' => [
                    'qtype' => $qtype,
                    'query' => '0',
                    'oper' => '>',
                    'page' => '1',
                    'rp' => '10000',
                    'sortname' => $qtype,
                    'sortorder' => 'desc'
                ]
            ];
            echo json_encode($response);
        } else {
            echo json_encode(['error' => 'Endpoint não encontrado']);
        }
    }
 	 	
 	
 	function getendpoint($endpoint_id = '') {
    if (!$endpoint_id) {
        echo json_encode(['error' => 'ID inválido']);
        return;
    }

    $query = $this->db_model->getSelect("*", "endpoints", array(
        'id' => $endpoint_id
    ));

    if ($query->num_rows()) {
        $row = $query->row();
        $base_url = $row->base_url;
        $parts = explode('/', rtrim($base_url, '/'));
        $last_segment = end($parts);
        $qtype = $last_segment . '.id';

        $response = array(
            'qtype' => $qtype,
            'body' => array(
                'qtype' => $qtype,
                'query' => '0',
                'page' => '1',
                'rp' => '20',
                'sortname' => $qtype,
                'sortorder' => 'desc'
            )
        );
        echo json_encode($response);
    } else {
        echo json_encode(array('error' => 'Endpoint não encontrado'));
    }
		}
 	
	function api_test_send()
    {
    $url_endpoint = rtrim($this->input->post('endpoint_url'), '/');
    $destination_endpoint = $this->input->post('destination_endpoints');

    if (is_array($destination_endpoint)) {
        $destination_endpoint = reset($destination_endpoint);
    }

    $destination_url = ltrim($this->common->get_field_name("base_url", "endpoints", array("id" => $destination_endpoint)), '/');
    $url = $url_endpoint . '/' . $destination_url;

    $method = strtoupper($this->input->post('method') ?? 'GET');
    $auth_type = $this->input->post('endpoint_auth');
    $user = $this->input->post('endpoint_user');
    $pass = $this->input->post('endpoint_password');
    $token = $this->input->post('endpoint_token');
    $headers_input = $this->input->post('headers');
    $body = $this->input->post('body');

    $headers = [];
    if (!empty($headers_input['key']) && !empty($headers_input['value'])) {
        foreach ($headers_input['key'] as $i => $key) {
            $value = $headers_input['value'][$i] ?? '';
            if (!empty($key)) {
                $headers[] = "$key: $value";
            }
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code == 200 || $http_code == 201) {
    $request_status = '0';
    } else {
    $request_status = '1';
    
    }

    $request_data = array(
        'url' => $url,
        'method' => $method,
        'headers' => json_encode($headers_input),
        'body' => $body,
        'status' => $request_status,
        'created_at' => date('Y-m-d H:i:s')
    );
    $this->db->insert('api_test_requests', $request_data);
    $request_id = $this->db->insert_id();

    if ($http_code != 200 || $http_code != 201) {
    $curl_error = $http_code;
    }

    $response_data = array(
        'request_id' => $request_id,
        'http_code' => $http_code,
        'response_body' => $response,
        'error' => $curl_error,
        'status' => $request_status,
        'created_at' => date('Y-m-d H:i:s')
    );
    $this->db->insert('api_test_responses', $response_data);
    
    $this->api_model->save_api_log($url, $body, $response, 'api_test_responses', $http_code );
    $data = [
        'http_code'       => $http_code,
        'total_time'      => $total_time,
        'curl_error'      => $curl_error,
        'response_body'   => $response,
        'request_summary' => json_encode([
            'url'                 => $url,
            'method'              => $method,
            'endpoint_auth'       => $auth_type,
            'endpoint_user'       => $user,
            'destination_id'      => $destination_endpoint,
            'destination_url'     => $destination_url,
            'headers'             => $headers,
            'body'                => json_decode($body, true) ?? $body
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ];

    $this->load->view('view_api_request_result', $data);
}
 
    function get_endpoint_info($endpoints_id)
    {
     $id = $this->$endpoints_id;
     if (!$id || !is_numeric($id)) {
         echo json_encode(['error' => 'ID inválido']);
         return;
     }
 
//     $this->load->model('common');
 
     $endpoint_nome = $this->common->get_field_name("descricao", "endpoints", array("id" => $id));
     $base_url = $this->common->get_field_name("base_url", "endpoints", array("id" => $id));
 
     if (!$endpoint_nome || !$base_url) {
         echo json_encode(['error' => 'Registro não encontrado']);
         return;
     }
 
     echo json_encode([
         'nome' => $endpoint_nome,
         'base_url' => ltrim($base_url, '/')
     ]);
 }
 
    function get_buttons_api_endpoints($id)
    {    
        if ($this->session->userdata('logintype') == '0'){  
            $ret_url = '<a href="' . base_url () . 'api_endpoints/api_test_form/'.$id.'" class="btn btn-royelblue btn-sm"  rel="" title="'.gettext('Test API').'">&nbsp;<i class="fa fa-rotate-right fa-fw"></i></a>&nbsp;';   
        }
        else{  
            $ret_url = '<a href="' . base_url () . 'api_endpoints/api_test_form/'.$id.'" class="btn btn-royelblue btn-sm"  rel="facebox_medium" title="'.gettext('Test API').'"><i class="fa fa-rotate-right fa-fw"></i></a>';  
        }       
        return $ret_url;    
    }
    
    function api_activity_list()
    {
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('API Activity Report');
        $data['search_flag'] = true;
        $data['report_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields'] = $this->api_endpoints_form->build_api_activity_list_for_admin();
        $data["grid_buttons"] = $this->api_endpoints_form->build_grid_buttons_admin();
        $data['form_search'] = $this->form->build_serach_form($this->api_endpoints_form->get_search_api_endpoints_form());
        $this->load->view('view_api_activity_list', $data);
    }

    function api_activity_list_json()
    {
        $json_data = array();
        $count_all = $this->api_endpoints_model->get_api_activity_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data = $paging_data["json_paging"];
        $query = $this->api_endpoints_model->get_api_activity_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        $grid_fields = json_decode($this->api_endpoints_form->build_api_activity_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    function api_activity_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);

        if ($this->input->post('advance_search', TRUE) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            if (isset($action['created_at'][0]) && $action['created_at'][0] != "") {
                $action['created_at'][0] = $this->common->convert_GMT_new($action['created_at'][0]);
            }
            if (isset($action['created_at'][1]) && $action['created_at'][1] != '') {
                $action['created_at'][1] = $this->common->convert_GMT_new($action['created_at'][1]);
            }
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('api_activity_search', $action);
        }
        if (@$ajax_search != 1) {
            redirect(base_url() . 'api_endpoints/api_activity_list/');
        }
    }

    function api_activity_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->unset_userdata('audit_list_search');
    }
    
    function api_activity_view($edit_id = '')
    {
        $this->permission->check_web_record_permission($edit_id, 'api_endpoints', "api_endpoints/api_activity_list/");
        $data['page_title'] = gettext('View API Log');
        $where = array(
            'id' => $edit_id
        );
        $account = $this->db_model->getSelect("*", "view_api_logs", $where);
        if ($account->num_rows() > 0) {
            foreach ($account->result_array() as $key => $value) {
                $edit_data = $value;
            }
            if ($edit_data['status'] == 1) {
                $edit_data['status'] = gettext('Request Error');
            } else {
                $edit_data['status'] = gettext('Request OK');
            }
            $data['form'] = $this->form->build_form($this->api_endpoints_form->get_form_fields_api_activity_view(), $edit_data);

            $this->load->view('view_api_activity_add_edit', $data);
        } else {
            redirect(base_url() . 'api_endpoints/api_activity_list/');
        }
    }
    
    function api_activity_edit($edit_id = '')
    {
        $data['page_title'] = gettext('Edit API Activity');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $where = array(
                'id' => $edit_id,
                "reseller_id" => $reseller
            );
        } else {
            $where = array(
                'id' => $edit_id
            );
        }
        $account = $this->db_model->getSelect("*", "view_api_logs", $where);
        if ($account->num_rows() > 0) {
            foreach ($account->result_array() as $key => $value) {
                $edit_data = $value;
            }
            $data['form'] = $this->form->build_form($this->email_form->get_form_fields_api_activity(), $edit_data);
            $this->load->view('view_api_activity_add_edit', $data);
        } else {
            redirect(base_url() . 'api_endpoints/api_activity_list/');
        }
        redirect(base_url() . 'api_endpoints/api_activity_list/');
    }
    
}
?>
