<!-- HEADER DESKTOP-->
        <header class="header-desktop3 d-none d-lg-block">
            <div class="section__content section__content--p35">
                <div class="header3-wrap">
                    <div class="header__logo">
                        <a href="index.php">
                            <h4 class="text-white"><?=$website_name?></h4>
                        </a>
                    </div>
                    <div class="header__navbar">
                        <ul class="list-unstyled">
                          <li>
                            <a href="index.php">
                              <i class="fas fa-th-list"></i>
                              <span class="bot-line"></span>Products Control Panel 
                            </a>
                          </li>
                          <?php if ($user_details['admin'] != 0): ?>
                          <li>
                            <a href="stock_suppliers.php">
                              <i class="fas fa-th-list"></i>
                              <span class="bot-line"></span>Stock Suppliers
                            </a>
                          </li>
                          <?php endif; ?>
                          <?php if ($user_details['admin'] == 2): ?>
                          <li>
                            <a href="users.php">
                              <i class="fas fa-user-plus"></i>
                              <span class="bot-line"></span>Users Control Panel
                            </a>
                          </li>
                          <li>
                            <a href="categories.php">
                              <span class="bot-line"></span>Categories Control Panel
                            </a>
                          </li>
                          <?php endif; ?>
                        </ul>
                    </div>
                    <div class="header__tool">
                        <div class="header-button-item has-noti js-item-menu">
                            
                        </div>
                        <div class="header-button-item js-item-menu">
                            
                        </div>
                        <div class="account-wrap">
                            <div class="account-item account-item--style2 clearfix js-item-menu">
                                <div class="image">
                                    <img src="assets/images/icon/avatar.png" alt="John Doe" />
                                </div>
                                <div class="content">
                                    <a class="js-acc-btn" href="#"><?=$user_details['username']?></a>
                                </div>
                                <div class="account-dropdown js-dropdown">
                                    <div class="info clearfix">
                                        <div class="image">
                                            <a href="#">
                                                <img src="assets/images/icon/avatar.png" alt="John Doe" />
                                            </a>
                                        </div>
                                        <div class="content">
                                            <h5 class="name">
                                                <a href="#"><?=$user_details['username']?></a>
                                            </h5>
                                            <span class="email"><?=$user_details['email']?></span>
                                        </div>
                                    </div>
                                    <div class="account-dropdown__body">
                                        <div class="account-dropdown__item">
                                            <a href="#" type="button" class="changePassword">
                                                <i class="zmdi zmdi-password"></i>Change Password</a>
                                        </div>
                                    </div>
                                    <div class="account-dropdown__footer">
                                        <a href="logout.php">
                                            <i class="zmdi zmdi-power"></i>Logout</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- END HEADER DESKTOP-->

        <!-- HEADER MOBILE-->
        <header class="header-mobile header-mobile-2 d-block d-lg-none">
            <div class="header-mobile__bar">
                <div class="container-fluid">
                    <div class="header-mobile-inner">
                        <a class="logo" href="index.html">
                            <h4 class="text-white"><?=$website_name?></h4>
                        </a>
                        <button class="hamburger hamburger--slider" type="button">
                            <span class="hamburger-box">
                                <span class="hamburger-inner"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
            <nav class="navbar-mobile">
                <div class="container-fluid">
                    <ul class="navbar-mobile__list list-unstyled">
                      <li>
                          <a href="index.php">
                            <i class="fas fa-th-list"></i>
                            <span class="bot-line"></span>Products Control Panel 
                          </a>
                        </li>
                        <li>
                          <a href="stock_suppliers.php">
                            <i class="fas fa-th-list"></i>
                            <span class="bot-line"></span>Stock Suppliers
                          </a>
                        </li>
                        <li>
                          <a href="new_account.php">
                            <i class="fas fa-user-plus"></i>
                            <span class="bot-line"></span>New Account
                          </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <div class="sub-header-mobile-2 d-block d-lg-none">
            <div class="header__tool">
                <div class="header-button-item has-noti js-item-menu">
                   
                </div>
                <div class="header-button-item js-item-menu">
                    
                </div>
                <div class="account-wrap">
                    <div class="account-item account-item--style2 clearfix js-item-menu">
                        <div class="image">
                            <img src="assets/images/icon/avatar.png" alt="John Doe" />
                        </div>
                        <div class="content">
                            <a class="js-acc-btn" href="#"><?=$user_details['username']?></a>
                        </div>
                        <div class="account-dropdown js-dropdown">
                            <div class="info clearfix">
                                <div class="image">
                                    <a href="#">
                                        <img src="assets/images/icon/avatar.png" alt="John Doe" />
                                    </a>
                                </div>
                                <div class="content">
                                    <h5 class="name">
                                        <a href="#"><?=$user_details['username']?></a>
                                    </h5>
                                    <span class="email"><?=$user_details['email']?></span>
                                </div>
                            </div>
                            <div class="account-dropdown__body">
                                <div class="account-dropdown__item">
                                    <a href="#" type="button" class="changePassword">
                                                <i class="zmdi zmdi-password"></i>Change Password</a>
                                </div>
                            </div>
                            <div class="account-dropdown__footer">
                                <a href="logout.php">
                                    <i class="zmdi zmdi-power"></i>Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END HEADER MOBILE -->
