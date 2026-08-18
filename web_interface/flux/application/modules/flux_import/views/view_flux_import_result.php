<?php extend('master.php') ?>
<?php startblock('extra_head') ?>
<style>
    .result-box {
        border-left: 4px solid;
        padding: 16px 20px;
        border-radius: 4px;
        background: #f8f9fa;
    }
    .result-box.success  { border-color: #28a745; }
    .result-box.info     { border-color: #17a2b8; }
    .result-box.warning  { border-color: #ffc107; }
    .result-box.danger   { border-color: #dc3545; }
    .result-box .count   { font-size: 2.4rem; font-weight: 700; line-height: 1; }
    .result-box .label   { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6c757d; margin-top: 4px; }
</style>
<?php endblock() ?>
<?php startblock('page-title') ?>
<?php echo $page_title; ?>
<?php endblock() ?>
<?php startblock('content') ?>
<section class="slice color-three pb-4">
    <div class="w-section inverse p-0">
        <div class="container-fluid">

            <?php if (isset($error) && !empty($error)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-danger">
                        <i class="fa fa-times-circle mr-2"></i>
                        <strong><?php echo gettext("Transaction error:"); ?></strong>
                        <?php echo htmlspecialchars($error); ?>
                        <br>
                        <?php echo gettext("No data was saved. Fix the problem and try again."); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- contadores -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="result-box success">
                        <div class="count text-success"><?php echo (int) $account_count; ?></div>
                        <div class="label"><?php echo gettext("Accounts created"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="result-box success">
                        <div class="count text-success"><?php echo (int) $did_count; ?></div>
                        <div class="label"><?php echo gettext("DIDs imported"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="result-box warning">
                        <div class="count text-warning"><?php echo (int) $skipped; ?></div>
                        <div class="label"><?php echo gettext("Records skipped (duplicates)"); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="result-box <?php echo empty($parse_errors) ? 'info' : 'danger'; ?>">
                        <div class="count <?php echo empty($parse_errors) ? 'text-info' : 'text-danger'; ?>">
                            <?php echo count($parse_errors); ?>
                        </div>
                        <div class="label"><?php echo gettext("Errors"); ?></div>
                    </div>
                </div>
            </div>

            <!-- erros detalhados -->
            <?php if (!empty($parse_errors)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <h5 class="bg-danger text-light p-3 m-0 rounded-top">
                            <i class="fa fa-exclamation-triangle mr-2"></i>
                            <?php echo gettext("Records not imported"); ?>
                            <span class="badge badge-light ml-2"><?php echo count($parse_errors); ?></span>
                        </h5>
                        <div class="p-3">
                            <p class="text-muted mb-2" style="font-size:13px;">
                                <?php echo gettext("The records below were ignored. Fix the CSV file and re-import only the items with errors."); ?>
                            </p>
                            <ul class="mb-0" style="font-size:13px;">
                                <?php foreach ($parse_errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- nota sobre campos financeiros -->
            <?php if ($did_count > 0): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info mb-0">
                        <i class="fa fa-info-circle mr-2"></i>
                        <?php echo gettext("Imported DIDs have zero cost, monthly fee and setup. Configure the values in the DIDs module."); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ações -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card p-3">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo base_url() ?>flux_import/flux_import_list/">
                                <button type="button" class="btn btn-secondary mr-2">
                                    <i class="fa fa-upload mr-1"></i>
                                    <?php echo gettext("New import"); ?>
                                </button>
                            </a>
                            <a href="<?php echo base_url() ?>accounts/customer_list/">
                                <button type="button" class="btn btn-primary mr-2">
                                    <i class="fa fa-users mr-1"></i>
                                    <?php echo gettext("View Accounts"); ?>
                                </button>
                            </a>
                            <a href="<?php echo base_url() ?>did/did_list/">
                                <button type="button" class="btn btn-success">
                                    <i class="fa fa-phone mr-1"></i>
                                    <?php echo gettext("View DIDs"); ?>
                                </button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
<?php endblock() ?>
<?php end_extend() ?>
