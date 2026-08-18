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

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class event_guard_form extends common
{
    public function __construct($library_name = '')
    {
        $this->CI = &get_instance();
    }
    // ── Form: whitelist ───────────────────────────────────────────────────────

    public function get_whitelist_form_fields($id = 0)
    {
        $form['forms'] = array(
            base_url() . 'event_guard/event_guard_whitelist_save/',
            array(
                'id'     => 'event_guard_whitelist_form',
                'method' => 'POST',
                'name'   => 'event_guard_whitelist_form'
            )
        );

        $form[gettext('Whitelist Entry')] = array(
            array(
                '',
                'HIDDEN',
                array(
                    'name'  => 'id',
                    'value' => $id > 0 ? $id : ''
                ),
                '',
                '',
                '',
                ''
            ),
            array(
                gettext('CIDR / IP Address'),
                'INPUT',
                array(
                    'name'        => 'cidr',
                    'size'        => '30',
                    'maxlength'   => '50',
                    'placeholder' => '192.168.1.0/24',
                    'class'       => 'text field medium'
                ),
                'trim|required|xss_clean',
                gettext('CIDR or single IP (e.g. 192.168.1.0/24)'),
                gettext('Please enter a valid CIDR or IP address')
            ),
            array(
                gettext('Description'),
                'INPUT',
                array(
                    'name'        => 'description',
                    'size'        => '40',
                    'maxlength'   => '255',
                    'placeholder' => gettext('Optional description'),
                    'class'       => 'text field medium'
                ),
                // FIX: description não é required
                'trim|xss_clean',
                gettext('Optional description for this entry'),
                ''
            ),
        );
        $form['button_cancel'] = array(
            'name'    => 'action',
            'content' => gettext('Close'),
            'value'   => 'Close',
            'type'    => 'button',
            'class'   => 'btn btn-secondary ml-2',
            'onclick' => "return redirect_page('NULL')"
        );
        $form['button_save'] = array(
            'name'    => 'action',
            'content' => gettext('Save'),
            'id'      => 'submit',
            'value'   => 'save',
            'type'    => 'button',
            'class'   => 'btn btn-success'
        );

        return $form;
    }

    // ── Search form ───────────────────────────────────────────────────────────

    public function get_event_guard_search_form()
    {
        $logintype = $this->CI->session->userdata('userlevel_logintype');
        $accountinfo = $this->CI->session->userdata('accountinfo');
        $reseller_id = $accountinfo['type'] == 1 ? $accountinfo['id'] : 0;
        $form['forms'] = array(
            "",
            array(
                'id' => "event_guard_search"
            )
        );
        if (($logintype == - 1) || ($logintype == 2)) {

            $form[gettext('Search')] = array(
                array(
                    gettext('IP Address'),
                    'INPUT',
                    array(
                        'name' => 'ip_address[ip_address]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'ip_address[ip_address-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Country'),
                    'INPUT',
                    array(
                        'name' => 'country[country]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'country[country-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Status'),
                    'log_status',
                    'SELECT',
                    '',
                    '',
                    'tOOL TIP',
                    'Please Enter account number',
                    '',
                    '',
                    '',
                    'set_event_guard_status'
                ),
                array(
                    gettext('Jail'),
                    'filter',
                    'SELECT',
                    '',
                    '',
                    'tOOL TIP',
                    'Please Enter account number',
                    '',
                    '',
                    '',
                    'set_event_guard_filter'
                ),
                array(
                    '',
                    'HIDDEN',
                    'ajax_search',
                    '1',
                    '',
                    '',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'advance_search',
                    '1',
                    '',
                    '',
                    ''
                )
            );
        } else {
            $form[gettext('Search')] = array(
                array(
                    gettext('IP Address'),
                    'INPUT',
                    array(
                        'name' => 'ip_address[ip_address]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'ip_address[ip_address-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Country'),
                    'INPUT',
                    array(
                        'name' => 'country[country]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'country[country-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Status'),
                    'log_status',
                    'SELECT',
                    '',
                    '',
                    'tOOL TIP',
                    'Please Enter account number',
                    '',
                    '',
                    '',
                    'set_event_guard_status'
                ),
                array(
                    '',
                    'HIDDEN',
                    'ajax_search',
                    '1',
                    '',
                    '',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'advance_search',
                    '1',
                    '',
                    '',
                    ''
                )
            );
        }
        $form['button_search'] = array(
            'name' => 'action',
            'id' => "event_guard_search_btn",
            'content' => gettext('Search'),
            'value' => 'save',
            'type' => 'button',
            'class' => 'btn btn-success float-right'
        );
        $form['button_reset'] = array(
            'name' => 'action',
            'id' => "id_reset",
            'content' => gettext('Clear'),
            'value' => 'cancel',
            'type' => 'reset',
            'class' => 'btn btn-secondary float-right mx-2'
        );

        return $form;
    }

    public function get_event_guard_whitelist_search_form()
    {
        $logintype = $this->CI->session->userdata('userlevel_logintype');
        $accountinfo = $this->CI->session->userdata('accountinfo');
        $reseller_id = $accountinfo['type'] == 1 ? $accountinfo['id'] : 0;
        $form['forms'] = array(
            "",
            array(
                'id' => "event_guard_whitelist_search"
            )
        );
        if (($logintype == - 1) || ($logintype == 2)) {
    
            $form[gettext('Search')] = array(
                array(
                    gettext('CIDR / IP Address'),
                    'INPUT',
                    array(
                        'name' => 'cidr[cidr]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'cidr[cidr-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Description'),
                    'INPUT',
                    array(
                        'name' => 'description[description]',
                        '',
                        'size' => '100',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'description[description-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'ajax_search',
                    '1',
                    '',
                    '',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'advance_search',
                    '1',
                    '',
                    '',
                    ''
                )
            );
        } else {
            $form[gettext('Search')] = array(
                array(
                    gettext('CIDR / IP Address'),
                    'INPUT',
                    array(
                        'name' => 'cidr[cidr]',
                        '',
                        'size' => '20',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'cidr[cidr-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    gettext('Description'),
                    'INPUT',
                    array(
                        'name' => 'description[description]',
                        '',
                        'size' => '100',
                        'class' => "text field"
                    ),
                    '',
                    'tOOL TIP',
                    '1',
                    'description[description-string]',
                    '',
                    '',
                    '',
                    'search_string_type',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'ajax_search',
                    '1',
                    '',
                    '',
                    ''
                ),
                array(
                    '',
                    'HIDDEN',
                    'advance_search',
                    '1',
                    '',
                    '',
                    ''
                )
            );
        }
        $form['button_search'] = array(
            'name' => 'action',
            'id' => "event_guard_whitelist_search_btn",
            'content' => gettext('Search'),
            'value' => 'save',
            'type' => 'button',
            'class' => 'btn btn-success float-right'
        );
        $form['button_reset'] = array(
            'name' => 'action',
            'id' => "id_reset",
            'content' => gettext('Clear'),
            'value' => 'cancel',
            'type' => 'reset',
            'class' => 'btn btn-secondary float-right mx-2'
        );
    
        return $form;
    }

    // ── Grid: lista de logs ───────────────────────────────────────────────────

    public function build_event_guard_list_for_admin()
    {
        $grid_field_arr = json_encode(array(
            array(
                "<input type='checkbox' name='chkAll' class='ace checkall'/><label class='lbl'></label>",
                "30",
                "",
                "",
                "",
                "",
                "false",
                "center"
            ),
            array(
                gettext("IP Address"),
                "140",
                "ip_address",
                "",
                "",
                "",
                "EDITABLE",
                "true",
                "left"
            ),
            array(
                gettext("Country"),
                "90",
                "country",
                "",
                "",
                "",
                "",
                "true",
                "center"
            ),
            array(
                gettext("Jail"),
                "110",
                "filter",
                "",
                "",
                "",
                "",
                "true",
                "center"
            ),
            array(
                gettext("Failures"),
                "80",
                "failures",
                "",
                "",
                "",
                "",
                "true",
                "center"
            ),
            array(
                gettext("Extension"),
                "160",
                "extension",
                "",
                "",
                "",
                "",
                "true",
                "left"
            ),
            array(
                gettext("User Agent"),
                "180",
                "user_agent",
                "",
                "",
                "",
                "",
                "true",
                "left"
            ),
            array(
                gettext("Hostname"),
                "120",
                "hostname",
                "",
                "",
                "",
                "",
                "true",
                "left"
            ),
            array(
                gettext("Date"),
                "140",
                "log_date",
                "log_date",
                "log_date",
                "convert_GMT_to",
                "",
                "true",
                "center"
            ),
            array(
                gettext("Status"),
                "100",
                "log_status",
                "log_status",
                "log_status",
                "get_event_guard_status_badge",
                "",
                "true",
                "center"
            ),
            array(
                gettext("Action"),
                "160",
                "",
                "",
                "",
                array(
                    "EDIT" => array(
                        "url"  => "event_guard/event_guard_edit/",
                        "mode" => "popup"
                    ),
                    "UNBLOCK" => array(
                        "url"  => "event_guard/event_guard_unblock/",
                        "mode" => "single",
                        'action' => 'unblock'
                    ),
                    "BLOCK" => array(
                        "url"  => "event_guard/event_guard_block/",
                        "mode" => "single",
                        'action' => 'block'
                    )
                ),
                "true"
            )
        ));

        return $grid_field_arr;
    }

    // ── Grid buttons ──────────────────────────────────────────────────────────

    public function build_grid_buttons()
    {
        $buttons_json = json_encode(array(
            array(
                gettext('Add to Block'),
                'btn btn-line-warning btn',
                'fa fa-lock fa-lg',
                'button_action',
                '/event_guard/event_guard_add/',
                'popup',
                'medium',
                'create'
            ),
            array(
                gettext("Delete"),
                "btn btn-line-danger",
                "fa fa-times-circle fa-lg",
                "button_action",
                "/event_guard/event_guard_delete_multiple/",
                "",
                "",
                "delete"
            )
        ));

        return $buttons_json;
    }

    // ── Grid: whitelist ───────────────────────────────────────────────────────

    public function build_whitelist_list_for_admin()
    {
        $grid_field_arr = json_encode(array(
            array(
                "<input type='checkbox' name='chkAll' class='ace checkall'/><label class='lbl'></label>",
                "30",
                "",
                "",
                "",
                "",
                "",
                "false",
                "center"
            ),
            array(
                gettext("CIDR / IP Address"),
                "150",
                "cidr",
                "",
                "",
                "",
                "EDITABLE",
                "true",
                "left"
            ),
            array(
                gettext('Description'),
                '300', 'description', '', '', '', '', 'true', 'left'
            ),
            array(
                gettext('Created Date'),
                '160', 'created_at', 'created_at', 'created_at', 'convert_GMT_to', '', 'true', 'center'
            ),
            array(
                gettext('Action'),
                '140', '', '', '',
                array(
                    'EDIT' => array(
                        'url'  => 'event_guard/event_guard_whitelist_edit/',
                        'mode' => 'popup'
                    ),
                    'DELETE' => array(
                        'url'  => 'event_guard/event_guard_whitelist_delete/',
                        'mode' => 'single'
                    )
                ),
                'true'
            )
        ));

        return $grid_field_arr;
    }

    // ── Whitelist grid buttons ────────────────────────────────────────────────

    public function build_whitelist_grid_buttons()
    {
        $buttons_json = json_encode(array(
            array(
                gettext('Add CIDR'),
                'btn btn-line-warning btn',
                'fa fa-plus-circle fa-lg',
                'button_action',
                '/event_guard/event_guard_whitelist_add/',
                'popup',
                '',
                'create'
            ),
            array(
                gettext("Delete"),
                "btn btn-line-danger",
                "fa fa-times-circle fa-lg",
                "button_action",
                "/event_guard/event_guard_whitelist_delete_multiple/",
                "",
                "",
                "delete"
            )
        ));

        return $buttons_json;
    }

    // ── Form: log entry ───────────────────────────────────────────────────────

    public function get_log_edit_form_fields($id = 0)
    {
        $form['forms'] = array(
            base_url() . 'event_guard/event_guard_log_save/',
            array(
                'id'     => 'event_guard_log_form',
                'method' => 'POST',
                'name'   => 'event_guard_log_form'
            )
        );

        $form[gettext('Log Entry')] = array(
            array(
                '',
                'HIDDEN',
                array(
                    'name'  => 'id',
                    'value' => $id > 0 ? $id : ''
                ),
                '', '', '', ''
            ),
            array(
                gettext('IP Address'),
                'INPUT',
                array(
                    'name'        => 'ip_address',
                    'size'        => '30',
                    'maxlength'   => '45',
                    'placeholder' => '192.168.1.1',
                    'class'       => 'text field medium'
                ),
                'trim|required|xss_clean',
                gettext('IPv4 or IPv6 address'),
                gettext('Please enter a valid IP address')
            ),
            array(
                gettext('Chain'),
                'filter',
                'SELECT',
                '',
                'trim|required|xss_clean',
                gettext('iptables chain where the IP was blocked'),
                gettext('Please select a chain'),
                '',
                '',
                '',
                'set_event_guard_filter'
            ),
            array(
                gettext('Status'),
                'log_status',
                'SELECT',
                '',
                'trim|required|xss_clean',
                gettext('Current status of this entry'),
                gettext('Please select a status'),
                '',
                '',
                '',
                'set_event_guard_status'
            ),
            array(
                gettext('Failures'),
                'INPUT',
                array(
                    'name'      => 'failures',
                    'size'      => '10',
                    'maxlength' => '10',
                    'readonly'  => true,
                    'class'     => 'text field small'
                ),
                'trim|integer|xss_clean',
                gettext('Number of failed attempts'),
                ''
            ),
            array(
                gettext('Country'),
                'INPUT',
                array(
                    'name'      => 'country',
                    'size'      => '30',
                    'maxlength' => '64',
                    'readonly'  => true,
                    'class'     => 'text field medium'
                ),
                'trim|xss_clean',
                gettext('Country detected via GeoIP'),
                ''
            ),
            array(
                gettext('Extension'),
                'INPUT',
                array(
                    'name'        => 'extension',
                    'size'        => '40',
                    'maxlength'   => '255',
                    'placeholder' => 'user@domain',
                    'readonly'    => true,
                    'class'       => 'text field medium'
                ),
                'trim|xss_clean',
                gettext('SIP extension that triggered the block'),
                ''
            ),
            array(
                gettext('User Agent'),
                'INPUT',
                array(
                    'name'      => 'user_agent',
                    'size'      => '60',
                    'maxlength' => '512',
                    'readonly'  => true,
                    'class'     => 'text field medium'
                ),
                'trim|xss_clean',
                gettext('SIP User-Agent header from the blocked device'),
                ''
            ),
        );

        $form['button_cancel'] = array(
            'name'    => 'action',
            'content' => gettext('Close'),
            'value'   => 'Close',
            'type'    => 'button',
            'class'   => 'btn btn-secondary ml-2',
            'onclick' => "return redirect_page('NULL')"
        );

        $form['button_save'] = array(
            'name'    => 'action',
            'content' => gettext('Save'),
            'id'      => 'submit',
            'value'   => 'save',
            'type'    => 'button',
            'class'   => 'btn btn-success'
        );

        return $form;
    }

    public function get_log_add_form_fields()
    {
        $form['forms'] = array(
            base_url() . 'event_guard/event_guard_log_save/',
            array(
                'id'     => 'event_guard_log_form',
                'method' => 'POST',
                'name'   => 'event_guard_log_form'
            )
        );

        $form[gettext('Log Entry')] = array(
            array(
                '',
                'HIDDEN',
                array(
                    'name' => 'id'
                ),
                '',
                '',
                '',
                ''
            ),
            array(
                gettext('IP Address'),
                'INPUT',
                array(
                    'name'        => 'ip_address',
                    'size'        => '30',
                    'maxlength'   => '45',
                    'placeholder' => '192.168.1.1',
                    'class'       => 'text field medium'
                ),
                'trim|required|xss_clean',
                gettext('IPv4 or IPv6 address'),
                gettext('Please enter a valid IP address')
            ),
            array(
                gettext('Chain'),
                'filter',
                'SELECT',
                '',
                'trim|required|xss_clean',
                gettext('iptables chain where the IP was blocked'),
                gettext('Please select a chain'),
                '',
                '',
                '',
                'set_event_guard_filter'
            ),
            array(
                '',
                'HIDDEN',
                array(
                    'name'  => 'log_status',
                    'value' => 'pending'
                ),
                '',
                '',
                '',
                ''
            )
        );

        $form['button_cancel'] = array(
            'name'    => 'action',
            'content' => gettext('Close'),
            'value'   => 'Close',
            'type'    => 'button',
            'class'   => 'btn btn-secondary ml-2',
            'onclick' => "return redirect_page('NULL')"
        );

        $form['button_save'] = array(
            'name'    => 'action',
            'content' => gettext('Save'),
            'id'      => 'submit',
            'value'   => 'save',
            'type'    => 'button',
            'class'   => 'btn btn-success'
        );

        return $form;
    }

    public function get_event_guard_status_badge($field1, $field2, $value)
    {
        $map = array(
            'tracking'  => array('badge-warning', gettext('Tracking')),
            'blocked'   => array('badge-danger',  gettext('Blocked')),
            'pending'   => array('badge-info',    gettext('Pending')),
            'unblocked' => array('badge-success', gettext('Unblocked')),
        );

        $key = isset($map[$value]) ? $value : 'pending';
        return '<span class="badge ' . $map[$key][0] . '">' . $map[$key][1] . '</span>';
    }
}

?>