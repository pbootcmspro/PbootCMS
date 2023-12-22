$(document).ready(function(){
	//初始化动画
	if (!(/msie [6|7|8|9]/i.test(navigator.userAgent))){
	    new WOW().init();
	};
	
	//在线客服
	$('.scroll-top').click(function(){$('html,body').animate({scrollTop: '0px'}, 800);});
	
	$('.online dl').on("mouseover",function(){
		$(this).find("dt").show();
		$(this).siblings().find("dt").hide();
	});
	
	$('.online dl').find('.remove').on("click",function(){
		$(this).parents("dt").hide();
	});
	
	$(window).scroll(function() {
		 if ($(document).scrollTop()<=100){
			 $('.online .scroll-top').hide();
		 }else{
			 $('.online .scroll-top').show();
		 }
		 
	});

});