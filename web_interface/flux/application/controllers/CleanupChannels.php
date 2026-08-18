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

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class CleanupChannels extends CI_Controller
{
    private $lock_file = '/var/log/freeswitch/cleanup_channels.lock';
    private $interval_seconds = 3600;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('db_model');
        $this->load->library('freeswitch_lib');
        $this->load->library('flux/common');
        $this->load->library('flux_log');
        $this->load->model('common_model');

        $cleanup_interval = Common_model::$global_config['system_config']['cleanup_channels'];
        if (isset($cleanup_interval) && !empty($cleanup_interval && $cleanup_interval > 0)) {
            $this->interval_seconds = $cleanup_interval;
        }
    }

    public function index()
    {
        if (!$this->acquireLock()) {
            $this->flux_log->write_log('WARN', 'Another cleanup process is already running, aborting this run');
            exit;
        }

        $this->flux_log->write_log('INFO', 'Start of cleanup of orphaned channels, calls, and stale db_data');

        $fs_servers = $this->getFreeswitchServers();
        if (empty($fs_servers)) {
            $this->flux_log->write_log('ERROR', 'No FreeSWITCH server configured, aborting cleanup');
            $this->releaseLock();
            exit;
        }

        $channels_removed = $this->cleanupOrphanChannels($fs_servers);
        $calls_removed = $this->cleanupOrphanCalls();
        $db_data_removed = $this->cleanupStaleDbData($fs_servers);

        $this->flux_log->write_log('INFO', "Cleanup done: channels=$channels_removed, calls=$calls_removed, db_data=$db_data_removed");

        $this->releaseLock();
        exit;
    }

    private function cleanupOrphanChannels($fs_servers)
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $this->interval_seconds);

        $query = $this->db->query(
            "SELECT uuid, created, hostname FROM channels WHERE created < ? ORDER BY created ASC",
            array($cutoff)
        );

        if ($query->num_rows() == 0) {
            $this->flux_log->write_log('INFO', 'No old channels found');
            return 0;
        }

        $removed = 0;
        foreach ($query->result() as $row) {
            $exists = $this->uuidExistsInFs($fs_servers, $row->uuid);

            if (!$exists) {
                $this->flux_log->write_log('INFO', "CHANNEL-ORPHAN uuid={$row->uuid} created={$row->created}");
                $this->db->where('uuid', $row->uuid);
                $this->db->delete('channels');
                $removed++;
            }
        }

        return $removed;
    }

    private function cleanupOrphanCalls()
    {
        $cutoff_epoch = time() - $this->interval_seconds;

        $query = $this->db->query(
            "SELECT c.call_uuid, c.caller_uuid, c.callee_uuid, c.call_created_epoch
             FROM calls c
             WHERE NOT EXISTS (SELECT 1 FROM channels ch WHERE ch.uuid = c.caller_uuid)
               AND NOT EXISTS (SELECT 1 FROM channels ch WHERE ch.uuid = c.callee_uuid)
               AND c.call_created_epoch < ?
             ORDER BY c.call_created_epoch ASC",
            array($cutoff_epoch)
        );

        if ($query->num_rows() == 0) {
            return 0;
        }

        $removed = 0;
        foreach ($query->result() as $row) {
            $this->flux_log->write_log('INFO', "CALL-ORPHAN call_uuid={$row->call_uuid} epoch={$row->call_created_epoch}");
            $this->db->where('call_uuid', $row->call_uuid);
            $this->db->delete('calls');
            $removed++;
        }

        return $removed;
    }

    private function cleanupStaleDbData($fs_servers)
    {
        $query = $this->db->query(
            "SELECT hostname, realm, data_key FROM db_data WHERE realm LIKE 'user_%' OR realm LIKE 'gw_%' OR realm LIKE 'did_%'"
        );

        if ($query->num_rows() == 0) {
            return 0;
        }

        $active_channels = $this->getActiveChannelAccounts($fs_servers);

        $removed = 0;
        foreach ($query->result() as $row) {
            $account_key = $row->realm;
            if (!in_array($account_key, $active_channels)) {
                $this->flux_log->write_log('INFO', "DB_DATA-STALE realm={$row->realm} key={$row->data_key}");
                $this->db->where('realm', $row->realm);
                $this->db->where('data_key', $row->data_key);
                $this->db->delete('db_data');
                $removed++;
            }
        }

        return $removed;
    }

    private function getActiveChannelAccounts($fs_servers)
    {
        $accounts = array();

        foreach ($fs_servers as $server) {
            $fp = $this->freeswitch_lib->event_socket_create(
                $server['freeswitch_host'],
                $server['freeswitch_port'],
                $server['freeswitch_password']
            );

            if (!$fp) {
                continue;
            }

            $response = $this->freeswitch_lib->event_socket_request($fp, 'api show channels');
            fclose($fp);

            if (empty($response)) {
                continue;
            }

            $lines = explode("\n", $response);
            $header = array();
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (empty($header) || strpos($line, 'uuid,') === 0) {
                    $header = explode(',', $line);
                    continue;
                }
                if (strpos($line, ' total.') !== false) {
                    break;
                }
                $cols = explode(',', $line);
                $accountcode_idx = array_search('accountcode', $header);
                if ($accountcode_idx !== false && isset($cols[$accountcode_idx])) {
                    $acc = trim($cols[$accountcode_idx]);
                    if ($acc !== '') {
                        $accounts[] = 'user_' . $acc;
                    }
                }
            }
        }

        return array_unique($accounts);
    }

    private function uuidExistsInFs($fs_servers, $uuid)
    {
        foreach ($fs_servers as $server) {
            $fp = $this->freeswitch_lib->event_socket_create(
                $server['freeswitch_host'],
                $server['freeswitch_port'],
                $server['freeswitch_password']
            );

            if (!$fp) {
                continue;
            }

            $response = $this->freeswitch_lib->event_socket_request($fp, "api uuid_exists " . $uuid);
            fclose($fp);

            $response = trim($response);
            if ($response === 'true') {
                return true;
            }
        }

        return false;
    }

    private function getFreeswitchServers()
    {
        $query = $this->db_model->getSelect("*", "freeswich_servers", "");
        return $query->result_array();
    }

    private function acquireLock()
    {
        if (file_exists($this->lock_file)) {
            $pid = (int)file_get_contents($this->lock_file);
            if ($pid > 0 && file_exists("/proc/$pid")) {
                return false;
            }
        }
        file_put_contents($this->lock_file, getmypid());
        return true;
    }

    private function releaseLock()
    {
        if (file_exists($this->lock_file)) {
            @unlink($this->lock_file);
        }
    }
}
