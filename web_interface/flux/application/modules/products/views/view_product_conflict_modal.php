<style>

#modalProductConflict .modal-content {
	border: 0;
	border-radius: .3rem;
}

@media (min-width: 1200px) {
	#modalProductConflict .modal-dialog {
		max-width: 1100px;
	}
}

#modalProductConflict .form-group {
	display: block;
	height: auto;
	margin-top: 0;
}

#modalProductConflict .control-label {
	position: static;
}

#modalProductConflict .modal-body {
	max-height: calc(100vh - 220px);
	overflow-y: auto;
}

#modalProductConflict .conflict-accounts {
	max-height: 35vh;
	overflow-y: auto;
}

#modalProductConflict .conflict-accounts table {
	margin-bottom: 0;
}

#modalProductConflict .conflict-accounts thead th {
	position: sticky;
	top: 0;
	z-index: 1;
	background: #f8f9fa;
}

#modalProductConflict .close {
	opacity: 1 !important;
	margin: 0;
	padding: 0;
	line-height: 1;
}

#modalProductConflict .conflict-actions-label {
	display: block;
	font-weight: 600;
	margin-bottom: .75rem;
}

#modalProductConflict .conflict-actions {
	display: flex;
	flex-wrap: wrap;
	margin: 0 -.5rem;
}

#modalProductConflict .conflict-option {
	flex: 1 1 320px;
	display: block;
	margin: 0 .5rem 1rem;
	padding: 1rem 1.25rem;
	border: 1px solid #dee2e6;
	border-radius: .3rem;
	background: #fff;
	cursor: pointer;
	transition: border-color .15s ease-in-out, background-color .15s ease-in-out;
}

#modalProductConflict .conflict-option:hover {
	border-color: #1c67bf;
}

#modalProductConflict .conflict-option input[type="radio"] {
	position: absolute;
	z-index: -1;
	opacity: 0;
}

#modalProductConflict .conflict-option-icon {
	display: block;
	font-size: 20px;
	color: #6c757d;
	margin-bottom: .5rem;
}

#modalProductConflict .conflict-option-title {
	display: block;
	font-weight: 600;
	font-size: 15px;
	color: #212529;
	margin-bottom: .25rem;
}

#modalProductConflict .conflict-option-desc {
	display: block;
	font-size: 13px;
	color: #6c757d;
	line-height: 1.4;
}

#modalProductConflict .conflict-option input[type="radio"]:checked ~ .conflict-option-body {
	color: #1c67bf;
}

#modalProductConflict .conflict-option input[type="radio"]:checked ~ .conflict-option-body .conflict-option-icon,
#modalProductConflict .conflict-option input[type="radio"]:checked ~ .conflict-option-body .conflict-option-title {
	color: #1c67bf;
}

#modalProductConflict .conflict-option.is-selected {
	border-color: #1c67bf;
	background: #f2f7fd;
	box-shadow: inset 0 0 0 1px #1c67bf;
}

#modalProductConflict .conflict-option:focus-within {
	box-shadow: 0 0 0 3px rgba(28, 103, 191, .25);
}

#modalProductConflict .modal-footer {
	display: flex;
	justify-content: flex-end;
	padding: 1rem 1.5rem;
	border-top: 1px solid #e9ecef;
}

#modalProductConflict .modal-footer .btn {
	min-width: 160px;
	margin: 0 0 0 .75rem;
}

#modalProductConflict .modal-footer .btn-secondary {
	background: #f1f3f5 !important;
	color: #495057 !important;
	border: 1px solid #ced4da !important;
}

#modalProductConflict .modal-footer .btn-secondary:hover {
	background: #e2e6ea !important;
}

@media (max-width: 575.98px) {
	#modalProductConflict .modal-footer {
		flex-direction: column-reverse;
	}

	#modalProductConflict .modal-footer .btn {
		width: 100%;
		margin: 0 0 .5rem;
	}
}

@media (max-height: 800px) {
	#modalProductConflict .modal-body {
		padding: 1rem 1.5rem !important;
	}

	#modalProductConflict .conflict-accounts {
		max-height: 25vh;
	}

	#modalProductConflict .conflict-option {
		padding: .75rem 1rem;
	}

	#modalProductConflict .conflict-option-icon {
		display: inline-block;
		margin: 0 .5rem 0 0;
		font-size: 16px;
		vertical-align: middle;
	}

	#modalProductConflict .conflict-option-title {
		display: inline-block;
		vertical-align: middle;
		margin-bottom: 0;
	}
}
</style>

<div class="modal fade" id="modalProductConflict" tabindex="-1" role="dialog" aria-labelledby="modalProductConflictTitle" data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="col-md-12 p-0 card-header">
				<h3 class="fw4 p-4 m-0" id="modalProductConflictTitle"><?php echo gettext('Conflict: Accounts with active package'); ?>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<img alt="close" src="/assets/images/closelabel.png" class="close_image" title="close" style="height:15px;width:15px;">
					</button>
				</h3>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-warning">
					<i class="fa fa-exclamation-triangle"></i>
					<span id="conflictAccountsCount"></span>
					<?php echo gettext('The following accounts already have a different active package. Choose how to proceed:'); ?>
				</div>
				<div class="table-responsive conflict-accounts mb-4">
					<table class="table table-sm table-bordered" id="conflictAccountsTable">
						<thead class="thead-light">
							<tr>
								<th><?php echo gettext('Account'); ?></th>
								<th><?php echo gettext('Current Package'); ?></th>
							</tr>
						</thead>
						<tbody id="conflictAccountsBody"></tbody>
					</table>
				</div>
				<span class="conflict-actions-label"><?php echo gettext('Action for these accounts:'); ?></span>
				<div class="conflict-actions">
					<label class="conflict-option is-selected" for="conflictReplace">
						<input type="radio" id="conflictReplace" name="conflict_action_modal" value="replace" checked>
						<span class="conflict-option-body">
							<i class="fa fa-exchange conflict-option-icon" aria-hidden="true"></i>
							<span class="conflict-option-title"><?php echo gettext('Replace package'); ?></span>
							<span class="conflict-option-desc"><?php echo gettext('Remove current package and add the new one'); ?></span>
						</span>
					</label>
					<label class="conflict-option" for="conflictKeep">
						<input type="radio" id="conflictKeep" name="conflict_action_modal" value="keep">
						<span class="conflict-option-body">
							<i class="fa fa-clone conflict-option-icon" aria-hidden="true"></i>
							<span class="conflict-option-title"><?php echo gettext('Keep both'); ?></span>
							<span class="conflict-option-desc"><?php echo gettext('Keep both packages active on the account'); ?></span>
						</span>
					</label>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo gettext('Cancel'); ?></button>
				<button type="button" class="btn btn-primary" id="btnConfirmConflict"><?php echo gettext('Confirm and Save'); ?></button>
			</div>
		</div>
	</div>
</div>

<script>

var productConflictForm = null;

function product_conflict_submit(formSelector, productId, btnSelector) {
	var $form = $(formSelector);
	var $btn  = $(btnSelector);
	var applyOnExisting = $form.find('select[name="apply_on_existing_account"]').val();
	var rateGroups      = $form.find('select[name="product_rate_group[]"]').val();

	productConflictForm = $form;
	$form.find('#conflict_action').val('');

	if (applyOnExisting !== '0' || !rateGroups || rateGroups.length === 0) {
		$form.submit();
		return;
	}

	var btnLabel = $btn.text();

	$.ajax({
		type    : 'POST',
		url     : '<?php echo base_url(); ?>products/products_check_rategroup_conflicts/',
		data    : {
			product_id             : productId || 0,
			'product_rate_group[]' : rateGroups
		},
		dataType: 'json',
		beforeSend: function() {
			$btn.prop('disabled', true).text('<?php echo addslashes(gettext("Checking...")); ?>');
		},
		success : function(response) {
			$btn.prop('disabled', false).text(btnLabel);

			if (!response.has_conflicts || response.conflicts.length === 0) {
				$form.submit();
				return;
			}

			var tbody = $('#conflictAccountsBody');
			tbody.empty();
			$.each(response.conflicts, function(i, row) {
				var name  = ((row.first_name || '') + ' ' + (row.last_name || '')).trim() || row.number;
				var label = row.number ? name + ' (' + row.number + ')' : name;
				tbody.append(
					'<tr><td>' + $('<span>').text(label).html() + '</td>' +
					'<td>' + $('<span>').text(row.existing_product_name).html() + '</td></tr>'
				);
			});

			$('#conflictAccountsCount').text(response.conflicts.length + ' <?php echo addslashes(gettext("accounts found with conflicting package.")); ?>');
			$('#conflictReplace').prop('checked', true).trigger('change');
			$('#modalProductConflict').modal('show');
		},
		error   : function() {
			$btn.prop('disabled', false).text(btnLabel);
			alert('<?php echo addslashes(gettext("Error checking conflicts. Please try again.")); ?>');
		}
	});
}

$(document).ready(function() {
	$('#modalProductConflict').on('change', 'input[name="conflict_action_modal"]', function() {
		$('#modalProductConflict .conflict-option').removeClass('is-selected');
		$(this).closest('.conflict-option').addClass('is-selected');
	});

	$('#btnConfirmConflict').on('click', function() {
		if (!productConflictForm) {
			return;
		}
		productConflictForm.find('#conflict_action').val($('input[name="conflict_action_modal"]:checked').val());
		$('#modalProductConflict').modal('hide');
		productConflictForm.submit();
	});
});
</script>
