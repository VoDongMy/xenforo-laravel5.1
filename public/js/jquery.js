$(document).ready(function() {
    $('#sidebar .links .sign-in').bind('click', function() {
        if ($(this).attr('data-hidden')==1) {
            $('#sidebar .form-sign-in .box').hide("slow", function() {
                $('#sidebar .links .sign-in').attr('data-hidden', 0);
            });
        }else{
        	$('#sidebar .form-sign-up .box').hide();
        	$('#sidebar .form-sign-in .box').removeClass('hidden').show("slow", function() {
                $('#sidebar .links .sign-in').attr('data-hidden', 1);
                $('#sidebar .links .sign-up').attr('data-hidden', 0);
            });
        }
    });
    $('#sidebar .links .sign-up').bind('click', function() {
        if ($(this).attr('data-hidden')==1) {
            $('#sidebar .form-sign-up .box').hide("slow", function() {
                $('#sidebar .links .sign-up').attr('data-hidden', 0);
            });
        }else{
        	$('#sidebar .form-sign-in .box').hide();
        	$('#sidebar .form-sign-up .box').removeClass('hidden').show("slow", function() {
                $('#sidebar .links .sign-up').attr('data-hidden', 1);
                $('#sidebar .links .sign-in').attr('data-hidden', 0);
            });
        }
    });

});