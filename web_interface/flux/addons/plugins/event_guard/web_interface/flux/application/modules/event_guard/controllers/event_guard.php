<?php

// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2026 Flux Telecom
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

class Event_guard extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('template_inheritance');
        $this->load->library('session');
        $this->load->library('flux_log');
        $this->load->library('event_guard_form');
        $this->load->library('flux/form', 'event_guard_form');
        $this->load->library('flux/permission');
        $this->load->library('FLUX_Sms');
        $this->load->model('event_guard_model');
        if ($this->session->userdata('user_login') == false) {
            redirect(base_url() . '/flux/login');
        }
    }

    // ── Logs ─────────────────────────────────────────────────────────────────

    public function event_guard_list()
    {
        $data['username']    = $this->session->userdata('user_name');
        $data['page_title']  = gettext('Event Guard - Blocked IPs');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields']  = $this->event_guard_form->build_event_guard_list_for_admin();
        $data['grid_buttons'] = $this->event_guard_form->build_grid_buttons();
        $data['form_search'] = $this->form->build_serach_form($this->event_guard_form->get_event_guard_search_form());
        $this->load->view('view_event_guard_list', $data);
    }

    public function event_guard_list_json()
    {
        $json_data   = array();
        $count_all   = $this->event_guard_model->get_event_guard_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data   = $paging_data['json_paging'];
        $query       = $this->event_guard_model->get_event_guard_list(true, $paging_data['paging']['start'], $paging_data['paging']['page_no']);
        $grid_fields = json_decode($this->event_guard_form->build_event_guard_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    public function event_guard_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);

        if ($this->input->post('advance_search', true) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('event_guard_list_search', $action);
        }

        if (@$ajax_search != 1) {
            redirect(base_url() . 'event_guard/event_guard_list/');
        }
    }

    public function event_guard_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('event_guard_list_search', '');
        redirect(base_url() . 'event_guard/event_guard_list/');
    }

    public function event_guard_unblock($id = '')
    {
        $this->flux_log->write_log('event_guard', json_encode(array(
            'action' => 'event_guard_unblock',
            'id'     => $id,
        )));

        $log_id = trim((string) $this->input->post('id'));
        if (empty($log_id)) {
            $log_id = (int) $id;
        }

        $result = $this->_do_unblock($log_id);

        $msg = isset($result['ip'])
            ? gettext('IP'). ' ' . $result['ip'] . ' ' . gettext('removed from block.')
            : ($result['error'] ?? gettext('Error.'));

        $this->session->set_flashdata('flux_errormsg', $msg);
        redirect(base_url() . 'event_guard/event_guard_list/');
    }

    public function event_guard_block($id = '')
    {
        $this->flux_log->write_log('event_guard', json_encode(array(
            'action' => 'event_guard_block',
            'id'     => $id,
        )));

        $log_id = trim((string) $this->input->post('id'));

        if (empty($log_id)) {
            $log_id = (int) $id;
        }

        $result = $this->_do_block($log_id);

        if (!empty($result['success'])) {
            $msg = gettext('IP'). ' ' . $result['ip'] . ' ' . gettext('added to block successfully!');
        } else {
            $msg = isset($result['error']) ? $result['error'] : gettext('Error.');
        }

        $this->session->set_flashdata('flux_notification', $msg);
        redirect(base_url() . 'event_guard/event_guard_list/');
    }

    public function event_guard_add()
    {
        $data['page_title'] = gettext('Event Guard - Add to Block');
        $data['form'] = $this->form->build_form(
            $this->event_guard_form->get_log_add_form_fields(),
            ''
        );
        $this->load->view('view_event_guard_add', $data);
    }

    public function event_guard_edit($id = '')
    {
        $data['page_title'] = gettext('Event Guard - Edit Log Entry');

        $id     = (int) $id;
        $record = array();

        if ($id > 0) {
            $record = $this->event_guard_model->get_log_by_id($id);
        }

        if (empty($record)) {
            $this->session->set_flashdata('flux_notification', gettext('Record not found.'));
            redirect(base_url() . 'event_guard/event_guard_list');
            return;
        }

        $data['form'] = $this->form->build_form(
            $this->event_guard_form->get_log_edit_form_fields($id),
            $record
        );

        $this->load->view('view_event_guard_edit', $data);
    }

    public function event_guard_log_save()
    {
        $add_array = $this->input->post();
        $edit_id   = (int) ($add_array['id'] ?? 0);

        $form_fields = ($edit_id > 0)
            ? $this->event_guard_form->get_log_edit_form_fields($edit_id)
            : $this->event_guard_form->get_log_add_form_fields();

        $data['form'] = $this->form->build_form($form_fields, $add_array);

        if ($this->form_validation->run() == false) {
            $data['validation_errors'] = validation_errors();
            echo $data['validation_errors'];
            exit();
        }

        $ip = trim((string) ($add_array['ip_address'] ?? ''));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(array('event_guard_error' => gettext('Invalid IP address.')));
            exit();
        }

        $allowed_filters  = array('sip-auth-fail', 'sip-auth-ip');
        $allowed_statuses = array('blocked', 'pending', 'unblocked', 'tracking');

        $filter     = trim((string) ($add_array['filter'] ?? ''));
        $log_status = ($edit_id <= 0)
            ? 'pending'
            : trim((string) ($add_array['log_status'] ?? ''));

        if (!in_array($filter, $allowed_filters)) {
            echo json_encode(array('event_guard_error' => gettext('Invalid chain.')));
            exit();
        }

        if (!in_array($log_status, $allowed_statuses)) {
            echo json_encode(array('event_guard_error' => gettext('Invalid status.')));
            exit();
        }

        $hostname    = Common_model::$global_config['system_config']['hostname'];
        $log_uuid    = $this->generate_uuid();
        $current_log = array();
        $old_status  = '';

        if ($edit_id > 0) {
            $current_log = $this->event_guard_model->get_log_by_id($edit_id);

            if (empty($current_log)) {
                echo json_encode(array('event_guard_error' => gettext('Record not found.')));
                exit();
            }

            $old_status = isset($current_log['log_status'])
                ? trim((string) $current_log['log_status'])
                : '';

            if (!empty($current_log['log_uuid'])) {
                $log_uuid = $current_log['log_uuid'];
            }

            if (!empty($current_log['hostname'])) {
                $hostname = $current_log['hostname'];
            }
        }

        if ($edit_id <= 0) {
            $add_array['country'] = $this->lookupCountry($ip);
        }

        $safe_data = array(
            'ip_address' => $ip,
            'log_uuid'   => $log_uuid,
            'filter'     => $filter,
            'log_status' => $log_status,
            'hostname'   => $hostname,
            'extension'  => trim((string) ($add_array['extension']  ?? '')),
            'user_agent' => trim((string) ($add_array['user_agent'] ?? '')),
            'failures'   => (int)  ($add_array['failures'] ?? 1),
            'country'    => trim((string) ($add_array['country']    ?? '')),
        );

        if ($edit_id <= 0) {
            $edit_id = $this->event_guard_model->add_log($safe_data);

            $this->flux_log->write_log('event_guard', json_encode(array(
                'action'  => 'manual_add_log',
                'edit_id' => $edit_id,
                'ip'      => $ip,
                'filter'  => $filter,
            )));

            $block_result = $this->_do_block($edit_id);

            if (empty($block_result['success'])) {
                $error = isset($block_result['error'])
                    ? $block_result['error']
                    : gettext('Unable to block IP.');

                echo json_encode(array('event_guard_error' => $error));
                exit();
            }

            echo json_encode(array(
                'SUCCESS' => gettext('IP') . ' ' . $ip . ' ' . gettext('added to block successfully!')
            ));
            exit();
        }

        $this->flux_log->write_log('event_guard_update', json_encode(array(
            'edit_id'    => $edit_id,
            'old_status' => $old_status,
            'new_status' => $log_status,
            'data'       => $safe_data,
        )));

        if ($log_status == 'blocked') {
            $this->event_guard_model->update_log($safe_data, $edit_id);

            $block_result = $this->_do_block($edit_id);

            $this->flux_log->write_log('block_result', json_encode($block_result));

            if (empty($block_result['success'])) {
                $error = isset($block_result['error'])
                    ? $block_result['error']
                    : gettext('Unable to block IP.');

                echo json_encode(array('event_guard_error' => $error));
                exit();
            }
        } else {
            if ($old_status == 'blocked') {
                $unblock_result = $this->_do_unblock($edit_id, $log_status);

                $this->flux_log->write_log('unblock_result', json_encode($unblock_result));

                if (empty($unblock_result['success'])) {
                    $error = isset($unblock_result['error'])
                        ? $unblock_result['error']
                        : gettext('Unable to unblock IP.');

                    echo json_encode(array('event_guard_error' => $error));
                    exit();
                }
            }

            $this->event_guard_model->update_log($safe_data, $edit_id);
        }

        echo json_encode(array(
            'SUCCESS' => gettext('IP') . ' ' . $ip . ' ' . gettext('updated successfully!')
        ));

        exit();
    }

    // ── Whitelist ─────────────────────────────────────────────────────────────

    public function event_guard_whitelist_add()
    {
        $data['page_title'] = gettext('Event Guard - Add to Whitelist');
        $data['form'] = $this->form->build_form(
            $this->event_guard_form->get_whitelist_form_fields(),
            ''
        );
        $this->load->view('view_event_guard_whitelist_add', $data);
    }

    public function event_guard_whitelist_edit($id = '')
    {
        $data['page_title'] = gettext('Event Guard - Edit Whitelist');

        $id     = (int) $id;
        $record = array();

        if ($id > 0) {
            $record = $this->event_guard_model->get_whitelist_by_id($id);
        }

        $data['form'] = $this->form->build_form(
            $this->event_guard_form->get_whitelist_form_fields($id),
            $record
        );
        $this->load->view('view_event_guard_whitelist_add', $data);
    }

    public function event_guard_whitelist_save()
    {
        $add_array = $this->input->post();

        $edit_id = isset($add_array['id']) ? (int) $add_array['id'] : 0;
        $data['form'] = $this->form->build_form(
            $this->event_guard_form->get_whitelist_form_fields($edit_id),
            $add_array
        );

        if ($this->form_validation->run() == false) {
            $data['validation_errors'] = validation_errors();
            echo $data['validation_errors'];
            exit();
        }

        $cidr = trim((string) ($add_array['cidr'] ?? ''));
        if (!$this->_valid_cidr($cidr)) {
            echo json_encode(array('event_guard_error' => gettext('Invalid CIDR format.')));
            exit();
        }

        $safe_data = array(
            'cidr'        => $cidr,
            'description' => trim((string) ($add_array['description'] ?? '')),
        );

        if ($edit_id > 0) {
            if ($this->event_guard_model->whitelist_cidr_exists($cidr, $edit_id)) {
                echo json_encode(array('event_guard_error' => gettext('IP already exists in system.')));
                exit();
            }

            $this->event_guard_model->edit_whitelist($safe_data, $edit_id);
            echo json_encode(array(
                'SUCCESS' => $cidr . ' ' . gettext('Updated Successfully!')
            ));
            exit();

        } else {
            if ($this->event_guard_model->whitelist_cidr_exists($cidr)) {
                echo json_encode(array('event_guard_error' => gettext('IP already exists in system.')));
                exit();
            }

            $this->event_guard_model->add_whitelist($safe_data);
            $this->session->set_flashdata('flux_errormsg', gettext('IP Added Successfully!'));
            echo json_encode(array(
                'SUCCESS' => $cidr . ' ' . gettext('Added Successfully!')
            ));
            exit();
        }
    }

    public function event_guard_whitelist_delete($id = 0)
    {
        $id = (int) $id;

        if ($id <= 0) {
            $this->session->set_flashdata('flux_notification', gettext('Invalid ID.'));
            redirect(base_url() . 'event_guard/event_guard_list');
            return;
        }

        $cidr = $this->event_guard_model->get_whitelist_cidr($id);
        $this->event_guard_model->delete_whitelist($id);
        $this->flux_log->write_log('event_guard', json_encode(array(
            'action' => 'whitelist_delete',
            'cidr'   => $cidr,
        )));
        $this->session->set_flashdata('flux_notification', $cidr . ' ' . gettext('removed from whitelist.'));
        redirect(base_url() . 'event_guard/event_guard_whitelist_list/');
    }

    public function event_guard_whitelist_delete_multiple()
    {
        $ids_raw = $this->input->post('selected_ids', true);

        if (is_array($ids_raw)) {
            $ids = $ids_raw;
        } else {
            $ids = array_filter(array_map(function ($v) {
                return (int) trim(str_replace("'", '', $v));
            }, explode(',', (string) $ids_raw)));
        }

        $this->flux_log->write_log('event_guard', json_encode(array(
            'action'       => 'event_guard_whitelist_delete_multiple',
            'selected_ids' => $ids,
        )));

        if (empty($ids)) {
            echo json_encode(array('success' => false, 'error' => gettext('No records selected.')));
            return;
        }

        echo $this->event_guard_model->delete_multiple_whitelist($ids);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function _valid_cidr($cidr)
    {
        if (strpos($cidr, '/') === false) {
            return (bool) filter_var($cidr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }

        $parts = explode('/', $cidr, 2);
        $ip    = $parts[0];
        $bits  = $parts[1];

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && is_numeric($bits)
            && (int) $bits >= 0
            && (int) $bits <= 32;
    }

    public function event_guard_whitelist_list()
    {
        $data['page_title']   = gettext('Event Guard - Whitelist');
        $data['search_flag'] = true;
        $this->session->set_userdata('advance_search', 0);
        $data['grid_fields']  = $this->event_guard_form->build_whitelist_list_for_admin();
        $data['grid_buttons'] = $this->event_guard_form->build_whitelist_grid_buttons();
        $data['form_search'] = $this->form->build_serach_form($this->event_guard_form->get_event_guard_whitelist_search_form());
        $this->load->view('view_event_guard_whitelist_list', $data);
    }

    public function event_guard_whitelist_list_json()
    {
        $count_all   = $this->event_guard_model->get_whitelist_list(false);
        $paging_data = $this->form->load_grid_config($count_all, $_GET['rp'], $_GET['page']);
        $json_data   = $paging_data['json_paging'];
        $query       = $this->event_guard_model->get_whitelist_list(true, $paging_data['paging']['start'], $paging_data['paging']['page_no']);
        $grid_fields = json_decode($this->event_guard_form->build_whitelist_list_for_admin());
        $json_data['rows'] = $this->form->build_grid($query, $grid_fields);
        echo json_encode($json_data);
    }

    public function event_guard_whitelist_list_search()
    {
        $ajax_search = $this->input->post('ajax_search', 0);

        if ($this->input->post('advance_search', true) == 1) {
            $this->session->set_userdata('advance_search', $this->input->post('advance_search'));
            $action = $this->input->post();
            unset($action['action']);
            unset($action['advance_search']);
            $this->session->set_userdata('event_guard_whitelist_list_search', $action);
        }

        if (@$ajax_search != 1) {
            redirect(base_url() . 'event_guard/event_guard_whitelist_list/');
        }
    }

    public function event_guard_whitelist_list_clearsearchfilter()
    {
        $this->session->set_userdata('advance_search', 0);
        $this->session->set_userdata('event_guard_whitelist_list_search', '');
        redirect(base_url() . 'event_guard/event_guard_whitelist_list/');
    }

    public function event_guard_delete_multiple()
    {
        $ids_raw = $this->input->post('selected_ids', true);

        if (is_array($ids_raw)) {
            $ids = $ids_raw;
        } else {
            $ids = array_filter(array_map(function ($v) {
                return (int) trim(str_replace("'", '', $v));
            }, explode(',', (string) $ids_raw)));
        }

        $this->flux_log->write_log('event_guard', json_encode(array(
            'action'       => 'event_guard_delete_multiple',
            'selected_ids' => $ids,
        )));

        if (empty($ids)) {
            echo json_encode(array('success' => false, 'error' => gettext('No records selected.')));
            return;
        }

        foreach ($ids as $id) {
            $this->_do_unblock($id);
        }

        echo $this->event_guard_model->delete_multiple_logs($ids);
    }

    private function _do_block($id)
    {
        $log_id = (int) $id;

        if ($log_id <= 0) {
            return array('success' => false, 'error' => gettext('Invalid ID.'));
        }

        $log = $this->event_guard_model->get_log_by_id($log_id);

        if (empty($log)) {
            return array('success' => false, 'error' => gettext('Record not found.'));
        }

        $ip       = trim((string) $log['ip_address']);
        $filter   = trim((string) $log['filter']);
        $log_uuid = trim((string) $log['log_uuid']);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('success' => false, 'error' => gettext('Invalid IP address.'));
        }

        if (empty($filter) || empty($log_uuid)) {
            return array('success' => false, 'error' => gettext('Invalid block record.'));
        }

        $cmd = "sudo fail2ban-client set " . escapeshellarg($filter) . " banip " . escapeshellarg($ip) . " 2>&1";
        $output_lines = array();
        $return_code  = 0;
        exec($cmd, $output_lines, $return_code);
        $output = trim(implode("\n", $output_lines));

        $this->flux_log->write_log('event_guard', json_encode(array(
            'action'      => 'fail2ban_banip',
            'cmd'         => $cmd,
            'output'      => $output,
            'return_code' => $return_code,
        )));

        if ($return_code !== 0) {
            return array(
                'success'    => false,
                'ip'         => $ip,
                'f2b_output' => $output,
                'error'      => trim($output) !== '' ? $output : gettext('Unable to block IP.')
            );
        }

        $ok = $this->event_guard_model->mark_blocked($log_uuid);

        if ($ok) {
            $this->flux_log->write_log('event_guard', json_encode(array(
                'action'   => 'block',
                'log_uuid' => $log_uuid,
                'ip'       => $ip,
                'filter'   => $filter,
            )));
        }

        return array('success' => $ok, 'ip' => $ip, 'f2b_output' => $output);
    }

    private function _do_unblock($id, $target_status = 'unblocked')
    {
        $log_id = (int) $id;

        if ($log_id <= 0) {
            return array('success' => false, 'error' => gettext('Invalid ID.'));
        }

        $allowed_target_statuses = array('pending', 'unblocked', 'tracking');
        if (!in_array($target_status, $allowed_target_statuses)) {
            $target_status = 'unblocked';
        }

        $log = $this->event_guard_model->get_log_by_id($log_id);

        if (empty($log)) {
            return array('success' => false, 'error' => gettext('Record not found.'));
        }

        if (!in_array($log['log_status'], array('blocked', 'tracking', 'pending'))) {
            return array('success' => false, 'error' => gettext('IP is not blocked or tracking.'));
        }

        $ip       = trim((string) $log['ip_address']);
        $filter   = trim((string) $log['filter']);
        $log_uuid = trim((string) $log['log_uuid']);

        $cmd = "sudo fail2ban-client set " . escapeshellarg($filter) . " unbanip " . escapeshellarg($ip) . " 2>&1";

        $output_lines = array();
        $return_code  = 0;
        exec($cmd, $output_lines, $return_code);

        $output = trim(implode("\n", $output_lines));

        $this->flux_log->write_log('event_guard_do_unblock', json_encode(array(
            'action'        => 'fail2ban_unbanip',
            'cmd'           => $cmd,
            'output'        => $output,
            'return_code'   => $return_code,
            'target_status' => $target_status,
        )));

        if ($return_code !== 0) {
            return array(
                'success'    => false,
                'ip'         => $ip,
                'f2b_output' => $output,
                'error'      => trim($output) !== '' ? $output : gettext('Unable to unblock IP.')
            );
        }

        $ok = $this->event_guard_model->set_unblocked($log_uuid, $target_status);

        return array('success' => $ok, 'ip' => $ip, 'f2b_output' => $output);
    }

    private function findExecutable(string $name): ?string
    {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($path !== '' && is_executable($path)) {
            return $path;
        }
        return null;
    }

    public function generate_uuid()
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function lookupCountry(string $ip): string
    {
        $isIpv6 = strpos($ip, ':') !== false;
        $binary  = $isIpv6 ? $this->findExecutable('geoiplookup6') : $this->findExecutable('geoiplookup');
        if ($binary === null) {
            return '';
        }
        exec($binary . ' ' . escapeshellarg($ip) . ' 2>/dev/null', $lines, $code);

        if ($code !== 0) {
            return '';
        }
        foreach ($lines as $line) {
            if (stripos($line, 'GeoIP Country Edition:') !== 0) {
                continue;
            }
            $country = trim(substr($line, strlen('GeoIP Country Edition:')));
            if ($country !== '' && stripos($country, 'not found') === false) {
                return $country;
            }
        }
        return '';
    }



}
