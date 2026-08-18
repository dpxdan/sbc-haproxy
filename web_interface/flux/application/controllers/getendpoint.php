<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2022 Flux Telecom
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

class Getendpoint extends MX_Controller {
function Getendpoint() {
		parent::__construct ();
		$this->load->model ( 'common_model' );
		$this->load->library ( 'common' );
		$this->load->model ( 'db_model' );
		$this->load->model ( 'Flux_common' );
	}

function index($endpoint_id = '') {
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
                'oper' => '>',
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
		}
		
?>