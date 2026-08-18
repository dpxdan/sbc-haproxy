<?php

defined('BASEPATH') OR exit('No direct script access allowed');

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

$config['DIDs-field'] = array(
    'Provider' => 'provider_id',
);

$config['DIDs-mapper-fields'] = array(
    'general_info' => array(
        'DID'                     => 'number',
        'Country'                 => 'country_id',
        'City'                    => 'city',
        'Province'                => 'province',
        'Call Type'               => 'call_type',
        'Destination'             => 'extensions',
        'Leg Timeout'             => 'leg_timeout',
    ),
    'pricing' => array(
        'Per Minute Cost'   => 'cost',
        'Initial Increment' => 'init_inc',
        'Increment'         => 'inc',
        'Setup Fee'         => 'setup',
        'Monthly Fee'       => 'monthlycost',
        'Connect Cost'      => 'connectcost',
        'Included Seconds'  => 'includedseconds',
        'Billing Days'      => 'billing_days',
        'Account'           => 'accountid',
    ),
);
