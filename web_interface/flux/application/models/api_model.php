<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2025 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// Flux SBC Version 4.2 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option ) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################
class Api_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->library('common');
        $this->load->model('common_model');
        $this->load->library('flux/order');
        $this->load->library('flux/signup_lib');
        $this->load->library('flux_log');
        $this->load->database();
    }

    /**
     * Inserts or updates an account in the 'accounts' table based on customer data.
     */
    public function upsert_account($customer_data) {
    $this->flux_log->write_log('api_model', "Upsert Account Start.");
    $existing_account = $this->db->get_where('accounts', ['id_external' => $customer_data['id'], 'deleted' => '0'])->row();
		if($customer_data['senha'] == ''){
      $customer_data['senha'] = $this->common->generate_password();      
      $encoded_password = $this->common->encode($customer_data['senha'] );
      } 
    else {
		$password = !empty($customer_data['senha']) ? $customer_data['senha'] : $this->common->generate_password();
		$encoded_password = $this->common->encode($password);
      }		
		$telefone = $this->sanitize_string($customer_data['fone']);
		$telefone_celular = $this->sanitize_string($customer_data['telefone_celular']);
		$razaoConvert = $this->sanitize_string($customer_data['razao']);
		$contatoConvert = $this->sanitize_string($customer_data['contato']);
		$emailConvert = strtolower($customer_data['email']);
		if (!empty($emailConvert)) {
		    $emails = preg_split('/[,;]+/', $emailConvert);
		    
		    if (count($emails) > 1) {
		        $customer_data['email'] = trim($emails[0]);
		        
		        $this->flux_log->write_log(
		            'api_model',
		            "Multiple emails found for customer ID {$customer_data['id']}. Using the first one: {$customer_data['email']}"
		        );
		    } else {
		        $customer_data['email'] = trim($emails[0]);
		    }
		}        
    $pin_number              = '';
		$pin_generate            = Common_model::$global_config['system_config']['generate_pin'];
		if ($pin_generate == 0 ) {
			$numberlength = common_model::$global_config['system_config']['pinlength'];
			$numberlength = ($numberlength < 6)?6:common_model::$global_config['system_config']['pinlength'];
			$pin_number   = rand(pow(10, $numberlength-1), pow(10, $numberlength)-1);
		}
		$account_number = preg_replace('/[^0-9]/', '', $customer_data['cnpj_cpf']);
        $account_data = [
            'id_external'       => $customer_data['id'],
            'company_name' => (!empty($customer_data['fantasia'])) ? $customer_data['fantasia'] : $customer_data['razao'],
            'first_name'        => $customer_data['razao'],
            'last_name'         => $customer_data['razao'],
        'password'          => $encoded_password,
			      'email' => (!empty($customer_data['email'])) ? $customer_data['email'] : $customer_data['id'] . ''.$razaoConvert.'@flux.net.br',            
			      'notification_email' => (!empty($customer_data['email'])) ? $customer_data['email'] : $customer_data['id'] . ''.$razaoConvert.'@flux.net.br',
            'telephone_1' => (!empty($telefone)) ? $telefone : '5155555555',
			      'telephone_2' => (!empty($telefone_celular)) ? $telefone_celular : '5155555555',
            'address_1' => $customer_data['endereco'] . ', ' . $customer_data['numero'] . ' - ' . $customer_data['bairro'],
            'city' => $this->get_city_name($customer_data['cidade']),
            'province' => $this->get_uf_name($customer_data['cidade']),
            'postal_code' => $customer_data['cep'],
            'creation'          => $customer_data['data_cadastro'],
        'reseller_id'       => isset($customer_data['reseller_id']) ? $customer_data['reseller_id'] : 0,        
            'status'            => ($customer_data['ativo'] == 'S') ? 0 : 1,
            'deleted'           => 0,
            'deleted_date'      => '1000-01-01 00:00:00',
        ];
        
        if ($existing_account) {
            $this->db->where('id_external', $customer_data['id']);
            $this->db->update('accounts', $account_data);
            $this->flux_log->write_log('api_model', 'Account updated for external ID: ' . $customer_data['id']);
        
        $this->db->select ( 'id' );
        $this->db->where ( 'id_external', $customer_data['id'] );
        $accountid = ( array ) $this->db->get ( 'accounts' )->first_row ();
                    
        } 
        else {
            $this->flux_log->write_log('api_model', 'New account creation: ' . $customer_data['id']);
        $existing_account_number = $this->db->get_where('accounts', ['number' => $account_number])->row();    
        if ($existing_account_number) {
        $this->flux_log->write_log('duplicate_number', json_encode($account_number));
        $customer_data['cnpj_cpf'] = $this->common->find_uniq_rendno_customer(10, 'number ', 'accounts');                        
        } 
        else {
        $customer_data['cnpj_cpf'] = $account_number;        
        }    
            $default_data = [
            'reseller_id'       => isset($customer_data['reseller_id']) ? $customer_data['reseller_id'] : 0,
                'pricelist_id'      => common_model::$global_config['system_config']['default_signup_rategroup'] ?: 1,
                'country_id'        => 28,
            'number'            => $customer_data['cnpj_cpf'],
                'currency_id'       => 16,
                'timezone_id'       => 78,
            'credit_limit'      => common_model::$global_config['system_config']['balance'] ?: '1000.0000',
            'balance'           => common_model::$global_config['system_config']['balance'] ?: '1000.0000',
                'maxchannels'       => 3,
                'charge_per_min'    => 0,
                'invoice_day'       => 1,
                'posttoexternal'    => 1,
                'sweep_id'          => 2,
                'type'              => 0,
            'notifications'     => 1,
                'password'          => $encoded_password,
                'pin'               => $pin_number,
            'sip_device_flag'   => 0,
                'deleted'           => 0,
                'deleted_date'      => '1000-01-01 00:00:00',
            'id_external'       => $customer_data['id'],
            'company_name' => (!empty($customer_data['fantasia'])) ? $customer_data['fantasia'] : $customer_data['razao'],
            'first_name'        => $customer_data['razao'],
            'last_name'         => $customer_data['razao'],
            'email' => (!empty($customer_data['email'])) ? $customer_data['email'] : $customer_data['id'] . ''.$razaoConvert.'@flux.net.br',            
            'notification_email' => (!empty($customer_data['email'])) ? $customer_data['email'] : $customer_data['id'] . ''.$razaoConvert.'@flux.net.br',
            'telephone_1' => (!empty($telefone)) ? $telefone : '5155555555',
            'telephone_2' => (!empty($telefone_celular)) ? $telefone_celular : '5155555555',
            'address_1' => $customer_data['endereco'] . ', ' . $customer_data['numero'] . ' - ' . $customer_data['bairro'],
            'city' => $this->get_city_name($customer_data['cidade']),
            'province' => $this->get_uf_name($customer_data['cidade']),
            'postal_code' => $customer_data['cep'],
            'creation'          => $customer_data['data_cadastro'],
            'status'            => ($customer_data['ativo'] == 'S') ? 0 : 1,
            ];
        $accountid = $this->signup_lib->proxy_create_account($default_data);
        $this->flux_log->write_log('api_model', 'New account created for external ID: ' . $customer_data['id'] . ' AccountID: '.$accountid);
        }
    return $accountid;
    }

    /**
     * Inserts or updates a SIP device and its associated DID product.
     */
    public function upsert_device($device_id) {
    $this->flux_log->write_log('api_model', 'upsert_device process.');
    $this->flux_log->write_log('api_model', "upsert_device process {$device_id}");
    $device = $this->db->get_where('voip_sippeers', ['id' => $device_id])->row();                
    if (!$device){
    $this->flux_log->write_log('api_model', 'upsert_device process.');
    return false;
    } 
    else {
    $account = $this->db->get_where('accounts', ['id_external' => $device->cliente_id, 'deleted' => '0'])->row();
    
    if (!$account) {
        $this->flux_log->write_log('api_model', "Account not found for customer ID: {$device->cliente_id}. Signaling for customer sync.");        
        return ['needs_customer_sync' => $device->cliente_id]; 
    }

    $existing_device = $this->db->get_where('sip_devices', ['id_sip_external' => $device->id])->row();
    $password = !empty($device->secret) ? $device->secret : $this->common->generate_password();
    $encoded_password = $this->common->encode($password);

		$pin_generate = common_model::$global_config['system_config']['generate_pin'];
		if ($pin_generate == 0 ) {
			$pin = (common_model::$global_config['system_config']['pinlength'] < 6) ? 6 : common_model::$global_config['system_config']['pinlength'];
			$pin_number = $this->common->find_uniq_rendno_customer($pin, 'number', 'accounts');
		}
		
		$digits=5;
		$random_password = rand(pow(10, $digits-1), pow(10, $digits)-1);
		$current_date = gmdate("Y-m-d H:i:s");
		
		$this->db->select('id');
		$this->db->from('accounts');
		$this->db->where('id_external', $device->cliente_id);
		$account_device = $this->db->get()->row();
		if ($account_device) {
		$account_id = $account_device->id;
		} 
		else {
		$account_id = $device->cliente_id;      
		}
    $username = $this->sanitize_string($device->name);
    if(empty($username)) {
    $username = $this->sanitize_string($device->callerid);    
    }
    if(!empty($username)){		    
    if ($existing_device) {
            $this->flux_log->write_log('existing_device', json_encode($existing_device));
            $sip_profile_info = $this->signup_lib->_proxy_get_sip_profile();
            $device_data = [
                'accountid' => $account_id,
                'status' => ($device->ativo == 'S') ? 0 : 1,
                'username' => preg_replace('/[^0-9]/', '', $username),
                'sip_profile_id' => $sip_profile_info ['id'],
                'reseller_id'=>isset($device->reseller_id) ? $device->reseller_id : '0',
                'id_sip_external' => isset($device->id) ? $device->id : '0',				
                'dir_params' => json_encode(array(
                  'password'=> $device->secret,
                  'vm-enabled' => 'false',
                  'vm-password'=> $random_password,
                  'vm-mailto'=> '',
                  'vm-attach-file'=>'false',
                  'vm-keep-local-after-email'=>'false',
                  'vm-email-all-messages'=>'false'
                )),
                'dir_vars'=>json_encode(array(
                  'effective_caller_id_name' => $device->cliente_razao,
                  'effective_caller_id_number' => $device->callerid,
                  'user_context'=>'default'
                )),
                'codec' => 'G729,PCMA,PCMU',
                'creation_date'=>$current_date,
                'last_modified_date'=>$current_date
            ];
            
            
            $this->db->where('id_sip_external', $existing_device->id_sip_external)->update('sip_devices', $device_data);
            $this->flux_log->write_log('api_model', "SIP device updated for external ID: {$device->id}");
            $this->flux_log->write_log('api_model', "SIP device name: {$device->name}");
            if ($account) {            
            $this->_create_did_product_and_order($device, $account);
            }
            return "updated";         
        } 
    else {        
            $device_data = [
            'id_sip_external' => $device->id,
            'number' => preg_replace('/[^0-9]/', '', $username),
            'reseller_id'=>isset($device->reseller_id) ? $device->reseller_id : '0',
            'pricelist_id'      => common_model::$global_config['system_config']['default_signup_rategroup'] ?: 1,
            'accountid' => $account_id,
            'country_id' => '28',
            'currency_id' => '16',
            'timezone_id' => '78',
            'credit_limit' => '100.00',
            'sweep_id' => '2',
            'id_external' => $device->cliente_id,
            'posttoexternal' => '1',
            'type' => '0',
            'notifications' => '1',
            'sip_device_flag' => '1',
            'password' => $encoded_password,
            'status' => ($device->ativo == 'S') ? 0 : 1
            ];
            $sip_profile = $this->signup_lib->_proxy_get_sip_profile();
            if ($sip_profile) {
                $this->signup_lib->_proxy_create_sip_device($device_data, $sip_profile);
                $this->flux_log->write_log('api_model', "New SIP device created for external ID: {$device->id}");
                if ($account) {
                $this->_create_did_product_and_order($device, $account);
                }
                return "inserted";
            }
        }
        }
    else {
    return false;
    }
    }
    }        

    private function _create_did_product_and_order($device, $account) {
        $this->flux_log->write_log('api_model', "create_did_product_and_order start");
        
        if ($this->db->get_where('dids', ['number' => $device->name])->num_rows() > 0) return;

        $product_data = [
        'name' => $device->name,
        'country_id' => 28,
        'product_category' => 4,
        'status' => 0,
        'can_purchase' => 0,
        'billing_type' => 1,
        'billing_days' => 30,
        'creation_date' => gmdate("Y-m-d H:i:s"),
        'last_modified_date' => gmdate("Y-m-d H:i:s")
        ];
        $this->db->insert("products", $product_data);
        $product_id = $this->db->insert_id();

        $account_did_id = $account->id;
        $account_city = $account->city;
        $account_province = $account->province;

        $did_data = [
        'number' => $device->name,
        'accountid' => $account_did_id,
        'country_id' => 28,
        'city' => $account_city,
        'province' => $account_province,
        'provider_id' => 3,
        'status' => 0,        
        'extensions' => $device->name,
        'product_id' => $product_id
        ];

        $this->db->insert("dids", $did_data);

        $this->order->confirm_order_proxy(['product_id' => $product_id,'payment_by' => "Account Balance"], $account_did_id, 1);
        $this->flux_log->write_log('api_model', "Created DID, Product, and Order for number: {$device->name}");
    }

	  public function salvar_planos_voip_externos($planos) {
	foreach ($planos as $registro) {
		$data = [
			'id_plataforma' => $registro['id_plataforma'],
			'descricao'     => $registro['descricao']
		];


		$this->db->where('id_plataforma', $registro['id_plataforma']);
		$query = $this->db->get('planos_voip_externos');

		if ($query->num_rows() > 0) {
			$this->db->where('id_plataforma', $registro['id_plataforma']);
			$this->db->update('planos_voip_externos', $data);
		} else {
			$this->db->insert('planos_voip_externos', $data);
		}
	}
}

    public function get_city_name($city_id) {
        $city = $this->db->select('cidade')->get_where('view_cidade', ['cidade_id' => $city_id])->row();
        return $city ? $city->cidade : 'Cidade Desconhecida';
    }

    public function get_uf_name($city_id) {
        $province = $this->db->select('estado')->get_where('view_cidade', ['cidade_id' => $city_id])->row();
        return $province ? $province->estado : 'Estado Desconhecido';
    }
    
    public function save_api_log($url, $payload, $response, $type, $http_code = null ) {
        $data = [
            'url'       => $url,
            'payload'   => is_string($payload) ? $payload : json_encode($payload),
            'response'  => $response,
            'http_code' => $http_code,
            'type'      => $type,
            'status'    => ($http_code >= 200 && $http_code < 300 ) ? 0 : 1,
        ];
        $this->db->insert('api_logs', $data);
    }
    
    public function sanitize_string($string) {
    
    $string = json_decode('"' . $string . '"');

    $string = trim($string);

    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

    $string = strtolower($string);

    $string = preg_replace('/[^a-z0-9]/', '', $string);

    return $string;
}
}
