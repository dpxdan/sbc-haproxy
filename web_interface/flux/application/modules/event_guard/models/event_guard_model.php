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

class Event_guard_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('flux_log');
    }

    // ── Logs (grid flexigrid) ─────────────────────────────────────────────────

    public function get_event_guard_list($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('event_guard_list_search');

        $where = array();

        if ($flag) {
            return $this->db_model->select('*', 'event_guard_logs', $where, 'id', 'DESC', $limit, $start);
        } else {
            return $this->db_model->countQuery('*', 'event_guard_logs', $where);
        }
    }

    public function get_logs($filters = array(), $limit = 100, $start = 0)
    {
        $where = array();

        if (!empty($filters['log_status'])) {
            $where['log_status'] = $filters['log_status'];
        }
        if (!empty($filters['hostname'])) {
            $where['hostname'] = $filters['hostname'];
        }

        if (!empty($filters['ip_address'])) {
            $this->db->like('ip_address', $filters['ip_address']);
        }

        $query = $this->db_model->select(
            'id, log_uuid, hostname, log_date, filter, ip_address, extension, user_agent, log_status',
            'event_guard_logs',
            $where,
            'log_date',
            'DESC',
            $limit,
            $start
        );

        return $query->num_rows() > 0 ? $query->result_array() : array();
    }

    public function count_logs($filters = array())
    {
        $where = array();

        if (!empty($filters['log_status'])) {
            $where['log_status'] = $filters['log_status'];
        }
        if (!empty($filters['hostname'])) {
            $where['hostname'] = $filters['hostname'];
        }
        if (!empty($filters['ip_address'])) {
            $this->db->like('ip_address', $filters['ip_address']);
        }

        return $this->db_model->countQuery('*', 'event_guard_logs', $where);
    }

    public function set_pending($log_uuid)
    {
        $this->db->where('log_uuid', $log_uuid);
        $this->db->where('log_status', 'blocked');
        $this->db->update('event_guard_logs', array(
            'log_status' => 'pending',
            'log_date'   => gmdate('Y-m-d H:i:s'),
        ));

        return $this->db->affected_rows() > 0;
    }

    public function get_log_by_id($id)
    {
        $query = $this->db_model->getSelect('*', 'event_guard_logs', array('id' => (int) $id));
        $row   = $query->row_array();
        return $row ?: array();
    }

    public function update_log($data, $id)
    {
        $data['log_date'] = gmdate('Y-m-d H:i:s');
        $this->db->where('id', (int) $id);
        $this->db->update('event_guard_logs', $data);
        return $this->db->affected_rows() > 0;
    }

    public function add_log($data)
    {
        $data['log_date'] = gmdate('Y-m-d H:i:s');
        $this->db->insert('event_guard_logs', $data);
        return $this->db->insert_id();
    }

    // ── Whitelist ─────────────────────────────────────────────────────────────

    public function get_whitelist()
    {
        $query = $this->db_model->select(
            'id, cidr, description, created_at',
            'event_guard_whitelist',
            array(),
            'created_at',
            'DESC'
        );

        return $query->num_rows() > 0 ? $query->result_array() : array();
    }

    public function get_whitelist_by_id($id)
    {
        $query = $this->db_model->getSelect('*', 'event_guard_whitelist', array('id' => (int) $id));
        $row   = $query->row_array();
        return $row ?: array();
    }

    public function get_whitelist_cidr($id)
    {
        $query = $this->db_model->getSelect('cidr', 'event_guard_whitelist', array('id' => (int) $id));
        $row   = $query->row_array();

        return isset($row['cidr']) ? $row['cidr'] : '';
    }

    public function whitelist_cidr_exists($cidr, $exclude_id = 0)
    {
        $this->db->where('cidr', $cidr);
        if ($exclude_id > 0) {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return $this->db->count_all_results('event_guard_whitelist') > 0;
    }

    public function add_whitelist($data)
    {
        $data['created_at'] = gmdate('Y-m-d H:i:s');
        $this->db->insert('event_guard_whitelist', $data);
        return $this->db->insert_id();
    }

    public function edit_whitelist($data, $id)
    {
        $data['created_at'] = gmdate('Y-m-d H:i:s');
        $this->db->where('id', (int) $id);
        $this->db->update('event_guard_whitelist', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete_whitelist($id)
    {
        return $this->db_model->delete('event_guard_whitelist', array('id' => (int) $id));
    }

    public function get_whitelist_list($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('event_guard_whitelist_list_search');

        if ($flag) {
            return $this->db_model->select(
                'id, cidr, description, created_at',
                'event_guard_whitelist',
                array(),
                'created_at',
                'DESC',
                $limit,
                $start
            );
        } else {
            return $this->db_model->countQuery('*', 'event_guard_whitelist', array());
        }
    }

    public function delete_multiple_logs($ids)
    {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        $ids_safe = array_map('intval', $ids);
        $this->db->where_in('id', $ids_safe);
        return $this->db->delete('event_guard_logs');
    }

    public function delete_logs($id)
    {
        return $this->db_model->delete('event_guard_logs', array('id' => (int) $id));
    }

    public function get_log_by_uuid($log_uuid)
    {
        $query = $this->db_model->getSelect(
            'id, ip_address, filter, log_status',
            'event_guard_logs',
            array('log_uuid' => $log_uuid)
        );
        $row = $query->row_array();
        return $row ?: array();
    }

    public function set_unblocked($log_uuid, $target_status = 'unblocked')
    {
        $allowed_target_statuses = array('pending', 'unblocked', 'tracking');
        if (!in_array($target_status, $allowed_target_statuses)) {
            $target_status = 'unblocked';
        }

        $this->db->where('log_uuid', $log_uuid);
        $this->db->update('event_guard_logs', array(
            'log_status' => $target_status,
            'log_date'   => gmdate('Y-m-d H:i:s'),
        ));
        return $this->db->affected_rows() > 0;
    }

    public function set_blocked($log_uuid)
    {
        $this->db->where('log_uuid', $log_uuid);
        $block_statuses = array('unblocked', 'tracking', 'pending');
        $this->db->where_in('log_status', $block_statuses);
        $this->db->update('event_guard_logs', array(
            'log_status' => 'blocked',
            'log_date'   => gmdate('Y-m-d H:i:s'),
        ));
        return $this->db->affected_rows() > 0;
    }

    public function mark_blocked($log_uuid)
    {
        $this->db->where('log_uuid', $log_uuid);
        return $this->db->update('event_guard_logs', array(
            'log_status' => 'blocked',
            'log_date'   => gmdate('Y-m-d H:i:s'),
        ));
    }

    public function delete_multiple_whitelist($ids)
    {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        $ids_safe = array_map('intval', $ids);
        $this->db->where_in('id', $ids_safe);
        return $this->db->delete('event_guard_whitelist');
    }

    public function get_ip_map($id)
    {
        $query = $this->db_model->getSelect('*', 'ip_map', array('id' => (int) $id));
        $row   = $query->row_array();
        return $row ?: array();
    }

    public function get_recent_blocked_ips($limit = 5)
    {
        $query = $this->db_model->select(
            'id, ip_address, country, filter, log_date, failures',
            'event_guard_logs',
            array('log_status' => 'blocked'),
            'log_date',
            'DESC',
            $limit,
            0
        );

        return $query->num_rows() > 0 ? $query->result_array() : array();
    }
}
