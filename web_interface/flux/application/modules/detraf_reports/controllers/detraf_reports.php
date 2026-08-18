<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e neg—cios
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

if (! defined('BASEPATH'))
    exit('No direct script access allowed');

class detraf_reports extends MX_Controller
{
    private $report_dir;
    function __construct()
    {
        parent::__construct();

        $this->load->helper('template_inheritance');

        $this->load->library('session');
        $this->load->library("detraf_reports_form");
        $this->load->library('flux/form', 'detraf_reports_form');
        $this->load->library('flux/permission');
        $this->load->library('flux_log');
        $this->load->model('detraf_reports_model');

       $this->report_dir = FCPATH . 'attachments/detraf_reports/';
        if (! is_dir($this->report_dir)) {
            if (! @mkdir($this->report_dir, 0750, TRUE) && ! is_dir($this->report_dir)) {
                log_message('error', 'Detraf: nao foi possivel criar o diretorio ' . $this->report_dir);
            }
        }
    }

    private function _require_login()
    {
        if ($this->session->userdata('user_login') == FALSE)
            redirect(base_url() . '/flux/login');
    }

    function detraf_reports_form()
    {
        $this->_require_login();
        $data['page_title'] = gettext('Generate Detraf Report');
        $data['form'] = $this->form->build_form($this->detraf_reports_form->get_detraf_reports_form_fields(), '');
        $this->load->view('view_detraf_reports_generate', $data);
    }

    function detraf_reports_add()
    {
        $this->_require_login();
        $data['username'] = $this->session->userdata('user_name');
        $data['page_title'] = gettext('Generate Detraf Report');
        $data['form'] = $this->form->build_form($this->detraf_reports_form->get_detraf_reports_form_fields(''), '');
        $this->load->view('view_detraf_reports_generate', $data);
    }


    function detraf_reports_generate()
    {
        $this->_require_login();
        $post = $this->input->post();

        $start_date    = $this->_sanitize_post_value($post['start_date'] ?? '');
        $end_date       = $this->_sanitize_post_value($post['end_date'] ?? '');
        $carrier_id     = $this->_sanitize_post_value($post['carrier_id'] ?? '');
        $eot_cred    = $this->_sanitize_post_value($post['eot_cred'] ?? '') !== '' ? $this->_sanitize_post_value($post['eot_cred']) : 'E83';
        $call_direction = $this->_sanitize_post_value($post['call_direction'] ?? '');

        if ($start_date === '' || $end_date === '') {
            echo json_encode(array('ERROR' => gettext('Start date and end date are required.')));
            exit();
        }

        if (! $this->_validate_date($start_date) || ! $this->_validate_date($end_date)) {
            echo json_encode(array('ERROR' => gettext('Invalid date format. Use YYYY-MM-DD.')));
            exit();
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            echo json_encode(array('ERROR' => gettext('End date cannot be earlier than start date.')));
            exit();
        }

        if ($call_direction !== '' && ! in_array($call_direction, array('inbound', 'outbound'), true)) {
            echo json_encode(array('ERROR' => gettext('Invalid call direction.')));
            exit();
        }

        $job_id = $this->detraf_reports_model->enqueue_job(array(
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'carrier_id'     => $carrier_id !== '' ? $carrier_id : NULL,
            'eot_cred'       => $eot_cred,
            'call_direction' => $call_direction !== '' ? $call_direction : NULL,
        ));

        echo json_encode(array(
            'SUCCESS' => gettext('Detraf report queued successfully! You can track its progress in the report list.'),
            'job_id'  => $job_id,
        ));
        exit();
    }

    function detraf_reports_requeue($job_id = '')
    {
        $this->_require_login();
        $this->permission->check_web_record_permission($job_id, 'detraf_report_jobs', 'detraf_reports/detraf_reports_list/');
        $this->detraf_reports_model->requeue_job($job_id);
        redirect(base_url() . 'detraf_reports/detraf_reports_list/');
    }

    function detraf_reports_list()
    {
        $this->_require_login();
        $data['page_title']  = gettext('Detraf Report History');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields']  = $this->detraf_reports_form->build_detraf_list_for_admin();
        $data['grid_buttons'] = $this->detraf_reports_form->build_grid_buttons();
        $data['form_search']  = $this->form->build_serach_form($this->detraf_reports_form->get_search_detraf_reports_form());
        $this->load->view('view_detraf_reports_list', $data);
    }

    function detraf_reports_list_json()
    {
        $this->_require_login();
        $json_data   = array();
        $count_all   = $this->detraf_reports_model->get_detraf_reports_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data   = $paging_data["json_paging"];
        $query       = $this->detraf_reports_model->get_detraf_reports_list(true, $paging_data["paging"]["start"], $paging_data["paging"]["page_no"]);
        $grid_fields = json_decode($this->detraf_reports_form->build_detraf_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    function detraf_reports_list_search()
    {
        $this->_require_login();
        $this->session->set_userdata('detraf_reports_list_search', $this->input->post());
        $this->session->set_userdata('advance_search', 1);
    }

    function detraf_reports_list_clearsearchfilter()
    {
        $this->_require_login();
        $this->session->unset_userdata('detraf_reports_list_search');
        $this->session->set_userdata('advance_search', 0);
    }

    function detraf_reports_download($log_id = '')
    {
        $this->_require_login();
        $this->permission->check_web_record_permission($log_id, 'detraf_report_logs', 'detraf_reports/detraf_reports_list/');

        $log = $this->detraf_reports_model->get_log($log_id);
        if (! $log) {
            show_404();
        }

        $filepath = $log['path'];
        if (! is_file($filepath)) {
            log_message('error', 'Detraf: arquivo nao encontrado para download - ' . $filepath);
            show_error(gettext('File not found on the server.'), 404);
        }

        $this->_send_file_download($log['file'], file_get_contents($filepath));
    }

    private function _send_file_download($filename, $data)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimes = array();
        if (defined('ENVIRONMENT') && is_file(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php')) {
            include(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php');
        } elseif (is_file(APPPATH . 'config/mimes.php')) {
            include(APPPATH . 'config/mimes.php');
        }

        if (! isset($mimes[$extension])) {
            $mime = 'application/octet-stream';
        } else {
            $mime = is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($data));

        echo $data;
        exit();
    }

    function detraf_reports_delete($delete_id = '')
    {
        $this->_require_login();
        $this->permission->check_web_record_permission($delete_id, 'detraf_report_logs', 'detraf_reports/detraf_reports_list/');
        $this->_delete_log_and_file($delete_id);
        redirect(base_url() . 'detraf_reports/detraf_reports_list/');
    }

    function detraf_reports_delete_multiple()
    {
        $this->_require_login();
        $ids     = $this->input->post("selected_ids", true);
        $ids_exp = explode(",", $ids);
        foreach ($ids_exp as $value) {
            $value = str_replace("'", "", $value);
            $this->_delete_log_and_file($value);
        }
        echo $this->db->affected_rows();
    }

    private function _delete_log_and_file($id)
    {
        $log = $this->detraf_reports_model->get_log($id);
        if ($log && is_file($log['path'])) {
            @unlink($log['path']);
        }
        $this->detraf_reports_model->delete_log($id);
    }

    private function _sanitize_post_value($value)
    {
        if (is_array($value)) {
            $value = end($value);
            $value = ($value === FALSE) ? '' : $value;
        }
        return trim((string) $value);
    }

    private function _validate_date($data)
    {
        if (is_array($data)) {
            $data = isset($data[0]) ? $data[0] : '';
        }

        $dataString = (string) $data;

        $d = DateTime::createFromFormat('Y-m-d', $dataString);

        return $d && $d->format('Y-m-d') === $dataString;
    }

}
