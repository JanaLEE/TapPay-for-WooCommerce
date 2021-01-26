var CWTAPPAY;

(function($){

	CWTAPPAY={
		Init:function(){}, 

		QueryStatus:function(obj){
			var strID=CWTAPPAY_vars.handler_id;
			var Parent=$('#'+strID+'_query-status');
			var data={
				action		:strID+'_QueryStatus', 
				order_id	:$(obj).data('id')};

			Parent.block({
				message:null,
				overlayCSS:{
					background:'#FFF',
					opacity:0.6}
				});

			this.AjaxPost(data, function(json){
				alert(json.result);
				//Parent.unblock();
				window.location.reload();
			});

		}, 

		ManualRenew:function(A){

			var strID=CWTAPPAY_vars.handler_id;

			var Parent=$('#'+strID+'_manual-renew');

			var data={
				action		:strID+'_ManualRenew', 
				type			:'manual-renew', 
				order_id	:$(A).data('id')};

			Parent.block({
				message:null,
				overlayCSS:{
					background:'#FFF',
					opacity:0.6}
				});

			this.AjaxPost(data, function(json){
				window.location.reload();
			});
		}, 

		AjaxPost:function(data, callback){
			$.ajax({
				type:'POST',
				data:data,
				dataType:'json',
				url:CWTAPPAY_vars.ajaxurl,

			}).always(function(response){
				//console.log('always', response);

			}).done(function(response){
				console.log('done', response);
				if(typeof callback=='function')callback(response);

			}).fail(function(response, textStatus, errorThrown){
				console.log('fail', response);
			});
		}, 
	};

	$(document).ready(function(){
		CWTAPPAY.Init();
	});

}(jQuery));