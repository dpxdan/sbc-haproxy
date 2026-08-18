<?php
include(FCPATH . 'application/views/popup_header.php');
?>
<?php

?>
<script type="text/javascript">
    $("#submit").click(function(e) {
        e.preventDefault();

        var $start_date = $("[name='start_date']");
        var $end_date = $("[name='end_date']");

        if ($start_date.val() === '' || $end_date.val() === '') {
            alert("<?php echo gettext('Please enter the start date and end date.'); ?>");
            return false;
        }

        var $btn = $(this);
        var label_original = $btn.text();
        $btn.prop('disabled', true).text("<?php echo gettext('Queuing...'); ?>");
        $("#detraf_result").html('');

        $.ajax({
            url: $("#detraf_reports_form").attr('action'),
            type: 'POST',
            data: $("#detraf_reports_form").serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.ERROR) {
                    $btn.prop('disabled', false).text(label_original);
                    $("#detraf_result").html('<div class="alert alert-danger">' + response.ERROR + '</div>');
                    return;
                }

                redirect_page("<?php echo base_url(); ?>detraf_reports/detraf_reports_list/");
            },
            error: function() {
                $btn.prop('disabled', false).text(label_original);
                $("#detraf_result").html('<div class="alert alert-danger"><?php echo gettext('Unexpected failure while generating the report.'); ?></div>');
            }
        });
    });
</script>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        $('.rm-col-md-12').addClass('float-right');
        $(".rm-col-md-12").removeClass("col-md-12");

        var from_date = date;
        var to_date = date;

        $("#customer_from_date").datepicker({
            value: from_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal: true,
            format: 'yyyy-mm-dd',
            footer: true
        });
        $("#customer_to_date").datepicker({
            value: to_date,
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            modal: true,
            format: 'yyyy-mm-dd',
            footer: true
        });

    });
</script>
<section class="slice m-0">
    <div class="w-section inverse p-0">
        <div class="col-md-12 p-0 card-header">
            <h3 class="fw4 p-4 m-0"><? echo $page_title; ?></h3 class="text-light p-3 rounded-top">
        </div>
    </div>
</section>
<div>
    <div>
        <section class="slice m-0">
            <div class="w-section inverse p-4">
                <div style="">
                    <?php

                    if (isset($validation_errors)) {
                        echo $validation_errors;
                    }
                    ?>
                </div>
                <?php echo $form; ?>
            </div>
        </section>
    </div>
</div>
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        $("input[type='hidden']").parents('li.form-group').addClass("d-none");
    });
</script>