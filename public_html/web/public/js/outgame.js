$.ajaxPrefilter(function(options){
    options.async = true;
});

var ajaxBusy = false;
$(document).ajaxStart( function(){ 
    ajaxBusy = true; 
}).ajaxStop( function(){
    ajaxBusy = false;
});

$(".closeCookieMessage").click(function(e){
    $.ajax({
        url : "/accept-cookies",
        type: "POST",
        data : {type: "accept"}
    });
});

$(document).on("click", "a.menuBurger", function (e){
    $("nav#menuMobile").slideToggle( "fast", function(){
        var body = $("html, body");
        body.stop().animate({scrollTop:0}, "300", "swing");
    });
    e.preventDefault();
});

$(document).ready(function(){
    $("nav#menu").parent().append("<a href='javascript:void(0);' class='menuBurger'>&nbsp;</a><nav id='menuMobile'>" + $("nav#menu").html() + "</nav>");
    $("[data-toggle='tooltip']").tooltip();
    $("html").height($(document).height());
});

if("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker
      .register("/sw.js")
      .then(res => console.log("service worker registered"))
      .catch(err => console.log("service worker not registered", err));
  });
}
