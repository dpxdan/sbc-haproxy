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

class Detraf_reports_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
    }

    function generate_report($params)
    {
        $carrier_id     = ($params['carrier_id'] !== '') ? $params['carrier_id'] : NULL;
        $call_direction = ($params['call_direction'] !== '') ? $params['call_direction'] : NULL;

        $sql = "CALL get_detraf_report(?, ?, ?, ?, ?)";

        $query = $this->db->query($sql, array(
            $params['start_date'],
            $params['end_date'],
            $carrier_id,
            $params['eot_cred'],
            $call_direction
        ));

        if ($query === FALSE) {

            $error_message = ($this->db->conn_id instanceof mysqli)
                ? $this->db->conn_id->error
                : 'unknown error';
            log_message('error', 'Detraf: falha ao executar get_detraf_report - ' . $error_message);
            return FALSE;
        }

        $result = $query->result_array();

        if ($this->db->conn_id instanceof mysqli) {
            while ($this->db->conn_id->more_results() && $this->db->conn_id->next_result()) {
                $extra_result = $this->db->conn_id->store_result();
                if ($extra_result instanceof mysqli_result) {
                    $extra_result->free();
                }
            }
        }

        return $result;
    }

    function insert_log($data)
    {
        $insert = array(
            'job_id'          => isset($data['job_id']) ? $data['job_id'] : NULL,
            'start_date'     => $data['start_date'],
            'end_date'        => $data['end_date'],
            'carrier_id'      => $data['carrier_id'] !== '' ? $data['carrier_id'] : NULL,
            'eot_cred'     => $data['eot_cred'],
            'call_direction'  => $data['call_direction'] !== '' ? $data['call_direction'] : NULL,
            'total_data' => $data['total_data'],
            'file'    => $data['file'],
            'path' => $data['path'],
            'generation_time'  => $data['generation_time'],
            'status'          => 1,
            'creation_date'   => date('Y-m-d H:i:s'),
        );

        if (isset($data['reseller_id']) && $data['reseller_id'] > 0) {
            $insert['reseller_id'] = $data['reseller_id'];
        } elseif ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $insert['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
        }

        $this->db->insert('detraf_report_logs', $insert);
        return $this->db->insert_id();
    }

    function enqueue_job($data)
    {
        $insert = array(
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'carrier_id'     => $data['carrier_id'],
            'eot_cred'       => $data['eot_cred'],
            'call_direction' => $data['call_direction'],
            'status'         => 'queued',
            'queued_at'      => date('Y-m-d H:i:s'),
            'creation_date'  => date('Y-m-d H:i:s'),
        );

        $reseller_id = 0;
        if ($this->session->userdata('accountinfo')) {
            $insert['requested_by'] = $this->session->userdata["accountinfo"]['id'];
        }

        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $reseller_id            = $this->session->userdata["accountinfo"]['id'];
            $insert['reseller_id'] = $reseller_id;
        }

        $this->db->insert('detraf_report_jobs', $insert);
        $job_id = $this->db->insert_id();

        $this->insert_log(array(
            'job_id'          => $job_id,
            'start_date'      => $data['start_date'],
            'end_date'        => $data['end_date'],
            'carrier_id'      => $data['carrier_id'],
            'eot_cred'        => $data['eot_cred'],
            'call_direction'  => $data['call_direction'],
            'total_data'      => 0,
            'file'            => '',
            'path'            => '',
            'generation_time' => NULL,
            'reseller_id'     => $reseller_id,
        ));

        return $job_id;
    }

    function lock_next_job()
    {
        $stale_threshold = gmdate('Y-m-d H:i:s', strtotime('-15 minutes'));

        $this->db->select('id');
        $this->db->where("(status = 'queued' OR (status = 'running' AND locked_at < '" . $this->db->escape_str($stale_threshold) . "'))", NULL, FALSE);
        $this->db->order_by('id', 'ASC');
        $this->db->limit(1);
        $candidate = $this->db->get('detraf_report_jobs')->row_array();

        if (! $candidate) {
            return FALSE;
        }

        $now = gmdate('Y-m-d H:i:s');

        $this->db->where('id', $candidate['id']);
        $this->db->where("(status = 'queued' OR (status = 'running' AND locked_at < '" . $this->db->escape_str($stale_threshold) . "'))", NULL, FALSE);
        $this->db->set('status', "'running'", FALSE);
        $this->db->set('locked_at', "'" . $now . "'", FALSE);
        $this->db->set('started_at', "IFNULL(started_at, '" . $now . "')", FALSE);
        $this->db->update('detraf_report_jobs');

        if ($this->db->affected_rows() !== 1) {
            return FALSE;
        }

        return $this->db_model->getSelect('*', 'detraf_report_jobs', array('id' => $candidate['id']))->row_array();
    }

    function mark_job_done($job_id, $total_files, $total_records)
    {
        $this->db->set('status', "'done'", FALSE);
        $this->db->set('finished_at', "'" . gmdate('Y-m-d H:i:s') . "'", FALSE);
        $this->db->set('total_files', (int) $total_files);
        $this->db->set('total_records', (int) $total_records);
        $this->db->where('id', $job_id);
        $this->db->update('detraf_report_jobs');
    }

    function mark_job_error($job_id, $message)
    {
        $this->db->set('status', "'error'", FALSE);
        $this->db->set('finished_at', "'" . gmdate('Y-m-d H:i:s') . "'", FALSE);
        $this->db->set('error_message', $message);
        $this->db->where('id', $job_id);
        $this->db->update('detraf_report_jobs');
    }

    function requeue_job($job_id)
    {
        $where = array('id' => $job_id, 'status' => 'error');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $where['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
        }

        $this->db->set('status', "'queued'", FALSE);
        $this->db->set('error_message', NULL);
        $this->db->set('locked_at', NULL);
        $this->db->set('started_at', NULL);
        $this->db->set('finished_at', NULL);
        $this->db->where($where);
        $this->db->update('detraf_report_jobs');

        return $this->db->affected_rows() > 0;
    }

    function get_job($job_id)
    {
        $where = array('id' => $job_id);
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $where['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
        }
        return $this->db_model->getSelect('*', 'detraf_report_jobs', $where)->row_array();
    }

    function get_log($id)
    {
        $where = array('id' => $id);
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $where['reseller_id'] = $this->session->userdata["accountinfo"]['id'];
        }
        $query = $this->db_model->getSelect("*", "detraf_report_logs", $where);
        return $query->row_array();
    }

    function get_detraf_reports_list($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('detraf_reports_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $reseller_id = $this->session->userdata["accountinfo"]['id'];
            $where       = array("reseller_id" => $reseller_id);
        } else {
            $reseller_id = NULL;
            $where       = array();
        }

        if (! $flag) {
            return $this->db_model->countQuery("*", "detraf_report_logs", $where);
        }

        $status_case = "CASE IFNULL(j.status, 'done')"
            . " WHEN 'queued'  THEN '<span class=\"badge badge-secondary\">" . gettext('Queued') . "</span>'"
            . " WHEN 'running' THEN '<span class=\"badge badge-warning\">" . gettext('Processing') . "</span>'"
            . " WHEN 'error'   THEN '<span class=\"badge badge-danger\">" . gettext('Error') . "</span>'"
            . " ELSE '<span class=\"badge flx_bgreen\">" . gettext('Done') . "</span>'"
            . " END AS job_status";

        $this->db->select("l.id, l.start_date, l.end_date, l.eot_cred, (CASE WHEN (l.call_direction='inbound') THEN concat('Entrada') ELSE concat('Saída') END) AS call_direction, l.total_data,
            l.file, l.path, l.creation_date, l.reseller_id, j.error_message AS job_error_message, " . $status_case, FALSE);
        $this->db->from("detraf_report_logs l");
        $this->db->join("detraf_report_jobs j", "j.id = l.job_id", "left");
        if ($reseller_id !== NULL) {
            $this->db->where("l.reseller_id", $reseller_id);
        }
        $this->db->order_by("l.id", "DESC");
        if ($limit) {
            $this->db->limit($limit, $start);
        }
        return $this->db->get();
    }

    function delete_log($id)
    {
        return $this->db->delete("detraf_report_logs", array('id' => $id));
    }

    function fill_log_placeholder($job_id, $data)
    {
        $update = array(
            'start_date'      => $data['start_date'],
            'end_date'        => $data['end_date'],
            'call_direction'  => $data['call_direction'] !== '' ? $data['call_direction'] : NULL,
            'total_data'      => $data['total_data'],
            'file'            => $data['file'],
            'path'            => $data['path'],
            'generation_time' => $data['generation_time'],
        );

        $this->db->where('job_id', $job_id);
        $this->db->where('file', '');
        $this->db->update('detraf_report_logs', $update);

        return $this->db->affected_rows() === 1;
    }

    function process_job($job, $report_dir)
    {
        try {
            $directions = ($job['call_direction'] !== NULL && $job['call_direction'] !== '')
                ? array($job['call_direction'])
                : array('inbound', 'outbound');

            $chunks = $this->split_period_by_month(
                date('Y-m-d', strtotime($job['start_date'])),
                date('Y-m-d', strtotime($job['end_date']))
            );

            $total_records = 0;
            $total_files    = 0;

            foreach ($chunks as $chunk) {
                foreach ($directions as $direction) {
                    $params = array(
                        'start_date'     => $chunk['start'],
                        'end_date'       => $chunk['end'],
                        'carrier_id'     => $job['carrier_id'],
                        'eot_cred'       => $job['eot_cred'],
                        'call_direction' => $direction,
                    );

                    $time_start      = microtime(true);
                    $rows            = $this->generate_report($params);
                    $generation_time = round(microtime(true) - $time_start, 3);

                    if ($rows === FALSE) {
                        throw new Exception(sprintf(
                            'Failed to execute get_detraf_report for %s to %s (%s).',
                            $chunk['start'], $chunk['end'], $direction
                        ));
                    }

                    $type      = ($direction === 'outbound') ? 'S' : 'E';
                    $file      = $this->build_cdr_filename($chunk['end'], $type);
                    $full_path = rtrim($report_dir, '/') . '/' . $file;

                    if (! $this->write_cdr_file($full_path, $rows)) {
                        throw new Exception('Failed to write the .CDR file on the server: ' . $full_path);
                    }

                    $log_data                    = $params;
                    $log_data['job_id']          = $job['id'];
                    $log_data['total_data']      = count($rows);
                    $log_data['file']            = $file;
                    $log_data['path']            = $full_path;
                    $log_data['generation_time'] = $generation_time;
                    $log_data['reseller_id']     = $job['reseller_id'];

                    if ($total_files === 0 && $this->fill_log_placeholder($job['id'], $log_data)) {
                        // primeiro arquivo do job: reaproveita a linha placeholder criada no enqueue_job
                    } else {
                        $this->insert_log($log_data);
                    }

                    $total_records += count($rows);
                    $total_files++;
                }
            }

            $this->mark_job_done($job['id'], $total_files, $total_records);

        } catch (Exception $e) {
            log_message('error', 'Detraf job #' . $job['id'] . ' failed: ' . $e->getMessage());
            $this->mark_job_error($job['id'], $e->getMessage());
        }
    }

    function split_period_by_month($start_date, $end_date)
    {
        $chunks = array();

        $current   = new DateTime($start_date);
        $end_total = new DateTime($end_date);

        while ($current <= $end_total) {
            $month_end = DateTime::createFromFormat('Y-m-d', $current->format('Y-m-t'));
            $chunk_end = ($month_end < $end_total) ? $month_end : clone $end_total;

            $chunks[] = array(
                'start' => $current->format('Y-m-d'),
                'end'   => $chunk_end->format('Y-m-d'),
            );

            $current = clone $chunk_end;
            $current->modify('+1 day');
        }

        return $chunks;
    }

    function build_cdr_filename($end_date, $type)
    {
        return sprintf('LC_FLUX_%s_SBC_%s.CDR', $type, str_replace('-', '', $end_date));
    }

    function write_cdr_file($filepath, $rows)
    {
        $fp = @fopen($filepath, 'w');
        if (! $fp) {
            log_message('error', 'Detraf: nao foi possivel abrir arquivo para escrita - ' . $filepath);
            return FALSE;
        }

        foreach ($rows as $row) {
            $line = implode(';', array(
                $row['id'],
                $row['EOTCREDORA'],
                $row['EOTDEVEDORA'],
                $row['GRUPOHORARIO'],
                $row['ASSINANTEA'],
                $row['DATADACHAMADA'],
                $row['HORADEATENDIMENTO'],
                $row['ASSINANTEB'],
                $row['DURACAOREAL'],
                $row['POIPPI'],
                $row['DESCRITOR'],
                $row['DURACAODETRAF'],
                $row['CATEGORIA'],
                $row['FDS'],
                $row['EXITCODE'],
                $row['CONTADORSAIDASPARCIAIS'],
                $row['IDENTIFICADORORIGEM'],
                $row['VRR'],
                $row['CNLA'],
                $row['CNLB'],
                $row['LOCALA'],
                $row['LOCALB'],
                $row['MESMAAREA'],
                $row['EOTA'],
                $row['EOTB'],
                $row['VALOR'],
                $row['RN1'],
                $row['troncoss7'],
                $row['DURATION'],
                $row['DATAHORA'],
                $row['encarreirar'],
                $row['CALLID'],
            ));

            if (fwrite($fp, $line . "\r\n") === FALSE) {
                fclose($fp);
                log_message('error', 'Detraf: falha ao escrever linha no arquivo - ' . $filepath);
                return FALSE;
            }
        }

        fclose($fp);
        return TRUE;
    }
}
