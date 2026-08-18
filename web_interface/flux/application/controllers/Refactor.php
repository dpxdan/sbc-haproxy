<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negocios
//
// Copyright (C) 2023 Flux Telecom
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

class Refactor extends MX_Controller {

public $repoPath = '/opt/flux';

    function __construct() {
        parent::__construct();
        $this->load->model("db_model");
        $this->load->library("flux/common");
        $this->load->library("flux_log");
    }

    // public function getRate($pattern, $rates){
    //     $filtered = array_filter($rates, function($item) use ($pattern){
    //         return $item['pattern'] == $pattern;
    //     });

    //     $result = reset($filtered);
    //     return $result['cost'];
    // }

    function index(){
        $query = $this->db->query("SELECT * FROM refactor where refactor_date < NOW() and status = 0");
        $result = $query->result_array();

        if ($result){
            // $this->flux_log->write_log("REFACTOR_CONTROLLER", json_encode($result));
            foreach ($result as $row){

                try{

                    $data_status['status'] = 2;
    
                    // update status
                    $this->db->where("id", $row['id']);
                    $this->db->update("refactor", $data_status);
    
                    // get rates
                    // $this->db->where("id", $row['pricelist_id']);
                    // $result_pricelist = $this->db->get("pricelists");
                    // $pricelist = $result_pricelist->row_array();
                    
                    $rates = $this->db->get_where('routes', array(
                        'status' => '0',
                        'pricelist_id' => $row['pricelist_id'],
                    ))->result_array();
                    $this->flux_log->write_log("REFACTOR_CONTROLLER - Rates", json_encode($rates));
                    
                    $rateMap = [];
                    foreach ($rates as $rate) {
                        $rateMap[$rate['pattern']] = $rate['cost'];
                    }
    
                    // get CDR
                    $this->db->where_not_in('calltype', array('FREE', 'Gratuita', 'DID'));
                    $cdrsData = $this->db->get_where('cdrs', array(
                        'accountid' => $row['account_id'],
                        'callstart >=' => $row['from_date'],
                        'callstart <=' => $row['to_date'],
                        'call_direction' => 'outbound',
                        'disposition' => 'NORMAL_CLEARING [16]',
                        // 'debit >' => 0,
                        'billseconds >' => 0,
                    ))->result_array();
    
                    // refactor
                    foreach ($cdrsData as $key) {                    
                        if (isset($rateMap[$key['pattern']])) {
                            // $this->flux_log->write_log("REFACTOR_CONTROLLER - Tariff key", json_encode($key));
                            $cost = $rateMap[$key['pattern']];
    
                            if ($cost != $key['rate_cost']){
                                if ($key['billseconds'] <= 30){
                                    $tariffedTime = 30;
                                } else {
                                    $restTime = $key['billseconds'] - 30;
                                    $tariffedTime = 30 + ceil($restTime / 6) * 6;
                                }
        
                                $calc_debit = round((($tariffedTime / 60) * $cost), 4);
                            } else {
                                $cost = $key['rate_cost'];
                                $calc_debit = $key['debit'];
                            }
                            $data['debit'] = $calc_debit;
                            $data['rate_cost'] = $cost;
                            $data['pricelist_id'] = $row['pricelist_id'];
                            
                            if ($key['pricelist_id'] != $row['pricelist_id'] || $cost != $key['rate_cost']) {
                                $this->db->where("uniqueid", $key['uniqueid']);
                                $this->db->update("cdrs", $data);
                            }
                        }
                        
                    }
                }catch(Exception $e){
                    $error_message = $e->getMessage();
                    $this->flux_log->write_log("REFACTOR_CONTROLLER - Exception", "Registro ID: {$row['id']} => " . $error_message);   
                }
            }

            $data_status['status'] = 1;

            // update status
            $this->db->where("id", $row['id']);
            $this->db->update("refactor", $data_status);

        }

        exit;
    }

}