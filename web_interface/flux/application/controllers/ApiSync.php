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
class ApiSync extends CI_Controller {


    public $external_api_id = '';
    public function __construct() {
        parent::__construct();
        $this->load->model('Sync_model');
        $this->load->model('api_model');
        $this->load->library('curl');
        $this->load->library('flux_log');
    }

    /**
     * Main orchestration method to synchronize all primary data.
     */
    public function sync() {
    $lockFile = '/tmp/api_sync.lock';

    if (file_exists($lockFile)) {
        $pid = file_get_contents($lockFile);
        if (posix_kill((int)$pid, 0)) {
            $this->flux_log->write_log('api_controller', "ApiSync is already running (PID $pid). Aborting.");
            return;
        } else {
            unlink($lockFile);
        }
    }

    file_put_contents($lockFile, getmypid());

    $this->flux_log->write_log('sync_service', 'Starting full data synchronization process.');

    try {
        $endpoints = $this->Sync_model->get_api_endpoints();
        if (empty($endpoints)) {
            $this->flux_log->write_log('api_controller', 'No active API endpoints found to synchronize.');
            return;
        }

        foreach ($endpoints as $endpoint) {
            $auth_string = $endpoint['endpoint_user'] . ':' . $endpoint['endpoint_password'];
            $api_url = $endpoint['endpoint_url'];
            $external_api_id = $endpoint['external_api_id'];
            $reseller_id = $endpoint['reseller_id'];
            
            
            $this->external_api_id = $external_api_id;

            $customer_ids = $this->_sync_peers($api_url, $auth_string, $reseller_id);

            if (empty($customer_ids)) {
                $this->flux_log->write_log('api_controller', 'No customers to sync for endpoint: ' . $endpoint['endpoint_name']);
                continue;
            }
            if ($customer_ids == false){
                continue;
            }

            $this->_sync_customers($api_url, $auth_string, $customer_ids, $reseller_id);
            $this->_sync_device_plans($api_url, $auth_string);
        }

        $this->flux_log->write_log('sync_service', 'Full data synchronization finished.');

    } catch (Exception $e) {
        $this->flux_log->write_log('api_controller', 'Exception during ApiSync: ' . $e->getMessage());
    } finally {
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}

    /**
     * Syncs SIP peers and returns an array of unique customer IDs.
     */
    private function _sync_peers($api_url, $auth_string, $reseller_id = '0') {
        $response = $this->request_voip_sippeers($api_url, $auth_string);
        if (empty($response['registros'])) {
            $this->flux_log->write_log('api_controller', 'API response for voip_sippeers was empty.');
            return [];
        }

        $api_peer_ids = array_column($response['registros'], 'id');
        $customer_ids = array_unique(array_column($response['registros'], 'cliente_id'));
        $contract_ids = array_unique(array_column($response['registros'], 'id_contrato'));

        foreach ($response['registros'] as $record) {
            $record['reseller_id'] = $reseller_id;
            $data_peer = $this->Sync_model->replace_peer($record);
            if (!empty($record['id_contrato'])) {
            $contract_response = $this->request_cliente_contrato($api_url, $auth_string, $record['id_contrato']);
            if (!empty($contract_response['registros'])) {
                foreach ($contract_response['registros'] as $contract) {
                    $this->Sync_model->replace_contract($contract);
                }
            } 
            } 
            if (is_array($data_peer) && isset($data_peer['needs_customer_sync'])) {
            $customers_to_sync_immediately[] = $data_peer['needs_customer_sync'];
            $this->flux_log->write_log('api_controller', 'Peer sync signaled immediate customer sync for ID: ' . $data_peer['needs_customer_sync']);
            continue;
            }
            
            }
        if (!empty($customers_to_sync_immediately)) {
            $unique_customers = array_unique($customers_to_sync_immediately);
            $this->flux_log->write_log('api_controller', 'Starting immediate customer sync for ' . count($unique_customers) . ' IDs.');
            $this->_sync_customers($api_url, $auth_string, $unique_customers, $reseller_id);
            
            foreach ($unique_customers as $customer_id) {
                $peers_to_retry = array_filter($response['registros'], function($peer) use ($customer_id) {
                    return $peer['cliente_id'] == $customer_id;
                });
                
                foreach ($peers_to_retry as $peer) {
                    $this->api_model->upsert_device($peer['id']);
                    $this->flux_log->write_log('api_controller', 'Retried upsert_device for peer ID: ' . $peer['id']);
            }
            }
        }

        $this->_cleanup_peers($api_peer_ids);
        return $customer_ids;
    }

    /**
     * Deletes local SIP peers that are no longer present in the API.
     */
    private function _cleanup_peers($api_peer_ids) {
        $records_to_delete = $this->Sync_model->get_stale_ids($api_peer_ids, 'id', 'voip_sippeers');
        foreach ($records_to_delete as $record) {
            $this->load->library('common');
            $this->common->delete_data('sip_devices', ['id_sip_external' => $record['id']]);
            $this->common->delete_data('voip_sippeers', ['id' => $record['id']]);
            $this->flux_log->write_log('api_controller', 'Deleted stale peer with ID: ' . $record['id']);
        }
    }

    /**
     * Syncs customer profiles and contracts.
     */
    private function _sync_customers($api_url, $auth_string, $customer_ids, $reseller_id = '0') {
        $this->flux_log->write_log('api_controller', '_sync_customers start.');
        foreach ($customer_ids as $id) {
            $response = $this->request_cliente($api_url, $auth_string, $id);
            if (!empty($response['registros'])) {
                $response['registros'][0]['reseller_id'] = $reseller_id;
                $this->Sync_model->replace_customer($response['registros'][0]);
            }
        }
    }

    /**
     * Syncs the 'id_plano_sip' for all VoIP devices.
     */
    private function _sync_device_plans($api_url, $auth_string) {
        $response = $this->request_voip_devices($api_url, $auth_string);
        if (empty($response['registros'])) {
            $this->flux_log->write_log('api_controller', 'Could not sync SIP plans, API response was empty.');
            return;
        }
        $this->Sync_model->update_device_plans($response['registros']);
        $this->flux_log->write_log('api_controller', 'Successfully synced SIP plan IDs.');
    }

    /**
     * Syncs geographic data (cities and states) from the API.
     */
    public function sync_locations() {
        $lockLocationsFile = '/tmp/locations_sync.lock';
        
        if (file_exists($lockLocationsFile)) {
            $pid = file_get_contents($lockLocationsFile);
            if (posix_kill((int)$pid, 0)) {
                $this->flux_log->write_log('api_controller', "sync_locations is already running (PID $pid). Aborting.");
                return;
            } else {
                unlink($lockLocationsFile);
            }
        }
        
        file_put_contents($lockLocationsFile, getmypid());
        
        
        try {
        
        $this->flux_log->write_log('api_controller', 'Starting locations synchronization.');
        $endpoints = $this->Sync_model->get_api_endpoints();
        if (empty($endpoints)) return;

        foreach ($endpoints as $endpoint) {
            $auth_string = $endpoint['endpoint_user'] . ':' . $endpoint['endpoint_password'];
            $api_url = $endpoint['endpoint_url'];

            $city_response = $this->request_cidade($api_url, $auth_string);
            if (!empty($city_response['registros'])) {
                $this->Sync_model->replace_cities($city_response['registros']);
            }

            $uf_response = $this->request_uf($api_url, $auth_string);
            if (!empty($uf_response['registros'])) {
                $this->Sync_model->replace_states($uf_response['registros']);
            }
        }
        $this->flux_log->write_log('api_controller', 'Locations synchronization finished.');
        }
        catch (Exception $e) {
                $this->flux_log->write_log('api_controller', 'Exception during ApiSync: ' . $e->getMessage());
            } 
            finally {
                if (file_exists($lockLocationsFile)) {
                    unlink($lockLocationsFile);
                }
            }
    }

    /**
     * Syncs VoIP plans between the local DB and the remote API.
     */
	public function sync_voip_plans() {
		$this->flux_log->write_log('api_controller', 'Starting VoIP plans synchronization.');
		$endpoints = $this->Sync_model->get_api_endpoints();
		if (empty($endpoints)) return;
	
		foreach ($endpoints as $endpoint) {
			$auth_string     = $endpoint['endpoint_user'] . ':' . $endpoint['endpoint_password'];
			$url_voip_plans  = $endpoint['endpoint_url'] . 'planos_voip';
			$external_api_id = $endpoint['external_api_id'];
	
			$api_plans_response = $this->send_post_request($url_voip_plans, $auth_string, [
				'rp'    => '25000',
				'qtype' => 'planos_voip.id',
				'oper'  => '>',
				'query' => '0',
			], 'listar');
	
			$api_plans = !empty($api_plans_response['registros']) ? $api_plans_response['registros'] : [];
	
			$this->api_model->salvar_planos_voip_externos($api_plans);			
	
			$api_platform_ids  = array_map('intval', array_column($api_plans, 'id_plataforma'));
			$this->flux_log->write_log('api_controller', 'IXC id_plataforma values: ' . implode(', ', $api_platform_ids));
	
			$local_products   = $this->db->select('id, name')->from('products')->where('product_category', 1)->where('status', 0)->get()->result_array();
			$local_product_ids = array_map('intval', array_column($local_products, 'id'));
	
			foreach ($local_products as $product) {
				if (in_array((int)$product['id'], $api_platform_ids)) continue;
	
				$payload = ['id_plataforma' => $product['id'], 'descricao' => $product['name']];
	
				$response  = $this->send_post_request($url_voip_plans, $auth_string, $payload, '');
				$http_code = $this->curl->info['http_code'];
	
				if ($http_code >= 200 && $http_code < 300) {
					$this->flux_log->write_log('api_controller', 'Sent new VoIP plan: ' . json_encode($payload));
				} else {
					$this->flux_log->write_log('api_controller', 'Failed to send VoIP plan ' . json_encode($payload) . '. HTTP: ' . $http_code . '. Response: ' . json_encode($response));
					$this->api_model->save_api_log($url_voip_plans, json_encode($payload), $response, 'create_voip_plan', $http_code);
				}
			}
	
			foreach ($api_plans as $api_plan) {
				if (in_array((int)$api_plan['id_plataforma'], $local_product_ids)) continue;
	
				$plan_id    = $api_plan['id'];
				$url_delete = $url_voip_plans . '/' . $plan_id;
	
				$ch_delete = curl_init($url_delete);
				curl_setopt($ch_delete, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch_delete, CURLOPT_HTTPHEADER, [
					'ixcsoft: ',
					'Authorization: Basic ' . base64_encode($auth_string),
				]);
				curl_setopt($ch_delete, CURLOPT_RETURNTRANSFER, true);
				$response_delete    = curl_exec($ch_delete);
				$http_code_delete   = curl_getinfo($ch_delete, CURLINFO_HTTP_CODE);
				curl_close($ch_delete);
	
				if ($http_code_delete >= 200 && $http_code_delete < 300) {
					$this->flux_log->write_log('api_controller', 'Deleted VoIP plan IXC ID: ' . $plan_id . ' (id_plataforma: ' . $api_plan['id_plataforma'] . ').');
				} else {
					$this->flux_log->write_log('api_controller', 'Failed to delete VoIP plan IXC ID: ' . $plan_id . '. HTTP: ' . $http_code_delete . '. Response: ' . json_encode($response_delete));
					$this->api_model->save_api_log($url_delete, '', $response_delete, 'delete_voip_plan', $http_code_delete);
				}
			}
		}
		$this->flux_log->write_log('api_controller', 'VoIP plans synchronization finished.');
	}
    
    /**
     * Syncs Call Detail Records (CDRs) by processing local records in batches
     * and sending each CDR individually to the API, as per API limitations.
     */
    public function sync_cdrs() {
    $lockCDRFile = '/tmp/cdr_sync.lock';

    if (file_exists($lockCDRFile)) {
        $pid = file_get_contents($lockCDRFile);
        if (posix_kill((int)$pid, 0)) {
            $this->flux_log->write_log('api_controller', "sync_cdrs is already running (PID $pid). Aborting.");
            return;
        } else {
            unlink($lockCDRFile);
        }
    }

    file_put_contents($lockCDRFile, getmypid());

    try {
    $this->flux_log->write_log('api_controller', 'Starting CDR synchronization (individual submission mode).');
        $endpoints = $this->Sync_model->get_api_endpoints();
        if (empty($endpoints)) {
            $this->flux_log->write_log('api_controller', 'No active API endpoints for CDR sync.');
            return;
        }
    
        foreach ($endpoints as $endpoint) {
            $auth_string = $endpoint['endpoint_user'] . ':' . $endpoint['endpoint_password'];
            $sync_cdrs_type = isset($endpoint['sync_cdrs_for']) ? $endpoint['sync_cdrs_for'] : '1';
            $url_cdr = $endpoint['endpoint_url'] . 'cdr';
            $batch_size = 200;
    
            do {
                $unsent_cdrs = $this->Sync_model->get_unsent_cdrs_batch($batch_size,$sync_cdrs_type);
                if (empty($unsent_cdrs)) {
                    $this->flux_log->write_log('api_controller', 'No new CDRs to send for this batch.');
                    break;
                }    
                $this->flux_log->write_log('api_controller', 'Processing a batch of ' . count($unsent_cdrs) . ' CDRs for individual submission.');    

                foreach ($unsent_cdrs as $cdr) {
                    $this->_send_cdr($url_cdr, $auth_string, $cdr);
                }
    
            } while (count($unsent_cdrs) === $batch_size);
        }
        $this->flux_log->write_log('api_controller', 'CDR synchronization finished.');  

    } 
    catch (Exception $e) {
        $this->flux_log->write_log('api_controller', 'Exception during ApiSync: ' . $e->getMessage());
    } 
    finally {
        if (file_exists($lockCDRFile)) {
            unlink($lockCDRFile);
        }
    }
}
    
    /**
     * Sends a single CDR to the API and updates the local record upon success.
     *
     * @param string $url_cdr The API endpoint URL for CDRs.
     * @param string $auth_string The authentication string.
     * @param array $cdr The CDR data array from the local database.
     */
    private function _send_cdr($url_cdr, $auth_string, $cdr) {
        if (empty($cdr['id_ligacao'])) {
            $this->flux_log->write_log('api_controller', 'CDR is missing id_ligacao: ' . json_encode($cdr));
            return;
        }
    
        $payload = [
            'accountcode' => $cdr['accountcode'],
            'billsec'     => $cdr['billsec'],
            'calldate'    => $this->convert_to_brasilia($cdr['calldate']),
            'clid'        => $cdr['clid'],
            'custo'       => $cdr['custo'],
            'valor_user'  => $cdr['valor_user'],
            'destino'     => $cdr['destino'],
            'did'         => $cdr['did'],
            'disposition' => $cdr['disposition'],
            'duration'    => $cdr['duration'],
            'id_ligacao'  => $cdr['id_ligacao'],
            'id_sip'      => $cdr['id_sip'],
            'importado'   => $cdr['importado'],
            'ramal'       => $cdr['ramal'],
            'uniqueid'    => $cdr['uniqueid'],
            'tarifado'    => $cdr['tarifado'],
            'tp_chamada'  => $cdr['tp_chamada'],
            'dst'         => $cdr['dst'],
            'src'         => $cdr['src'],
            'dest_pais'   => $cdr['dest_pais']
        ];
    
        $response = $this->send_post_request_lib($url_cdr, $auth_string, $payload, '');
        $http_code = $this->curl->info['http_code'];
    

        if ($http_code >= 200 && $http_code < 300 && !empty($response['id'] )) {
            $this->Sync_model->mark_cdr_as_sent($cdr['id_ligacao'], $response['id']);
            $this->flux_log->write_log('api_controller', "Successfully sent CDR {$cdr['id_ligacao']}. IXC ID: {$response['id']}.");
            $response_id = isset($response['id']) ? $response['id'] : null;
            if ($response_id) {
            
			$put_payload = $payload;
			$put_payload['id'] = $response_id;
			
			$urlCurl = $url_cdr.'/'.$response_id.'';
	
			$ch_put = curl_init($urlCurl);
			curl_setopt($ch_put, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch_put, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'ixcsoft: ',
				'Authorization: Basic ' . base64_encode($auth_string)
			));        
			curl_setopt($ch_put, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch_put, CURLOPT_POST, true);
			curl_setopt($ch_put, CURLOPT_POSTFIELDS, json_encode($put_payload));
									
			$response_put = curl_exec($ch_put);
			$httpCodePut = curl_getinfo($ch_put, CURLINFO_HTTP_CODE);
			curl_close($ch_put);
	
			if ($httpCodePut == 200 || $httpCodePut == 201) {
				$this->flux_log->write_log('api_controller', "PUT realizado com sucesso no ID {$response_id}: " . json_encode($put_payload));
				$this->flux_log->write_log('api_controller', "PUT Response {$response_id}: " . json_encode($response_put));
			} 
			else {
				$this->api_model->save_api_log(
					$urlCurl,
					json_encode($put_payload),
					$response_put,
					'update_cdr',
					$httpCodePut
				);
			} 
		
            
            }
            
        } 
        else {
            $this->flux_log->write_log('api_controller', "Failed to send CDR {$cdr['id_ligacao']}. HTTP Code: {$http_code}. Response: " . json_encode($response ));
        }
    }

    private function _get_all_api_call_ids($url_cdr, $auth_string) {
        $api_call_ids = [];
        $page = 1;
        $rp = 1000;
        $sortname = 'cdr.id';
        $sortorder = 'desc';
        do {
            $response = $this->send_post_request($url_cdr, $auth_string, ['page' => $page, 'rp' => $rp, 'sortname' => $sortname, 'sortorder' => $sortorder], 'listar');
            $records = !empty($response['registros']) ? $response['registros'] : [];
            if (!empty($records)) {
                $api_call_ids = array_merge($api_call_ids, array_column($records, 'id'));
            }
            $page++;
        } while (count($records) == $rp);
        return array_filter($api_call_ids);
    }

    // ==========================================================================
    // API REQUEST HELPERS
    // ==========================================================================
    private function request_voip_sippeers($api_url, $auth_string) {
        
        if (!empty($this->external_api_id)) {
        $qtype = 'id_integracao';
        $external_api_id = $this->external_api_id;
        $oper = '=';
        } else {
        $qtype = 'id';
        $external_api_id = 0;
        $oper = '>';
        }
        
        return $this->send_post_request($api_url . 'view_voip_sippeers_cliente', $auth_string, ['rp' => '20000', 'qtype' => 'view_voip_sippeers_cliente.'.$qtype.'', 'query' => $external_api_id, 'oper' => ''.$oper.''], 'listar');
    }
    private function request_cliente($api_url, $auth_string, $id) {
        $this->flux_log->write_log('api_controller', 'request_cliente process.');
        return $this->send_post_request($api_url . 'cliente', $auth_string, ['qtype' => 'cliente.id', 'query' => $id, 'oper' => '='], 'listar');
    }
    private function request_cliente_contrato($api_url, $auth_string, $id_contrato) {
        $this->flux_log->write_log('api_controller', 'request_cliente_contrato process.');
        return $this->send_post_request($api_url . 'cliente_contrato', $auth_string, ['qtype' => 'cliente_contrato.id', 'query' => $id_contrato, 'oper' => '='], 'listar');
    }
    private function request_voip_devices($api_url, $auth_string) {
        if (!empty($this->external_api_id)) {
        $qtype = 'id_integracao';
        $external_api_id = $this->external_api_id;
        $oper = '=';
        } else {
        $qtype = 'id';
        $external_api_id = 0;
        $oper = '>';
        }
        
        return $this->send_post_request($api_url . 'voip_sippeers', $auth_string, ['rp' => '20000', 'qtype' => 'voip_sippeers.'.$qtype.'', 'query' => $external_api_id, 'oper' => ''.$oper.''], 'listar');
    }
    private function request_cidade($api_url, $auth_string) {
        return $this->send_post_request($api_url . 'cidade', $auth_string, ['rp' => '100000'], 'listar');
    }
    private function request_uf($api_url, $auth_string) {
        return $this->send_post_request($api_url . 'uf', $auth_string, ['rp' => '100000'], 'listar');
    }

    // ==========================================================================
    // UTILITY HELPERS
    // ==========================================================================   
    
    private function send_post_request_lib($url, $auth, $postData, $action = 'listar') {
    
    $this->curl = new Curl();

    $default_params = [
        'page'      => '1',
        'rp'        => '20000',
        'sortorder' => 'asc'
    ];
    
    if ($action === '') {
        $final_data = $postData;
    } else {
        $final_data = array_merge($default_params, $postData);
    }        
    $this->curl->create($url);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($auth)
    ];

    if (stripos($url, 'webservice') !== false) {
        $headers[] = 'ixcsoft: ' . $action;        
    } 

    
    $this->curl->option(CURLOPT_HTTPHEADER, $headers);
    $this->curl->option(CURLOPT_RETURNTRANSFER, true);
    $this->curl->option(CURLOPT_TIMEOUT, 1000);
    $this->curl->option(CURLOPT_SSL_VERIFYPEER, true);
    $this->curl->option(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    $this->curl->option(CURLOPT_ENCODING, '');
    $this->curl->option(CURLINFO_HEADER_OUT, true);

    $this->curl->post(json_encode($final_data));
    $response = $this->curl->execute();
    $http_code = $this->curl->info['http_code'];
    

    if ($http_code >= 400) {
        $this->api_model->save_api_log($url, $final_data, $response, $action, $http_code );
    }

    return json_decode($response, true);
}

    private function send_post_request($url, $auth, array $postData = [], $action = 'listar')
    {
        // Define parâmetros padrão apenas se a ação exigir
        $default_params = [
            'page'      => '1',
            'rp'        => '20000',
            'sortorder' => 'asc'
        ];
    
        $final_data = ($action === '') 
            ? $postData 
            : array_merge($default_params, $postData);
    
        // Monta headers
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($auth)
        ];
    
        if (stripos($url, 'webservice') !== false) {
            $headers[] = 'ixcsoft: ' . $action;
        }
    
        // Inicia sessão cURL
        $ch = curl_init($url);
    
        // Configura opções
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_SSL_VERIFYPEER  => false, // defina true se tiver CA configurado
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING        => '',
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => json_encode($final_data),
            CURLINFO_HEADER_OUT     => true,
        ]);
    
        // Executa requisição
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
    
        // Fecha handle
        curl_close($ch);
    
        // Decodifica JSON
        $decoded = json_decode($response, true);
        $data = [
            'url'       => $url,
            'payload'   => is_string($final_data) ? $final_data : json_encode($final_data),
            'response'  => $decoded,
            'http_code' => $http_code,            
        ];
        
        $json_error = json_last_error();
    
        // Loga erros HTTP, cURL ou JSON
        if ($http_code >= 400 || $json_error !== JSON_ERROR_NONE || !empty($curl_error)) {
            $this->api_model->save_api_log(
                $url,
                $final_data,
                $response ?: $curl_error,
                $action,
                $http_code,
                $json_error
            );
        }
    
        // Retorna resposta decodificada ou erro
        if (!empty($curl_error)) {
            return ['error' => 'cURL error: ' . $curl_error];
        }
    
        if ($json_error !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response'];
        }
    
        return $decoded;
    }


    private function convert_to_brasilia($data_utc) {
        try {
            $date = new DateTime($data_utc, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $this->flux_log->write_log('api_controller', 'Invalid date format for conversion: ' . $data_utc);
            return $data_utc;
        }
    }
}
