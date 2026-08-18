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
class Sync_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->model('api_model');
        $this->load->library('flux_log');
        $this->load->library('common');
    }

    /**
     * Efficiently inserts or updates a voip_sippeers record using ON DUPLICATE KEY UPDATE.
     */
    public function replace_peer($data) {
        $this->flux_log->write_log('sync_model', 'replace_peer sync_model process.');

    $allowed_fields = [
        'id', 'cliente_id', 'name', 'username', 'host', 'context', 'type', 'nat', 'qualify',
        'disallow', 'allow', 'dtmfmode', 'secret', 'callerid', 'defaultuser', 'id_contrato',
        'ativo', 'id_plano_sip', 'id_integracao', 'cliente_razao', 'reseller_id'
    ];

    $filtered_data = array_intersect_key($data, array_flip($allowed_fields));

    $name        = trim((string)($filtered_data['name'] ?? ''));
    $defaultuser = trim((string)($filtered_data['defaultuser'] ?? ''));
    $callerid    = trim((string)($filtered_data['callerid'] ?? ''));
    $secret      = trim((string)($filtered_data['secret'] ?? ''));
    $peer_id     = $filtered_data['id'] ?? null;

    $existing_peer = [];
    if (!empty($peer_id)) {
        $existing_peer = $this->db
            ->select('name, defaultuser, callerid, secret')
            ->from('voip_sippeers')
            ->where('id', $peer_id)
            ->get()
            ->row_array() ?? [];
    }

    $existing_name        = trim((string)($existing_peer['name'] ?? ''));
    $existing_defaultuser = trim((string)($existing_peer['defaultuser'] ?? ''));
    $existing_callerid    = trim((string)($existing_peer['callerid'] ?? ''));
    $existing_secret      = trim((string)($existing_peer['secret'] ?? ''));

    $generate_random_name   = ($name === '' && $defaultuser === '' && $callerid === '' &&
                               $existing_name === '' && $existing_defaultuser === '' && $existing_callerid === '');
    $generate_random_secret = ($secret === '' && $existing_secret === '');

  
    if ($generate_random_name) {
        $final_name = $this->common->find_uniq_rendno_customer(10, 'username', 'sip_devices');
        $this->flux_log->write_log('sync_model', "Generated random peer name: {$final_name}");
    } 
    else {
        $final_name = $name
            ?: ($defaultuser
            ?: ($callerid
            ?: ($existing_name
            ?: ($existing_defaultuser ?: $existing_callerid))));
    }
    
    if ($generate_random_secret) {
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $final_secret = '';
        for ($i = 0; $i < $length; $i++) {
            $final_secret .= $characters[rand(0, strlen($characters) - 1)];
        }
        $this->flux_log->write_log('sync_model', "Generated random peer secret: {$final_secret}");
        }
    else {
        $final_secret = $secret ?: $existing_secret;
    }


    $filtered_data['name']        = $final_name;
    $filtered_data['defaultuser'] = $final_name;
    $filtered_data['callerid']    = $final_name;
    $filtered_data['secret']      = $final_secret;

    if (!empty($filtered_data)) {
        $this->upsert('voip_sippeers', $filtered_data);
        $device_account = $this->api_model->upsert_device($filtered_data['id']);
        
        if (is_array($device_account) && !empty($device_account['needs_customer_sync'])) {
                return ['needs_customer_sync' => $device_account['needs_customer_sync']]; 
            }

        $this->flux_log->write_log('sync_model', 'device_account true.');
        return true;
            }

    return false;
    }

    /**
     * Efficiently inserts or updates a customer record.
     */
    public function replace_customer($data) {
        $allowed_fields = ['id', 'razao', 'cnpj_cpf', 'email', 'telefone_celular', 'fone', 'contato', 'ativo', 'tipo_pessoa', 'endereco', 'bairro', 'cidade', 'cep', 'id_conta', 'data_cadastro', 'ultima_atualizacao', 'numero', 'senha', 'fantasia', 'reseller_id'];
        $filtered_data = array_intersect_key($data, array_flip($allowed_fields));
        
        if (!empty($filtered_data['cnpj_cpf'])) {
        $filtered_data['cnpj_cpf'] = preg_replace('/[^0-9]/', '', $filtered_data['cnpj_cpf']);        
        }
        
        
        if (!empty($filtered_data['email'])) {
    $emailConvert = strtolower($filtered_data['email']);
    $emails = preg_split('/[,;]+/', $emailConvert);
		    
		    if (count($emails) > 1) {
		        $filtered_data['email'] = trim($emails[0]);
		        
		        $this->flux_log->write_log(
		            'sync_model',
		            "Multiple emails found for customer ID {$filtered_data['id']}. Using the first one: {$filtered_data['email']}"
		        );
    } 
    else {
		        $filtered_data['email'] = trim($emails[0]);
		    }
		}                 
        
         if (empty($filtered_data['fone'])) {
            $filtered_data['fone'] = $filtered_data['telefone_celular'];            
        }
        
        
        if (isset($filtered_data['fone'])) {
           $filtered_data['fone'] = $this->api_model->sanitize_string($filtered_data['fone']);        
        }
        
        if (!empty($filtered_data)) {
            $this->upsert('clientes', $filtered_data);
            $accountid = $this->api_model->upsert_account($filtered_data);
            $this->flux_log->write_log('replace_customer_accountid', json_encode($accountid));
            return $accountid;
        }
    }

    /**
     * Efficiently inserts or updates a customer contract record.
     */
    public function replace_contract($data) {
        $this->flux_log->write_log('sync_model', 'replace_contract sync_model process.');
        $allowed_fields = ['id', 'contrato', 'data', 'data_ativacao', 'id_cliente', 'id_vd_contrato', 'status', 'ultima_atualizacao'];
        $filtered_data = array_intersect_key($data, array_flip($allowed_fields));
        if (!empty($filtered_data)) {
            $this->upsert('cliente_contrato', $filtered_data);
        }
    }

    /**
     * Efficiently inserts or updates multiple city records.
     */
    public function replace_cities($data) {
        if (empty($data)) return;
        $this->upsert_batch('cidade', $data);
    }

    /**
     * Efficiently inserts or updates multiple state (UF) records.
     */
    public function replace_states($data) {
        if (empty($data)) return;
        $this->upsert_batch('uf', $data);
    }

    /**
     * Updates device plans in a single batch query.
     */
    public function update_device_plans($records) {
        if (empty($records)) return;
        $update_data = [];
        foreach ($records as $record) {
            if (isset($record['id']) && isset($record['id_plano_sip'])) {
                $update_data[] = ['id' => $record['id'], 'id_plano_sip' => $record['id_plano_sip']];
            }
        }
        if (!empty($update_data)) {
            $this->db->update_batch('voip_sippeers', $update_data, 'id');
        }
    }

    // ==========================================================================
    // HELPER FUNCTIONS FOR EFFICIENT DATABASE OPERATIONS
    // ==========================================================================

    /**
     * Generic function to perform an "INSERT ... ON DUPLICATE KEY UPDATE" for a single row.
     *
     * @param string $table The name of the table.
     * @param array $data Associative array of data to insert/update.
     */
    private function upsert($table, $data) {
        $this->flux_log->write_log('sync_model', "upsert process. Table: {$table}");
        $columns = array_keys($data);
        $values = array_values($data);
        
        $sql = "INSERT INTO " . $this->db->protect_identifiers($table) . " (" . implode(', ', array_map([$this->db, 'protect_identifiers'], $columns)) . ") ";
        $sql .= "VALUES (" . rtrim(str_repeat('?, ', count($values)), ', ') . ") ";
        $sql .= "ON DUPLICATE KEY UPDATE ";

        $update_pairs = [];
        foreach ($columns as $col) {
            if (strtolower($col) !== 'id') {
                $update_pairs[] = $this->db->protect_identifiers($col) . " = VALUES(" . $this->db->protect_identifiers($col) . ")";
            }
        }
        $sql .= implode(', ', $update_pairs);

        $this->db->query($sql, $values);
    }

    /**
     * Generic function to perform a batch "INSERT ... ON DUPLICATE KEY UPDATE".
     *
     * @param string $table The name of the table.
     * @param array $data An array of associative arrays.
     */
    public function upsert_batch($table, $data) {
        if (empty($data)) {
            return;
        }
        
        $columns = array_keys($data[0]);
        $update_cols = array_filter($columns, function($col) { return strtolower($col) !== 'id'; });

        $sql = "INSERT INTO " . $this->db->protect_identifiers($table) . " (" . implode(', ', array_map([$this->db, 'protect_identifiers'], $columns)) . ") VALUES ";

        $value_placeholders = [];
        $all_values = [];
        $placeholder_row = '(' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')';

        foreach ($data as $row) {
            $value_placeholders[] = $placeholder_row;
            foreach ($columns as $col) {
                $all_values[] = $row[$col];
            }
        }

        $sql .= implode(', ', $value_placeholders);
        $sql .= " ON DUPLICATE KEY UPDATE ";

        $update_pairs = [];
        foreach ($update_cols as $col) {
            $update_pairs[] = $this->db->protect_identifiers($col) . " = VALUES(" . $this->db->protect_identifiers($col) . ")";
        }
        $sql .= implode(', ', $update_pairs);

        $this->db->query($sql, $all_values);
    }


    // ==========================================================================
    // DATA RETRIEVAL FUNCTION
    // ==========================================================================
        
    /**
     * Marks a batch of CDRs as sent using their call IDs.
     *
     * @param array $call_ids An array of 'id_ligacao' values to update.
     */
    public function mark_cdr_batch_as_sent($call_ids) {
        if (empty($call_ids)) {
            return;
        }
        $this->db->where_in('id_ligacao', $call_ids);
        $this->db->update('cdr', ['enviado_ixc' => 'sim']);
    }
    
    public function mark_cdr_batch_as_sent_with_id($update_data) {
        if (empty($update_data)) {
            return 0;
        }
        return $this->db->update_batch('cdr', $update_data, 'id_ligacao');
    }
    
    /**
     * Fetches a batch of unsent CDRs from the local database.
     *
     * @param int $limit The maximum number of CDRs to fetch.
     * @return array
     */

    public function get_unsent_cdrs_batch($limit,$sync_cdrs_type = '1') {
        /*
        $sync_cdrs_type: 0 - Inbound
        $sync_cdrs_type: 1 - Outbound
        $sync_cdrs_type: 2 - Both
        */
        
        if ($sync_cdrs_type == '0') {
        return $this->db->where('enviado_ixc', 'nao')
                        ->where('id_ligacao IS NOT NULL')
                        ->like('tp_chamada','DID')
                        ->order_by('calldate desc')
                        ->limit($limit)
                        ->get('cdr')
                        ->result_array();        
        }
        elseif ($sync_cdrs_type == '1') {
        return $this->db->where('enviado_ixc', 'nao')
                        ->where('id_ligacao IS NOT NULL')
                        ->not_like('tp_chamada','DID')
                        ->order_by('calldate desc')
                        ->limit($limit)
                        ->get('cdr')
                        ->result_array();        
        }
        else {
        return $this->db->where('enviado_ixc', 'nao')
                        ->where('id_ligacao IS NOT NULL')
                        ->order_by('calldate desc')
                        ->limit($limit)
                        ->get('cdr')
                        ->result_array();
        }
                        
    }
    
    /**
     * Marks a single CDR as sent and records the IXC ID.
     *
     * @param string $call_id The 'id_ligacao' of the CDR to update.
     * @param int $ixc_id The new ID returned by the IXC API.
     */
    public function mark_cdr_as_sent($call_id, $ixc_id) {
        $this->db->where('id_ligacao', $call_id);
        $this->db->update('cdr', [
            'enviado_ixc' => 'sim',
            'ixc_id'      => $ixc_id
        ]);
    }
    
    public function get_stale_ids($api_ids, $column, $table) {
        if (empty($api_ids)) return [];
        return $this->db->where_not_in($column, $api_ids)->get($table)->result_array();
    }
    
    public function get_unsent_cdrs($api_call_ids) {
        if (!empty($api_call_ids)) {
            //$this->db->where_not_in('id_ligacao', $api_call_ids);
        }
        return $this->db->where('enviado_ixc', 'nao')
                        ->where('id_ligacao IS NOT NULL')
                        ->not_like('tp_chamada','DID')
                        ->get('cdr')
                        ->result_array();
    }       

    public function get_api_endpoints() {
        return $this->db->get_where('api_endpoints', ['status' => '0', 'run_cron' => '0'])->result_array();
    }
}
