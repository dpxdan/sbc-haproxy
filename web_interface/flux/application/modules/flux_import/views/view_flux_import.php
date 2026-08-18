<?php extend('master.php') ?>
<?php startblock('extra_head') ?>
<?php endblock() ?>
<?php startblock('page-title') ?>
<?php echo $page_title; ?>
<?php endblock() ?>
<?php startblock('content') ?>
<section class="slice color-three">
    <div class="w-section">

        <?php if (isset($error) && !empty($error)): ?>
        <div class="row mb-3">
            <div class="col-md-12">
                <span class="alert alert-danger d-block"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <form method="post"
            action="<?php echo base_url() ?>flux_import/preview/"
            enctype="multipart/form-data"
            id="flux_import_form"
            name="flux_import_form">

            <div class="row">

                <!-- Bloco 1: informações do formato -->
                <div class="col-md-12 mb-4">
                    <div class="row">

                        <div class="col-md-10 col-sm-12">
                            <div class="card h-100">
                                <h3 class="bg-secondary text-light p-3 m-0 rounded-top">
                                    <?php echo gettext("Unified Import of Accounts and DIDs"); ?>
                                </h3>
                                <div class="p-3">
                                    <p class="mb-1"><?php echo gettext("The CSV file must contain the following columns separated by semicolon (;):"); ?></p>
                                    <i class="text-muted" style="font-size: 13px;">
                                        <?php echo gettext("Records with existing numbers (DIDs) or CNPJs will be ignored."); ?>
                                    </i>
                                </div>
                            </div>
                        </div>

                        <!--<div class="col-md-2 col-sm-12 mt-3 mt-md-0">
                            <div class="card h-100">
                                <label class="card-header text-center m-0 p-3">
                                    <?php echo gettext("Sample File"); ?>
                                </label>
                                <div class="p-3 d-flex align-items-center justify-content-center flex-grow-1">
                                    <a href="<?php echo base_url(); ?>flux_import/download_sample/"
                                        class="btn btn-success btn-block">
                                        <i class="fa fa-download"></i> <?php echo gettext("Download"); ?>
                                    </a>
                                </div>
                            </div>
                        </div>-->
                        <div class="col-md-2 col-sm-12 float-left pl-md-4 p-0">
                        						<div class="w-box card col-md-12 form-group px-0">
                        							<label class="card-header text-center m-0"><?php echo gettext("Get Sample file"); ?></label>
                        							<div class="col-md-12 p-3">
                        								<a href="<?= base_url(); ?>flux_import/download_sample/flux_import_sample"
                        									class="btn btn-success btn-block text-light"><i class="fa fa-download"></i> <?php echo gettext("Download"); ?></a>
                        							</div>
                        						</div>
                        					</div>

                    </div>
                </div>

                <!-- Bloco 2: upload e configurações -->
                <div class="col-md-12 mb-4">
                    <div class="card col-md-12 p-0">
                        <div class="pb-4" id="floating-label">
                            <h3 class="bg-secondary text-light p-3 rounded-top">
                                <?php echo gettext("Select File"); ?>
                            </h3>

                            <div class="col-md-4 form-group">
                                <label class="p-0 control-label">
                                    <?php echo gettext("Column Delimiter"); ?>
                                </label>
                                <select name="csv_delimiter" class="form-control">
                                    <option value=";"><?php echo gettext("Semicolon ( ; ) — default"); ?></option>
                                    <option value=","><?php echo gettext("Comma ( , )"); ?></option>
                                    <option value="&#9;"><?php echo gettext("Tab"); ?></option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <label class="control-label mb-4">
                                    <?php echo gettext("CSV File"); ?>
                                    <small class="text-muted ml-2"><?php echo gettext("(maximum:"); ?> <?php echo str_replace("M", "MB", ini_get('upload_max_filesize')); ?>)</small>
                                </label>
                                <div class="col-12 mt-4 d-flex">
                                    <div class="col-md-6 float-left" data-ripple="">
                                        <input type="file"
                                            name="flux_import_file"
                                            id="flux_import_file"
                                            class="custom-file-input"
                                            title="<?php echo gettext("Only CSV files are allowed."); ?>" />
                                        <label class="custom-file-label btn-primary btn-file text-left" for="flux_import_file"></label>
                                    </div>
                                    <div class="col-md-6 float-left align-self-center">
                                        <span id="remove_file_wrap" class="answer_list float-left d-none">
                                            <button type="button" class="btn btn-danger" id="remove_file">
                                                <?php echo gettext("Remove"); ?>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Bloco 3: o que será importado -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <h3 class="bg-secondary text-light p-3 m-0 rounded-top">
                            <?php echo gettext("What will be imported"); ?>
                        </h3>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="fa fa-users text-primary mt-1 mr-3" style="font-size: 18px; min-width: 20px;"></i>
                                        <span><?php echo gettext("One account per unique CNPJ found in the file."); ?></span>
                                    </div>
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="fa fa-phone text-success mt-1 mr-3" style="font-size: 18px; min-width: 20px;"></i>
                                        <span><?php echo gettext("One DID per number (column 'numero'), linked to the respective CNPJ account."); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="fa fa-ban text-warning mt-1 mr-3" style="font-size: 18px; min-width: 20px;"></i>
                                        <span><?php echo gettext("Existing accounts and DIDs will be ignored (no overwrite)."); ?></span>
                                    </div>
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="fa fa-info-circle text-muted mt-1 mr-3" style="font-size: 18px; min-width: 20px;"></i>
                                        <span><?php echo gettext("Financial fields (cost, monthly fee) are imported as zero and must be configured later."); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="col-md-12">
                    <div class="text-center">
                        <button class="btn btn-primary" type="submit" name="action" value="preview">
                            <i class="fa fa-eye"></i> <?php echo gettext("Preview before importing"); ?>
                        </button>
                        <a href="<?php echo base_url() . 'accounts/customer_list/' ?>">
                            <button class="btn btn-secondary mx-2" type="button">
                                <?php echo gettext("Cancel"); ?>
                            </button>
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</section>

<script>
    (function () {
        var fileInput = document.getElementById('flux_import_file');
        var removeBtn = document.getElementById('remove_file');
        var removeWrap = document.getElementById('remove_file_wrap');
        var fileLabel = document.querySelector('.custom-file-label');

        fileInput.addEventListener('change', function (e) {
            if (e.target.files.length > 0) {
                fileLabel.textContent = e.target.files[0].name;
                removeWrap.classList.remove('d-none');
            }
        });

        removeBtn.addEventListener('click', function () {
            fileInput.value = null;
            fileLabel.textContent = '';
            removeWrap.classList.add('d-none');
        });
    })();
</script>
<?php endblock() ?>
<?php end_extend() ?>
