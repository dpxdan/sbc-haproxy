<? extend('master.php') ?>
<? startblock('extra_head') ?>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquery.validate.min.js"></script>
<script type="text/javascript" language="javascript">
  $(document).ready(function () {
    $(window).load(function () {
      $(".did_dropdown").removeClass("col-md-5");
      $(".did_dropdown").addClass("col-md-3");
    });
    build_grid("did_grid", "",<? echo $grid_fields; ?>,<? echo $grid_buttons; ?>);
  $('.checkall').click(function () {
    $('.chkRefNos').prop('checked', $(this).prop('checked'));
  });
  $("#did_search_btn").click(function () {
    post_request_for_search("did_grid", "", "did_search");
  });
  $("#id_reset").click(function () {
    clear_search_request("did_grid", "");
  });
  $("#did_batch_update_form").click(function () {
    var destination_error = validate_did_batch_destination();
    if (destination_error !== "") {
      display_flux_message(destination_error, "notification");
      return false;
    }
    submit_form("did_batch_update");
  })
  $(document).on("change", "#call_type", function () {
    did_batch_destination_change();
  });
  $(document).on("change", "select[name='extensions[operator]']", function () {
    toggle_extensions_field();
  });
  $("#id_batch_reset").click(function () {
    $(".update_drp").each(function () {
      var name = this.name;
      var split_name;
      if (name != undefined) {
        split_name = name.split("[");
        $('#' + split_name[0]).hide();
        $('#' + split_name[0]).val("");
        $('.update_drp').selectpicker('refresh');
      } else {
        $('.update_drp').val("1");
        $('.update_drp').selectpicker('refresh');
      }
      $('pGroup div').removeClass('dropdown');
      if (document.getElementById("expiry")) {
        document.getElementById("expiry").style.display = "block";
      }
    });
    $(".rate_group").val("");
    $('.rate_group').selectpicker('refresh');
    $(".billing_days").val("");
    $('.billing_days').selectpicker('refresh');
    $(".reverse_rate").val("");
    $('.reverse_rate').selectpicker('refresh');
    $(".status").val("");
    $('.status').selectpicker('refresh');
    $(".call_type").val("");
    $('.call_type').selectpicker('refresh');
    $(".bypass_media").val("");
    $('.bypass_media').selectpicker('refresh');
    $(".sip_profile_id").val("");
    $('.sip_profile_id').selectpicker('refresh');
    did_batch_destination_change();
  });
  $(".update_drp").change(function () {
    var inputid = this.name.split("[");
    if (this.value != "1") {
      $('#' + inputid[0]).show();
    } else {
      $('#' + inputid[0]).hide();
    }
  }).each(function () {
    var inputid = this.name.split("[");
    if (this.value != "1") {
      $('#' + inputid[0]).show();
      $(this).addClass("mr-4");
    }
    else {
      $('#' + inputid[0]).hide();
      $(this).removeClass("mr-4");
    }
  });
  $('#purchase_did_form').validate({
    rules: {
      free_did_list: {
        required: true,
      }
    },
    messages: {
      free_did_list: {
        required: "<?php echo gettext('The Available DIDs field is required.'); ?>"
      }
    },
    errorPlacement: function (error, element) {
      error.appendTo('#err');
    }
  });
  $("#purchase_did").click(function () {
    $("#search_generate_bar").slideToggle("slow");
  });
 });


  var did_destination_xhr = null;

  function did_batch_destination_change() {
    var call_type = $("#call_type").val();
    if (did_destination_xhr) {
      did_destination_xhr.abort();
    }
    did_destination_xhr = $.ajax({
      type: "POST",
      url: "<?= base_url() ?>did/did_batch_destination_change/" + call_type,
      data: '',
      success: function (response) {
        var field = $("#extensions");
        if (field.parent().hasClass('bootstrap-select')) {
          field.selectpicker('destroy');
          field = $("#extensions");
        }
        field.replaceWith(response);
        if ($("#extensions").is('select')) {
          $("#extensions").selectpicker();
        }
        toggle_extensions_field();
      }
    });
  }

  function toggle_extensions_field() {
    var field = $("#extensions");
    if (field.length === 0) {
      return;
    }
    var target = field.parent().hasClass('bootstrap-select') ? field.parent() : field;
    if ($("select[name='extensions[operator]']").val() == "1") {
      target.hide();
    } else {
      target.show();
    }
  }
  function validate_did_batch_destination() {
    if ($("select[name='extensions[operator]']").val() != "2") {
      return "";
    }

    var destination = $.trim($("#extensions").val());

    if (destination === "") {
      return "<?php echo gettext('Enter the destination or set the operator to Preserve.'); ?>";
    }
    if (destination.length > 180) {
      return "<?php echo gettext('The destination must be at most 180 characters long.'); ?>";
    }

    switch ($("#call_type").val()) {
      case "1":
        return /^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+(:\d{1,5})?$/.test(destination) ? ""
          : "<?php echo gettext('Invalid destination: use the format number@host or number@host:port.'); ?>";
      case "2":
        return is_valid_ip_destination(destination) ? ""
          : "<?php echo gettext('Invalid destination: enter an IP address or IP:port.'); ?>";
      case "4":
        return /^\+?[0-9]+$/.test(destination) ? ""
          : "<?php echo gettext('Invalid destination: enter digits only, optionally starting with +.'); ?>";
      default:
        return "";
    }
  }

  function is_valid_ip_destination(destination) {
    var parts = destination.split(":");
    if (parts.length > 2) {
      return false;
    }
    if (parts.length == 2) {
      var port = parts[1];
      if (!/^\d{1,5}$/.test(port) || parseInt(port, 10) < 1 || parseInt(port, 10) > 65535) {
        return false;
      }
    }
    var octets = parts[0].split(".");
    if (octets.length != 4) {
      return false;
    }
    for (var i = 0; i < octets.length; i++) {
      if (!/^\d{1,3}$/.test(octets[i]) || parseInt(octets[i], 10) > 255) {
        return false;
      }
    }
    return true;
  }

  function account_change(val) {
    $.ajax({
      type: "POST",
      url: "<?= base_url() ?>/accounts/customer_account_change/" + val,
      data: '',
      success: function (alt) {
        $("#accountid").html(alt);
      }
    });
  }
</script>
<style>
  #err {
    height: 20px !important;
    width: 100% !important;
    float: left;
  }

  label.error {
    float: left;
    color: red;
    padding-left: .3em;
    vertical-align: top;
    padding-left: 0px;
    margin-top: -10px;
    width: 100% !important;
  }
</style>
<? endblock() ?>
<? startblock('page-title') ?>
<?= $page_title ?>
<? endblock() ?>
<? startblock('content') ?>

<section class="slice color-three">
  <div class="w-section inverse p-0">
    <div class="col-12">
      <div class="portlet-content mb-4" id="search_bar" style="display: none">
        <?php echo $form_search; ?>
      </div>
    </div>
  </div>
</section>

<?php
if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
  $permissioninfo = $this->session->userdata('permissioninfo');
  $logintype = $this->session->userdata('logintype');
  $ret_url = '';
  ?>


  <div class="main-wrapper">
    <div id="content" class="container-fluid">
      <div class="row">
        <div class="p-4 col-md-12">
          <div class="my-4 slice color-three float-left content_border col-md-12" id="search_generate_bar"
            style="display:none;cursor: pointer;">
            <div id="floating-label" class="card pb-4">
              <form class="row px-4" id="purchase_did_form" name='purchase_did_form' method="post"
                action="<?= base_url() ?>did/did_reseller_purchase/" enctype="multipart/form-data">
                <div class="col-md-4">
                  <div class='col-md-12 form-group p-0'>
                    <label class="control-label"><?php echo gettext('Available DIDs');?> :</label>
                    <? echo $didlist; ?>
                  </div>
                  <span id="err"></span>
                </div>
                <div class="col-md-4">
                  <div class='col-md-12 form-group p-0'>
                    <label class="control-label"><?php echo gettext('Accounts');?> :</label>
                    <?php
                    $where = array(
                      "status" => 0,
                      "deleted" => 0,
                      "type" => 0,
                      "reseller_id" => $account_id
                    );
                    $account_arr = array(
                      "id" => "account_id",
                      "name" => "account_id",
                      "class" => "account_id"
                    );
                    $account = form_dropdown_all($account_arr, $this->db_model->build_concat_dropdown("id,first_name,last_name,number", "accounts", "", $where), '');

                    echo $account;
                    ?>
                  </div>
                  <span id="err"></span>
                </div>
                <div class="col-md-12">
                  <center>
                    <input class="margin-l-20 btn btn-success btn-lg" name="action" value="Purchase DID" type="submit">
                  </center>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php } ?>

<section class="slice color-three">
  <div class="w-section inverse p-0">
    <div class="col-12">
      <div class="portlet-content mb-4" id="search_bar" style="cursor: pointer; display: none">
        <?php echo $form_search; ?>
      </div>
    </div>
  </div>
</section>
<section class="slice color-three">
  <div class="w-section inverse p-0">

    <div class="col-12">
      <span id="error_msg" class="text-danger"></span>
      <div class="portlet-content mb-4" id="update_bar" style="display: none;">
        <?php echo $form_batch_update; ?>
      </div>
    </div>
  </div>
</section>
<section class="slice color-three pb-4">
  <div class="w-section inverse p-0">
    <?php if (($logintype == 1) && (isset($permissioninfo['did']['did_list']['purchase']) and $permissioninfo['did']['did_list']['purchase'] == 0)) { ?>
      <form method="POST" action="<?php echo base_url(); ?>did/did_available_list/" enctype="multipart/form-data" id="">
        <input type="submit" class="btn btn-info mb-4" name="purchase_did" value=<?php echo gettext("Buy DIDs") ?>
        id="buy_did">
      </form>
    <?php } ?>
    <div class="card col-md-12 pb-4">
      <table id="did_grid" align="left" style="display: none;"></table>
    </div>
  </div>
</section>
<? endblock() ?>
<? end_extend() ?>