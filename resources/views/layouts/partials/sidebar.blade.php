<div id="sidebar">
    <!-- Search -->
    <form action="#" class="search" method="post">
        <div class="cl">&nbsp;</div>
        <input type="text" class="field blink" value="search" title="search" />
        <div class="btnp">
            <input type="submit" value="go" />
        </div>
        <div class="cl">&nbsp;</div>
    </form>
    <!-- End Search -->
    <!-- Sign In Links -->
    <div class="links">
        <div class="cl">&nbsp;</div>
        <a class="left sign-in" data-hidden = '0'>Sign In</a> <a class="right sign-up" data-hidden = '0'>Create account</a>
        <div class="cl">&nbsp;</div>
    </div>
    <!-- End Sign In Links -->
    <div class="form-sign-in">
        <div class="box hidden" >
            <h2>Sign In</h2>
                <div class="grid__container">
                  <form action="{{route('auth_sign_in')}}" method="post" class="form form--login">
                    <input type="hidden" name="_token" value="{{csrf_token()}}">
                    <div class="form__field">
                      <label class="fontawesome-user" for="login__username"><span class="hidden">Username</span></label>
                      <input id="login__username" name="user_name" type="text" class="form__input" placeholder="Username" required>
                    </div>
                    <div class="form__field">
                      <label class="fontawesome-lock" for="login__password"><span class="hidden">Password</span></label>
                      <input id="login__password" name="password" type="password" class="form__input" placeholder="Password" required>
                    </div>
                    <div class="form__field">
                      <input type="submit" value="Sign In">
                    </div>
                  </form>
                  <p class="text--center">Not a member? <a href="#">Sign up now</a> <span class="fontawesome-arrow-right"></span></p>
                </div>
            <div class="cl">&nbsp;</div>
        </div>
    </div>

    <div class="form-sign-up">
            <div class="box hidden" >
                <h2>Sign In</h2>
                    <div class="grid__container">
                      <form action="" method="post" class="form form--login">
                        <input type="hidden" name="_token" value="{{csrf_token()}}">
                        <div class="form__field">
                          <label class="fontawesome-user" for="login__username"><span class="hidden">Username</span></label>
                          <input id="login__username" type="text" class="form__input" placeholder="Username" required>
                        </div>
                        <div class="form__field">
                          <label class="fontawesome-user" for="login__username"><span class="hidden">Email</span></label>
                          <input id="login__username" type="text" class="form__input" placeholder="Email" required>
                        </div>
                        <div class="form__field">
                          <label class="fontawesome-lock" for="login__password"><span class="hidden">Password</span></label>
                          <input id="login__password" type="password" class="form__input" placeholder="Password" required>
                        </div>
                        <div class="form__field">
                          <label class="fontawesome-lock" for="login__password"><span class="hidden">Confirm password</span></label>
                          <input id="login__password" type="password" class="form__input" placeholder="Confirm password" required>
                        </div>
                        <div class="form__field">
                          <input type="submit" value="Sign up">
                        </div>
                      </form>
                      <p class="text--center">Not a member? <a href="#">Sign up now</a> <span class="fontawesome-arrow-right"></span></p>
                    </div>
                <div class="cl">&nbsp;</div>
            </div>
        </div>

    <!-- Box Latest News -->
    <div class="box">
        <h2>Thành Viên Mới</h2>
        <ul>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/images/avatar_l.png')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Lorem ipsum dolo</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/images/avatar_female_l.png')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Dolor amet urna isque</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/images/avatar_male_l.png')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Molestie id sceler leo</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/images/avatar_female_l.png')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Sed elementum molesti</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
        </ul>
        <a href="#" class="up">See more</a>
        <div class="cl">&nbsp;</div>
    </div>
    <!-- End Box Latest News -->
    <!-- Box Latest Reviews -->
    <div class="box">
        <h2>Mua-Bán</h2>
        <ul>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/thumb5.jpg')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Lorem ipsum dolo</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/thumb6.jpg')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Dolor amet urna isque</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/thumb7.jpg')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Molestie id sceler leo</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
            <li> <a href="#" class="image"><img src="{{URL::asset('public/css/images/thumb8.jpg')}}" alt="" /></a>
                <div class="info">
                    <h5><a href="#">Sed elementum molesti</a></h5>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
                </div>
                <div class="cl">&nbsp;</div>
            </li>
        </ul>
        <a href="#" class="up">See more</a>
        <div class="cl">&nbsp;</div>
    </div>
    <!-- End Box Latest Reviews -->
    <!-- Box Latest Posts -->
    <div class="box">
        <h2>Diển ĐÀn</h2>
        <ul>
            <li>
                <h5><a href="#">Lorem ipsum dolo</a></h5>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
            </li>
            <li>
                <h5><a href="#">Dolor amet urna isque</a></h5>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
            </li>
            <li>
                <h5><a href="#">Molestie id sceler leo</a></h5>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
            </li>
            <li>
                <h5><a href="#">Sed elementum molesti</a></h5>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
            </li>
            <li>
                <h5><a href="#">Sed elementum molesti</a></h5>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed elementum molestie urna, id scelerisque leo </p>
            </li>
        </ul>
        <a href="#" class="up">See more</a>
        <div class="cl">&nbsp;</div>
    </div>
    <!-- End Box Latest Posts -->
</div>