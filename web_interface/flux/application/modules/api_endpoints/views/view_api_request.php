<?php include(FCPATH.'application/views/popup_header.php'); ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/flexigrid.css" type="text/css">

<style>
  #api_response_result {
      height: 500px;
      overflow-y: auto;
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 4px;
  }
</style>

<script type="text/javascript">
  $(document).ready(function(){
    $(".breadcrumb li a").removeAttr("data-ripple");
  });  	
</script>

<section class="slice m-0">
  <div class="w-section inverse p-0">
    <div>
      <div>
        <div class="col-md-12 p-0 card-header">
          <h3 class="fw4 p-4 m-0"><?php echo $page_title; ?></h3>
        </div>
      </div>
    </div>
  </div>
</section>

<div>
  <div>
    <section class="slice m-0">
      <div class="w-section inverse p-4">
        <div>
          <?php if (isset($validation_errors)) echo $validation_errors; ?>
        </div>

        <form method="post" id="api_test_form" action="<?= base_url('api_endpoints/api_test_send') ?>">
          <div class="form-group">
            <label for="endpoint_url"><?php echo gettext('Endpoint URL'); ?></label>
            <input type="text" class="form-control" name="endpoint_url" id="endpoint_url" value="<?= isset($endpoint_info['endpoint_url']) ? $endpoint_info['endpoint_url'] : '' ?>" readonly/>
            <input type="hidden" name="url" id="final_url" />
          </div>

          <div class='form-group'>
            <label for="destination_endpoints"><?php echo gettext('Destination Endpoint'); ?></label>
            <select name="destination_endpoints" id="api_destination_endpoints" class="form-control" onchange="changeBodyDefault(this.value)">
              <?php foreach($destination_endpoints as $key1 => $destination_endpoint) { ?>
                <option value= "<?php echo $key1; ?>"> <?php echo  $destination_endpoint ?> </option>
              <?php } ?>
            </select>
          </div>

          <div class="form-group">
            <label for="method"><?php echo gettext('HTTP Method'); ?></label>
            <select name="method" id="method" class="form-control">
              <option value="GET">GET</option>
              <option value="POST" selected>POST</option>
              <option value="PUT">PUT</option>
              <option value="DELETE">DELETE</option>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="rp_limit"><?php echo gettext('Limit Records'); ?></label>
              <select id="rp_limit" name="rp_limit" class="form-control" onchange="updateBodyRP()">
                  <option value="1" selected>1 <?php echo gettext('records'); ?></option>
                  <option value="5">5 <?php echo gettext('records'); ?></option>
                  <option value="10">10 <?php echo gettext('records'); ?></option>
                  <option value="15">15 <?php echo gettext('records'); ?></option>
                  <option value="20">20 <?php echo gettext('records'); ?></option>
                  <option value="50">50 <?php echo gettext('records'); ?></option>
                  <option value="100">100 <?php echo gettext('records'); ?></option>
                  <option value="200">200 <?php echo gettext('records'); ?></option>
                  <option value="500">500 <?php echo gettext('records'); ?></option>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label for="page_field"><?php echo gettext('Page'); ?></label>
              <input type="number" id="page_field" name="page_field" class="form-control" value="1" min="1" step="1" onchange="updateBodyPage()">
            </div>
          </div>
          <hr>
          <h5><?php echo gettext('Authentication'); ?></h5>

          <div class="form-group">
            <label for="endpoint_auth"><?php echo gettext('Authentication Type'); ?></label>
            <select name="endpoint_auth" id="endpoint_auth" class="form-control">
              <option value=""><?php echo gettext('None'); ?></option>
              <option value="basic" selected><?php echo gettext('Basic'); ?></option>
              <option value="bearer"><?php echo gettext('Bearer Token'); ?></option>
            </select>
          </div>

          <div class="form-group">
            <label for="endpoint_user"><?php echo gettext('Authentication User'); ?></label>
            <input type="text" class="form-control" name="endpoint_user" id="endpoint_user" value="<?php echo (isset($endpoint_info['endpoint_user']))?$endpoint_info['endpoint_user']:'' ?>">
          </div>

          <div class="form-group">
            <label for="endpoint_password"><?php echo gettext('Authentication Password'); ?></label>
            <input type="password" class="form-control" name="endpoint_password" id="endpoint_password" value="<?php echo (isset($endpoint_info['endpoint_password']))?$endpoint_info['endpoint_password']:'' ?>">
          </div>

          <div class="form-group">
            <label for="endpoint_token"><?php echo gettext('Authentication Token'); ?></label>
            <input type="text" class="form-control" name="endpoint_token" id="endpoint_token" value="<?php echo (isset($endpoint_info['endpoint_token']))?$endpoint_info['endpoint_token']:'' ?>">
          </div>

          <hr>
          <h5><?php echo gettext('Additional Headers'); ?></h5>

          <div id="headers-container"></div>
          <button type="button" class="btn btn-outline-primary mb-3" id="add-header"><?php echo gettext('Add'); ?></button>

          <hr>
          <div class="form-group">
            <label for="body"><?php echo gettext('Body (JSON)'); ?></label>
            <textarea class="form-control" name="body" id="body" rows="10" placeholder='{"key": "value"}' readonly></textarea>
          </div>

          <button type="submit" class="btn btn-secondary"><?php echo gettext('Send'); ?></button>
          <br/><br/>
        </form>

        <div id="api_response_result" class="mt-4 p-3"></div>
      </div>
    </section>
  </div>
</div>

<script>
  $(document).ready(function () {
    function updateFinalURL() {
      const baseUrl = $('#endpoint_url').val().replace(/\/$/, '');
      const destination = $('#api_destination_endpoints').val();
      $('#final_url').val(baseUrl + '/' + destination);
    }
    function addIXCHeader() {
      const endpointUrl = '<?php echo $endpoint_info["endpoint_url"]; ?>';
      const HeaderIXCSet = $('input[name="headers[key][]"]').filter(function () {
        return $(this).val().toLowerCase() === 'ixcsoft';
      }).length > 0;
    
      if (endpointUrl.includes('webservice') && !HeaderIXCSet) {
        $('#headers-container').append(`
          <div class="header-pair form-row mb-2">
            <div class="col">
              <input type="text" name="headers[key][]" class="form-control" value="ixcsoft" readonly />
            </div>
            <div class="col">
              <input type="text" name="headers[value][]" class="form-control" value="listar" readonly />
            </div>
            <div class="col-auto">
              <button type="button" onclick="$(this).parent().parent().remove()" class="btn btn-danger btn-sm"><?php echo gettext('Remove'); ?></button>
            </div>
          </div>`);
      }
    }

    $('#api_destination_endpoints').on('change', function () {
      updateFinalURL();
      addIXCHeader();
    });
    updateFinalURL();
    addIXCHeader();

    $('#add-header').click(function () {
      $('#headers-container').append(`
        <div class="header-pair form-row mb-2">
          <div class="col">
            <input type="text" name="headers[key][]" placeholder="<?php echo gettext('Header'); ?>" class="form-control" />
          </div>
          <div class="col">
            <input type="text" name="headers[value][]" placeholder="<?php echo gettext('Value'); ?>" class="form-control" />
          </div>
          <div class="col-auto">
            <button type="button" onclick="$(this).parent().parent().remove()" class="btn btn-danger btn-sm"><?php echo gettext('Remove'); ?></button>
          </div>
        </div>`);
    });

    $('#api_test_form').submit(function (e) {
      e.preventDefault();
      updateFinalURL();

      const authType = $('#endpoint_auth').val().trim().toLowerCase();
      const user = $('#endpoint_user').val();
      const pass = $('#endpoint_password').val();
      const token = $('#endpoint_token').val();

      $('.header-pair').each(function () {
        const keyInput = $(this).find('input[name="headers[key][]"]');
        if (keyInput.val().toLowerCase() === 'authorization') {
          $(this).remove();
        }
      });

      let authHeader = null;
      if (authType === 'basic' && user && pass) {
        authHeader = 'Basic ' + btoa(user + ':' + pass);
      } else if (authType === 'bearer' && token) {
        authHeader = 'Bearer ' + token;
      }

      if (authHeader) {
        $('#headers-container').append(`
          <div class="header-pair form-row mb-2">
            <div class="col">
              <input type="text" name="headers[key][]" class="form-control" value="Authorization" readonly />
            </div>
            <div class="col">
              <input type="text" name="headers[value][]" class="form-control" value="${authHeader}" readonly />
            </div>
            <div class="col-auto">
              <button type="button" onclick="$(this).parent().parent().remove()" class="btn btn-danger btn-sm"><?php echo gettext('Remove'); ?></button>
            </div>
          </div>`);
      }

      const formData = $(this).serialize();

      $('#api_response_result').html('<div class="text-center text-muted"><?php echo gettext("Sending Request"); ?>...</div>');

      $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: formData,
        success: function (response) {
          $('#api_response_result').html(response);
        },
        error: function (xhr, status, error) {
          $('#api_response_result').html('<div class="text-danger">Erro na requisição: ' + error + '</div>');
        }
      });
    });
  });
</script>

<script type="text/javascript">
let lastBodyUsed = null;

function changeBodyDefault(endpointId) {
    if (!endpointId) return;

    const rpLimitField = document.getElementById('rp_limit');
    const rpValue = rpLimitField ? parseInt(rpLimitField.value, 10) || 100 : 100;

    $.ajax({
        type: "POST",
        url: "<?= base_url() ?>getendpoint/" + endpointId,
        data: '',
        success: function(response) {
            try {
                const data = JSON.parse(response);
                if (data.body) {
                    const pageField = document.getElementById('page_field');
                    const pageValue = pageField ? parseInt(pageField.value, 10) || 1 : 1;

                    data.body.rp = rpValue;
                    data.body.page = pageValue;

                    lastBodyUsed = data.body;

                    const bodyField = document.getElementById('body');
                    if (bodyField) {
                        bodyField.value = JSON.stringify(data.body, null, 4);
                    }
                } else {
                    console.error("Erro: Resposta inválida");
                }
            } catch (e) {
                console.error("Erro ao interpretar resposta JSON:", e);
            }
        }
    });
}

function updateBodyRP() {
    if (!lastBodyUsed) return;

    const rpLimitField = document.getElementById('rp_limit');
    const RPNewValue = rpLimitField ? parseInt(rpLimitField.value, 10) || 100 : 100;

    lastBodyUsed.rp = RPNewValue;

    const bodyField = document.getElementById('body');
    if (bodyField) {
        bodyField.value = JSON.stringify(lastBodyUsed, null, 4);
    }
}

function updateBodyPage() {
    if (!lastBodyUsed) return;

    const pageField = document.getElementById('page_field');
    const PageNewValue = pageField ? parseInt(pageField.value, 10) || 1 : 1;

    lastBodyUsed.page = PageNewValue;

    const bodyField = document.getElementById('body');
    if (bodyField) {
        bodyField.value = JSON.stringify(lastBodyUsed, null, 4);
    }
}
</script>

