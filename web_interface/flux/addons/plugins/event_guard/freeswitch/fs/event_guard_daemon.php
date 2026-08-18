<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
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

define('ENVIRONMENT', 'production');

if (defined('ENVIRONMENT')) {
    switch (ENVIRONMENT) {
        case 'development':
            error_reporting(E_ALL);
            break;
        case 'testing':
        case 'production':
            error_reporting(0);
            break;
        default:
            exit('The application environment is not set correctly.');
    }
}

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '256M');

include("lib/flux.db.php");
include("lib/flux.logger.php");
include("lib/flux.fslogger.php");
include("lib/flux.lib.php");
include("lib/flux.constants.php");
include("lib/flux.custom.php");
include("lib/flux.eventsocket.php");

$db       = new db();
$lib      = new lib();
$config   = $lib->get_configurations($db);
$fslogger = new fslogger($lib);

$hostname        = $config['hostname']       ?? gethostname();
$block_threshold = (int)($config['block_threshold'] ?? 5);

$fs_server_row = $db->run("SELECT * FROM `freeswich_servers` WHERE `status` = 0 ORDER BY `last_modified_date` DESC LIMIT 1");

if (!empty($fs_server_row) && isset($fs_server_row[0]['freeswitch_password'])) {
    $esl_password = $fs_server_row[0]['freeswitch_password'];
    $esl_host = $fs_server_row[0]['freeswitch_host'];
    $esl_port = $fs_server_row[0]['freeswitch_port'];
    $fslogger->log('event_guard: senha ESL carregada de freeswich_servers.');
} else {
    $esl_password = $config['esl_password'] ?? 'ClueCon';
    $esl_host        = $config['esl_host']       ?? '127.0.0.1';
    $esl_port        = (int)($config['esl_port'] ?? 8021);
    $fslogger->log('event_guard: AVISO — freeswich_servers vazio, usando fallback da tabela system.');
}
unset($fs_server_row);

$watched_subclasses = array(
    'sofia::register_attempt',
    'sofia::register_failure',
    'sofia::wrong_call_state',
    'sofia::pre_register',
    'event_guard:unblock',
);

$f2b_jails = array(
    'sip-auth-fail' => 'sip-auth-fail',
    'sip-auth-ip'   => 'sip-auth-ip',
);

foreach ($f2b_jails as $jail) {
    fail2ban_jail_check($jail, $fslogger);
}

$allowed_cache   = array();
$whitelist_cache = null;
$whitelist_mtime = 0;
$reg_cache       = array();
$reg_cache_mtime = 0;

$WHITELIST_TTL = (int)($config['whitelist_ttl'] ?? 30);
$REG_TTL       = (int)($config['reg_ttl']       ?? 60);

openlog('fluxsbc', LOG_PID, LOG_AUTH);

$socket = new EventSocket();

if (!$socket->connect($esl_host, $esl_port, $esl_password)) {
    $fslogger->log('event_guard: não foi possível conectar ao Event Socket. Abortando.');
    closelog();
    exit(1);
}

esl_subscribe($socket, $fslogger);
$fslogger->log('event_guard: daemon iniciado. hostname=' . $hostname . ' threshold=' . $block_threshold);

while (true) {

    if (!$socket->connected()) {
        $fslogger->log('event_guard: Event Socket desconectado. Reconectando...');
        sleep(1);
        if ($socket->connect($esl_host, $esl_port, $esl_password)) {
            esl_subscribe($socket, $fslogger);
            $fslogger->log('event_guard: reconectado.');
        } else {
            $fslogger->log('event_guard: falha ao reconectar. Nova tentativa em 1s...');
            continue;
        }
    }

    $json_response = $socket->read_event();

    if (empty($json_response['$'])) {
        continue;
    }

    preg_match('/"Event-Subclass"\s*:\s*"([^"]+)"/', $json_response['$'], $_m);
    $subclass_raw = isset($_m[1]) ? urldecode($_m[1]) : '';
    unset($_m);

    if (!in_array($subclass_raw, $watched_subclasses, true)) {
        unset($json_response);
        continue;
    }

    $event = json_decode($json_response['$'], true);
    unset($json_response);

    if (!is_array($event)) {
        continue;
    }

    $subclass = $event['Event-Subclass'] ?? '';

    if ($subclass === 'sofia::register_attempt') {

        $auth_result = $event['auth-result'] ?? '';
        if ($auth_result === 'FORBIDDEN') {
            $ip = $event['network-ip'] ?? '';
            if ($ip !== '' && !access_allowed($ip, $db, $fslogger, $allowed_cache, $whitelist_cache, $whitelist_mtime, $reg_cache, $reg_cache_mtime, $WHITELIST_TTL, $REG_TTL, $esl_password)) {
                event_guard_block($ip, 'sip-auth-fail', $event, $db, $fslogger, $hostname, $block_threshold);
            }
        }

    } elseif ($subclass === 'sofia::register_failure') {

        $ip = $event['network-ip'] ?? '';
        if ($ip !== '' && !access_allowed($ip, $db, $fslogger, $allowed_cache, $whitelist_cache, $whitelist_mtime, $reg_cache, $reg_cache_mtime, $WHITELIST_TTL, $REG_TTL, $esl_password)) {
            event_guard_block($ip, 'sip-auth-fail', $event, $db, $fslogger, $hostname, $block_threshold);
        }

    } elseif ($subclass === 'sofia::wrong_call_state') {

        $ip = $event['network-ip'] ?? '';
        if ($ip !== '' && !access_allowed($ip, $db, $fslogger, $allowed_cache, $whitelist_cache, $whitelist_mtime, $reg_cache, $reg_cache_mtime, $WHITELIST_TTL, $REG_TTL, $esl_password)) {
            event_guard_block($ip, 'sip-auth-fail', $event, $db, $fslogger, $hostname, $block_threshold);
        }

    } elseif ($subclass === 'sofia::pre_register') {

        $to_host = $event['to-host'] ?? '';
        if (filter_var($to_host, FILTER_VALIDATE_IP)) {
            $ip = $event['network-ip'] ?? '';
            if ($ip !== '' && !access_allowed($ip, $db, $fslogger, $allowed_cache, $whitelist_cache, $whitelist_mtime, $reg_cache, $reg_cache_mtime, $WHITELIST_TTL, $REG_TTL, $esl_password)) {
                event_guard_block($ip, 'sip-auth-ip', $event, $db, $fslogger, $hostname, $block_threshold);
            }
        }

    } elseif ($subclass === 'event_guard:unblock') {

        process_unblock_queue($db, $fslogger, $hostname, $allowed_cache);

    }

    unset($event);
}

closelog();

// ─────────────────────────────────────────────────────────────────────────────

function esl_subscribe($socket, $fslogger)
{
    $socket->request('event json ALL');
    $socket->request('filter Event-Name CUSTOM');
    $fslogger->log('event_guard: inscrito no Event Socket.');
}

function access_allowed($ip, $db, $fslogger, &$allowed_cache, &$whitelist_cache, &$whitelist_mtime, &$reg_cache, &$reg_cache_mtime, $WHITELIST_TTL, $REG_TTL, $esl_password)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    $now = time();

    if (isset($allowed_cache[$ip])) {
        if (($now - $allowed_cache[$ip]) < $WHITELIST_TTL) {
            $fslogger->log('event_guard: cache hit para ' . $ip);
            return true;
        }
        unset($allowed_cache[$ip]);
        $fslogger->log('event_guard: cache expirado para ' . $ip . ', re-verificando.');
    }

    if (whitelist_allowed($ip, $db, $fslogger, $whitelist_cache, $whitelist_mtime, $WHITELIST_TTL)) {
        $allowed_cache[$ip] = $now;
        $fslogger->log('event_guard: ' . $ip . ' permitido pela whitelist.');
        return true;
    }

    if (is_registered($ip, $fslogger, $reg_cache, $reg_cache_mtime, $REG_TTL, $esl_password)) {
        $allowed_cache[$ip] = $now;
        $fslogger->log('event_guard: ' . $ip . ' permitido por registro ativo.');
        return true;
    }

    return false;
}

function whitelist_allowed($ip, $db, $fslogger, &$whitelist_cache, &$whitelist_mtime, $WHITELIST_TTL)
{
    $now = time();

    if ($whitelist_cache === null || ($now - $whitelist_mtime) >= $WHITELIST_TTL) {
        $rows            = $db->run("SELECT cidr FROM event_guard_whitelist");
        $whitelist_cache = is_array($rows) ? $rows : array();
        $whitelist_mtime = $now;
        $fslogger->log('event_guard: whitelist recarregada (' . count($whitelist_cache) . ' entradas).');
    }

    foreach ($whitelist_cache as $row) {
        if (cidr_match($row['cidr'], $ip)) {
            return true;
        }
    }

    return false;
}

function cidr_match($cidr, $ip)
{
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }

    list($subnet, $bits) = explode('/', $cidr, 2);
    $bits        = (int) $bits;
    $ip_long     = ip2long($ip);
    $subnet_long = ip2long($subnet);

    if ($ip_long === false || $subnet_long === false) {
        return false;
    }

    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

    return ($ip_long & $mask) === ($subnet_long & $mask);
}

function is_registered($ip, $fslogger, &$reg_cache, &$reg_cache_mtime, $REG_TTL, $esl_password)
{
    $now = time();

    if (($now - $reg_cache_mtime) >= $REG_TTL) {
        $output          = shell_exec("fs_cli -p{$esl_password} -x 'show registrations as json' 2>/dev/null");
        $reg_cache       = array();
        $reg_cache_mtime = $now;

        if (!empty($output)) {
            $data = json_decode($output, true);
            if (is_array($data['rows'] ?? null)) {
                foreach ($data['rows'] as $row) {
                    $rip = $row['network_ip'] ?? '';
                    if ($rip !== '') {
                        $reg_cache[$rip] = true;
                    }
                }
            }
        }

        $fslogger->log('event_guard: registrations recarregado (' . count($reg_cache) . ' IPs).');
    }

    return isset($reg_cache[$ip]);
}

function fail2ban_jail_check($jail, $fslogger)
{
    $output = shell_exec("sudo fail2ban-client status {$jail} 2>&1");

    if (strpos((string) $output, 'Sorry') !== false || strpos((string) $output, 'ERROR') !== false) {
        $fslogger->log('event_guard: AVISO — jail "' . $jail . '" não encontrado. Verifique /etc/fail2ban/jail.d/');
    } else {
        $fslogger->log('event_guard: jail ' . $jail . ' OK.');
    }
}

function event_guard_block($ip, $filter, $event, $db, $fslogger, $hostname, $block_threshold)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
    }

    $ip_safe     = $db->quote($ip);
    $filter_safe = $db->quote($filter);
    $host_safe   = $db->quote($hostname);

    $existing = $db->run(
        "SELECT id, failures, log_status FROM event_guard_logs
         WHERE ip_address = {$ip_safe}
           AND filter     = {$filter_safe}
           AND hostname   = {$host_safe}
           AND log_status IN ('tracking', 'blocked')
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!empty($existing) && $existing[0]['log_status'] === 'blocked') {
        $id_safe = (int) $existing[0]['id'];
        $fslogger->log('event_guard: ip=' . $ip . ' já bloqueado, incrementando failures. id=' . $id_safe);
        $db->run(
            "UPDATE event_guard_logs
             SET failures = failures + 1, log_date = NOW()
             WHERE id = {$id_safe}"
        );
        return;
    }

    if (!empty($existing) && $existing[0]['log_status'] === 'tracking') {
        $id_safe      = (int) $existing[0]['id'];
        $new_failures = (int) $existing[0]['failures'] + 1;

        $fslogger->log('event_guard: ip=' . $ip . ' tracking. failures=' . $new_failures . '/' . $block_threshold);

        if ($new_failures < $block_threshold) {
            $db->run(
                "UPDATE event_guard_logs
                 SET failures = {$new_failures}, log_date = NOW()
                 WHERE id = {$id_safe}"
            );
            return;
        }

        _fail2ban_ban($ip, $filter, $event, $fslogger);
        $db->run(
            "UPDATE event_guard_logs
             SET failures   = {$new_failures},
                 log_status = 'blocked',
                 log_date   = NOW()
             WHERE id = {$id_safe}"
        );
        return;
    }

    $log_uuid   = generate_uuid();
    $uuid_safe  = $db->quote($log_uuid);
    $extension  = $db->quote(($event['to-user'] ?? '') . '@' . ($event['to-host'] ?? ''));
    $user_agent = $db->quote($event['user-agent'] ?? '');
    $country    = $db->quote(lookupCountry($ip));

    $fslogger->log('event_guard: nova ocorrência ip=' . $ip . ' jail=' . $filter . ' (1/' . $block_threshold . ')');

    $db->run(
        "INSERT INTO event_guard_logs
            (log_uuid, hostname, log_date, filter, ip_address, extension, user_agent, log_status, failures, country)
         VALUES
            ({$uuid_safe}, {$host_safe}, NOW(), {$filter_safe}, {$ip_safe}, {$extension}, {$user_agent}, 'tracking', 1, {$country})"
    );
}

function _fail2ban_ban($ip, $filter, $event, $fslogger)
{
    $cmd    = "sudo fail2ban-client set {$filter} banip {$ip} 2>&1";
    $output = shell_exec($cmd);
    $fslogger->log('event_guard: fail2ban banip → ' . $cmd . ' : ' . trim((string) $output));

    syslog(
        LOG_WARNING,
        sprintf(
            'event_guard: blocked ip=%s jail=%s ext=%s@%s ua=%s',
            $ip,
            $filter,
            $event['to-user']    ?? '',
            $event['to-host']    ?? '',
            $event['user-agent'] ?? ''
        )
    );
}

function event_guard_unblock($ip, $filter, $fslogger)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
    }

    $cmd    = "sudo fail2ban-client set {$filter} unbanip {$ip} 2>&1";
    $output = shell_exec($cmd);
    $fslogger->log('event_guard: fail2ban unbanip → ' . $cmd . ' : ' . trim((string) $output));
}

function process_unblock_queue($db, $fslogger, $hostname, &$allowed_cache)
{
    $host_safe = $db->quote($hostname);
    $rows      = $db->run(
        "SELECT id, ip_address, filter
         FROM event_guard_logs
         WHERE log_status = 'pending'
           AND hostname   = {$host_safe}"
    );

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        event_guard_unblock($row['ip_address'], $row['filter'], $fslogger);

        unset($allowed_cache[$row['ip_address']]);

        syslog(
            LOG_WARNING,
            'event_guard: unblocked ip=' . $row['ip_address'] . ' jail=' . $row['filter']
        );

        $id_safe = (int) $row['id'];
        $db->run(
            "UPDATE event_guard_logs
             SET log_status = 'unblocked', log_date = NOW()
             WHERE id = {$id_safe}"
        );

        $fslogger->log('event_guard: desbloqueado ip=' . $row['ip_address'] . ' jail=' . $row['filter']);
    }
}

function generate_uuid()
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function lookupCountry(string $ip): string
{
    $isIpv6 = strpos($ip, ':') !== false;
    $binary  = $isIpv6 ? findExecutable('geoiplookup6') : findExecutable('geoiplookup');
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

function findExecutable(string $name): ?string
{
    $path = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
    if ($path !== '' && is_executable($path)) {
        return $path;
    }
    return null;
}
