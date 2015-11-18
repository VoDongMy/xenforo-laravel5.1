<script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
        <script src="{{URL::asset('public/js/jquery.flexslider.js')}}" type="text/javascript"></script>
        <script src="{{URL::asset('public/js/jquery.js')}}" type="text/javascript"></script>
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
                        // $.flexloader(slider);
                    }
                  });
                });
                    
        </script>