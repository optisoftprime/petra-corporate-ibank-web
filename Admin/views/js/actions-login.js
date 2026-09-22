$(function () {
	$('#loginform').bind('submit',function (event) {
			if ($.trim($('#loginame').val()) =='') {
				$('#loginame').css('border-color','#f00');
				event.preventDefault();
			}else if ($.trim($('#password').val()) =='') {
				$('#password').css('border-color','#f00');
				event.preventDefault();
			}else {
				return true;
			}
		});
	});