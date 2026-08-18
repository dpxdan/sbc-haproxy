<? extend('master.php') ?>
<? startblock('extra_head') ?>
<? endblock() ?>
<?= startblock('page-title') ?>
<?= gettext($page_title); ?>
<? endblock() ?>
<?= startblock('content') ?>

<script>
function open_menu(module) {
    var div_id = module + '_menu';
    $('#' + div_id).toggle();
}

function open_sub_menu(parent_div_id, list, i) {
    var div_id = list + '_menu';
    $('#' + div_id).toggle();
    if ($('#' + parent_div_id + ' .collaps-' + i).html() == '<i class="fa fa-plus"></i>') {
        $('#' + parent_div_id + ' .collaps-' + i).html('<i class="fa fa-minus"></i>');
    } else {
        $('#' + parent_div_id + ' .collaps-' + i).html('<i class="fa fa-plus"></i>');
    }
}

function login_type_change(val) {
    if (val === '') {
        return;
    }
    $.ajax({
        type: 'POST',
        url: '<?= base_url() ?>permissions/permissions_get_panel/',
        data: { login_type: val },
        beforeSend: function () {
            $('#permissions_panel').html('<div class="col-md-12 text-center p-4"><i class="fa fa-spinner fa-spin"></i></div>');
        },
        success: function (response) {
            $('#permissions_panel').html(response);
            bind_permission_checkboxes();
            bind_group_checkboxes();
            sync_all_group_checkboxes();
        }
    });
}

function form_submit() {
    var name        = document.getElementById('name').value;
    var description = document.getElementById('description').value;

    document.getElementById('name_err').innerHTML        = '';
    document.getElementById('description_err').innerHTML = '';

    if (name === '') {
        document.getElementById('name_err').style.display = 'block';
        document.getElementById('name_err').innerHTML     = "<i style='color:#D95C5C; padding-right: 6px; padding-top: 10px;' class='fa fa-exclamation-triangle'></i><span class='popup_error error p-0'> Name field is required</span>";
    } else if (description === '') {
        document.getElementById('description_err').style.display = 'block';
        document.getElementById('description_err').innerHTML     = "<i style='color:#D95C5C; padding-right: 6px; padding-top: 10px;' class='fa fa-exclamation-triangle'></i><span class='popup_error error p-0'> Description field is required</span>";
    } else {
        document.getElementById('permissions_form').submit();
    }
}

function bind_permission_checkboxes() {
    $('.permission_checkbox').off('click').on('click', function () {
        var id = $(this)[0].id;
        if (id.match('_main$')) {
            if ($('#' + id).is(':checked')) {
                $('#' + id + '_table input[type=checkbox]').prop('checked', true);
            } else {
                $('#' + id + '_table input[type=checkbox]').prop('checked', false);
            }
        } else {
            var table_id          = $('#' + $(this)[0].id).parent().parent().parent().parent()[0].id;
            var countchecked      = $('#' + table_id + ' input[type=checkbox]:checked').length;
            var main_id           = table_id.replace('_table', '');
            var custom_id         = main_id.replace('_main', '');
            var current_event_id  = id.replace(custom_id + '_', '');

            if (current_event_id !== '' && $('#' + id).prop('checked')) {
                $('input:checkbox[id=' + custom_id + '_list]').prop('checked', true);
                if ($('input:checkbox[id=' + custom_id + '_list]').prop('checked', true)) {
                    $('input:checkbox[id=' + main_id + ']').prop('checked', true);
                }
                if (countchecked > 0) {
                    $('input:checkbox[id=' + main_id + ']').prop('checked', true);
                } else {
                    $('input:checkbox[id=' + main_id + ']').prop('checked', false);
                }
            } else {
                if (current_event_id === 'list') {
                    if ($('input:checkbox[id=' + custom_id + '_list]').prop('checked', false)) {
                        $('input:checkbox[id=' + main_id + ']').prop('checked', false);
                        $('#' + id + '_table input[type=checkbox]').prop('checked', false);
                        $('#' + custom_id + '_main_table').find('.permission_checkbox').prop('checked', false);
                    }
                }
            }
        }
    });

    $('.permission_checkbox').on('click', function () {
        sync_group_checkbox(this);
    });
}

function bind_group_checkboxes() {
    $('.group_checkbox').off('click').on('click', function () {
        var is_checked = $(this).is(':checked');
        $(this).prop('indeterminate', false);
        $(this).closest('.heading')
            .find('.permission_group input[type=checkbox]')
            .prop('checked', is_checked);
    });
}

function sync_group_checkbox(element) {
    var $group = $(element).closest('.permission_group');
    if ($group.length === 0) {
        return;
    }

    var $master = $group.closest('.heading').find('.group_checkbox').first();
    var $boxes  = $group.find('input[type=checkbox]');
    var total   = $boxes.length;
    var checked = $boxes.filter(':checked').length;

    $master.prop('checked', total > 0 && checked === total);
    $master.prop('indeterminate', checked > 0 && checked < total);
}

function sync_all_group_checkboxes() {
    $('.permission_group').each(function () {
        sync_group_checkbox(this);
    });
}

$(document).ready(function () {
    bind_permission_checkboxes();
    bind_group_checkboxes();
    sync_all_group_checkboxes();
});
</script>

<style>
hr {
    display: block;
    height: 1.5%;
    border: 0;
    border-top: 3px solid #1B3280;
    margin: 1em 0;
    padding: 0;
}
table {
    background-color: transparent;
    margin: 10px 0 10px 0;
    border-collapse: inherit;
    border-radius: 5px;
    border: 1px solid #2ca0c8;
    color: #2ca0ca;
    line-height: 30px;
    padding: 10px 0 10px 0;
}
.heading {
    box-shadow: 5px 10px 100px rgba(100, 100, 100, 0.15) inset;
    border-radius: 4px;
    padding: 10px;
    margin: 0 0 10px 0;
}
.backgroundfff {
    background: #fff;
    border-radius: 3px;
    padding: 10px 0;
    margin: 10px 0 5px 0px;
    height: auto !important;
    display: flow-root;
}
.w-section li {
    line-height: 30px !important;
}
.bordergrey {
    border: 1px solid #c0c0c0;
    border-radius: 3px;
    padding: 10px 0 0 0;
    margin: 0 0 10px 0;
    background: #ebeef2;
}
.submenu {
    color: #2ca0c9;
    font-size: 16px;
}
</style>

<section class="slice color-three">
    <div class="w-section inverse p-0">
        <form id="permissions_form" method="POST" action="/permissions/permissions_save/" name="permissions_form">
            <div class="pop_md col-12 p-0">
                <div class="card">
                    <ul class="p-0">
                        <div class="col-md-12 p-0 padding">

                            <h3 class="bg-secondary text-light p-3 rounded-top">
                                <?php echo gettext('Roles & Permissions'); ?>
                            </h3>

                            <div id="floating-label" class="col-md-12 mb-4">
                                <div class="row">

                                    <div class="col-md-3 form-group">
                                        <label class="p-0 control-label">
                                            <?php echo gettext('Role Name') . ' :'; ?>
                                            <span class="text-dark"> *</span>
                                        </label>
                                        <input type="hidden" class="error form-control" value="" name="id" id="id">
                                        <input type="text"
                                               class="error col-md-12 form-control form-control-lg"
                                               placeholder="Enter Name"
                                               name="name"
                                               id="name"
                                               value="<?= $role_name ?>">
                                        <div class="text-danger tooltips error_div float-left p-0" id="name_err"></div>
                                    </div>

                                    <div class="col-md-3 form-group">
                                        <label class="p-0 control-label">
                                            <?php echo gettext('Description') . ' :'; ?>
                                            <span class="text-dark"> *</span>
                                        </label>
                                        <input type="text"
                                               class="error form-control form-control-lg"
                                               placeholder="Enter Description"
                                               value="<?= $description ?>"
                                               name="description"
                                               id="description">
                                        <div class="text-danger tooltips error_div float-left p-0" id="description_err"></div>
                                    </div>

                                    <div class="col-md-3 form-group">
                                        <label class="p-0 control-label">
                                            <?php echo gettext('Permission Type') . ' :'; ?>
                                            <span class="text-dark"> *</span>
                                        </label>
                                        <select name="login_type"
                                                id="login_type"
                                                class="col-md-12 form-control form-control-lg selectpicker"
                                                data-live-search="true"
                                                onchange="login_type_change(this.value)">
                                            <?php foreach ($login_type_list as $key => $login_type_item) : ?>
                                                <option value="<?php echo $login_type_item['permission_type_code']; ?>">
                                                    <?php echo gettext($login_type_item['permission_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>

                                <div class="col-md-12 text-right">
                                    <input id="save_button"
                                           class="save_button btn btn-success"
                                           name="save_button"
                                           value="<?php echo gettext('Save'); ?>"
                                           type="button"
                                           onclick="form_submit()">
                                    <input name="action"
                                           type="button"
                                           value="<?php echo gettext('Cancel'); ?>"
                                           class="btn btn-secondary ml-2"
                                           onclick="return redirect_page('/permissions/permissions_list/')">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div id="permissions_panel">
                                    <?php
                                    $i = 0;
                                    foreach ($permission_main_array as $module_key => $module_value) :
                                        $div_function_name = $module_key . '_menu';
                                    ?>

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
                                                        $sub_menu_id         = $sub_module_key . '_menu';
                                                        $main_check_box_name = 'permission[' . $first_sub_module_key . '][' . $sub_module_key . '][main]';
                                                        $link_id             = 'li_' . $sub_module_key . '_menu';
                                                        ?>

                                                        <ul>
                                                            <li class="row mb-2"
                                                                style="margin-left: 0px !important; margin-right: 0px !important;"
                                                                id="<?php echo $link_id; ?>">

                                                                <input
                                                                    id="<?= $first_sub_module_key . '_' . $sub_module_key . '_main'; ?>"
                                                                    class="float-left permission_checkbox <?= $first_sub_module_key . '_' . $sub_module_key; ?>"
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
                                                                       id="<?= $first_sub_module_key . '_' . $sub_module_key . '_main_table'; ?>">
                                                                    <tr>

                                                                        <?php $array_count = count($sub_module_value); ?>
                                                                        <?php for ($i = 0; $i < $array_count; $i++) : ?>
                                                                            <?php if ($sub_module_value[$i] !== 'main') : ?>

                                                                                <?php
                                                                                $loop_flag          = $i / 4;
                                                                                $loop_value_explode = explode('.', $loop_flag);
                                                                                $check_bax_name     = 'permission[' . $first_sub_module_key . '][' . $sub_module_key . '][' . $sub_module_value[$i] . ']';
                                                                                ?>

                                                                                <td class="text-dark" style="width: 10%; border: none; padding-left: 20px;">
                                                                                    <input class="permission_checkbox"
                                                                                           id="<?= $first_sub_module_key . '_' . $sub_module_key . '_' . strtolower(str_replace(' ', '_', $sub_module_value[$i])); ?>"
                                                                                           type="checkbox"
                                                                                           name="<?php echo $check_bax_name; ?>"
                                                                                           value="0"
                                                                                           style="width: 20px;">
                                                                                    <?php
                                                                                    $sub_module_value[$i] = ($sub_module_value[$i] === 'Delete') ? 'Delete Multiple' : $sub_module_value[$i];
                                                                                    echo gettext(ucwords(str_replace('_', ' ', $sub_module_value[$i])));
                                                                                    ?>
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
                                </div><!-- #permissions_panel -->
                            </div>

                        </div>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</section>

<? endblock() ?>
<? end_extend() ?>
