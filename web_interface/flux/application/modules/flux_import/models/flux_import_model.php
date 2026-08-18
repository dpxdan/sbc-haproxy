<?php

// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
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
class Flux_import_model extends CI_Model
{
    function __construct()
    {
		parent::__construct();
        $this->load->library('flux_log');
        $this->load->library('flux/order');
	}

    function get_provider_id(string $name): int
    {
        $this->db->select('id');
        $this->db->where('type', 3);
        $this->db->where('status', 0);
        $this->db->where('deleted', 0);
        $this->db->like('company_name', $name, 'both');
        $row = (array) $this->db->get('accounts')->first_row();
        return (int) ($row['id'] ?? 0);
    }

    function get_country_id(string $country_name): int
    {
        $this->db->select('id');
        $this->db->where('country', $country_name);
        $row = (array) $this->db->get('countrycode')->first_row();
        return (int) ($row['id'] ?? 0);
    }

    function get_call_type_code(string $call_type): int
    {
        $this->db->select('call_type_code');
        $this->db->where('call_type', $call_type);
        $row = (array) $this->db->get('did_call_types')->first_row();
        return (int) ($row['call_type_code'] ?? 0);
    }

    function account_exists(string $number): int
    {
        $this->db->select('id');
        $row = (array) $this->db->get_where('accounts', [
            'number'  => $number,
            'deleted' => 0,
        ])->first_row();
        return (int) ($row['id'] ?? 0);
    }

    function did_exists(string $number): bool
    {
        return $this->db->get_where('dids', ['number' => $number])->num_rows() > 0;
    }

    function insert_account(array $data): int
    {
        $this->flux_log->write_log('insert_account', json_encode($data));
        $this->load->library("flux/signup_lib");
            
        $last_id = $this->signup_lib->create_account($data);
        if (empty($last_id)) {
            $last_id = $this->db->insert_id();
        }
        return $last_id;
    }

    function insert_product(array $data): int
    {
        $this->flux_log->write_log('insert_product', json_encode($data));
        $this->db->insert('products', $data);
        return $this->db->insert_id();
    }

    function insert_did(array $data): void
    {
        $this->flux_log->write_log('insert_did', json_encode($data));
        $this->db->insert('dids', $data);
        $accountinfo = $this->session->userdata('accountinfo');
        if ($data['accountid'] > 0) {
            $add_array['is_parent_billing'] = 'true';
            $add_array['product_id'] = $data['product_id'];
            $add_array['payment_by'] = 'Account Balance';
            $add_array['charge_type'] = 'DID';
            $add_array['create_invoice'] = 'false';
            $order_id = $this->order->confirm_order($add_array, $data['accountid'], $accountinfo);
        }        
    }

    function bulk_insert_dids(array $rows): void
    {
        if (!empty($rows)) {
            $this->db->insert_batch('dids', $rows);
        }
    }

    function bulk_insert_accounts(array $rows): void
    {
        if (!empty($rows)) {
            $this->db->insert_batch('accounts', $rows);
        }
    }
    
    function get_pricelist_by_cnpj(string $cnpj): int
    {
        $this->db->select('id');
        $this->db->like('name', $cnpj, 'both');
        $row = (array) $this->db->get('pricelists')->first_row();
        return (int) ($row['id'] ?? 0);
    }
    
    function create_sip_device(array $accountinfo): int
    {
        $this->flux_log->write_log('create_sip_device', json_encode([
            'number' => $accountinfo['number']
        ]));
        $this->flux_log->write_log('create_sip_device', json_encode($accountinfo));
        
        $current_date = gmdate("Y-m-d H:i:s");
        
        $this->db->select('id');
        $this->db->where('name', 'default');
        $sipprofile = (array) $this->db->get('sip_profiles')->first_row();
        
        $digits=5;
        $random_password = rand(pow(10, $digits-1), pow(10, $digits)-1);
        

        $data = [
            'username' => $accountinfo['number'],
            'reseller_id' => $accountinfo['reseller_id'],
            'accountid' => $accountinfo['id'],
    
            'dir_params' => json_encode([
                "password"=> $random_password,
                'vm-enabled' => "false",
                "vm-password"=> rand(10000,99999),
                "vm-mailto"=> $accountinfo['email'] ?? '',
                "vm-attach-file"=>"true",
                "vm-keep-local-after-email"=>"true",
                "vm-email-all-messages"=>"true"
            ]),
    
            'dir_vars'=>json_encode([
                'effective_caller_id_name' => $accountinfo['number'],
                'effective_caller_id_number' => $accountinfo['number'],
                "user_context"=>"default"
            ]),
    
            'codec' => 'G729,PCMA,PCMU',
            'status' => $accountinfo['status'] ?? 0,
            'creation_date'=>$current_date,
            'last_modified_date'=>$current_date
        ];
    
        $this->db->insert("sip_devices", $data);
    
        return $this->db->insert_id();
    }
    
}