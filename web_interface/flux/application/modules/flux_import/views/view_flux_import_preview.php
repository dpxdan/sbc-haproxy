<?php extend('master.php') ?>
<?php startblock('extra_head') ?>
<style>
    .preview-table td,
    .preview-table th {
        font-size: 12px;
        white-space: nowrap;
        padding: 5px 8px;
        vertical-align: middle;
    }
    .badge-portado  { background-color: #17a2b8; color: #fff; }
    .badge-novo     { background-color: #6c757d; color: #fff; }
    .badge-ativo    { background-color: #28a745; color: #fff; }
    .badge-inativo  { background-color: #dc3545; color: #fff; }
    .badge-normal   { background-color: #6c757d; color: #fff; }
    .badge-premium  { background-color: #ffc107; color: #212529; }
    .summary-box {
        border-left: 4px solid;
        padding: 12px 16px;
        border-radius: 4px;
        background: #f8f9fa;
    }
    .code { color: #007bff; }
    .summary-box.accounts { border-color: #007bff; }
    .summary-box.dids     { border-color: #28a745; }
    .summary-box.skipped  { border-color: #ffc107; }
    .summary-box.errors   { border-color: #dc3545; }
    .summary-box .count   { font-size: 2rem; font-weight: 700; line-height: 1; }
    .summary-box .label   { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6c757d; }
</style>
<?php endblock() ?>
<?php startblock('page-title') ?>
<?php echo $page_title; ?>
<?php endblock() ?>
<?php startblock('content') ?>
<section class="slice color-three pb-4">
    <div class="w-section inverse p-0">

        <div class="container-fluid mb-4">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="summary-box accounts">
                        <div class="count text-primary"><?php echo $total_accounts; ?></div>
                        <div class="label"><?php echo gettext("Accounts to create"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="summary-box dids">
                        <div class="count text-success"><?php echo $total_dids; ?></div>
                        <div class="label"><?php echo gettext("DIDs to import"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="summary-box skipped">
                        <div class="count text-warning"><?php echo $total_parse_errors; ?></div>
                        <div class="label"><?php echo gettext("Lines with reading errors"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="summary-box errors">
                        <div class="count text-danger"><?php echo $total_accounts + $total_dids; ?></div>
                        <div class="label"><?php echo gettext("Total to process"); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($parse_errors)): ?>
        <div class="container-fluid mb-4">
            <div class="card">
                <h5 class="bg-danger text-light p-3 m-0 rounded-top">
                    <?php echo gettext("Errors found reading the file"); ?>
                    <span class="badge badge-light ml-2"><?php echo count($parse_errors); ?></span>
                </h5>
                <div class="p-3">
                    <ul class="mb-0" style="font-size:13px;">
                        <?php foreach ($parse_errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($total_parse_errors > count($parse_errors)): ?>
                        <p class="text-muted mt-2 mb-0">
                            <?php echo gettext("... and") . ' ' . ($total_parse_errors - count($parse_errors)) . ' ' . gettext("more errors not displayed."); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="container-fluid mb-4">
            <div class="card">
                <h5 class="bg-secondary text-light p-3 m-0 rounded-top">
                    <?php echo gettext("Preview — Accounts"); ?>
                    <span class="badge badge-light ml-2"><?php echo gettext("first") . ' ' . count($preview_accounts); ?></span>
                </h5>
                <div class="p-3 table-responsive">
                    <table class="table table-bordered table-hover preview-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th><?php echo gettext("Company Name"); ?></th>
                                <th><?php echo gettext("CNPJ/CPF"); ?></th>
                                <th><?php echo gettext("Account Number"); ?></th>
                                <th><?php echo gettext("Status"); ?></th>
                                <th><?php echo gettext("Max Channels"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($preview_accounts)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <?php echo gettext("No accounts to import."); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($preview_accounts as $acc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($acc['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($acc['tax_number']); ?></td>
                                    <td><code style="color: #007bff !important;"><?php echo htmlspecialchars($acc['number']); ?></code></td>
                                    <td>
                                        <?php if ($acc['status'] == 0): ?>
                                            <span class="badge badge-ativo"><?php echo gettext("Active"); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-inativo"><?php echo gettext("Inactive"); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo (int) $acc['maxchannels']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($total_accounts > count($preview_accounts)): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:12px;">
                            <?php echo gettext("Showing") . ' ' . count($preview_accounts) . ' ' . gettext("of") . ' ' . $total_accounts . ' ' . gettext("accounts."); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="container-fluid mb-4">
            <div class="card">
                <h5 class="bg-secondary text-light p-3 m-0 rounded-top">
                    <?php echo gettext("Preview — DIDs"); ?>
                    <span class="badge badge-light ml-2"><?php echo gettext("first") . ' ' . count($preview_dids); ?></span>
                </h5>
                <div class="p-3 table-responsive">
                    <table class="table table-bordered table-hover preview-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th><?php echo gettext("Number"); ?></th>
                                <th><?php echo gettext("Account"); ?></th>
                                <th><?php echo gettext("Status"); ?></th>
                                <th><?php echo gettext("Call Type"); ?></th>
                                <th><?php echo gettext("Max Channels"); ?></th>
                                <th><?php echo gettext("Reverse Rate"); ?></th>
                                <th><?php echo gettext("Destination"); ?></th>
                                <th><?php echo gettext("Created Date"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($preview_dids)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted">
                                        <?php echo gettext("No DIDs to import."); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($preview_dids as $did): ?>
                                <tr>
                                    <td><code style="color: #007bff !important;"><?php echo htmlspecialchars($did['number']); ?></code></td>
                                    <td><?php echo htmlspecialchars($did['_cnpj_key']); ?></td>
                                    <td>
                                        <?php if ($did['status'] == 0): ?>
                                            <span class="badge badge-ativo"><?php echo gettext("Active"); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-inativo"><?php echo gettext("Inactive"); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($did['call_type'] == 2): ?>
                                            <?php echo gettext("Direct-IP"); ?>
                                        <?php else: ?>
                                            <?php echo gettext("DID-Local"); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo (int) $did['maxchannels']; ?></td>
                                    <td class="text-center">
                                        <?php
                                            $cls = $did['reverse_rate'] == 1 ? 'badge-normal' : 'badge-premium';
                                            $label = $did['reverse_rate'] == 1 ? gettext("Inactive") : gettext('Active');
                                        ?>
                                        <span class="badge <?php echo $cls; ?>"><?php echo $label; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($did['extensions']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($did['last_modified_date'], 0, 10)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($total_dids > count($preview_dids)): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:12px;">
                            <?php echo gettext("Show") . ' ' . count($preview_dids) . ' ' . gettext("of") . ' ' . $total_dids . ' ' . gettext("DIDs."); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="card p-3">
                <form method="post" action="<?php echo base_url() ?>flux_import/process/" id="flux_import_process">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="mb-0 text-muted" style="font-size:13px;">
                                <?php echo gettext("When confirmed,"); ?>
                                <strong><?php echo $total_accounts; ?></strong> <?php echo gettext("accounts will be created and"); ?>
                                <strong><?php echo $total_dids; ?></strong> <?php echo gettext("DIDs will be imported. The operation is executed in a transaction — if an error occurs, no data will be saved."); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="<?php echo base_url() ?>flux_import/flux_import_list/">
                                <button type="button" class="btn btn-secondary mr-2">
                                    <?php echo gettext("Back"); ?>
                                </button>
                            </a>
                            <button type="submit" class="btn btn-success" id="btn_process"
                                <?php echo ($total_accounts === 0 && $total_dids === 0) ? 'disabled' : ''; ?>>
                                <i class="fa fa-check"></i> <?php echo gettext("Confirm and Import"); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script>
    document.getElementById('flux_import_process').addEventListener('submit', function () {
        var btn = document.getElementById('btn_process');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php echo gettext("Processing..."); ?>';
    });
</script>
<?php endblock() ?>
<?php end_extend() ?>
