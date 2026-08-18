<?php include(FCPATH . 'application/views/popup_header.php'); ?>

<script type="text/javascript">
    function add_manual_block() {
        var prefix    = $('#manual_prefix').val().replace(/[^0-9]/g, '');
        var direction = $('input[name="manual_direction"]:checked').val() || 'outbound';
        var dest      = $('#manual_destination').val();

        if (!prefix) {
            alert("<?php echo gettext('Please enter a numeric code.'); ?>");
            return;
        }

        $.ajax({
            type: "POST",
            cache: false,
            async: true,
            url: "<?= base_url(); ?>/accounts/customer_block_prefix_manual/<?= $accountid; ?>/",
            data: { prefix: prefix, destination: dest, direction: direction },
            success: function (data) {
                if (data) {
                    $('#manual_prefix').val('');
                    $('#manual_destination').val('');
                    $('#pattern_grid').flexReload();
                    $.facebox.close();
                } else {
                    alert("<?php echo gettext('Problem adding block code.'); ?>");
                }
            }
        });
    }
</script>



<section class="slice m-0">
    <div class="w-section inverse p-0">
        <div class="col-md-12 p-0 card-header">
            <h3 class="fw4 p-4 m-0"><?php echo gettext("Add Block Code"); ?></h3>
        </div>
    </div>
</section>


<section class="slice m-0">
    <div class="w-section inverse p-4">
        <div class="col-12 pb-4">

            <div class="form-group">
                <label><?php echo gettext("Code (numeric)"); ?></label>
                <input type="text" id="manual_prefix" class="form-control"
                    maxlength="15" placeholder="Ex: 5511" />
            </div>

            <div class="form-group">
                <label><?php echo gettext("Destination (optional)"); ?></label>
                <input type="text" id="manual_destination" class="form-control" maxlength="100" />
            </div>

            <div class="form-group">
                <label><?php echo gettext("Direction"); ?></label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_out" value="outbound" checked>
                    <label class="form-check-label" for="mdir_out"><?php echo gettext("Outbound"); ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_in" value="inbound">
                    <label class="form-check-label" for="mdir_in"><?php echo gettext("Inbound"); ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_both" value="both">
                    <label class="form-check-label" for="mdir_both"><?php echo gettext("Both"); ?></label>
                </div>
            </div>

            <button class="btn btn-line-warning" onclick="add_manual_block();">
                <i class="fa fa-plus-circle fa-lg"></i><?php echo gettext("Add Block Code"); ?>
            </button>

        </div>
    </div>
</section>
