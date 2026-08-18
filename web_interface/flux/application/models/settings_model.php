<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2025 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// Flux SBC Version 4.0 and above
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

class settings_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->library('common');
        $this->load->model('common_model');
        $this->load->library('flux_log');
        $this->load->database();
    }

    public function get($key) {
        $q = $this->getSelect('value', 'system', array('name' => $key), 1);
        $row = $q->row();
        return $row ? $row->value : null;
    }
    function getSelect($select, $tableName, $where) {
    	$this->db->select ( $select, false );
    	$this->db->from ( $tableName );
    	if ($where != '') {
    		$this->db->where ( $where );
    	}
    	$query = $this->db->get ();
    	return $query;
    }
    public function set($key, $value) {
        $cdr_mode = common_model::$global_config['system_config']['cdr_mode'];
        $cdr_log_dir = common_model::$global_config['system_config']['cdr_log_dir'];
        $cdr_url = common_model::$global_config['system_config']['cdr_url'];
        return $this->db->replace('system', array(
            'name' => $key,
            'value' => $value
        ));
    }
    public function mark_cdr_mode($key, $value) {
        $this->db->where('name', $key);
        $this->db->update('system', [            
            'value'      => $value
        ]);
    }
}
