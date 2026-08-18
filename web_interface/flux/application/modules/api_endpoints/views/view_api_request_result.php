<div class="container">
    <h2><?= gettext('API Request Result') ?></h2>
    <div class="card mb-4">
        <div class="card-header"><strong><?= gettext('Summary Response') ?></strong></div>
        <div class="card-body">
            <p><strong><?= gettext('Status Code') ?>:</strong> <?= $http_code ?? 'N/A' ?></p>
            <?php if (isset($total_time)) : ?>
                <p><strong><?= gettext('Response Time') ?>:</strong> <?= round($total_time, 3) ?> <?= gettext('seconds') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($response_headers)) : ?>
        <div class="card mb-4">
            <div class="card-header"><strong><?= gettext('Response Headers') ?></strong></div>
            <div class="card-body">
                <pre><?= htmlspecialchars($response_headers) ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong><?= gettext('Response Body') ?></strong></div>
        <div class="card-body">
            <pre class="response-body"><?php
      $json = json_decode($response_body, true);
      if ($json) {
          echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      } else {
          echo htmlspecialchars($response_body);
      }
    ?></pre>
        </div>
    </div>

    <?php if (!empty($request_summary)) : ?>
        <div class="card mb-4">
            <div class="card-header"><strong><?= gettext('Sent Request') ?></strong></div>
            <div class="card-body">
                <pre><?= htmlspecialchars($request_summary) ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <!--<a href="<?php echo base_url(); ?>api_endpoints/api_endpoints_list/" class="btn btn-secondary"><?= gettext('Back') ?></a>-->
</div>
<style>
.response-body {
    max-height: 400px;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: auto;
    background-color: #f8f9fa;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    white-space: pre;
    font-size: 14px;
    font-family: monospace;
}
</style>
