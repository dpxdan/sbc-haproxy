<title>
<?php echo $page_title; ?> | Flux Telecom - Unindo pessoas e negócios
</title>

<?php
include('header.php');
?>

<section class="slice m-0">
<div class="panel panel-default w-section inverse p-4">
  <div class="panel-heading"><strong>Configuração JSON CDR</strong></div>
  <div class="panel-body">
    <form id="cdr-config-form" class="form-horizontal" method="post" action="<?=base_url()?>cdr_mode/setMode">
      <div class="form-group">
        <label class="col-sm-2 control-label">Modo</label>
        <div class="col-sm-10">
          <label class="radio-inline"><input type="radio" name="mode" value="file" <?= $mode === 'file' ? 'checked' : '' ?>> Arquivo (log-dir)</label>
          <label class="radio-inline"><input type="radio" name="mode" value="url" <?= $mode === 'url' ? 'checked' : '' ?>> URL (POST)</label>
        </div>
      </div>

      <div class="form-group" id="group-url">
        <label class="col-sm-2 control-label">URL</label>
        <div class="col-sm-10">
          <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($cdr_url) ?>" placeholder="http://127.0.0.1:8735/cdr.php">
        </div>
      </div>

      <div class="form-group" id="group-logdir">
        <label class="col-sm-2 control-label">Diretório de logs</label>
        <div class="col-sm-10">
          <input type="text" name="log_dir" class="form-control" value="<?= htmlspecialchars($cdr_log_dir) ?>" placeholder="/var/log/freeswitch/json_cdr">
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
          <button type="button" id="save-btn" class="btn btn-primary">Salvar</button>
          <span id="status" style="margin-left:10px"></span>
        </div>
      </div>
    </form>
  </div>
</div>
</section>
<script>
(function(){
  var form = document.getElementById('cdr-config-form');
  var saveBtn = document.getElementById('save-btn');
  var status = document.getElementById('status');
  function updateVisibility(){
    var mode = form.mode.value;
    document.getElementById('group-url').style.display = mode === 'url' ? 'block' : 'none';
    document.getElementById('group-logdir').style.display = mode === 'file' ? 'block' : 'none';
  }
  form.addEventListener('change', updateVisibility);
  updateVisibility();

  saveBtn.addEventListener('click', function(){
    var formData = new FormData(form);
    var obj = { mode: formData.get('mode'), url: formData.get('url'), log_dir: formData.get('log_dir') };
    status.textContent = 'Salvando...';
    fetch('<?= site_url('cdr_mode/setMode') ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(obj)
    }).then(function(r){ return r.json(); }).then(function(j){
      if (j.ok) status.textContent = 'Salvo';
      else status.textContent = 'Erro: ' + (j.error || 'unknown');
    }).catch(function(err){
      status.textContent = 'Erro: ' + err;
    });
  });
})();
</script>
<? start_block_marker('content') ?>
<? end_block_marker() ?>
<?php 
if(!empty($this->session->userdata('accountinfo'))) { 
 include('footer.php'); 
} else {
include('footer.php'); 
} 
?>