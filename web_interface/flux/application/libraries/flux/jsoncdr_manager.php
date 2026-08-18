<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
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
if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );
class jsoncdr_manager {
    protected $CI;
    protected $cfg;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->config('jsoncdr');
        $this->cfg = $this->CI->config->item('jsoncdr');
        $this->CI->load->model ( 'settings_model' );
        $this->CI->load->library('flux_log');
    }

    public function apply() {
        $mode = $this->CI->settings_model->get('cdr_mode') ?: 'file';
        $url = $this->CI->settings_model->get('cdr_url') ?: '';
        $logDir = $this->CI->settings_model->get('cdr_log_dir') ?: '/var/log/freeswitch/json_cdr';
        
        $logDataApply = [
				'mode'          => $mode,
				'url'          => $url,
				'logDir'=> $logDir,        
			];
	    $this->CI->flux_log->write_log('apply', json_encode($logDataApply));
        
        $tplPath = $this->cfg['template_path'];
        $outPath = $this->cfg['output_path'];

        if (!file_exists($tplPath)) {
            throw new RuntimeException('Template not found: ' . $tplPath);
        }

        $tpl = file_get_contents($tplPath);
        $replacements = array(
            '{{URL}}' => $mode === 'url' ? $this->escape($url) : '',
            '{{LOG_DIR}}' => $mode === 'file' ? $this->escape($logDir) : '',
            '{{AUTH_SCHEME}}' => 'basic',
            '{{LOG_DISK}}' => $mode === 'file' ? 'true' : 'false',
            '{{CRED}}' => '',
            '{{ENCODE}}' => 'false',
            '{{RETRIES}}' => '0',
            '{{DELAY}}' => '5000',
            '{{DISABLE_100_CONTINUE}}' => 'false',
            '{{ERR_LOG_DIR}}' => '/var/log/freeswitch/json_cdr/json_cdr_error',
            '{{SSL_KEY_PATH}}' => '',
            '{{SSL_KEY_PASSWORD}}' => '',
            '{{SSL_VERSION}}' => '',
            '{{ENABLE_SSL_VERIFYHOST}}' => 'false',
            '{{SSL_CERT_PATH}}' => '',
            '{{ENABLE_CACERT_CHECK}}' => 'false',
            '{{SSL_CACERT_FILE}}' => '',
        );
        $this->CI->flux_log->write_log('jsoncdr_manager_apply', json_encode($replacements));
        $final = strtr($tpl, $replacements);

        if (file_put_contents($outPath, $final) === false) {
            throw new RuntimeException('Failed to write config to ' . $outPath . '. Check permissions.');
        }

        $this->reloadModule();
        if ($mode === 'file') {
            $this->systemctl('enable --now json_cdr.service');
        } else {
            $this->systemctl('stop --now json_cdr.service');
            $this->systemctl('disable json_cdr.service');
        }

        return true;
    }

    protected function escape($v) {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function reloadModule() {
        @exec('/usr/bin/fs_cli -x "reload mod_json_cdr" 2>&1', $out, $rc);
        if ($rc !== 0) {
            $this->CI->flux_log->write_log('error', 'Jsoncdr_manager: reload mod_json_cdr failed: ' . implode("\n", (array)$out));
        }
    }
    
    protected function systemctl($args) {

    if (preg_match('/[^a-z0-9\\.\\-\\s_]+/i', $args)) {
        $this->CI->flux_log->write_log('error', 'Jsoncdr_manager: suspicious systemctl args: ' . $args);
        return;
    }

    $cmd = 'sudo /bin/systemctl ' . $args . ' 2>&1';

    @exec($cmd, $out, $rc);

    if ($rc !== 0) {
        $this->CI->flux_log->write_log(
            'error',
            'Jsoncdr_manager: systemctl ' . $args . ' failed: ' . implode("\n", (array)$out)
        );
    }
}

}
