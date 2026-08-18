<?php

// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
//
// Copyright (C) 2021 Flux Telecom
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
class permissions_model extends CI_Model
{

    const MODULE_PERMISSIONS_MAX_LENGTH = 2000;

    function permissions_model()
    {
        parent::__construct();
        $this->load->library("flux_log");
    }

    function getpermissions_list($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('permissions_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $where = array(
                "reseller_id" => $reseller
            );
        } 
        else {
            $where = array(
                "reseller_id" => "0"
            );
        }
        if ($flag) {
            $query = $this->db_model->select("*", "view_permissions", $where, "id", "DESC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "view_permissions", $where);
        }
        return $query;
    }

    function add_permissions($add_array)
    {
        $permission_array = array();
        unset($add_array["save_button"]);
        $permission_array = $add_array['permission'];
        $permission_encode = json_encode($permission_array);
        $insert_array = array();
        $insert_array['permissions'] = $permission_encode;
        $insert_array['name'] = $add_array['name'];
        $insert_array['description'] = $add_array['description'];
        $insert_array['login_type'] = $add_array['login_type'];
        $insert_array['creation_date'] = gmdate('Y-m-d H:i:s');
        $insert_array['modification_date'] = '0000-00-00 00:00:00';
        $accountinfo = $this->session->userdata("accountinfo");
        $insert_array['reseller_id'] = $accountinfo['type'] == 1 ? $accountinfo['id'] : 0;
        $this->db->insert("permissions", $insert_array);
        return $this->db->insert_id();
    }

    function edit_permissions($add_array, $id)
    {
        $permission_array = array();
        unset($add_array["save_button"]);
        $permission_array = $add_array['permission'];
        $permission_encode = json_encode($permission_array);
        $update_array = array();
        $update_array['permissions'] = $permission_encode;
        $update_array['name'] = $add_array['name'];
        $update_array['login_type'] = $add_array['login_type'];
        $update_array['description'] = $add_array['description'];
        $update_array['modification_date'] = gmdate('Y-m-d H:i:s');
        $this->db->where("id", $id);
        $this->db->update("permissions", $update_array);
    }

    function remove_permissions($id)
    {
        $this->db->delete("permissions", array(
            "id" => $id
        ));
        return true;
    }

    function get_permission_types()
    {
        return $this->db_model->getSelect('*', 'permissions_types', '')->result_array();
    }

    function get_permission_types_except($permission_type_code)
    {
        $where = array('permission_type_code <>' => $permission_type_code);
        return $this->db_model->getSelectWithOrder('*', 'permissions_types', $where, 'asc', 'permission_name')->result_array();
    }

    function sync_userlevels_modules($role_id, $permission_array)
    {
        $role_id = (int) $role_id;

        if ($role_id <= 0 || !is_array($permission_array) || empty($permission_array)) {
            return false;
        }

        $allowed_pairs = array();
        foreach ($permission_array as $module_name => $module_value) {
            if (!is_array($module_value)) {
                continue;
            }
            foreach ($module_value as $module_url => $event_array) {
                if (is_array($event_array) && isset($event_array['list']) && $event_array['list'] == '0') {
                    $allowed_pairs[$module_name . '/' . $module_url] = true;
                }
            }
        }

        if (empty($allowed_pairs)) {
            return false;
        }

        $this->db->select('id, menu_label, module_name, module_url');
        $menu_query = $this->db->get('menu_modules');

        $module_groups = array();
        foreach ($menu_query->result_array() as $menu_row) {
            $pair = $this->menu_module_pair($menu_row);
            if ($pair === '' || !isset($allowed_pairs[$pair])) {
                continue;
            }
            $label = trim($menu_row['menu_label']);
            if (!isset($module_groups[$label])) {
                $module_groups[$label] = array();
            }
            $module_groups[$label][] = (int) $menu_row['id'];
        }

        if (empty($module_groups)) {
            return false;
        }

        foreach ($module_groups as $label => $ids) {
            sort($ids);
            $module_groups[$label] = $ids;
        }

        $this->db->distinct();
        $this->db->select('type');
        $this->db->where('permission_id', $role_id);
        $account_query = $this->db->get('accounts');

        if ($account_query->num_rows() == 0) {
            return false;
        }

        foreach ($account_query->result_array() as $account_row) {
            $this->add_modules_to_userlevel((int) $account_row['type'], $module_groups);
        }

        return true;
    }

    function menu_module_pair($menu_row)
    {
        $parts = array();
        foreach (explode('/', $menu_row['module_url']) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        if (count($parts) >= 2) {
            return $parts[0] . '/' . $parts[1];
        }

        if (count($parts) == 1) {
            return $menu_row['module_name'] . '/' . $parts[0];
        }

        return '';
    }

    function add_modules_to_userlevel($userlevelid, $module_groups)
    {
        $where = array('userlevelid' => $userlevelid);
        $query = $this->db_model->getSelect('module_permissions', 'userlevels', $where);

        if ($query->num_rows() == 0) {
            return false;
        }

        $row         = $query->row_array();
        $current_str = trim($row['module_permissions']);
        $current_ids = array();

        foreach (explode(',', $current_str) as $current_id) {
            $current_id = trim($current_id);
            if ($current_id !== '') {
                $current_ids[] = (int) $current_id;
            }
        }

        $missing_ids = array();
        foreach ($module_groups as $group_ids) {
            if (empty($group_ids)) {
                continue;
            }
            $already = array_intersect($group_ids, $current_ids);
            if (empty($already)) {
                $missing_ids[] = $group_ids[0];
            }
        }

        if (empty($missing_ids)) {
            return false;
        }

        $new_str = $current_str === ''
            ? implode(',', $missing_ids)
            : $current_str . ',' . implode(',', $missing_ids);

        if (strlen($new_str) > self::MODULE_PERMISSIONS_MAX_LENGTH) {
            $this->load->library('flux_log');
            $this->flux_log->write_log(
                'error',
                'sync_userlevels_modules: userlevelid ' . $userlevelid . ' nao atualizado, module_permissions excederia '
                . self::MODULE_PERMISSIONS_MAX_LENGTH . ' caracteres (' . strlen($new_str) . '). Ids nao aplicados: '
                . implode(',', $missing_ids)
            );
            return false;
        }

        $this->db->where('userlevelid', $userlevelid);
        $this->db->update('userlevels', array('module_permissions' => $new_str));

        return true;
    }

    function get_roles_and_permissions($login_type)
    {
        return $this->db_model->select(
            '*',
            'roles_and_permission',
            array(
                'login_type' => (int) $login_type,
                'status'     => 0,
            ),
            'priority',
            'ASC',
            '',
            ''
        )->result_array();
    }

}
