<?php
// Ensure that $website_name and $user_details are defined before including this file
?>
<!-- HEADER DESKTOP-->
<header class="header-desktop3 d-none d-lg-block">
    <div class="section__content section__content--p35">
        <div class="header3-wrap">
            <!-- Logo -->
            <div class="header__logo">
                <a href="index.php">
                    <h4 class="text-white"><?=$website_name?></h4>
                </a>
            </div>
            <!-- Navigation Menu -->
            <div class="header__navbar">
                <ul class="list-unstyled">
                    <!-- Products Control Panel -->
                    <li>
                        <a href="index.php">
                            <i class="fas fa-th-list"></i>
                            <span class="bot-line"></span>Products Control Panel
                        </a>
                    </li>
                    <!-- Order Stock Link -->
                    <li>
                        <a href="order_list.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="bot-line"></span>Order Stock
                        </a>
                    </li>
                    <!-- Stock Suppliers (Visible to admin users) -->
                    <?php if ($user_details['admin'] != 0): ?>
                    <li>
                        <a href="stock_suppliers.php">
                            <i class="fas fa-th-list"></i>
                            <span class="bot-line"></span>Stock Suppliers
                        </a>
                    </li>
                    <?php endif; ?>
                    <!-- Users Control Panel and Categories Control Panel (Visible to super admin users) -->
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
                    <!-- Removed extra empty conditional statement -->
                </ul>
            </div>
            <!-- Header Tools -->
            <div class="header__tool">
                <!-- Add your existing header tool content here -->
                <!-- Example: notifications, messages, account dropdown -->
                <!-- ... -->
                <!-- Account Wrap -->
                <div class="account-wrap">
                    <!-- Account Item -->
                    <div class="account-item account-item--style2 clearfix js-item-menu">
                        <div class="image">
                            <img src="assets/images/icon/avatar.png" alt="User Avatar" />
                        </div>
                        <div class="content">
                            <a class="js-acc-btn" href="#"><?=$user_details['username']?></a>
                        </div>
                        <div class="account-dropdown js-dropdown">
                            <!-- Account Info -->
                            <div class="info clearfix">
                                <div class="image">
                                    <a href="#">
                                        <img src="assets/images/icon/avatar.png" alt="User Avatar" />
                                    </a>
                                </div>
                                <div class="content">
                                    <h5 class="name">
                                        <a href="#"><?=$user_details['username']?></a>
                                    </h5>
                                    <span class="email"><?=$user_details['email']?></span>
                                </div>
                            </div>
                            <!-- Account Dropdown Body -->
                            <div class="account-dropdown__body">
                                <div class="account-dropdown__item">
                                    <a href="#" type="button" class="changePassword">
                                        <i class="zmdi zmdi-password"></i>Change Password</a>
                                </div>
                            </div>
                            <!-- Account Dropdown Footer -->
                            <div class="account-dropdown__footer">
                                <a href="logout.php">
                                    <i class="zmdi zmdi-power"></i>Logout</a>
                            </div>
                        </div>
                    </div>
                    <!-- End Account Item -->
                </div>
                <!-- End Account Wrap -->
            </div>
            <!-- End Header Tools -->
        </div>
    </div>
</header>
<!-- END HEADER DESKTOP-->

<!-- HEADER MOBILE-->
<header class="header-mobile header-mobile-2 d-block d-lg-none">
    <!-- Mobile Header Bar -->
    <div class="header-mobile__bar">
        <div class="container-fluid">
            <div class="header-mobile-inner">
                <a class="logo" href="index.php">
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
    <!-- Mobile Navigation Menu -->
    <nav class="navbar-mobile">
        <div class="container-fluid">
            <ul class="navbar-mobile__list list-unstyled">
                <!-- Products Control Panel -->
                <li>
                    <a href="index.php">
                        <i class="fas fa-th-list"></i>
                        <span class="bot-line"></span>Products Control Panel
                    </a>
                </li>
                <!-- Order Stock Link -->
                <li>
                    <a href="order_list.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="bot-line"></span>Order Stock
                    </a>
                </li>
                <!-- Stock Suppliers (Visible to admin users) -->
                <?php if ($user_details['admin'] != 0): ?>
                <li>
                    <a href="stock_suppliers.php">
                        <i class="fas fa-th-list"></i>
                        <span class="bot-line"></span>Stock Suppliers
                    </a>
                </li>
                <?php endif; ?>
                <!-- Users Control Panel and Categories Control Panel (Visible to super admin users) -->
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
                <!-- New Account Link (If needed) -->
                <!-- Uncomment if you wish to include the New Account link -->
                <!-- <li>
                    <a href="new_account.php">
                        <i class="fas fa-user-plus"></i>
                        <span class="bot-line"></span>New Account
                    </a>
                </li> -->
            </ul>
        </div>
    </nav>
    <!-- Mobile Sub-header -->
    <div class="sub-header-mobile-2 d-block d-lg-none">
        <div class="header__tool">
            <!-- Add your existing mobile header tool content here -->
            <!-- Example: notifications, messages, account dropdown -->
            <!-- ... -->
            <!-- Account Wrap -->
            <div class="account-wrap">
                <!-- Account Item -->
                <div class="account-item account-item--style2 clearfix js-item-menu">
                    <div class="image">
                        <img src="assets/images/icon/avatar.png" alt="User Avatar" />
                    </div>
                    <div class="content">
                        <a class="js-acc-btn" href="#"><?=$user_details['username']?></a>
                    </div>
                    <div class="account-dropdown js-dropdown">
                        <!-- Account Info -->
                        <div class="info clearfix">
                            <div class="image">
                                <a href="#">
                                    <img src="assets/images/icon/avatar.png" alt="User Avatar" />
                                </a>
                            </div>
                            <div class="content">
                                <h5 class="name">
                                    <a href="#"><?=$user_details['username']?></a>
                                </h5>
                                <span class="email"><?=$user_details['email']?></span>
                            </div>
                        </div>
                        <!-- Account Dropdown Body -->
                        <div class="account-dropdown__body">
                            <div class="account-dropdown__item">
                                <a href="#" type="button" class="changePassword">
                                    <i class="zmdi zmdi-password"></i>Change Password</a>
                            </div>
                        </div>
                        <!-- Account Dropdown Footer -->
                        <div class="account-dropdown__footer">
                            <a href="logout.php">
                                <i class="zmdi zmdi-power"></i>Logout</a>
                            </div>
                        </div>
                    </div>
                    <!-- End Account Item -->
                </div>
            </div>
            <!-- End Account Wrap -->
        </div>
    </div>
</header>
<!-- END HEADER MOBILE -->
