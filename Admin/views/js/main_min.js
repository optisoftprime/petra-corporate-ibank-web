$(document).ready(function() {
    $('.button-left').click(function(){
        $('.sidebar').toggleClass('fliph');
    });
    $('.menu-item').click(function(){
        // $('.menu-item').not(this).removeClass('active');
        $(this).toggleClass('active');
        $(this).siblings().removeClass('active');
    });
})
