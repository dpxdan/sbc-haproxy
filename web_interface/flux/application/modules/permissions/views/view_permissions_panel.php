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
?>
<?php if (empty($permission_main_array)) : ?>

    <div class="col-md-12 text-center text-muted p-4">
        <?php echo gettext('No permissions available for this type.'); ?>
    </div>

<?php else : ?>

    <?php $i = 0; ?>
    <?php foreach ($permission_main_array as $module_key => $module_value) : ?>

        <?php $div_function_name = $module_key . '_menu'; ?>

        <div class="col-md-12 alert-primary heading">

            <input type="checkbox"
                   class="group_checkbox"
                   title="<?php echo gettext('Select All'); ?>"
                   style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">

            <a class="btn" onclick="open_menu('<?php echo $module_key; ?>');">
                <h3 class="text-left m-0">
                    <?php echo gettext(ucwords(str_replace('_', ' ', $module_key))); ?>
                </h3>
            </a>

            <div id="<?php echo $div_function_name; ?>" class="backgroundfff permission_group" style="display: none;">

                <?php foreach ($module_value as $first_sub_module_key => $first_sub_module_value) : ?>
                    <?php foreach ($first_sub_module_value as $sub_module_key => $sub_module_value) : ?>

                        <?php
                        $sub_menu_id        = $sub_module_key . '_menu';
                        $main_check_box_name = 'permission[' . $first_sub_module_key . '][' . $sub_module_key . '][main]';
                        $link_id            = 'li_' . $sub_module_key . '_menu';
                        $main_checkbox_id   = $first_sub_module_key . '_' . $sub_module_key . '_main';
                        $main_table_id      = $first_sub_module_key . '_' . $sub_module_key . '_main_table';
                        ?>

                        <ul>
                            <li class="row mb-2"
                                style="margin-left: 0px !important; margin-right: 0px !important;"
                                id="<?php echo $link_id; ?>">

                                <input
                                    id="<?php echo $main_checkbox_id; ?>"
                                    class="float-left permission_checkbox <?php echo $first_sub_module_key . '_' . $sub_module_key; ?>"
                                    type="checkbox"
                                    name="<?php echo $main_check_box_name; ?>"
                                    value="0"
                                    style="width: 20px;">

                                <div class="float-left">
                                    <a onclick="open_sub_menu('<?php echo $link_id; ?>', '<?php echo $sub_module_key; ?>', '<?php echo $i; ?>');"
                                       class="btn btn-link">
                                        <span class="collaps-<?php echo $i; ?>">
                                            <i class="fa fa-plus"></i>
                                        </span>
                                        <?php echo gettext($display_name_array[$module_key][$first_sub_module_key][$sub_module_key]); ?>
                                    </a>
                                </div>
                            </li>

                            <div class="col-md-12" id="<?php echo $sub_menu_id; ?>" style="display: none;">
                                <table class="card"
                                       border="1"
                                       style="width: 100%;"
                                       bordercolor="#fff"
                                       id="<?php echo $main_table_id; ?>">
                                    <tr>

                                        <?php $array_count = count($sub_module_value); ?>
                                        <?php for ($i = 0; $i < $array_count; $i++) : ?>
                                            <?php if ($sub_module_value[$i] !== 'main') : ?>

                                                <?php
                                                $loop_flag          = $i / 4;
                                                $loop_value_explode = explode('.', $loop_flag);
                                                $check_bax_name     = 'permission[' . $first_sub_module_key . '][' . $sub_module_key . '][' . $sub_module_value[$i] . ']';
                                                $checkbox_id        = $first_sub_module_key . '_' . $sub_module_key . '_' . strtolower(str_replace(' ', '_', $sub_module_value[$i]));
                                                $label_text         = ($sub_module_value[$i] === 'Delete') ? 'Delete Multiple' : $sub_module_value[$i];
                                                ?>

                                                <td class="text-dark" style="width: 10%; border: none; padding-left: 20px;">
                                                    <input
                                                        class="permission_checkbox"
                                                        id="<?php echo $checkbox_id; ?>"
                                                        type="checkbox"
                                                        name="<?php echo $check_bax_name; ?>"
                                                        value="0"
                                                        style="width: 20px;">
                                                    <?php echo gettext(ucwords(str_replace('_', ' ', $label_text))); ?>
                                                </td>

                                                <?php if (!isset($loop_value_explode[1])) : ?>
                                                    </tr><tr>
                                                <?php endif; ?>

                                            <?php endif; ?>
                                        <?php endfor; ?>

                                    </tr>
                                </table>
                            </div>
                        </ul>

                    <?php endforeach; ?>
                <?php endforeach; ?>

            </div>
        </div>

    <?php endforeach; ?>

<?php endif; ?>
