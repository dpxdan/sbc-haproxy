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

/**
 * EventSocket
 *
 * Cliente PHP puro para o mod_event_socket do FreeSWITCH.
 * Protocolo: TCP text, headers separados por \n, blocos por \n\n.
 *
 * Uso:
 *   $socket = new EventSocket();
 *   $socket->connect('127.0.0.1', 8021, 'ClueCon');
 *   $socket->request('event json ALL');
 *   $socket->request('filter Event-Name CUSTOM');
 *   while (true) {
 *       $event = $socket->read_event();
 *       if (!empty($event['$'])) { ... }
 *   }
 */
class EventSocket
{
    /** @var resource|null */
    private $socket = null;

    /** @var bool */
    private $is_connected = false;

    /** @var int Timeout de leitura em segundos */
    private $read_timeout = 0;

    /** @var int Timeout de leitura em microsegundos */
    private $read_timeout_usec = 200000; // 200ms

    /**
     * Conecta ao Event Socket do FreeSWITCH e autentica.
     *
     * @param  string $host
     * @param  int    $port
     * @param  string $password
     * @return bool
     */
    public function connect($host = '127.0.0.1', $port = 8021, $password = 'ClueCon')
    {
        $this->is_connected = false;

        $errno  = 0;
        $errstr = '';

        $this->socket = @fsockopen($host, $port, $errno, $errstr, 5);

        if (!$this->socket) {
            error_log('EventSocket: fsockopen falhou: ' . $errstr . ' (' . $errno . ')');
            return false;
        }

        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 5);

        // Aguarda "Content-Type: auth/request"
        $response = $this->_read_block();

        if (strpos($response, 'auth/request') === false) {
            error_log('EventSocket: resposta inesperada na autenticação: ' . $response);
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        // Envia credencial
        fwrite($this->socket, "auth {$password}\n\n");

        $auth_response = $this->_read_block();

        if (strpos($auth_response, '+OK accepted') === false) {
            error_log('EventSocket: autenticação recusada: ' . $auth_response);
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        // Após autenticação usa leitura não-bloqueante com timeout curto
        stream_set_blocking($this->socket, false);
        stream_set_timeout($this->socket, $this->read_timeout, $this->read_timeout_usec);

        $this->is_connected = true;
        return true;
    }

    /**
     * Verifica se o socket está conectado e operacional.
     *
     * @return bool
     */
    public function connected()
    {
        if (!$this->is_connected || !is_resource($this->socket)) {
            return false;
        }

        $meta = stream_get_meta_data($this->socket);

        if ($meta['eof'] || $meta['timed_out']) {
            $this->is_connected = false;
            return false;
        }

        return true;
    }

    /**
     * Envia um comando ao Event Socket.
     * Ex: 'event json ALL', 'filter Event-Name CUSTOM'
     *
     * @param  string $command
     * @return string Resposta do FreeSWITCH
     */
    public function request($command)
    {
        if (!$this->connected()) {
            return '';
        }

        stream_set_blocking($this->socket, true);
        fwrite($this->socket, $command . "\n\n");
        $response = $this->_read_block();
        stream_set_blocking($this->socket, false);

        return $response;
    }

    /**
     * Lê o próximo evento disponível no socket.
     * Retorna array com os headers do evento; o body JSON fica em $result['$'].
     *
     * @return array
     */
    public function read_event()
    {
        if (!$this->connected()) {
            return array();
        }

        $raw = $this->_read_block();

        if (empty($raw)) {
            return array();
        }

        return $this->_parse_event($raw);
    }

    /**
     * Fecha a conexão com o Event Socket.
     */
    public function disconnect()
    {
        if (is_resource($this->socket)) {
            fwrite($this->socket, "exit\n\n");
            fclose($this->socket);
        }

        $this->socket       = null;
        $this->is_connected = false;
    }

    // ── Métodos privados ──────────────────────────────────────────────────────

    /**
     * Lê um bloco completo do socket (headers + body).
     * O protocolo ESL delimita blocos por \n\n;
     * quando há Content-Length, lê exatamente N bytes de body após os headers.
     *
     * @return string
     */
    private function _read_block()
    {
        if (!is_resource($this->socket)) {
            return '';
        }

        $buffer  = '';
        $headers = '';
        $body    = '';

        // Lê os headers linha a linha até encontrar linha vazia
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 4096);

            if ($line === false) {
                // Sem dados no momento (non-blocking)
                usleep(5000); // 5ms
                continue;
            }

            if ($line === "\n") {
                // Fim dos headers
                break;
            }

            $headers .= $line;
        }

        if (empty($headers)) {
            return '';
        }

        // Verifica Content-Length para ler o body
        if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
            $length    = (int) $matches[1];
            $body      = '';
            $remaining = $length;

            while ($remaining > 0 && !feof($this->socket)) {
                $chunk = fread($this->socket, $remaining);

                if ($chunk === false || $chunk === '') {
                    usleep(5000);
                    continue;
                }

                $body      .= $chunk;
                $remaining -= strlen($chunk);
            }
        }

        return $headers . "\n" . $body;
    }

    /**
     * Converte o bloco raw em array associativo.
     * O body JSON (eventos) fica na chave '$'.
     *
     * @param  string $raw
     * @return array
     */
    private function _parse_event($raw)
    {
        $result  = array();
        $parts   = explode("\n\n", $raw, 2);
        $headers = $parts[0];
        $body    = isset($parts[1]) ? trim($parts[1]) : '';

        foreach (explode("\n", $headers) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            $result[$key] = urldecode($value);
        }

        // Body JSON vai para a chave '$' — mesmo padrão do ESL original
        if ($body !== '') {
            $result['$'] = $body;
        }

        return $result;
    }
}
