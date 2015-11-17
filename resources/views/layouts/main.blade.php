<!DOCTYPE html PUBLIC >
<html >
    <head>
        @include('layouts.partials.meta_des')
    </head>
    <body>
        <!-- Shell -->
        <div class="shell">
            <!-- Header -->
            <div id="header">
                <!-- Logo -->
                <h1 id="logo"><a href="#">autoportal your friend on the road</a></h1>
                <!-- End Logo -->
                <!-- Navigation -->
                @include('layouts.partials.navi')
                <!-- End Navigation -->
            </div>
            <!-- End Header -->
            <!-- Content -->
            <div id="content">
                <!-- Sidebar -->
                @include('layouts.partials.sidebar')
                <!-- End Sidebar -->
                <!-- Main -->
                <div id="main">
                    <!-- Top Image -->
                    <div class="custom-navigation">
                          <!-- <a href="#" class="flex-prev">Prev</a> -->
                          <div class="custom-controls-container navigate"></div>
                          <!-- <a href="#" class="flex-next">Next</a> -->
                        </div>
                    <div class="transparent-frame">
                        <div class="frame">&nbsp;</div>
                        <!-- <img src="{{URL::asset('public/css/images/sls.jpg')}}" alt="" />  -->
                    <!-- Place somewhere in the <body> of your page -->
                        <div class="flexslider">
                          <ul class="slides">
                            <li>
                              <img src="{{URL::asset('public/css/images/sls.jpg')}}" />
                            </li>
                            <li>
                              <img src="{{URL::asset('public/css/images/sls.jpg')}}" />
                            </li>
                            <li>
                              <img src="{{URL::asset('public/css/images/sls.jpg')}}" />
                            </li>
                            <li>
                              <img src="{{URL::asset('public/css/images/sls.jpg')}}" />
                            </li>
                          </ul>
                        </div>
                        
                    </div>
                    <div class="cl">&nbsp;</div>
                    <!-- End Top Image -->
                    <!-- Box -->
                    <div class="box">
                        <h2>Chat Box</h2>
                        <div class="body-chatbox">
                            
                        </div>
                        <div class="cl">&nbsp;</div>
                    </div>
                    <!-- End Box -->
                    <!-- Box -->
                    <div class="box">
                        <h2>Editor's Pick</h2>
                        <ul>
                            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/car4.jpg')}}" alt="" /></a>
                                <div class="info">
                                    <h4><a href="#">Dolor amet urna isque</a></h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo sodales sit amet. Curabitur volutpat lorem euismod nunc tincidunt condimentum.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna.</p>
                                <a class="up">read more</a> </div>
                                <div class="cl">&nbsp;</div>
                            </li>
                            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/car5.jpg')}}" alt="" /></a>
                                <div class="info">
                                    <h4><a href="#">Lorem dolor consectetur elit.</a></h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo sodales sit amet. Curabitur volutpat lorem euismod nunc tincidunt condimentum.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna.</p>
                                <a class="up">read more</a> </div>
                                <div class="cl">&nbsp;</div>
                            </li>
                            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/car6.jpg')}}" alt="" /></a>
                                <div class="info">
                                    <h4><a href="#">Sed elementum molestie urna</a></h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo sodales sit amet. Curabitur volutpat lorem euismod nunc tincidunt condimentum.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna.</p>
                                <a class="up">read more</a> </div>
                                <div class="cl">&nbsp;</div>
                            </li>
                            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/car7.jpg')}}" alt="" /></a>
                                <div class="info">
                                    <h4><a href="#">Tincidunt conimentum ipsum</a></h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo sodales sit amet. Curabitur volutpat lorem euismod nunc tincidunt condimentum.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna.</p>
                                <a class="up">read more</a> </div>
                                <div class="cl">&nbsp;</div>
                            </li>
                        </ul>
                        <a href="#" class="up">See more</a>
                        <div class="cl">&nbsp;</div>
                    </div>
                    <!-- End Box -->
                </div>
                <!-- End Main -->
                <div class="cl">&nbsp;</div>
            </div>
            <!-- End Content -->
            <!-- Footer -->
            <div id="footer">
                <p>&copy; Sitename.com. Design by <a href="http://chocotemplates.com">ChocoTemplates.com</a></p>
            </div>
            <!-- End Footer -->
        </div>
        <!-- End Shell -->
        <!-- <div align=center>minskVN.com <a href='#'>developer VoDongMy</a>
        </div> -->
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
        <script src="{{URL::asset('public/js/jquery.flexslider.js')}}" type="text/javascript"></script>
        <script type="text/javascript">
                $(window).load(function() {
                  $('.flexslider').flexslider({
                    animation: "slide",
                    controlsContainer: $(".custom-controls-container"),
                    customDirectionNav: $(".custom-navigation a"),
                    directionNav: true,
                    controlNav: true,
                    before: function(slider) {
                        // $('.flex-control-nav li').removeClass('active');
                      },
                    after: function(slider) {
                        $('.flex-control-nav li').removeClass('active');
                        index = (slider.currentSlide+1);
                        $('.flex-control-nav li:nth-child('+index+')').addClass('active');
                      },
                    start : function(slider) {
                        $('.flex-control-nav li:nth-child(1)').addClass('active');
                        $('.flex-control-nav li').click(function(event) {
                         event.preventDefault();                     
                         $('.flex-control-nav li').removeClass('active');                    
                         $(this).addClass('active');
                         $('.flexslider').show();
                         var slideTo = $(this).attr("rel"); //Grab rel value from link;
                         var slideToInt = parseInt(slideTo); 
                         if (slider.currentSlide != slideToInt) {
                            $(this).addClass('active');
                            slider.flexAnimate(slideToInt) //Move the slider to the correct slide (Unless the slider is also already showing the slide we want);
                         }
                        });
                        $.flexloader(slider);
                    }
                  });
                });
                    
        </script>
    </body>
</html>