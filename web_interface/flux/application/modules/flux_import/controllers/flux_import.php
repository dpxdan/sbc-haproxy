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

class Flux_import extends MX_Controller
{
    private $acceptable_mime = array(
        'application/csv',
        'application/x-csv',
        'text/csv',
        'text/comma-separated-values',
        'text/x-comma-separated-values',
        'text/plain',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->config('flux_import_config');
        $this->load->model('flux_import_model');
        $this->load->library('flux/permission');
        $this->load->helper('template_inheritance');
        $this->load->library('session');
        $this->load->library('csvreader');
        $this->load->library('flux/order');
        $this->load->library("flux_log");
        $this->load->helper('download');
        $this->load->model ( 'common_model' );
        $this->load->library ( 'common' );
        $this->load->model ( 'db_model' );
        
        if ($this->session->userdata('user_login') == FALSE) {
            redirect(base_url() . 'flux/login');
        }
    }

    public function flux_import_list()
    {
        $this->session->unset_userdata(array('flux_import_file', 'flux_import_errors'));
        $data['page_title'] = gettext('Import Accounts and DIDs');
        $this->load->view('view_flux_import', $data);
    }

    public function preview()
    {
        $data['page_title'] = gettext('Import Preview');

        if (empty($_FILES['flux_import_file']['name'])) {
            $data['error'] = gettext('Please select a CSV file.');
            return $this->load->view('view_flux_import', $data);
        }

        $file     = $_FILES['flux_import_file'];
        $segments = explode('.', $file['name']);

        if (count($segments) !== 2 || strtolower(end($segments)) !== 'csv') {
            $data['error'] = gettext('Only .csv files are allowed.');
            return $this->load->view('view_flux_import', $data);
        }

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $this->acceptable_mime)) {
            $data['error'] = gettext('Invalid file format.');
            return $this->load->view('view_flux_import', $data);
        }

        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
            $data['error'] = gettext('Upload failed. Please try again.');
            return $this->load->view('view_flux_import', $data);
        }

        $full_path   = $this->config->item('rates-file-path');
        $stored_name = 'flux-import-' . date('Y-m-d-H-i-s') . '.csv';

        if (!move_uploaded_file($file['tmp_name'], $full_path . $stored_name)) {
            $data['error'] = gettext('Failed to move file. Check permissions.');
            return $this->load->view('view_flux_import', $data);
        }

        $this->session->set_userdata('flux_import_file', $stored_name);

        $delimiter = $this->input->post('csv_delimiter', TRUE);
        if (empty($delimiter)) {
            $delimiter = ';';
        }

        $parsed = $this->_parse_csv($full_path . $stored_name, $delimiter);

        if (empty($parsed)) {
            $data['error'] = gettext('CSV file is empty or has no valid data.');
            return $this->load->view('view_flux_import', $data);
        }

        list($account_rows, $did_rows, $parse_errors) = $this->_prepare_rows($parsed);

        $data['preview_accounts']   = array_slice($account_rows, 0, 10);
        $data['preview_dids']       = array_slice($did_rows, 0, 10);
        $data['total_accounts']     = count($account_rows);
        $data['total_dids']         = count($did_rows);
        $data['total_parse_errors'] = count($parse_errors);
        $data['parse_errors']       = array_slice($parse_errors, 0, 20);
        $this->flux_log->write_log('view_flux_import_preview', json_encode($data));

        $this->load->view('view_flux_import_preview', $data);
    }

    public function process()
    {
        $full_path   = $this->config->item('rates-file-path');
        $stored_name = $this->session->userdata('flux_import_file');

        if (empty($stored_name) || !file_exists($full_path . $stored_name)) {
            redirect(base_url() . 'flux_import/flux_import');
            return;
        }

        $delimiter = $this->input->post('csv_delimiter', TRUE);
        if (empty($delimiter)) {
            $delimiter = ';';
        }

        $parsed = $this->_parse_csv($full_path . $stored_name, $delimiter);
        list($account_rows, $did_rows, $parse_errors) = $this->_prepare_rows($parsed);

        $this->db->trans_start();

        $account_count = 0;
        $did_count     = 0;
        $skipped       = 0;
        $insert_errors = array();

        foreach ($account_rows as $cnpj => $acc) {
            $existing_id = $this->flux_import_model->account_exists($acc['number']);
            if ($existing_id > 0) {
                $account_rows[$cnpj]['_resolved_id'] = $existing_id;
                $skipped++;
                continue;
            }

            $insert_data = $acc;
            unset($insert_data['_resolved_id']);

            $new_id = $this->flux_import_model->insert_account($insert_data);
            $this->flux_log->write_log('procces_insert_account:'.$new_id.'', json_encode($insert_data));
            
            if ($new_id > 0) {
                $account_rows[$cnpj]['_resolved_id'] = $new_id;
                $account_count++;
            } else {
                $insert_errors[] = 'Falha ao inserir conta: ' . $acc['number'];
            }
        }

        foreach ($did_rows as $did) {
                
            if ($this->flux_import_model->did_exists($did['number'])) {
                $skipped++;
                continue;
            }
        
            $cnpj_key  = isset($did['_cnpj_key']) ? $did['_cnpj_key'] : '';
            $accountid = 0;
        
            if ($cnpj_key !== '' && isset($account_rows[$cnpj_key])) {
                $accountid = isset($account_rows[$cnpj_key]['_resolved_id'])
                    ? (int) $account_rows[$cnpj_key]['_resolved_id']
                    : 0;
            }

            $accountinfo = null;
            if ($accountid > 0) {
                $accountinfo = $this->db
                    ->get_where('accounts', ['id' => $accountid])
                    ->row_array();
            }
            
            if (!empty($did['_use_sip_device']) && $accountinfo) {
            
                if ($did['reverse_rate'] == 0) {
                    $rate_group = $this->flux_import_model->get_pricelist_by_cnpj($did['_cnpj_key']);
                } 
                else {
                    $fallback_pricelist_id = $account_rows[$cnpj_key]['pricelist_id'] ?? 1;
                    $rate_group = $this->_get_account_pricelist_id($did['_cnpj_key'], $fallback_pricelist_id);
                }
                
            
                $sip_accountinfo = $accountinfo;
                $sip_accountinfo['number'] = $did['number'];
                $sip_accountinfo['domain'] = $did['_did_domain'];
                $this->flux_log->write_log('sip_accountinfo', json_encode($sip_accountinfo));
                $this->flux_import_model->create_sip_device($sip_accountinfo);
            
                $did['extensions'] = $did['number'];
                $did['call_type']  = 0;
                $did['rate_group'] = $rate_group;
            }
            
        
            $product_id = $this->flux_import_model->insert_product(array(
                            'name'                      => $did['number'],
                            'description'               => $did['_tipo_terminal'],
                            'product_category'          => 4,
                            'buy_cost'                  => 0,
                            'price'                     => 0,
                            'setup_fee'                 => 0,
                            'can_purchase'              => 0,
                            'can_resell'                => 0,
                            'commission'                => 0,
                            'billing_type'              => 1,
                            'billing_days'              => 0,
                            'free_minutes'              => 0,
                            'apply_on_rategroups'       => '',
                            'destination_rategroups'    => '',
                            'destination_countries'     => '',
                            'destination_calltypes'     => '',
                            'apply_on_existing_account' => 1,
                            'applicable_for'            => 0,
                            'release_no_balance'        => 0,
                            'status'                    => $did['status'],
                            'is_deleted'                => 0,
                            'created_by'                => 1,
                            'reseller_id'               => 0,
                            'creation_date'             => gmdate('Y-m-d H:i:s'),
                            'last_modified_date'        => gmdate('Y-m-d H:i:s'),
                            'country_id'                => 28,
                        ));
            $this->flux_log->write_log('process_insert_product', json_encode($product_id));
            $insert_data = $did;
            unset(
                $insert_data['_cnpj_key'],
                $insert_data['_tipo_terminal'],
                $insert_data['_origem_terminal'],
                $insert_data['_use_sip_device'],
                $insert_data['_did_domain']
            );
        
            $insert_data['accountid']  = $accountid;
            $insert_data['product_id'] = $product_id;
            $this->flux_log->write_log('process_insert_did', json_encode($insert_data));
            $this->flux_import_model->insert_did($insert_data);
            $did_count++;
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $data['error'] = gettext('Transaction error. No data was saved.');
        }

        @unlink($full_path . $stored_name);
        $this->session->unset_userdata('flux_import_file');

        $data['page_title']    = gettext('Import Result');
        $data['account_count'] = $account_count;
        $data['did_count']     = $did_count;
        $data['skipped']       = $skipped;
        $data['parse_errors']  = array_merge($parse_errors, $insert_errors);

        $this->load->view('view_flux_import_result', $data);
    }

    public function download_sample_old()
    {
        $this->load->helper('download');
        $full_path = FCPATH . 'assets/Rates_File/flux_import_sample.csv';
        ob_clean();
        $data = file_get_contents($full_path);
        force_download('flux_import_sample.csv', $data);
    }
    
    function download_sample($file_name)
    {
        $this->load->helper('download');
        
        $full_path = FCPATH . "assets/Rates_File/" . $file_name . ".csv";
        
        if (!file_exists($full_path)) {
            $this->session->set_flashdata('flux_notification', gettext('File not exists!'));
            redirect(base_url() . 'flux_import/flux_import_list/');
        }
        
        $file = file_get_contents($full_path);
        
        if ($file === false) {
            $this->session->set_flashdata('flux_notification', gettext('Errors found reading the file'));
            redirect(base_url() . 'flux_import/flux_import_list/');
        }
        
        ob_clean();
        force_download($file_name.".csv", $file);
    }

    private function _parse_csv($path, $delimiter = ';')
    {
        $rows   = array();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $rows;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $raw_headers = fgetcsv($handle, 0, $delimiter);
        if (!$raw_headers) {
            fclose($handle);
            return $rows;
        }

        $headers = array_map(function($h) {
            return strtolower(trim($h));
        }, $raw_headers);

        while (($line = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (count($line) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, $line);
        }

        fclose($handle);
        return $rows;
    }

    private function _prepare_rows($parsed)
    {
        $status_map = $this->config->item('flux_import_status_map');
    
        $account_rows = [];
        $did_rows     = [];
        $errors       = [];
    
        $current_date = gmdate('Y-m-d H:i:s');
    
        foreach ($parsed as $line_num => $row) {
    
            $line_label = 'Linha ' . ($line_num + 2);
    
            $number = trim(isset($row['numero']) ? $row['numero'] : '');
            $cnpj   = preg_replace('/\D/', '', trim(isset($row['nu_cpfcnpj']) ? $row['nu_cpfcnpj'] : ''));
            
            if (preg_match('/^800/', $number) && !preg_match('/^0800/', $number)) {
                $number = '0' . $number;
            }
    
            if ($number === '' || $cnpj === '') {
                $errors[] = $line_label . ': numero ou nu_cpfcnpj vazio.';
                continue;
            }
    
            if (!is_numeric($number)) {
                $errors[] = $line_label . ': numero "' . $number . '" nao e numerico.';
                continue;
            }
    
            if (!isset($account_rows[$cnpj])) {
                $account_rows[$cnpj] = $this->_build_account_row($row, $cnpj, $status_map, $current_date);
            }

            $raw_ip = $row['ip'] ?? '';
            $ip = $this->_normalize_endpoint($raw_ip);
            $use_sip_device = ($ip === '');
    
            $call_type = $this->_resolve_call_type_by_endpoint($ip);
    
            [$city, $province] = $this->_parse_cidade_uf($row['cidade_uf'] ?? '');
    
            $did_number = preg_replace('/\D/', '', $number);
    
            $reverse_rate = (preg_match('/^(0800|800)/', $did_number)) ? 0 : 1;
    
            $fallback_pricelist_id = $account_rows[$cnpj]['pricelist_id'] ?? 0;
    
            if ($reverse_rate == 0) {
                $rate_group = $this->flux_import_model->get_pricelist_by_cnpj($cnpj);
                //$rate_group = $this->_get_account_pricelist_id($cnpj, $fallback_pricelist_id);
            } else {
                $rate_group = 1;
            }
    
            $status_raw = trim($row['status_terminal'] ?? 'Ativo');
            $status_terminal = $status_map[$status_raw] ?? 0;
    
            $dt_ativacao = $this->_parse_date_br($row['dt_ativacao'] ?? '');
    
            $tipo_terminal_raw = trim($row['tipo_terminal'] ?? '');
            $did_domain_raw = $this->_resolve_did_domain($row['dominio'] ?? '');
    
            $did_rows[] = [
                '_cnpj_key'        => $cnpj,
                '_tipo_terminal'   => $tipo_terminal_raw,
                '_origem_terminal' => trim($row['origem_terminal'] ?? ''),
                '_use_sip_device'  => $use_sip_device,
                '_did_domain' => $did_domain_raw,
    
                'number'           => $number,
                'accountid'        => 0,
                'parent_id'        => 0,
                'provider_id'      => 0,
                'country_id'       => 28,
                'city'             => $city,
                'province'         => $province,
    
                'call_type'        => $use_sip_device ? 0 : $call_type,
                'extensions'       => $use_sip_device ? '' : $ip,
                'rate_group'       => $rate_group,
    
                'maxchannels'      => (int) ($row['nu_qtd_canais'] ?? 0),
                'status'           => $status_terminal,
    
                'cost'             => 0,
                'connectcost'      => 0,
                'includedseconds'  => 0,
                'monthlycost'      => 0,
                'setup'            => 0,
                'init_inc'         => 0,
                'inc'              => 0,
                'leg_timeout'      => 30,
                'product_id'       => 0,
    
                'last_modified_date' => $dt_ativacao ?: $current_date,
    
                'always' => 0,
                'always_destination' => '',
                'user_busy' => 0,
                'user_busy_destination' => '',
                'user_not_registered' => 0,
                'user_not_registered_destination'=> '',
                'no_answer' => 0,
                'no_answer_destination' => '',
    
                'failover_extensions' => '',
                'failover_call_type'  => 1,
    
                'always_vm_flag' => 1,
                'user_busy_vm_flag' => 1,
                'user_not_registered_vm_flag' => 1,
                'no_answer_vm_flag' => 1,
                'call_type_vm_flag' => 1,
    
                'hg_type'       => 0,
                'reverse_rate'  => $reverse_rate,
                'area_code'     => (int) ($row['ddd_sys'] ?? 0),
            ];
        }
    
        return [$account_rows, $did_rows, $errors];
    }
    
    private function _build_account_row($row, $cnpj, $status_map, $current_date)
    {
        $config = Common_model::$global_config['system_config'];
    
        $status_raw = trim($row['status_terminal'] ?? 'Ativo');
        $status     = $status_map[$status_raw] ?? 0;
    
        $razao = trim($row['nm_razao_social'] ?? '');
        
        $tipo_billing_raw = trim(isset($row['conta']) ? $row['conta'] : '');
        $sweep_id = ($tipo_billing_raw === 'Faturamento Mensal') ? 2 : 0;
    
        $dt_ativacao = $this->_parse_date_br($row['dt_ativacao'] ?? '');
        $creation    = $dt_ativacao ?: $current_date;
    
        [$city, $province] = $this->_parse_cidade_uf($row['cidade_uf'] ?? '');
    
        $cnpj_clean    = preg_replace('/\D/', '', $cnpj);
        $dominio_clean = strtolower(trim($row['dominio'] ?? ''));
    
        $email = ($cnpj_clean && $dominio_clean)
            ? $cnpj_clean . '@' . $dominio_clean
            : '';

        $pin_number = '';
        if (($config['generate_pin'] ?? 0) == 0) {
            $pin_length = ($config['pinlength'] ?? 6) < 6 ? 6 : $config['pinlength'];
            $pin_number = $this->common->find_uniq_rendno_customer($pin_length, 'number', 'accounts');
        }
    
        return [
            'number'              => $cnpj_clean,
            'first_name'          => $razao,
            'last_name'           => '',
            'company_name'        => $razao,
    
            'email'               => $email,
            'notification_email'  => $email,
    
            'tax_number'          => $row['nu_cpfcnpj'] ?? '',
            'reference'           => $dominio_clean,
    
            'status'              => $status,
            'deleted'             => 0,
    
            'city'                => $city,
            'province'            => $province,
            'address_1'           => '',
            'postal_code'         => '',
    
            'maxchannels'         => (int) $config['maxchannels'],
            'cps'                 => 0,
    
            'pricelist_id'        => (int) $config['default_signup_rategroup'],
            'currency_id'         => $config['default_currency_id'] ?? 16,
            'country_id'          => $config['country'] ?? 28,
            'timezone_id'         => (int) $config['default_timezone'],
    
            'sweep_id'            => $sweep_id,
            'posttoexternal'      => 1,
            'type'                => 0,
            'reseller_id'         => 0,
    
            'balance'             => 0,
            'credit_limit'        => 0,
            'notify_credit_limit' => 0,
    
            'password'            => $this->common->encode($this->common->generate_password()),
    
            'creation'            => $creation,
            'expiry'              => gmdate('Y-m-d H:i:s', strtotime($creation . ' +10 years')),
    
            'first_used'          => '0000-00-00 00:00:00',
            'deleted_date'        => '0000-00-00 00:00:00',
    
            'notify_flag'         => (int) $config['notify_flag'],
            'is_recording'        => 1,
            'allow_ip_management' => 1,
    
            'local_call'          => 0,
            'charge_per_min'      => '0',
            'invoice_day'         => 1,
    
            'dialed_modify'       => '',
            'pin'                 => $pin_number,
            'sip_device_flag'     => 1,
    
            'validfordays'        => 3652,
    
            '_resolved_id'        => 0,
        ];
    }

    private function _parse_cidade_uf($cidade_uf)
    {
        $parts    = explode('/', $cidade_uf, 2);
        $city     = trim(isset($parts[0]) ? $parts[0] : '');
        $province = trim(isset($parts[1]) ? $parts[1] : '');
        return [$city, $province];
    }

    private function _parse_date_br($date)
    {
        if (empty($date)) {
            return '';
        }
        $d = \DateTime::createFromFormat('d/m/Y', trim($date));
        return $d ? $d->format('Y-m-d H:i:s') : '';
    }
    
    private function _resolve_call_type_by_endpoint($value)
    {
        return $this->_is_valid_ip_or_domain_endpoint($value) ? 2 : 3;
    }

    private function _is_valid_ip_or_domain_endpoint($value)
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $value = preg_replace('#^[a-z]+://#i', '', $value);

        $host = $value;
        $port = null;

        if (substr_count($value, ':') === 1) {
            list($host, $port) = explode(':', $value, 2);
            $host = trim($host);
            $port = trim($port);
    
            if ($port === '' || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        if (preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
            return true;
        }

        return false;
    }
    
    private function _normalize_endpoint($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '\N' || strtoupper($value) === 'NULL') {
            return '';
        }

        return $value;
    }
    
    private function _get_account_pricelist_id($account_number, $fallback_pricelist_id = 1)
    {
        $this->db->select('pricelist_id');
        $row = (array) $this->db->get_where('accounts', [
            'number'  => $account_number,
            'deleted' => 0
        ])->first_row();

        if (!empty($row) && isset($row['pricelist_id'])) {
            return (int) $row['pricelist_id'];
        }

        return (int) $fallback_pricelist_id;
    }
    
    private function _resolve_did_domain(string $raw): string
    {
        $raw = strtolower(trim($raw));
    
        if ($raw === '' || $raw === '\n' || strtoupper($raw) === 'NULL') {
            return '';
        }
    
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $raw)) {
            return $raw;
        }
    
        return '';
    }
}