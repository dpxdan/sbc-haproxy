<?php
// ##############################################################################
// Flux Telecom - Unindo pessoas e negócios
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

class Detraf_reports_form extends common
{
    function __construct($library_name = '')
    {
        $this->CI = & get_instance();
    }

    function get_detraf_reports_form_fields()
    {
        $form['forms'] = array(
            base_url() . 'detraf_reports/detraf_reports_generate/',
            array(
                'id' => 'detraf_reports_form',
                'method' => 'POST',
                'name' => 'detraf_reports_form'
            )
        );

        $form[gettext('Detraf Report Filters')] = array(
            array(
                gettext('Detraf Start Date'),
                'INPUT',
                array(
                    'name' => 'start_date[]',
                    'id' => 'customer_from_date',
                    'size' => '20',
                    'class' => 'text field '
                ),
                '',
                gettext('First day of the period to be reported (YYYY-MM-DD)'),
                ''
            ),
            array(
                gettext('Detraf End Date'),
                'INPUT',
                array(
                    'name' => 'end_date[]',
                    'id' => 'customer_to_date',
                    'size' => '20',
                    'class' => 'text field '
                ),
                '',
                gettext('Last day of the period to be reported (YYYY-MM-DD)'),
                ''
            ),
            array(
                gettext('Carrier ID'),
                'INPUT',
                array(
                    'name' => 'carrier_id',
                    'size' => '20',
                    'class' => 'text field',
                    'maxlength' => '200'
                ),
                'trim|xss_clean',
                gettext('One or more carrier IDs separated by comma. Leave blank for all carriers.'),
                ''
            ),
            array(
                gettext('Creditor EOT'),
                'INPUT',
                array(
                    'name' => 'eot_cred',
                    'size' => '10',
                    'class' => 'text field',
                    'maxlength' => '10',
                    'value' => 'E83'
                ),
                'trim|required|xss_clean',
                gettext('EOT code of the creditor carrier. Default: E83'),
                ''
            ),
            array(
                gettext('Call Direction'),
                'call_direction',
                'SELECT',
                '',
                '',
                gettext('Filter by inbound, outbound or both call directions'),
                '',
                '',
                '',
                '',
                'set_call_direction'
            )
        );

        $form['button_save'] = array(
            'name' => 'action',
            'content' => gettext('Generate Report'),
            'value' => 'save',
            'id' => 'submit',
            'type' => 'button',
            'class' => 'btn btn-success'
        );

        $form['button_cancel'] = array(
            'name' => 'action',
            'content' => gettext('Close'),
            'value' => 'cancel',
            'type' => 'button',
            'class' => 'btn btn-secondary mx-2',
            'onclick' => 'return redirect_page(\'NULL\')'
        );

        return $form;
    }

    function set_call_direction()
    {
        return array(
            '' => gettext('Both'),
            'inbound' => gettext('Inbound'),
            'outbound' => gettext('Outbound')
        );
    }

    function get_search_detraf_reports_form()
    {
        $form['forms'] = array(
            '',
            array(
                'id' => 'detraf_reports_search'
            )
        );

        $form[gettext('Search')] = array(
            array(
                gettext('Creditor EOT'),
                'INPUT',
                array(
                    'name' => 'eot_cred[eot_cred]',
                    'size' => '20',
                    'class' => 'text field'
                ),
                '',
                gettext('Filter by EOT code'),
                '1',
                'eot_cred[eot_cred-string]',
                '',
                '',
                '',
                'search_string_type',
                ''
            ),
            array(
                gettext('Detraf Start Date'),
                'INPUT',
                array(
                    'name' => 'start_date[]',
                    'id' => 'customer_search_start_date',
                    'size' => '20',
                    'class' => "text field "
                ),
                '',
                'tOOL TIP',
                '',
                'customer_start_date[customer_start_date-date]'
            ),
            array(
                gettext('Detraf End Date'),
                'INPUT',
                array(
                    'name' => 'end_date[]',
                    'id' => 'customer_search_end_date',
                    'size' => '20',
                    'class' => "text field "
                ),
                '',
                'tOOL TIP',
                '',
                'customer_end_date[customer_end_date-date]'
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

        $form['button_search'] = array(
            'name' => 'action',
            'id' => 'detraf_reports_search_btn',
            'content' => gettext('Search'),
            'value' => 'save',
            'type' => 'button',
            'class' => 'btn btn-success float-right'
        );

        $form['button_reset'] = array(
            'name' => 'action',
            'id' => 'id_reset',
            'content' => gettext('Reset'),
            'value' => 'cancel',
            'type' => 'button',
            'class' => 'btn btn-secondary float-right mx-2'
        );

        return $form;
    }
    
    function build_detraf_list_for_admin()
    {
        if ($this->CI->session->userdata('logintype') == 1 || $this->CI->session->userdata('logintype') == 5) {
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
                    gettext('Detraf Start Date'),
                    '110',
                    'start_date',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'left'
                ),
                array(
                    gettext('Detraf End Date'),
                    '110',
                    'end_date',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'left'
                ),
                array(
                    gettext('Creditor EOT'),
                    '100',
                    'eot_cred',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'left'
                ),
                array(
                    gettext('Direction'),
                    '90',
                    'call_direction',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'left'
                ),
                array(
                    gettext('Total Records'),
                    '130',
                    'total_data',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'center'
                ),
                array(
                    gettext('File'),
                    '260',
                    'file',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'left'
                ),
                array(
                    gettext('Job Status'),
                    '110',
                    'job_status',
                    '',
                    '',
                    '',
                    '',
                    'false',
                    'center'
                ),
                array(
                    gettext('Generated At'),
                    '150',
                    'creation_date',
                    '',
                    '',
                    '',
                    '',
                    'true',
                    'center'
                ),
                array(
                    gettext('Action'),
                    '150',
                    '',
                    '',
                    '',
                    array(
                        'DOWNLOAD' => array(
                            'url' => 'detraf_reports/detraf_reports_download/',
                            'mode' => 'single'
                        ),
                        'DELETE' => array(
                            'url' => 'detraf_reports/detraf_reports_delete/',
                            'mode' => 'single'
                        )
                    ),
                    'false'
                )
            ));
        } else {
    
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
                                gettext('Detraf Start Date'),
                                '110',
                                'start_date',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'left'
                            ),
                            array(
                                gettext('Detraf End Date'),
                                '110',
                                'end_date',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'left'
                            ),
                            array(
                                gettext('Creditor EOT'),
                                '100',
                                'eot_cred',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'left'
                            ),
                            array(
                                gettext('Direction'),
                                '90',
                                'call_direction',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'left'
                            ),
                            array(
                                gettext('Total Records'),
                                '130',
                                'total_data',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'center'
                            ),
                            array(
                                gettext('File'),
                                '260',
                                'file',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'left'
                            ),
                            array(
                                gettext('Job Status'),
                                '110',
                                'job_status',
                                '',
                                '',
                                '',
                                '',
                                'false',
                                'center'
                            ),
                            array(
                                gettext('Generated At'),
                                '150',
                                'creation_date',
                                '',
                                '',
                                '',
                                '',
                                'true',
                                'center'
                            ),
                            array(
                                gettext('Action'),
                                '150',
                                '',
                                '',
                                '',
                                array(
                                    'DETRAF' => array(
                                        'url' => 'detraf_reports/detraf_reports_download/',
                                        'mode' => 'single'
                                    ),
                                    'DELETE' => array(
                                        'url' => 'detraf_reports/detraf_reports_delete/',
                                        'mode' => 'single'
                                    )
                                ),
                                'true'
                            )
                        ));
        }
        return $grid_field_arr;
    }

    function build_grid_buttons()
    {
        $buttons_json = json_encode(array(
            array(
                gettext('Generate New Report'),
                'btn btn-line-warning btn',
                'fa fa-plus-circle fa-lg',
                'button_action',
                '/detraf_reports/detraf_reports_add/',
                'popup',
                '',
                'create'
            ),
            array(
                gettext('Delete'),
                'btn btn-line-danger',
                'fa fa-times-circle fa-lg',
                'button_action',
                '/detraf_reports/detraf_reports_delete_multiple/',
                '',
                '',
                'delete'
            )
        ));

        return $buttons_json;
    }
}
?>