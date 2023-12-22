$(document).ready(function (e) {
    $('.form_datetime').datetimepicker({
    	language: 'zh-CN',
    	format: 'yyyy-mm-dd hh:ii:00',
    	linkFormat: 'yyyy-mm-dd hh:ii:00',
        weekStart: 1,
        todayBtn:  1,
		autoclose: 1,
		todayHighlight: 1,
		startView: 2,
		forceParse: 0,
        showMeridian: 0
    });
	$('.form_date').datetimepicker({
		language: 'zh-CN',
		format: 'yyyy-mm-dd',
		linkFormat: 'yyyy-mm-dd',
        weekStart: 1,
        todayBtn:  1,
		autoclose: 1,
		todayHighlight: 1,
		startView: 2,
		minView: 2,
		forceParse: 0,
		fontAwesome:1
    });
	$('.form_time').datetimepicker({
		language: 'zh-CN',
		format: 'hh:ii:00',
		linkFormat: 'hh:ii:00',
        weekStart: 1,
        todayBtn:  1,
		autoclose: 1,
		todayHighlight: 1,
		startView: 1,
		minView: 0,
		maxView: 1,
		forceParse: 0
    });
})


