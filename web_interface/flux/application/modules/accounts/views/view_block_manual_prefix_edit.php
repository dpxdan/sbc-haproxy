<?php include(FCPATH . 'application/views/popup_header.php'); ?>

<script type="text/javascript">
    function update_manual_block() {
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
            url: "<?= base_url(); ?>/accounts/customer_update_block_prefix/<?= $accountid; ?>/<?= $patternid; ?>/",
            data: { prefix: prefix, destination: dest, direction: direction },
            success: function (data) {
                if (data) {
                    $('#pattern_grid').flexReload();
                    $.facebox.close();
                } else {
                    alert("<?php echo gettext('Problem updating block code.'); ?>");
                }
            }
        });
    }
</script>



<section class="slice m-0">
    <div class="w-section inverse p-0">
        <div class="col-md-12 p-0 card-header">
            <h3 class="fw4 p-4 m-0"><?php echo gettext("Edit Block Code"); ?></h3>
        </div>
    </div>
</section>


<section class="slice m-0">
    <div class="w-section inverse p-4">
        <div class="col-12 pb-4">

            <div class="form-group">
                <label><?php echo gettext("Code (numeric)"); ?></label>
                <input type="text" id="manual_prefix" class="form-control"
                    maxlength="15" placeholder="Ex: 5511"
                    value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>" />
            </div>

            <div class="form-group">
                <label><?php echo gettext("Destination (optional)"); ?></label>
                <input type="text" id="manual_destination" class="form-control" maxlength="100"
                    value="<?php echo htmlspecialchars($destination, ENT_QUOTES); ?>" />
            </div>

            <div class="form-group">
                <label><?php echo gettext("Direction"); ?></label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_out" value="outbound" <?php echo ($direction == 'outbound') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mdir_out"><?php echo gettext("Outbound"); ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_in" value="inbound" <?php echo ($direction == 'inbound') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mdir_in"><?php echo gettext("Inbound"); ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manual_direction"
                        id="mdir_both" value="both" <?php echo ($direction == 'both') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mdir_both"><?php echo gettext("Both"); ?></label>
                </div>
            </div>

            <button class="btn btn-line-warning" onclick="update_manual_block();">
                <?php echo gettext("Save"); ?>
            </button>

        </div>
    </div>
</section>
