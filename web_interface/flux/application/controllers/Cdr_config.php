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

class Cdr_config extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('settings_model');
        $this->load->library('flux/jsoncdr_manager');
        $this->load->config('jsoncdr');
        $this->load->library('flux_log');

        $this->api_key = $this->config->item('jsoncdr')['api_key'];
    }

    public function index() {
        $this->load->helper('url');
        $mode = $this->settings_model->get('cdr_mode') ?: 'file';
        $data = [
            'mode' => $mode,
            'cdr_url' => $this->settings_model->get('cdr_url'),
            'cdr_log_dir' => $this->settings_model->get('cdr_log_dir'),
            'page_title' => gettext('Configuração JSON CDR')
        ];
        $this->load->view('cdr_config_view', $data);
    }

    public function getMode() {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['mode' => $this->settings_model->get('cdr_mode')]);
    }

    public function setMode() {
        header('Content-Type: application/json; charset=utf-8');

		$raw = file_get_contents('php://input');
		$input = json_decode($raw, true);
	    $this->flux_log->write_log('setMode', json_encode($input));
		if (!is_array($input) || empty($input['mode'])) {
			http_response_code(400);
			echo json_encode(['error' => 'mode required']);
			return;
		}

        $mode = $input['mode'];
        $url = isset($input['url']) ? $input['url'] : null;
        $logDir = isset($input['log_dir']) ? $input['log_dir'] : null;

        if (!in_array($mode, ['file','url'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid mode']);
            return;
        }
        $this->settings_model->mark_cdr_mode('cdr_mode', $mode);
        $this->settings_model->mark_cdr_mode('cdr_log_dir', $logDir);
        $this->settings_model->mark_cdr_mode('cdr_url', $url);
        $this->jsoncdr_manager->apply();

        echo json_encode(['ok' => true, 'mode' => $mode]);
    }
}
