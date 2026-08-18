<? extend('master.php') ?>

<? startblock('extra_head') ?>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/chart/highcharts.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/chart/exporting.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/chart/highcharts-3d.js"></script>

<style>
.second{
	color: black;
	opacity: 0.4;
	border: 0;
	width: 100%;
	padding: 100px 0;
	margin: 5px;
	display: relative;
	text-align: center;
	font-size: 250%;
}

.ball-clip-rotate > div {
    display: inline-block;
    -webkit-animation: 1s ease-in-out 0s normal none infinite running spin-rotate;
    animation: 1s ease-in-out 0s normal none infinite running spin-rotate;
    background-color: #fff;
    width: 12px;
    height: 14px;
    border-radius: 100%;
    margin: 2px;
    -webkit-animation-fill-mode: both;
    animation-fill-mode: both;
    border: 0 solid #fff;
    border-bottom-color: transparent;
    background: url('<?= base_url() ?>assets/images/loder-1.png') !important;
}

@-webkit-keyframes spin-rotate {
    0% {
        -webkit-transform: rotate(0deg);
        transform: rotate(0deg);
    }

    to {
        -webkit-transform: rotate(1turn);
        transform: rotate(1turn);
    }
}

@keyframes spin-rotate {
    0% {
        -webkit-transform: rotate(0deg);
        transform: rotate(0deg);
    }

    to {
        -webkit-transform: rotate(1turn);
        transform: rotate(1turn);
    }
}
</style>

<script type="text/javascript">
    Highcharts.setOptions({
        chart: {
            style: {
                fontFamily: 'inherit',
                fontSize: '13px'
            }
        },
        title: {
            style: { fontSize: '14px' }
        },
        subtitle: {
            style: { fontSize: '13px' }
        },
        xAxis: {
            labels: { style: { fontSize: '13px' } },
            title: { style: { fontSize: '13px' } }
        },
        yAxis: {
            labels: { style: { fontSize: '13px' } },
            title: { style: { fontSize: '13px' } }
        },
        legend: {
            itemStyle: {
                fontSize: '13px',
                fontWeight: 'normal'
            }
        },
        tooltip: {
            style: { fontSize: '13px' }
        },
        accessibility: {
            enabled: false
        },
        plotOptions: {
            series: {
                dataLabels: {
                    style: {
                        fontSize: '12px',
                        fontWeight: 'normal',
                        textOutline: 'none'
                    }
                }
            }
        }
    });

    var monthNames = [
        "<?php echo gettext('January'); ?>",
        "<?php echo gettext('February'); ?>",
        "<?php echo gettext('March'); ?>",
        "<?php echo gettext('April'); ?>",
        "<?php echo gettext('May'); ?>",
        "<?php echo gettext('June'); ?>",
        "<?php echo gettext('July'); ?>",
        "<?php echo gettext('August'); ?>",
        "<?php echo gettext('September'); ?>",
        "<?php echo gettext('October'); ?>",
        "<?php echo gettext('November'); ?>",
        "<?php echo gettext('December'); ?>"
    ];

    function get_period_label(drop_val) {
        if (drop_val === 't_week') {
            return '<?php echo gettext("This Week"); ?>';
        }
        var period = get_selected_period();
        return monthNames[parseInt(period.month, 10) - 1] + ' ' + period.year;
    }

    function get_selected_period() {
        var monthYear = $("#month_year_dropdown").val();

        if (monthYear) {
            var parts = monthYear.split('#');
            return {
                month: parts[0],
                year: parts[1]
            };
        }

        var currentDate = new Date();
        return {
            month: String(currentDate.getMonth() + 1).padStart(2, '0'),
            year: String(currentDate.getFullYear())
        };
    }

    function refresh_dashboard(drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;

        var radiobuttonvalue = $("input[name='calls_pie_chart']:checked").val();
        var radiobuttonvaluecountry = $("input[name='country_pie_chart']:checked").val();

        $('.period_label').text(get_period_label(drop_val));

        get_account_count(drop_val, year, month);
        get_total_calls(drop_val, year, month);
        get_orders_count(drop_val, year, month);
        get_orders_fail_count(drop_val, year, month);
        get_refill_value(drop_val, year, month);
        get_customer_report(drop_val, year, month);

        build_recharge_graph(drop_val);
        build_call_graph(radiobuttonvalue, drop_val);
        build_country_graph(radiobuttonvaluecountry, drop_val);
        build_error_code_graph(drop_val);
        build_ddd_graph(drop_val);
        build_trunk_stats(drop_val);
    }
    
    function build_recharge_graph(drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;
    
        $.ajax({
            type: 'POST',
            dataType: 'json',
            async: false,
            url: "<?php echo base_url(); ?>dashboard/customerReport_call_statistics_with_profit/",
            data: { month: month, year: year, drop_val: drop_val },
            beforeSend: function() {
                $("#call-graph")
                    .append('<div class="loading col-md-offset-6"><div class="ball-clip-rotate"><div></div></div></div>')
                    .show();
            },
            complete: function() {
                $("#call-graph").empty().hide();
            },
            success: function(response_data) {
                $("#call-graph").hide();
    
                var weekDays = [
                    '<?php echo gettext("Sun"); ?>',
                    '<?php echo gettext("Mon"); ?>',
                    '<?php echo gettext("Tue"); ?>',
                    '<?php echo gettext("Wed"); ?>',
                    '<?php echo gettext("Thu"); ?>',
                    '<?php echo gettext("Fri"); ?>',
                    '<?php echo gettext("Sat"); ?>'
                ];
    
                var monthNames = [
                    '<?php echo gettext("Jan"); ?>', '<?php echo gettext("Feb"); ?>',
                    '<?php echo gettext("Mar"); ?>', '<?php echo gettext("Apr"); ?>',
                    '<?php echo gettext("may"); ?>', '<?php echo gettext("Jun"); ?>',
                    '<?php echo gettext("Jul"); ?>', '<?php echo gettext("Aug"); ?>',
                    '<?php echo gettext("Sep"); ?>', '<?php echo gettext("Oct"); ?>',
                    '<?php echo gettext("Nov"); ?>', '<?php echo gettext("Dec"); ?>'
                ];
    
                var xCategories = [];
                var monthIndex = parseInt(month, 10) - 1;
    
                if (drop_val == 't_week') {
                    $.each(response_data.date, function(i, day) {
                        var dt = new Date(year, monthIndex, parseInt(day, 10));
                        xCategories.push(weekDays[dt.getDay()] + ' ' + day);
                    });
                } else {
                    var monthAbbr = monthNames[monthIndex];
                    $.each(response_data.date, function(i, day) {
                        xCategories.push(day + '/' + monthAbbr);
                    });
                }
    
                Highcharts.chart('call_graph_data', {
                    chart: {
                        zoomType: 'xy',
                        styledMode: false
                    },
                    credits: {
                        enabled: false
                    },
                    title: {
                        text: ''
                    },
                    xAxis: [{
                        categories: xCategories,
                        labels: {
                            rotation: (drop_val == 't_month' && response_data.date.length > 15) ? -45 : 0,
                            style: { fontSize: '11px' }
                        }
                    }],
                    yAxis: [
                        {
                            min: 0,
                            title: { text: ' ' }
                        },
                        {
                            min: 0,
                            opposite: true,
                            title: { text: '<?php echo gettext("Profit per day"); ?>' }
                        }
                    ],
                    tooltip: {
                        backgroundColor: '#FEFEC5',
                        borderColor: 'black',
                        borderRadius: 10,
                        borderWidth: 2,
                        formatter: function() {
                            if (this.series.name == '<?php echo gettext("Total Calls"); ?>') {
                                var idx = this.point.index;
                                return '<b>Total : </b>' + this.y +
                                    '<br/><b>ACD : </b>' + (response_data.acd[idx] ? response_data.acd[idx][1] : 0) +
                                    '<br/><b>MCD : </b>' + (response_data.mcd[idx] ? response_data.mcd[idx][1] : 0) +
                                    '<br/><b>ASR : </b>' + (response_data.asr[idx] ? response_data.asr[idx][1] : 0);
                            }
                            return this.series.name + ': <b>' + this.y + '</b>';
                        }
                    },
                    legend: {
                        layout: 'horizontal',
                        align: 'center',
                        x: 0,
                        verticalAlign: 'top',
                        y: 0,
                        backgroundColor: '#EFEFEF'
                    },
                    series: [
                        {
                            name: '<?php echo gettext("Total Calls"); ?>',
                            type: 'column',
                            color: '#6E8CD7',
                            yAxis: 0,
                            data: response_data.total
                        },
                        {
                            name: '<?php echo gettext("Answered Calls"); ?>',
                            type: 'spline',
                            color: '#34D3EB',
                            yAxis: 0,
                            data: response_data.answered,
                            marker: { enabled: true }
                        },
                        {
                            name: '<?php echo gettext("Failed Calls"); ?>',
                            type: 'spline',
                            color: Highcharts.getOptions().colors[2],
                            yAxis: 0,
                            data: response_data.failed,
                            marker: { enabled: true }
                        },
                        {
                            name: '<?php echo gettext("Profit"); ?>',
                            type: 'spline',
                            yAxis: 1,
                            color: '#008000',
                            data: response_data.profit,
                            marker: { enabled: true },
                            dashStyle: 'shortdot'
                        }
                    ]
                });
            }
        });
    }

    function get_account_count(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/account_count",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#accounts_count").html(response_data.count);
                $("#today_accounts_count").html(response_data.count);
            }
        });
    }

    function get_total_calls(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/call_count",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#call_count").html(response_data.total_calls);
                $("#today_call_count").html(response_data.total_calls);
            }
        });
    }

    function get_orders_count(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/orders_count",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#order_count").html(response_data.count);
                $("#today_order_count").html(response_data.count);
            }
        });
    }

    function get_orders_fail_count(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/orders_fail_count",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#order_fail_count").html(response_data.count);
                $("#today_order_fail_count").html(response_data.count);
            }
        });
    }

    function get_refill_value(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/getrefill_value",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#refill_value").html(response_data.total_refill_amount);
                $("#today_refill_value").html(response_data.total_refill_amount);
            }
        });
    }

    function get_customer_report(drop_val, year, month) {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/customerReport_calculation",
            dataType: 'JSON',
            data: { drop_val: drop_val, year: year, month: month },
            success: function(response_data) {
                $("#period_label_summary").text(get_period_label(drop_val));
                $("#asr_today").html(response_data.ASR);
                $("#asr_month").html(response_data.ASR_month);
                $("#acd_today").html(response_data.ACD);
                $("#acd_month").html(response_data.ACD_month);
                $("#mcd_today").html(response_data.mcd);
                $("#mcd_month").html(response_data.mcd_month);
                $("#total_calls_today").html(response_data.total_calls);
                $("#total_calls_month").html(response_data.total_calls_month);
                $("#debit_today").html(response_data.total_debit);
                $("#debit_month").html(response_data.total_debit_month);
                $("#cost_today").html(response_data.total_cost);
                $("#cost_month").html(response_data.total_cost_month);
                $("#profit_today").html(response_data.profit);
                $("#profit_month").html(response_data.profit_month);
            }
        });
    }

    function build_trunk_stats(drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;

        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/get_trunk_stats/",
            dataType: 'JSON',
            data: { year: year, month: month, drop_val: drop_val },
            beforeSend: function() {
                $("#trunk-loading")
                    .append('<div class="loading col-md-offset-6"><div class="ball-clip-rotate"><div></div></div></div>')
                    .show();
            },
            complete: function() {
                $("#trunk-loading").hide();
            },
            success: function(data) {
                var tbody = $("#trunk_stats_body");
                if (!data || data.length === 0) {
                    tbody.html('<tr><td colspan="4" class="text-center text-muted"><i class="fa fa-meh-o"></i> <?php echo gettext("No Records Found"); ?></td></tr>');
                    return;
                }

                var rows = '';
                $.each(data, function(i, row) {
                    rows += '<tr>' +
                        '<td>' + row.trunk + '</td>' +
                        '<td>' + row.attempts + '</td>' +
                        '<td>' + row.completed + '</td>' +
                        '<td>' + row.asr + '</td>' +
                        '</tr>';
                });

                tbody.html(rows);
            }
        });
    }

    function build_call_graph(url, drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;

        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/customerReport_maximum_call" + url + "/",
            dataType: 'JSON',
            async: false,
            data: { year: year, month: month, drop_val: drop_val },
            beforeSend: function() {
                $("#call-count")
                    .append('<div class="loading col-md-offset-6"><div class="ball-clip-rotate"><div></div></div></div>')
                    .show();
            },
            complete: function() {
                $("#call-count").empty().hide();
            },
            success: function(response_data) {
                var radiobuttonvalue = $("input[name='calls_pie_chart']:checked").val();

                $("#bygraph").val(
                    radiobuttonvalue == 'count'
                        ? "<?php echo gettext('By Calls'); ?>"
                        : "<?php echo gettext('By Minutes'); ?>"
                );

                $("#call-count").hide();

                if (response_data == '') {
                    $("div.call_count_data").hide();
                    $("div.call_count_not_data")
                        .addClass("second")
                        .show()
                        .html('<i class="fa fa-meh-o"></i> <?php echo gettext("No Records Found"); ?>');
                } 
                else {
                    $("div.call_count_not_data").hide();
                    $("div.call_count_data").show();

                    Highcharts.chart('call_count_data', {
                        chart: {
                            type: 'pie',
                            options3d: {
                                enabled: true,
                                alpha: 45,
                                beta: 0,
                                depth: 25,
                                viewDistance: 25
                            }
                        },
                        credits: { enabled: false },
                        title: { text: "" },
                        subtitle: { text: '' },
                        tooltip: {
                            backgroundColor: '#FEFEC5',
                            borderColor: 'black',
                            borderRadius: 10,
                            borderWidth: 2,
                            formatter: function() {
                                return this.point.name + ': <b>' + this.y + '</b>';
                            }
                        },
                        plotOptions: {
                            pie: {
                                allowPointSelect: true,
                                cursor: 'pointer',
                                depth: 25,
                                dataLabels: { enabled: false },
                                showInLegend: true
                            }
                        },
                        series: [{
                            name: '',
                            data: response_data
                        }]
                    });
                }
            }
        });
    }

    function build_country_graph(url, drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;

        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/customerReport_maximum_country" + url + "/",
            dataType: 'JSON',
            data: { year: year, month: month, drop_val: drop_val },
            beforeSend: function() {
                $("#country-count")
                    .append('<div class="loading col-md-offset-6"><div class="ball-clip-rotate"><div></div></div></div>')
                    .show();
            },
            complete: function() {
                $("#country-count").empty().hide();
            },
            success: function(response_data) {
                var radiobuttonvaluecountry = $("input[name='country_pie_chart']:checked").val();
                var graphtip;

                if (radiobuttonvaluecountry == 'count') {
                    $("#bygraphcountry").val("<?php echo gettext('By Calls'); ?>");
                    graphtip = "<?php echo gettext('Calls'); ?>";
                } else {
                    $("#bygraphcountry").val("<?php echo gettext('By Minutes'); ?>");
                    graphtip = "<?php echo gettext('Minutes'); ?>";
                }

                $("#country-count").hide();

                if (response_data == '') {
                    $("div.country_count_data").hide();
                    $("div.country_not_data")
                        .addClass("second")
                        .show()
                        .html('<i class="fa fa-meh-o"></i> <?php echo gettext("No Records Found"); ?>');
                } else {
                    $("div.country_not_data").hide();
                    $("div.country_count_data").show();

                    Highcharts.chart('country_count_data', {
                        chart: { styledMode: false },
                        credits: { enabled: false },
                        title: { text: "" },
                        subtitle: { text: '' },
                        tooltip: {
                            backgroundColor: '#FEFEC5',
                            borderColor: 'black',
                            borderRadius: 10,
                            borderWidth: 2,
                            formatter: function() {
                                return '<?php echo gettext("Total"); ?> ' + this.point.name + ' ' + graphtip + ': <b>' + this.y + '</b>';
                            }
                        },
                        plotOptions: {
                            pie: {
                                allowPointSelect: true,
                                cursor: 'pointer',
                                depth: 25,
                                dataLabels: { enabled: false },
                                showInLegend: true
                            }
                        },
                        series: [{
                            type: 'pie',
                            name: '',
                            data: response_data
                        }]
                    });
                }
            }
        });
    }

    function load_accounts_filter() {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/get_accounts_list",
            dataType: 'JSON',
            success: function(data) {
                if (data.length > 0) {
                    var options = '<option value="0"><?php echo gettext("All Accounts"); ?></option>';

                    $.each(data, function(i, item) {
                        options += '<option value="' + item.id + '">' + item.label + '</option>';
                    });

                    $('#error_code_account_filter').html(options);
                    $('#ddd_account_filter').html(options);
                    $('#error_code_account_filter, #ddd_account_filter').selectpicker('refresh');
                }
            }
        });
    }

    function build_error_code_graph(drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;
        var account_id = $("#error_code_account_filter").val() || 0;

        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/customerReport_error_codes/",
            dataType: 'JSON',
            data: { year: year, month: month, drop_val: drop_val, account_id: account_id },
            beforeSend: function() {
                $("#error-code-loading").show();
            },
            complete: function() {
                $("#error-code-loading").hide();
            },
            success: function(response_data) {
                if (!response_data || response_data.length === 0 || response_data[0].length === 0) {
                    $("div.error_code_data").hide();
                    $("div.error_code_not_data")
                        .addClass("second")
                        .show()
                        .html('<i class="fa fa-meh-o"></i> <?php echo gettext("No Records Found"); ?>');
                    return;
                }

                $("div.error_code_not_data").hide();
                $("div.error_code_data").show();

                var total = 0;
                var pie_data = [];

                $.each(response_data, function(i, row) {
                    if (row.length === 2) {
                        total += row[1];
                        pie_data.push({ name: row[0], y: row[1] });
                    }
                });

                Highcharts.chart('error_code_chart_data', {
                    chart: {
                        type: 'pie',
                        options3d: { enabled: true, alpha: 45 }
                    },
                    title: { text: '' },
                    credits: { enabled: false },
                    exporting: { enabled: false },
                    tooltip: {
                        pointFormat: '<b>{point.name}</b><br/><?php echo gettext("Calls"); ?>: <b>{point.y}</b> ({point.percentage:.1f}%)'
                    },
                    plotOptions: {
                        pie: {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            depth: 35,
                            dataLabels: {
                                enabled: true,
                                distance: 12,
                                style: {
                                    fontSize: '11px',
                                    fontWeight: 'normal',
                                    textOutline: 'none',
                                    color: '#444'
                                },
                                formatter: function() {
                                    if (this.percentage < 5) {
                                        return null;
                                    }

                                    return Highcharts.numberFormat(this.y, 0) +
                                        '<br/>' + Highcharts.numberFormat(this.percentage, 1) + '%';
                                }
                            },
                            showInLegend: true
                        }
                    },
                    legend: {
                        enabled: true,
                        layout: 'vertical',
                        align: 'right',
                        verticalAlign: 'middle',
                        itemStyle: {
                            fontSize: '11px',
                            fontWeight: 'normal',
                            color: '#333'
                        },
                        itemHoverStyle: { color: '#000' },
                        symbolRadius: 3,
                        maxHeight: 200,
                        navigation: {
                            style: { fontSize: '11px' }
                        },
                        labelFormatter: function() {
                            var pct = total > 0 ? Highcharts.numberFormat((this.y / total) * 100, 1) : 0;

                            return this.name +
                                ' <span style="color:#999">' +
                                Highcharts.numberFormat(this.y, 0) +
                                ' (' + pct + '%)</span>';
                        }
                    },
                    series: [{
                        name: '<?php echo gettext("Calls"); ?>',
                        data: pie_data
                    }]
                });
            }
        });
    }

    function build_ddd_graph(drop_val) {
        var period = get_selected_period();
        var year = period.year;
        var month = period.month;
        var account_id = $("#ddd_account_filter").val() || 0;

        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/customerReport_ddd_stats/",
            dataType: 'JSON',
            data: { year: year, month: month, drop_val: drop_val, account_id: account_id },
            beforeSend: function() {
                $("#ddd-loading").show();
            },
            complete: function() {
                $("#ddd-loading").hide();
            },
            success: function(response_data) {
                if (!response_data || response_data.length === 0 || response_data[0].length === 0) {
                    $("div.ddd_data").hide();
                    $("div.ddd_not_data")
                        .addClass("second")
                        .show()
                        .html('<i class="fa fa-meh-o"></i> <?php echo gettext("No Records Found"); ?>');
                    return;
                }

                $("div.ddd_not_data").hide();
                $("div.ddd_data").show();

                var categories = [];
                var values = [];
                var total = 0;

                $.each(response_data, function(i, row) {
                    if (row.length === 2) {
                        categories.push(row[0]);
                        values.push(row[1]);
                        total += row[1];
                    }
                });

                var max_val = Math.max.apply(null, values);
                var chart_data = $.map(values, function(v) {
                    var intensity = 0.35 + (0.65 * (v / max_val));
                    return {
                        y: v,
                        color: 'rgba(52, 152, 219, ' + intensity.toFixed(2) + ')'
                    };
                });

                Highcharts.chart('ddd_chart_data', {
                    chart: {
                        type: 'column',
                        backgroundColor: '#ffffff',
                        style: { fontFamily: 'inherit' },
                        zoomType: 'xy',
                        spacingTop: 15,
                        spacingBottom: 15
                    },
                    title: { text: '' },
                    credits: { enabled: false },
                    exporting: { enabled: false },
                    xAxis: {
                        categories: categories,
                        title: {
                            text: '<?php echo gettext("DDD"); ?>',
                            style: { fontSize: '12px', color: '#666' }
                        },
                        labels: {
                            style: { fontSize: '11px', color: '#444' }
                        },
                        lineColor: '#e0e0e0',
                        tickColor: '#e0e0e0',
                        gridLineColor: '#f5f5f5'
                    },
                    yAxis: {
                        min: 0,
                        title: {
                            text: '<?php echo gettext("Calls"); ?>',
                            style: { fontSize: '12px', color: '#666' }
                        },
                        labels: {
                            style: { fontSize: '11px', color: '#444' }
                        },
                        gridLineColor: '#f0f0f0',
                        gridLineDashStyle: 'ShortDash'
                    },
                    tooltip: {
                        useHTML: true,
                        backgroundColor: '#fff',
                        borderColor: '#ddd',
                        borderRadius: 6,
                        borderWidth: 1,
                        shadow: true,
                        style: { fontSize: '12px' },
                        formatter: function() {
                            var pct = total > 0 ? Highcharts.numberFormat((this.y / total) * 100, 1) : 0;
                            var rank = $.inArray(this.y, values.slice().sort(function(a, b) {
                                return b - a;
                            })) + 1;

                            return '<div style="min-width:160px">' +
                                '<span style="font-weight:bold;font-size:14px">DDD ' + this.point.category + '</span><br/>' +
                                '<span style="color:#666"><?php echo gettext("Calls"); ?>: </span><b>' + Highcharts.numberFormat(this.y, 0) + '</b><br/>' +
                                '<span style="color:#666"><?php echo gettext("Share"); ?>: </span><b>' + pct + '%</b><br/>' +
                                '<span style="color:#666"><?php echo gettext("Total"); ?>: </span><b>' + Highcharts.numberFormat(total, 0) + '</b><br/>' +
                                '<span style="color:#666"><?php echo gettext("Rank"); ?>: </span><b>#' + rank + '</b>' +
                                '</div>';
                        }
                    },
                    plotOptions: {
                        column: {
                            borderRadius: 3,
                            borderWidth: 0,
                            dataLabels: {
                                enabled: true,
                                style: {
                                    fontSize: '10px',
                                    fontWeight: 'normal',
                                    textOutline: 'none',
                                    color: '#444'
                                },
                                formatter: function() {
                                    if (this.y === 0) {
                                        return null;
                                    }

                                    return Highcharts.numberFormat(this.y, 0);
                                }
                            },
                            states: {
                                hover: { brightness: 0.1 }
                            }
                        }
                    },
                    legend: { enabled: false },
                    responsive: {
                        rules: [{
                            condition: { maxWidth: 500 },
                            chartOptions: {
                                xAxis: { labels: { rotation: -45 } },
                                plotOptions: { column: { dataLabels: { enabled: false } } }
                            }
                        }]
                    },
                    series: [{
                        name: '<?php echo gettext("Calls"); ?>',
                        data: chart_data
                    }]
                });
            }
        });
    }

    function build_today_result() {
        $.ajax({
            type: 'POST',
            url: "<?php echo base_url(); ?>dashboard/get_today_result",
            dataType: 'JSON',
            success: function(response_data) {
                $("#today_refill_value").html(response_data.today_refill_amount);
                $("#today_order_count").html(response_data.today_order_count);
                $("#today_order_fail_count").html(response_data.today_order_fail_count);
                $("#today_accounts_count").html(response_data.today_account_count);
                $("#today_call_count").html(response_data.today_total_calls);
            }
        });
    }

    $(document).ready(function() {
        $(".button_notification").click(function() {
            if (!$(this).hasClass('button_clicked')) {
                $(this).addClass("button_clicked");
                var accountid = $(this).val();

                $.ajax({
                    type: 'POST',
                    url: "<?php echo base_url(); ?>dashboard/send_notification/",
                    data: "accountid=" + accountid,
                    success: function(response) {
                        $('.button_clicked').attr("disabled", true);
                        $("#toast-container").css("display", "block");

                        setTimeout(function() {
                            $('.button_notification').attr("disabled", false);
                        }, 3000);

                        $(".toast-message").html("Notification Send Successfully");
                        $('.toast-top-right').delay(5000).fadeOut();
                        $(".button_notification").removeClass("button_clicked");
                    }
                });
            }
        });

        $('.selectpicker').selectpicker();

        var period = get_selected_period();
        $("#month_year_name").html(monthNames[parseInt(period.month, 10) - 1]);

        load_accounts_filter();
        refresh_dashboard('t_week');

        $('input[name=calls_pie_chart]').change(function() {
            var radiobuttonvalue = $("input[name='calls_pie_chart']:checked").val();
            var drop_val = $("#today_dropdown").val();
            build_call_graph(radiobuttonvalue, drop_val);
        });

        $('input[name=country_pie_chart]').change(function() {
            var radiobuttonvaluecountry = $("input[name='country_pie_chart']:checked").val();
            var drop_val = $("#today_dropdown").val();
            build_country_graph(radiobuttonvaluecountry, drop_val);
        });

        $("#month_year_dropdown").change(function() {
            var items = $(this).val().split('#');
            $("#month_year_name").html(monthNames[parseInt(items[0], 10) - 1]);
            refresh_dashboard('t_month');
        });

        $("#today_dropdown").change(function() {
            var period = get_selected_period();
            $("#month_year_name").html(monthNames[parseInt(period.month, 10) - 1]);
            refresh_dashboard($(this).val());

            if (this.value == 't_month') {
                $(".year_select").removeClass("d-none");
            } else {
                $(".year_select").addClass("d-none");
            }
        });

        $(document).on('change', '#error_code_account_filter', function() {
            build_error_code_graph($("#today_dropdown").val());
        });

        $(document).on('change', '#ddd_account_filter', function() {
            build_ddd_graph($("#today_dropdown").val());
        });
    });
</script>
<? endblock() ?>

<?php startblock('page-title') ?>
    <?php echo $page_title; ?>
<?php endblock() ?>

<? startblock('content') ?>

<?php
function uptime()
{
    if (PHP_OS == "Linux") {
        $uptime = @file_get_contents("/proc/uptime");

        if ($uptime !== false) {
            $uptime = explode(" ", $uptime);
            $uptime = $uptime[0];
            $days = explode(".", (($uptime % 31556926) / 86400));
            $hours = explode(".", ((($uptime % 31556926) % 86400) / 3600));
            $minutes = explode(".", (((($uptime % 31556926) % 86400) % 3600) / 60));
            $time = ".";

            if ($minutes > 0) {
                $time = $minutes[0] . " " . gettext("mins") . $time;
            }
            if ($minutes > 0 && ($hours > 0 || $days > 0)) {
                $time = ", " . $time;
            }
            if ($hours > 0) {
                $time = $hours[0] . " " . gettext("hours") . $time;
            }
            if ($hours > 0 && $days > 0) {
                $time = ", " . $time;
            }
            if ($days > 0) {
                $time = $days[0] . " " . gettext("days") . $time;
            }
        } else {
            $time = false;
        }
    } else {
        $time = false;
    }

    return $time;
}

function get_server_memory_usage()
{
    $free = shell_exec('free');
    $free = (string) trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $memory_usage = $mem[2] / $mem[1] * 100;

    return $memory_usage;
}

function get_server_cpu_usage()
{
    $output = shell_exec('cat /proc/loadavg');
    $loadavg = substr($output, 0, strpos($output, " "));

    return $loadavg;
}

function byteFormat($bytes, $unit = "", $decimals = 2)
{
    $units = array(
        'B'  => 0,
        'KB' => 1,
        'MB' => 2,
        'GB' => 3,
        'TB' => 4,
        'PB' => 5,
        'EB' => 6,
        'ZB' => 7,
        'YB' => 8
    );

    $value = 0;

    if ($bytes > 0) {
        if (!array_key_exists($unit, $units)) {
            $pow = floor(log($bytes) / log(1024));
            $unit = array_search($pow, $units);
        }

        $value = ($bytes / pow(1024, floor($units[$unit])));
    }

    if (!is_numeric($decimals) || $decimals < 0) {
        $decimals = 2;
    }

    return sprintf('%.' . $decimals . 'f ' . $unit, $value);
}

function get_memory()
{
    $memory = shell_exec("free -m");
    $array_memory = explode("\n", $memory);
    $mem = explode(":", $array_memory[1]);
    $mem[1] = trim(preg_replace('/\s\s+/', ' ', $mem[1]));
    $mem1 = explode(" ", $mem[1]);

    return $mem1;
}

function get_swap_memory()
{
    $memory = shell_exec("free -m");
    $array_memory = explode("\n", $memory);
    $mem = explode(":", $array_memory[2]);
    $mem[1] = trim(preg_replace('/\s\s+/', ' ', $mem[1]));
    $mem1 = explode(" ", $mem[1]);

    return $mem1;
}

function get_harddisk()
{
    $memory = shell_exec("df -h");
    $array_memory = explode("\n", $memory);
    $memory_used = array();

    foreach ($array_memory as $key => $val) {
        $val = trim(preg_replace('/\s\s+/', ' ', $val));
        $mem1 = explode(" ", $val);

        if ($mem1[count($mem1) - 1] == '/') {
            $memory_used['used'] = $mem1[count($mem1) - 4];
            $memory_used['total'] = $mem1[count($mem1) - 5];
            $memory_used['avail'] = $mem1[count($mem1) - 3];
        }
    }

    return $memory_used;
}

function get_cpu_cores()
{
    return shell_exec("nproc --all");
}

function getOS()
{
    $os_platform = shell_exec("hostnamectl | grep 'Operating'");
    $array_memory = explode("\n", $os_platform);
    $os_platform = explode(":", $array_memory[0]);

    if (!empty($os_platform)) {
        return $os_platform[1];
    }

    return '';
}

function getArchitecture()
{
    $ar_platform = shell_exec("hostnamectl | grep 'Architecture'");
    $array_memory = explode("\n", $ar_platform);
    $ar_platform = explode(":", $array_memory[0]);

    if (!empty($ar_platform)) {
        return $ar_platform[1];
    }

    return '';
}

function getStatichost()
{
    $hostname_platform = shell_exec("hostnamectl | grep 'Static hostname'");
    $array_memory = explode("\n", $hostname_platform);
    $hostname_platform = explode(":", $array_memory[0]);

    if (!empty($hostname_platform)) {
        return $hostname_platform[1];
    }

    return '';
}

function get_kernel_version()
{
    $kernel = explode(' ', file_get_contents('/proc/version'));
    return $kernel[2];
}

function create_formatted_date($startdate, $enddate, $timezone, $timevisibly)
{
    $startdate = strtotime($startdate);
    $enddate = strtotime($enddate);

    $startonlydate = date('Y-m-d', $startdate);
    $endonlydate = date('Y-m-d', $enddate);

    if (strtotime($startonlydate) == strtotime($endonlydate)) {
        if ($timevisibly == 0) {
            $output = date('d M Y', $startdate);
        } else {
            $output = date('d M-Y', $startdate) . ' ' . date('H:i A', $startdate) . ' - ' . date('H:i A', $startdate) . " [" . $timezone . "]";
        }
    } else {
        if ($timevisibly == 0) {
            $output = date('d M Y', $startdate) . " - " . date('d M Y', $enddate);
        } else {
            $output = date('d M-Y H:i A', $startdate) . ' - ' . date('d M-Y H:i A', $enddate) . " [" . $timezone . "]";
        }
    }

    return $output;
}
?>

<section class="slice p-0">
    <div class="w-section inverse p-0">
        <div class="">
            <div class="row">
                <div class="col-sm-12 mb-3">
                    <div class="col-lg-9 col-md-8 p-0 float-left card border call_graph_box">
                        <h3 class="text-dark float-left col-sm-12 p-3">
                            <div class="p-0 float-left">
                                <div style="margin-top:3px;">
                                    <i class="fa fa-bar-chart-o text-primary fa-fw"></i>
                                    <?php echo gettext("Call Stat"); ?>
                                </div>
                            </div>

                            <div class="float-right col-lg-8 col-md-9 p-0" id="floating-label">
                                <div class="today_dd col-md-3 float-right pb-md-0">
                                    <select id="today_dropdown" name="today" class="form-control selectpicker m-0" style="z-index:9;">
                                        <option value="t_week"><?php echo gettext("This Week"); ?></option>
                                        <option value="t_month"><?php echo gettext("By Month"); ?></option>
                                    </select>
                                </div>

                                <div class="year_select d-none">
                                    <div class="col-md-3 float-right pb-3 pr-md-0 pt-3 pt-md-0 pb-md-0">
                                        <select id="month_year_dropdown" name="year" class="form-control selectpicker m-0" style="z-index:9;">
                                            <?php
                                            $month_names = array(
                                                1  => gettext('January'),
                                                2  => gettext('February'),
                                                3  => gettext('March'),
                                                4  => gettext('April'),
                                                5  => gettext('May'),
                                                6  => gettext('June'),
                                                7  => gettext('July'),
                                                8  => gettext('August'),
                                                9  => gettext('September'),
                                                10 => gettext('October'),
                                                11 => gettext('November'),
                                                12 => gettext('December'),
                                            );

                                            for ($i = 0; $i < 6; $i++) {
                                                $timestamp = strtotime(date('Y-m-01') . " -$i months");
                                                $month_num = (int) date('n', $timestamp);
                                                $year_num = date('Y', $timestamp);
                                                $value = date('m#Y', $timestamp);
                                                $label = $month_names[$month_num] . ' ' . $year_num;
                                                $selected = ($i === 0) ? ' selected="selected"' : '';

                                                echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>' . "\n";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </h3>

                        <div id="call-graph"></div>
                        <div id="call_graph_data" class="call_graph_data p-0"></div>
                    </div>

                    <div class="float-left col-lg-3 col-md-4 px-sm-0 pl-lg-3 pt-4 pt-md-0 p-0">
                        <div id="month_total_info" class="col-12">
                            <div class="card p-4">
                                <div class="col-md-12 float-left p-1 alert-dark">
                                    <div class="col-6 float-left"><span id="period_label_summary"><?php echo gettext("This Week"); ?></span></div>
                                    <div class="col-6 float-left text-right"><?php echo gettext("This Month"); ?></div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-primary">
                                    <div class="col-12 text-left badge text-primary"><?php echo gettext("ASR (%)"); ?></div>
                                    <div class="col-6 float-left" id="asr_today">0.00</div>
                                    <div class="col-6 float-left text-right" id="asr_month">0.00</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-dark">
                                    <div class="col-12 text-left badge text-dark"><?php echo gettext("ACD"); ?></div>
                                    <div class="col-6 float-left" id="acd_today">0</div>
                                    <div class="col-6 float-left text-right" id="acd_month">0</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-warning">
                                    <div class="col-12 text-left badge text-warning"><?php echo gettext("MCD"); ?></div>
                                    <div class="col-6 float-left" id="mcd_today">0.0000</div>
                                    <div class="col-6 float-left text-right" id="mcd_month">0.0000</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-danger">
                                    <div class="col-12 text-left badge text-danger"><?php echo gettext("Total Calls"); ?></div>
                                    <div class="col-6 float-left" id="total_calls_today">0</div>
                                    <div class="col-6 float-left text-right" id="total_calls_month">0</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-secondary">
                                    <div class="col-12 text-left badge text-secondary"><?php echo gettext("Debit"); ?></div>
                                    <div class="col-6 float-left" id="debit_today">0.0000 USD</div>
                                    <div class="col-6 float-left text-right" id="debit_month">0.0000 USD</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-info">
                                    <div class="col-12 text-left badge text-info"><?php echo gettext("Cost"); ?></div>
                                    <div class="col-6 float-left" id="cost_today">0.0000 USD</div>
                                    <div class="col-6 float-left text-right" id="cost_month">0.0000 USD</div>
                                </div>

                                <div class="col-md-12 float-left p-2 border border-success">
                                    <div class="col-12 text-left badge text-success"><?php echo gettext("Profit"); ?></div>
                                    <div class="col-6 float-left" id="profit_today">0.0000 USD</div>
                                    <div class="col-6 float-left text-right" id="profit_month">0.0000 USD</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="dashboard_values row mb-3">
                <a href="<?php echo base_url(); ?>accounts/customer_list/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pr-lg-2">
                    <div class="bg-primary card col-12 text-light">
                        <div class="alert-primary col-md-12 pt-2 px-0">
                            <dl class="row m-0">
                                <dt class="col-7"><span class="period_label"><?php echo gettext("This Week"); ?></span></dt>
                                <dd class="col-5 text-right" id="today_accounts_count">0</dd>
                            </dl>
                        </div>
                        <div class="col-lg-8 col-7 float-left py-4">
                            <div class="h1" id="accounts_count">0</div>
                            <h3><?php echo gettext("New Accounts"); ?></h3>
                        </div>
                        <div class="col-lg-4 col-5 float-left py-4">
                            <i class="fa fa-users fa-4x float-left"></i>
                        </div>
                    </div>
                </a>

                <a href="<?php echo base_url(); ?>orders/orders_list/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pl-lg-2 pr-lg-2">
                    <div class="card col-12 text-light bg-success">
                        <div class="alert-success col-md-12 pt-2 px-0">
                            <dl class="row m-0">
                                <dt class="col-7"><span class="period_label"><?php echo gettext("This Week"); ?></span></dt>
                                <dd class="col-5 text-right" id="today_order_count">0</dd>
                            </dl>
                        </div>
                        <div class="col-lg-8 col-7 float-left py-4">
                            <div class="h1" id="order_count">0</div>
                            <h3><?php echo gettext("Orders"); ?></h3>
                        </div>
                        <div class="col-lg-4 col-5 float-left py-4">
                            <i class="fa fa-shopping-cart fa-4x float-left"></i>
                        </div>
                    </div>
                </a>

                <a href="<?php echo base_url(); ?>orders/orders_list/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pl-lg-2 pr-lg-2">
                    <div class="card col-12 text-light bg-warning">
                        <div class="alert-warning col-md-12 pt-2 px-0">
                            <dl class="row m-0">
                                <dt class="col-7"><span class="period_label"><?php echo gettext("This Week"); ?></span></dt>
                                <dd class="col-5 text-right" id="today_order_fail_count">0</dd>
                            </dl>
                        </div>
                        <div class="col-lg-8 col-7 float-left py-4">
                            <div class="h1" id="order_fail_count">0</div>
                            <h3><?php echo gettext("Fail Orders"); ?></h3>
                        </div>
                        <div class="col-lg-4 col-5 float-left py-4">
                            <i class="fa fa-warning fa-4x float-left"></i>
                        </div>
                    </div>
                </a>

                <a href="<?php echo base_url(); ?>reports/customerReport/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pl-lg-2">
                    <div class="card col-12 text-light bg-danger">
                        <div class="alert-danger col-md-12 pt-2 px-0">
                            <dl class="row m-0">
                                <dt class="col-7"><span class="period_label"><?php echo gettext("This Week"); ?></span></dt>
                                <dd class="col-5 text-right" id="today_call_count">0</dd>
                            </dl>
                        </div>
                        <div class="col-lg-8 col-7 float-left py-4">
                            <div class="h1" id="call_count">0</div>
                            <h3><?php echo gettext("Total Calls"); ?></h3>
                        </div>
                        <div class="col-lg-4 col-5 float-left py-4">
                            <i class="fa fa-phone fa-4x float-left"></i>
                        </div>
                    </div>
                </a>
            </div>

            
            <?php
            $accountinfo = $this->session->userdata("accountinfo");
            if($accountinfo['type'] == -1 || $accountinfo['type'] == 2){
            ?>
            <div class="row equal-height">
            <div class="col-lg-6 pr-lg-2 mb-3">
            
            <div class="card mb-3 dashboard-block h-100">
              <h3 class="text-dark p-3"><i class="fa fa-money text-primary fa-fw"></i> <?php echo gettext("Low Balance"); ?>
              <a href="<?php echo base_url();?>low_balance/low_balance_list/" class="float-right btn btn-secondary"><?php echo  gettext("View All"); ?></a>
              </h3>
              <div class="card-body">
                  <div class="table-responsive">
                   <table class="table table-hover">
                    <thead class="thead-light">
                  <?php 
                      $accountinfo = $this->session->userdata('accountinfo');
                      $currency_id = $accountinfo['currency_id'];
                      $currency = $this->common->get_field_name('currency', 'currency', $currency_id);
                    ?>
                      <tr>
                        <th scope="col"><?php echo gettext("Account"); ?></th>
                        <th scope="col"><?php echo gettext("Balance"). "($currency)";?></th>
                        <th scope="col"><?php echo gettext("Credit Limit"). "($currency)";?></th>
                        <th scope="col"><?php echo gettext("Action"); ?></th>
                      </tr>
                    </thead> 
                    <tbody>
                    <?php
                             if($accountinfo['type'] == '1'){
                                 $reseller_id = $accountinfo['id'];
                             }
                             else{
                                 $reseller_id = "0";
                             }
                         
                          $where = "notify_flag = '" . 0 . "' AND deleted = '" . 0 .  " ' AND status = ' " . 0 . " ' AND (posttoexternal ='" . 0 . "' AND " . "balance <= notify_credit_limit"  . ") OR ( posttoexternal ='" . 1 . "' AND " . "credit_limit - balance <= notify_credit_limit"  . ")";
            
                             $entity_array = array (
                                     "0",
                                     "1",
                                     "3" 
                             );
                             $limit = 5;
                             $this->db->where_in ( "type", $entity_array );
                             $this->db->limit($limit);
                             $query = $this->db_model->select("*", "accounts", $where, "id", "DESC");
                             if ($query->num_rows () > 0) {
                                 $account_data = $query->result_array ();
                                 foreach ( $account_data as $data_key => $accountinformation ) {
            
                                      echo "<tr>";
                                      echo "<td>".$this->common->get_field_name_coma_new ('first_name,last_name,number,company_name', 'accounts', $accountinformation ['id'] )."</td>";
                                       echo "<td>".$this->common->convert_to_currency($select, $table, $accountinformation['balance'])."</td>";
                                       echo "<td>".$this->common->convert_to_currency($select, $table, $accountinformation['credit_limit'])."</td>";
                                       echo "<td>";
                              ?>
                              <a href="<?php echo base_url();?>accounts/customer_payment_process_add/<?php echo $accountinformation['id'];?>" rel="facebox">
                              <button class="btn btn-secondary" value="<?php echo $accountinformation['id'];?>" type="button"><?php echo gettext("Recharge");?></button></a>
                                  <?php  
                                      echo "</td>";
                                      echo "</tr>";
                                 }
            
                             }
                      ?>
                    </tbody>
                  </table>
               </div>
             </div>
            </div>
            </div>
            <?php } ?>
            
            <?php  
            $accountinfo = $this->session->userdata("accountinfo");
            if($accountinfo['type'] == -1 || $accountinfo['type'] == 2 ){
            ?>
            <div class="col-lg-6 pl-lg-2 mb-3">
            
            <div class="card mb-3 dashboard-block h-100">
            
            <h3 class="text-dark p-3"><i class="fa fa-phone text-primary fa-fw"></i> <?php echo gettext("Trunk Statistics"); ?>
            <a href="<?php echo base_url();?>summary/provider/" class="float-right btn btn-secondary"><?php echo  gettext("View All"); ?></a>
            </h3>
            <div class="card-body">
              <div class="table-responsive">
              <table class="table table-hover">
                <thead class="thead-light">
                  <tr>
                    <th scope="col"><?php echo gettext("Trunk"); ?></th>
                    <th scope="col"><?php echo gettext("Attempted Calls"); ?></th>
                    <th scope="col"><?php echo gettext("Completed Calls"); ?></th>
                    <th scope="col"><?php echo gettext("ASR"); ?></th>
                  </tr>
                </thead> 
                <tbody>
                <?php
                      $limit = 5;
                      $table_name= "cdrs";
                      $where1['provider_id >'] = 0;
                      $where1['callstart >='] =$this->common->convert_GMT_new ( date('Y-m-d') . " 00:00:00");
                      $where1['callstart <='] =$this->common->convert_GMT_new (date('Y-m-d') . " 23:59:59");
                      $query = $this->db->select("trunk_id" . ",COUNT(*) AS attempts, AVG(billseconds) AS acd,MAX(billseconds) AS mcd,SUM(billseconds) AS duration,SUM(CASE WHEN calltype !='free' THEN billseconds ELSE 0 END) as billable,SUM(CASE WHEN billseconds > 0 THEN 1 ELSE 0 END) as completed,SUM(provider_call_cost) AS cost,",false);
                      $this->db->from($table_name);
                      $this->db->where($where1);
                      $this->db->group_by('trunk_id');
                      $this->db->order_by('callstart' , "DESC");
                      $this->db->limit($limit);
                      $result = $this->db->get(); 
                      $account_data = $result->result_array ();
                      foreach ( $account_data as $data_key => $val ) {
                          $atmpt =  $val['attempts'];
                          $cmplt = $val['completed'];
                          $asr = ($atmpt > 0) ? (round(($cmplt / $atmpt) * 100, 2)) : '0.00';
            
                          echo "<tr>";
                          echo "<td>".$this->common->get_field_name ( 'name', 'trunks', $val ['trunk_id'] )."</td>";
                          echo "<td>".$val['attempts']."</td>";
                          echo "<td>".$val['completed']."</td>";
                          echo "<td>".$asr."</td>";
                          }
                         
                  ?>
                </tbody>
              </table>
            </div>
            </div>
            </div>
            </div>
            </div>
            <?php }?>
            <?php
            $accountinfo = $this->session->userdata('accountinfo');
            if ($accountinfo['type'] == -1 || $accountinfo['type'] == 2):
            ?>
            <div class="row">
            <div class="col-lg-12 mb-3">
            <div class="card mb-3 dashboard-block">
              <h3 class="text-dark p-3">
                <i class="fa fa-shield text-primary fa-fw"></i> <?php echo gettext('Event Guard - Blocked IPs'); ?>
                <a href="<?php echo base_url(); ?>event_guard/event_guard_list/" class="float-right btn btn-secondary">
                  <?php echo gettext('View All'); ?>
                </a>
              </h3>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="thead-light">
                      <tr>
                        <th scope="col"><?php echo gettext('IP Address'); ?></th>
                        <th scope="col"><?php echo gettext('Country'); ?></th>
                        <th scope="col"><?php echo gettext('Filter'); ?></th>
                        <th scope="col"><?php echo gettext('Date / Time'); ?></th>
                        <th scope="col"><?php echo gettext('Failures'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($blocked_ips)): ?>
                      <?php foreach ($blocked_ips as $row): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($row['ip_address']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['country']); ?></td>
                        <td><span class="badge badge-danger"><?php echo htmlspecialchars($row['filter']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                        <td><?php echo (int) $row['failures']; ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted">
                          <?php echo gettext('No blocked IPs found.'); ?>
                        </td>
                      </tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            </div>
            </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-lg-6 pr-lg-2">
                    <div class="card mb-3 dashboard-block">
                        <h3 class="text-dark p-3">
                            <i class="fa fa-users text-primary fa-fw"></i>
                            <?php echo gettext("Top 10 Accounts"); ?>

                            <div class="dropdown float-right col-5 p-0">
                                <input
                                    type="text"
                                    class="border-primary col-8 float-right form-control text-center text-primary"
                                    readonly="true"
                                    name="bygraph"
                                    id="bygraph"
                                    value="<?php echo gettext("By Minutes"); ?>"
                                    style="background: transparent;font-size: 12px;"
                                >
                                <button type="button" class="btn btn-link dropdown-toggle position-relative float-right py-0" id="radioToggleAccounts" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="radioToggleAccounts">
                                    <label class="dropdown-item">
                                        <input type="radio" name="calls_pie_chart" id="option2" value="count" class="ace" autocomplete="off" checked>
                                        <?php echo gettext("By Calls"); ?>
                                    </label>
                                    <label class="dropdown-item">
                                        <input type="radio" name="calls_pie_chart" id="option1" value="minutes" class="ace" autocomplete="off">
                                        <?php echo gettext("By Minutes"); ?>
                                    </label>
                                </div>
                            </div>
                        </h3>

                        <div class="card-body">
                            <div id="call-count"></div>
                            <div id="call_count_data" class="call_count_data col-md-12" style="display:none"></div>
                            <div id="call_count_not_data" class="call_count_not_data col-md-12" style="display:none"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 pl-lg-2">
                    <div class="card mb-3 dashboard-block">
                        <h3 class="text-dark p-3">
                            <i class="fa fa-phone text-primary fa-fw"></i>
                            <?php echo gettext("Top 10 Call Types"); ?>

                            <div class="dropdown float-right col-5 p-0">
                                <input
                                    type="text"
                                    class="border-primary col-8 float-right form-control text-center text-primary"
                                    readonly="true"
                                    name="bygraphcountry"
                                    id="bygraphcountry"
                                    value="<?php echo gettext("By Minutes"); ?>"
                                    style="background: transparent;font-size: 12px;"
                                >
                                <button type="button" class="btn btn-link dropdown-toggle position-relative float-right py-0" id="radioToggleCountry" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="radioToggleCountry">
                                    <label class="dropdown-item">
                                        <input type="radio" name="country_pie_chart" id="option22" value="count" class="ace" autocomplete="off" checked>
                                        <?php echo gettext("By Calls"); ?>
                                    </label>
                                    <label class="dropdown-item">
                                        <input type="radio" name="country_pie_chart" id="option11" value="minutes" class="ace" autocomplete="off">
                                        <?php echo gettext("By Minutes"); ?>
                                    </label>
                                </div>
                            </div>
                        </h3>

                        <div class="card-body">
                            <div id="country-count"></div>
                            <div id="country_count_data" class="col-md-12 country_count_data" style="display:none"></div>
                            <div id="country_not_data" class="col-md-12 country_not_data" style="display:none"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 pr-lg-2">
                    <div class="card mb-3 dashboard-block">
                        <h3 class="text-dark p-3">
                            <i class="fa fa-exclamation-triangle text-primary fa-fw"></i>
                            <?php echo gettext("Call Disposition"); ?>

                            <?php
                            $accountinfo = $this->session->userdata('accountinfo');
                            if ($accountinfo['type'] == 1 || $accountinfo['type'] == 2 || $accountinfo['type'] == -1):
                            ?>
                                <div class="float-right col-5 p-0">
                                    <select id="error_code_account_filter" class="form-control selectpicker" style="font-size:12px;">
                                        <option value="0"><?php echo gettext("All Accounts"); ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </h3>

                        <div class="card-body">
                            <div id="error-code-loading" class="col-md-offset-6" style="display:none;">
                                <div class="ball-clip-rotate"><div></div></div>
                            </div>
                            <div id="error_code_chart_data" class="error_code_data col-md-12" style="min-height:280px; display:none;"></div>
                            <div class="error_code_not_data col-md-12" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 pl-lg-2">
                    <div class="card mb-3 dashboard-block">
                        <h3 class="text-dark p-3">
                            <i class="fa fa-map-marker text-primary fa-fw"></i>
                            <?php echo gettext("Calls by DDD"); ?>

                            <?php
                            if ($accountinfo['type'] == 1 || $accountinfo['type'] == 2 || $accountinfo['type'] == -1):
                            ?>
                                <div class="float-right col-5 p-0">
                                    <select id="ddd_account_filter" class="form-control selectpicker" style="font-size:12px;">
                                        <option value="0"><?php echo gettext("All Accounts"); ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </h3>

                        <div class="card-body">
                            <div id="ddd-loading" class="col-md-offset-6" style="display:none;">
                                <div class="ball-clip-rotate"><div></div></div>
                            </div>
                            <div id="ddd_chart_data" class="ddd_data col-md-12" style="min-height:280px; display:none;"></div>
                            <div class="ddd_not_data col-md-12" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            if (
                $this->session->userdata('logintype') == 2 ||
                $this->session->userdata('logintype') == 4 ||
                $this->session->userdata('logintype') == -1
            ) {
            ?>
                <div class="row event-info-box">
                    <div class="col-lg-12">
                        <div class="row">
                            <div class="col-lg-6 pr-lg-2">
                                <div class="card mb-3 dashboard-block">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-clock-o text-primary fa-fw"></i>
                                        <?php echo gettext("Up Time"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <?php echo uptime(); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 pl-lg-2">
                                <div class="card mb-3 dashboard-block">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-tasks text-primary fa-fw"></i>
                                        <?php echo gettext("CPU Cores"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <?php echo get_cpu_cores(); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 pr-lg-2">
                                <div class="card mb-3 dashboard-block">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-life-ring text-primary fa-fw"></i>
                                        <?php echo gettext("Hard Disk Usage"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <table class="table table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th scope="col"><?php echo gettext("Total"); ?></th>
                                                    <th scope="col"><?php echo gettext("Used"); ?></th>
                                                    <th scope="col"><?php echo gettext("Available"); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $get_details = get_harddisk(); ?>
                                                <?php if (!empty($get_details)) { ?>
                                                    <tr>
                                                        <td scope="col"><?php echo $get_details['total']; ?></td>
                                                        <td scope="col"><?php echo $get_details['used']; ?></td>
                                                        <td scope="col"><?php echo $get_details['avail']; ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 pl-lg-2">
                                <div class="card mb-3 dashboard-block specs-info">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-desktop text-primary fa-fw"></i>
                                        <?php echo gettext("Operating System"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <ul class="p-0 os-info">
                                            <li>
                                                <span class="type-os"><?php echo gettext("OS"); ?> : </span>
                                                <span><?php echo getOS(); ?></span>
                                            </li>
                                            <li>
                                                <span class="type-os"><?php echo gettext("Architecture"); ?> : </span>
                                                <span><?php echo getArchitecture(); ?></span>
                                            </li>
                                            <li>
                                                <span class="type-os"><?php echo gettext("Static hostname"); ?> : </span>
                                                <span><?php echo getStatichost(); ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 pr-lg-2">
                                <div class="card mb-3 dashboard-block">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-tachometer text-primary fa-fw"></i>
                                        <?php echo gettext("CPU Usage"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <?php echo get_server_cpu_usage(); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 pl-lg-2">
                                <div class="card mb-3 dashboard-block">
                                    <h3 class="text-dark p-3">
                                        <i class="fa fa-shield text-primary fa-fw"></i>
                                        <?php echo gettext("Kernel Version"); ?>
                                    </h3>
                                    <div class="card-body">
                                        <?php echo get_kernel_version(); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row event-info-box">
                    <div class="col-lg-12">
                        <div class="card mb-3 dashboard-block">
                            <h3 class="text-dark p-3">
                                <i class="fa fa-server text-primary fa-fw"></i>
                                <?php echo gettext("Memory Usage"); ?>
                            </h3>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <?php
                                    $memory_info = get_memory();
                                    $swap_info = get_swap_memory();
                                    ?>
                                    <table class="table table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th scope="col"></th>
                                                <th scope="col"><?php echo gettext("Total"); ?></th>
                                                <th scope="col"><?php echo gettext("Used"); ?></th>
                                                <th scope="col"><?php echo gettext("Free"); ?></th>
                                                <th scope="col"><?php echo gettext("Shared"); ?></th>
                                                <th scope="col"><?php echo gettext("Cache"); ?></th>
                                                <th scope="col"><?php echo gettext("Available"); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td scope="col"><?php echo gettext("Memory"); ?></td>
                                                <td scope="col"><?php if ($memory_info[0] != '') { echo $memory_info[0] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($memory_info[1] != '') { echo $memory_info[1] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($memory_info[2] != '') { echo $memory_info[2] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($memory_info[3] != '') { echo $memory_info[3] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($memory_info[4] != '') { echo $memory_info[4] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($memory_info[5] != '') { echo $memory_info[5] . 'MB'; } ?></td>
                                            </tr>
                                            <tr>
                                                <td scope="col"><?php echo gettext("Swap"); ?></td>
                                                <td scope="col"><?php if ($swap_info[0] != '') { echo $swap_info[0] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($swap_info[1] != '') { echo $swap_info[1] . 'MB'; } ?></td>
                                                <td scope="col"><?php if ($swap_info[2] != '') { echo $swap_info[2] . 'MB'; } ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
<? endblock() ?>

<? startblock('sidebar') ?>
<? endblock() ?>

<? end_extend() ?>