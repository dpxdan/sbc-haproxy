<?php include(FCPATH.'application/views/popup_header.php'); ?>
<script type="text/javascript">
    $("#submit").click(function(){
        submit_form("gateway_form");
    })
</script>



<section class="slice m-0">
	<div class="w-section inverse p-0">
		<div>
			<div>
				<div class="col-md-12 p-0 card-header">
					<h3 class="fw4 p-4 m-0"><? echo $page_title; ?></h3>
				</div>
			</div>
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

<script type="text/javascript">
$(document).ready(function() {
    $("input[type='hidden']").parents('li.form-group').addClass("d-none");
    $("textarea").parents('li.form-group').addClass("h-auto");
  
    var cid_from = <?php echo (int) $cid_from; ?>;

    if (cid_from === 0) {
        $("#caller_id_type")
            .parents('li.form-group')
            .addClass("d-none");
        $("#caller_id_number")
            .parents('li.form-group')
            .addClass("d-none");
    }

});
</script>

<script>
var callerIdTips = {
    single: <?php echo json_encode(gettext(
        'Enter a single phone number with area code. Use 10 digits for landline or 11 digits for mobile. Examples: 5145001010 or 51956661010'
    )); ?>,
    multiple: <?php echo json_encode(gettext(
        'Enter one or more phone numbers with area code. Separate the numbers using commas, without spaces. Example: 5145001010,51956661010'
    )); ?>,
    range: <?php echo json_encode(gettext(
        'Enter the initial phone number with area code, followed by ":" and four digits. This format allows generating a sequence of numbers automatically. Examples: 5145001010:9090 or 51955001010:9999'
    )); ?>,
    default: <?php echo json_encode(gettext(
        'Enter the number that will be used as the caller identification.'
    )); ?>
};

function updateCallerIdTip(type) {

    var tip = callerIdTips[type] || callerIdTips.default;

    var $label = $('#caller_id_number')
        .closest('li.form-group')
        .find('label');

    if ($label.length) {

        $label.tooltip('dispose');

        $label.attr('data-original-title', tip);

        $label.tooltip({
            html: true,
            boundary: 'window'
        });
    }
}

$(document).ready(function () {
    updateCallerIdTip($('#caller_id_type').val());
});
</script>

