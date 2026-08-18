<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negocios
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

class ProcessDetrafQueue extends MX_Controller
{
    private $report_dir;

    function __construct()
    {
        parent::__construct();

        $this->load->model('detraf_reports/detraf_reports_model');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '3000');

        $this->report_dir = FCPATH . 'attachments/detraf_reports/';
        if (! is_dir($this->report_dir)) {
            @mkdir($this->report_dir, 0750, TRUE);
        }
    }

    function index()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $job = $this->detraf_reports_model->lock_next_job();
        if ($job) {
            $this->detraf_reports_model->process_job($job, $this->report_dir);
        }

        exit();
    }
}
