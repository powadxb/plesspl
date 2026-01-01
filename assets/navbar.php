<?php
// Check permissions for stock_suppliers page
$stock_suppliers_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'stock_suppliers'", 
    [$user_id]
);
$has_stock_suppliers_access = !empty($stock_suppliers_access) && $stock_suppliers_access[0]['has_access'];

// Check permissions for zindex (Stock View) page
$stock_view_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'zindex'", 
    [$user_id]
);
$has_stock_view_access = !empty($stock_view_access) && $stock_view_access[0]['has_access'];

// Check permissions for magento_merchandiser page
$merchandiser_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'magento_merchandiser'", 
    [$user_id]
);
$has_merchandiser_access = !empty($merchandiser_access) && $merchandiser_access[0]['has_access'];

// Check permissions for manufacturers page
$manufacturers_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'manufacturers'", 
    [$user_id]
);
$has_manufacturers_access = !empty($manufacturers_access) && $manufacturers_access[0]['has_access'];

// Check permissions for essential management pages
$essential_categories_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_categories'", 
    [$user_id]
);
$has_essential_categories_access = !empty($essential_categories_access) && $essential_categories_access[0]['has_access'];

$essential_product_types_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_product_types'", 
    [$user_id]
);
$has_essential_product_types_access = !empty($essential_product_types_access) && $essential_product_types_access[0]['has_access'];

$essential_mapping_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'essential_mapping'", 
    [$user_id]
);
$has_essential_mapping_access = !empty($essential_mapping_access) && $essential_mapping_access[0]['has_access'];

$has_any_essential_access = $has_essential_categories_access || $has_essential_product_types_access || $has_essential_mapping_access;

// Check permissions for pricelist generation
$pricelist_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'generate_pricelist'", 
    [$user_id]
);
$has_pricelist_access = !empty($pricelist_access) && $pricelist_access[0]['has_access'];

// Check permissions for SecondHand inventory system
$secondhand_view_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-View'", 
    [$user_id]
);
$has_secondhand_view_access = !empty($secondhand_view_access) && $secondhand_view_access[0]['has_access'];

$secondhand_manage_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'SecondHand-Manage'", 
    [$user_id]
);
$has_secondhand_manage_access = !empty($secondhand_manage_access) && $secondhand_manage_access[0]['has_access'];

$has_secondhand_access = $has_secondhand_view_access || $has_secondhand_manage_access;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$website_name ?? 'Your Website'?></title>
    <!-- Include your CSS and Font Awesome files here -->
</head>
<body>
    <!-- HEADER DESKTOP -->
    <header class="header-desktop3 d-none d-lg-block shadow-sm">
        <div class="section__content section__content--p35">
            <div class="header3-wrap">
                <!-- Logo -->
                <div class="header__logo">
                    <a href="index.php" class="d-flex align-items-center text-decoration-none">
                        <h4 class="text-white mb-0 font-weight-bold">
                            <i class="fas fa-layer-group me-2"></i>
                            <?=$website_name ?? 'Your Website'?>
                        </h4>
                    </a>
                </div>
                
                <!-- Navigation Menu -->
                <div class="header__navbar">
                    <ul class="list-unstyled mb-0">
                        <?php if ($has_merchandiser_access || $user_details['admin'] >= 1): ?>
                        <li>
                            <a href="magento_merchandiser.php" class="nav-link d-flex align-items-center">
                                <i class="fas fa-store me-2"></i>
                                Merchandiser
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="has-sub nav-item">
                            <a href="#" class="nav-link d-flex align-items-center">
                                <i class="fas fa-store me-2"></i>
                                Store Operations
                                <i class="fas fa-chevron-down ms-2 small"></i>
                            </a>
                            <ul class="header3-sub-list list-unstyled shadow-lg rounded-3 py-2">
                                <li>
                                    <a href="cctv_quotes.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-video me-2"></i>
                                        <span>CCTV Quote</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="quotes.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-quote-right me-2"></i>
                                        <span>PC Quote</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="courier_log.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-truck me-2"></i>
                                        <span>Courier Log</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="tasks.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-tasks me-2"></i>
                                        <span>Tasks</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="order_list.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        <span>Order Stock</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="count_stock.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-clipboard-check me-2"></i>
                                        <span>Stock Count</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="labels.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-tags me-2"></i>
                                        <span>Labels</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <a href="rma/index.php" class="nav-link d-flex align-items-center">
                                <i class="fas fa-undo me-2"></i>
                                RMA
                            </a>
                        </li>
                        
                        <?php if ($has_secondhand_access): ?>
                        <li class="has-sub">
                            <a href="#" class="nav-link d-flex align-items-center">
                                <i class="fas fa-recycle me-2"></i>
                                Second Hand
                                <i class="fas fa-chevron-down ms-2 small"></i>
                            </a>
                            <ul class="header3-sub-list list-unstyled shadow-lg rounded-3 py-2">
                                <li>
                                    <a href="secondhand/secondhand.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-box me-2"></i>
                                        <span>Inventory</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="secondhand/trade_in_management.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-exchange-alt me-2"></i>
                                        <span>Trade-Ins</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        
                        <li class="has-sub">
                            <a href="#" class="nav-link d-flex align-items-center">
                                <i class="fas fa-th-list me-2"></i>
                                Products Control Panel
                                <i class="fas fa-chevron-down ms-2 small"></i>
                            </a>
                            <ul class="header3-sub-list list-unstyled shadow-lg rounded-3 py-2">
                                <li>
                                    <a href="index.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-th-list me-2"></i>
                                        <span>Product Grid</span>
                                    </a>
                                </li>
                                <?php if ($has_stock_view_access || $user_details['admin'] != 0): ?>
                                <li>
                                    <a href="zindex.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-box-open me-2"></i>
                                        <span>Stock View</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($has_stock_suppliers_access): ?>
                                <li>
                                    <a href="stock_suppliers.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-boxes me-2"></i>
                                        <span>Stock Suppliers</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        
                        <!-- Pricelists -->
                        <?php if ($has_pricelist_access): ?>
                        <li class="has-sub">
                            <a href="#" class="nav-link d-flex align-items-center">
                                <i class="fas fa-file-invoice me-2"></i>
                                Pricelists
                                <i class="fas fa-chevron-down ms-2 small"></i>
                            </a>
                            <ul class="header3-sub-list list-unstyled shadow-lg rounded-3 py-2">
                                <li>
                                    <a href="generate_pricelist.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-file-alt me-2"></i>
                                        <span>Generate Pricelist</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($user_details['admin'] == 2): ?>
                        <li class="has-sub">
                            <a href="#" class="nav-link d-flex align-items-center">
                                <i class="fas fa-cogs me-2"></i>
                                Settings
                                <i class="fas fa-chevron-down ms-2 small"></i>
                            </a>
                            <ul class="header3-sub-list list-unstyled shadow-lg rounded-3 py-2">
                                <li>
                                    <a href="users.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-user-plus me-2"></i>
                                        <span>Users</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="categories.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-sitemap me-2"></i>
                                        <span>Categories</span>
                                    </a>
                                </li>
                                <?php if ($has_manufacturers_access): ?>
                                <li>
                                    <a href="manufacturers.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-industry me-2"></i>
                                        <span>Manufacturers</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <a href="control_panel.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-tools me-2"></i>
                                        <span>Control Panel</span>
                                    </a>
                                </li>
                                
                                <?php if ($has_any_essential_access): ?>
                                <!-- Divider -->
                                <li><hr class="dropdown-divider my-2"></li>
                                
                                <!-- Essential Management Section -->
                                <li class="px-3 py-1">
                                    <small class="text-muted font-weight-bold">ESSENTIAL MANAGEMENT</small>
                                </li>
                                
                                <?php if ($has_essential_categories_access): ?>
                                <li>
                                    <a href="manage_essential_categories.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-sitemap me-2"></i>
                                        <span>Essential Categories</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($has_essential_product_types_access): ?>
                                <li>
                                    <a href="manage_product_types.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-tags me-2"></i>
                                        <span>Product Types</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($has_essential_mapping_access): ?>
                                <li>
                                    <a href="map_products.php" class="d-flex align-items-center px-3 py-2 text-dark hover-highlight">
                                        <i class="fas fa-link me-2"></i>
                                        <span>Map Products</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Header Tools -->
                <div class="header__tool">
                    <div class="account-wrap">
                        <div class="account-item account-item--style2 clearfix js-item-menu">
                            <div class="image rounded-circle overflow-hidden border border-2 border-white">
                                <img src="assets/images/icon/avatar.png" alt="User Avatar" class="img-fluid" />
                            </div>
                            <div class="content ms-2">
                                <a class="js-acc-btn text-white text-decoration-none d-flex align-items-center" href="#">
                                    <?=$user_details['username']?>
                                    <i class="fas fa-chevron-down ms-2 small"></i>
                                </a>
                            </div>
                            <div class="account-dropdown js-dropdown shadow-lg rounded-3">
                                <div class="info clearfix p-3 border-bottom">
                                    <div class="image rounded-circle overflow-hidden border border-2">
                                        <a href="#">
                                            <img src="assets/images/icon/avatar.png" alt="User Avatar" class="img-fluid" />
                                        </a>
                                    </div>
                                    <div class="content ms-2">
                                        <h5 class="name mb-1">
                                            <a href="#" class="text-dark"><?=$user_details['username']?></a>
                                        </h5>
                                        <span class="email text-muted small"><?=$user_details['email']?></span>
                                    </div>
                                </div>
                                <div class="account-dropdown__body p-2">
                                    <div class="account-dropdown__item">
                                        <a href="#" class="changePassword d-flex align-items-center px-3 py-2 text-dark hover-highlight rounded">
                                            <i class="zmdi zmdi-password me-2"></i>
                                            <span>Change Password</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="account-dropdown__footer p-2">
                                    <a href="logout.php" class="d-flex align-items-center px-3 py-2 text-danger hover-highlight rounded">
                                        <i class="zmdi zmdi-power me-2"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- HEADER MOBILE -->
    <header class="header-mobile d-block d-lg-none">
        <div class="header-mobile__bar">
            <div class="container-fluid">
                <div class="header-mobile-inner d-flex justify-content-between align-items-center py-3">
                    <a class="logo" href="index.php">
                        <h4 class="text-white mb-0 font-weight-bold">
                            <i class="fas fa-layer-group me-2"></i>
                            <?=$website_name ?? 'Your Website'?>
                        </h4>
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
                <ul class="list-unstyled m-0 p-0">
                    <!-- Merchandiser (Mobile) -->
                    <?php if ($has_merchandiser_access || $user_details['admin'] >= 1): ?>
                    <li>
                        <a href="magento_merchandiser.php" class="mobile-menu-item">
                            <i class="fas fa-store me-2"></i>
                            <span>Merchandiser</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Store Operations -->
                    <li class="has-sub">
                        <a href="javascript:void(0);" class="js-arrow mobile-menu-item" data-target="store-operations">
                            <i class="fas fa-store me-2"></i>
                            <span>Store Operations</span>
                            <i class="fas fa-chevron-down ms-auto submenu-arrow"></i>
                        </a>
                        <ul class="list-unstyled submenu" id="store-operations">
                            <li>
                                <a href="cctv_quotes.php" class="mobile-menu-subitem">
                                    <i class="fas fa-video me-2"></i>
                                    <span>CCTV Quote</span>
                                </a>
                            </li>
                            <li>
                                <a href="quotes.php" class="mobile-menu-subitem">
                                    <i class="fas fa-quote-right me-2"></i>
                                    <span>PC Quote</span>
                                </a>
                            </li>
                            <li>
                                <a href="courier_log.php" class="mobile-menu-subitem">
                                    <i class="fas fa-truck me-2"></i>
                                    <span>Courier Log</span>
                                </a>
                            </li>
                            <li>
                                <a href="tasks.php" class="mobile-menu-subitem">
                                    <i class="fas fa-tasks me-2"></i>
                                    <span>Tasks</span>
                                </a>
                            </li>
                            <li>
                                <a href="order_list.php" class="mobile-menu-subitem">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    <span>Order Stock</span>
                                </a>
                            </li>
                            <li>
                                <a href="count_stock.php" class="mobile-menu-subitem">
                                    <i class="fas fa-clipboard-check me-2"></i>
                                    <span>Stock Count</span>
                                </a>
                            </li>
                            <li>
                                <a href="labels.php" class="mobile-menu-subitem">
                                    <i class="fas fa-tags me-2"></i>
                                    <span>Labels</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- RMA -->
                    <li>
                        <a href="rma/index.php" class="mobile-menu-item">
                            <i class="fas fa-undo me-2"></i>
                            <span>RMA</span>
                        </a>
                    </li>
                    
                    <!-- Products Control Panel -->
                    <li class="has-sub">
                        <a href="javascript:void(0);" class="js-arrow mobile-menu-item" data-target="products-menu">
                            <i class="fas fa-th-list me-2"></i>
                            <span>Products Control Panel</span>
                            <i class="fas fa-chevron-down ms-auto submenu-arrow"></i>
                        </a>
                        <ul class="list-unstyled submenu" id="products-menu">
                            <li>
                                <a href="index.php" class="mobile-menu-subitem">
                                    <i class="fas fa-th-list me-2"></i>
                                    <span>Product Grid</span>
                                </a>
                            </li>
                            <?php if ($has_stock_view_access || $user_details['admin'] != 0): ?>
                            <li>
                                <a href="zindex.php" class="mobile-menu-subitem">
                                    <i class="fas fa-box-open me-2"></i>
                                    <span>Stock View</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($has_stock_suppliers_access): ?>
                            <li>
                                <a href="stock_suppliers.php" class="mobile-menu-subitem">
                                    <i class="fas fa-boxes me-2"></i>
                                    <span>Stock Suppliers</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- Second-Hand Inventory -->
                    <?php if ($has_secondhand_access): ?>
                    <li class="has-sub">
                        <a href="javascript:void(0);" class="js-arrow mobile-menu-item" data-target="secondhand-menu">
                            <i class="fas fa-recycle me-2"></i>
                            <span>Second Hand</span>
                            <i class="fas fa-chevron-down ms-auto submenu-arrow"></i>
                        </a>
                        <ul class="list-unstyled submenu" id="secondhand-menu">
                            <li>
                                <a href="secondhand/secondhand.php" class="mobile-menu-subitem">
                                    <i class="fas fa-box me-2"></i>
                                    <span>Inventory</span>
                                </a>
                            </li>
                            <li>
                                <a href="secondhand/trade_in_management.php" class="mobile-menu-subitem">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    <span>Trade-Ins</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Pricelists -->
                    <?php if ($has_pricelist_access): ?>
                    <li class="has-sub">
                        <a href="javascript:void(0);" class="js-arrow mobile-menu-item" data-target="pricelists-menu">
                            <i class="fas fa-file-invoice me-2"></i>
                            <span>Pricelists</span>
                            <i class="fas fa-chevron-down ms-auto submenu-arrow"></i>
                        </a>
                        <ul class="list-unstyled submenu" id="pricelists-menu">
                            <li>
                                <a href="generate_pricelist.php" class="mobile-menu-subitem">
                                    <i class="fas fa-file-alt me-2"></i>
                                    <span>Generate Pricelist</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Settings -->
                    <?php if ($user_details['admin'] == 2): ?>
                    <li class="has-sub">
                        <a href="javascript:void(0);" class="js-arrow mobile-menu-item" data-target="settings-menu">
                            <i class="fas fa-cogs me-2"></i>
                            <span>Settings</span>
                            <i class="fas fa-chevron-down ms-auto submenu-arrow"></i>
                        </a>
                        <ul class="list-unstyled submenu" id="settings-menu">
                            <li>
                                <a href="users.php" class="mobile-menu-subitem">
                                    <i class="fas fa-user-plus me-2"></i>
                                    <span>Users</span>
                                </a>
                            </li>
                            <li>
                                <a href="categories.php" class="mobile-menu-subitem">
                                    <i class="fas fa-sitemap me-2"></i>
                                    <span>Categories</span>
                                </a>
                            </li>
                            <?php if ($has_manufacturers_access): ?>
                            <li>
                                <a href="manufacturers.php" class="mobile-menu-subitem">
                                    <i class="fas fa-industry me-2"></i>
                                    <span>Manufacturers</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="control_panel.php" class="mobile-menu-subitem">
                                    <i class="fas fa-tools me-2"></i>
                                    <span>Control Panel</span>
                                </a>
                            </li>
                            
                            <?php if ($has_any_essential_access): ?>
                            <!-- Divider -->
                            <li class="dropdown-divider my-2"></li>
                            
                            <!-- Essential Management Section -->
                            <li class="px-3 py-1">
                                <small class="text-muted font-weight-bold">ESSENTIAL MANAGEMENT</small>
                            </li>
                            
                            <?php if ($has_essential_categories_access): ?>
                            <li>
                                <a href="manage_essential_categories.php" class="mobile-menu-subitem">
                                    <i class="fas fa-sitemap me-2"></i>
                                    <span>Essential Categories</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($has_essential_product_types_access): ?>
                            <li>
                                <a href="manage_product_types.php" class="mobile-menu-subitem">
                                    <i class="fas fa-tags me-2"></i>
                                    <span>Product Types</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($has_essential_mapping_access): ?>
                            <li>
                                <a href="map_products.php" class="mobile-menu-subitem">
                                    <i class="fas fa-link me-2"></i>
                                    <span>Map Products</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <style>
    .header-desktop3 {
        background: linear-gradient(135deg, #4a90e2, #2c3e50);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .header__navbar ul li a {
        color: rgba(255, 255, 255, 0.9);
        transition: all 0.3s ease;
        padding: 8px 16px;
        border-radius: 8px;
    }

    .header__navbar ul li a:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.1);
    }

    .header3-sub-list {
        background: white;
        min-width: 200px;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .hover-highlight:hover {
        background-color: #f8f9fa;
        text-decoration: none;
    }

    .account-dropdown {
        background: white;
        min-width: 280px;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .account-item .image {
        width: 40px;
        height: 40px;
    }

    /* Subtle animation for dropdowns */
    .header3-sub-list, .account-dropdown {
        transform-origin: top;
        animation: dropdownFade 0.2s ease;
    }

    @keyframes dropdownFade {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Enhanced Mobile Menu Styles */
    .header-mobile {
        background: linear-gradient(135deg, #4a90e2, #2c3e50);
        position: relative;
        z-index: 999;
    }

    .header-mobile-inner {
        padding: 10px 15px;
    }

    .navbar-mobile {
        display: none;
        background: white;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-height: 70vh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .navbar-mobile.show {
        display: block;
    }

    .mobile-menu-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: #2c3e50;
        border-bottom: 1px solid #eee;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        user-select: none;
        -webkit-tap-highlight-color: rgba(0,0,0,0.1);
        position: relative;
    }

    .mobile-menu-item:hover,
    .mobile-menu-item:focus,
    .mobile-menu-item:active {
        background-color: #f8f9fa;
        text-decoration: none;
        color: #2c3e50;
    }

    .submenu-arrow {
        transition: transform 0.3s ease;
        font-size: 12px;
    }

    .has-sub.show .submenu-arrow {
        transform: rotate(180deg);
    }

    .submenu {
        display: none;
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }

    .has-sub.show .submenu {
        display: block;
        max-height: 400px;
    }

    .mobile-menu-subitem {
        display: flex;
        align-items: center;
        padding: 12px 20px 12px 40px;
        color: #2c3e50;
        border-bottom: 1px solid #dee2e6;
        text-decoration: none;
        -webkit-tap-highlight-color: rgba(0,0,0,0.1);
        transition: background-color 0.2s ease;
    }

    .mobile-menu-subitem:hover,
    .mobile-menu-subitem:focus,
    .mobile-menu-subitem:active {
        background-color: #e9ecef;
        text-decoration: none;
        color: #2c3e50;
    }

    /* Fix for touch devices */
    @media (max-width: 991px) {
        .mobile-menu-item,
        .mobile-menu-subitem {
            touch-action: manipulation;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    }

    /* Hamburger Button Styles */
    .hamburger {
        padding: 10px;
        display: inline-block;
        cursor: pointer;
        background-color: transparent;
        border: 0;
        margin: 0;
        outline: none;
        touch-action: manipulation;
    }

    .hamburger-inner, 
    .hamburger-inner::before, 
    .hamburger-inner::after {
        background-color: white;
        height: 2px;
        width: 24px;
        position: absolute;
        transition: transform 0.15s ease;
    }

    .hamburger-inner {
        margin-top: -2px;
    }

    .hamburger-inner::before {
        content: '';
        display: block;
        top: -6px;
    }

    .hamburger-inner::after {
        content: '';
        display: block;
        bottom: -6px;
    }

    .hamburger.is-active .hamburger-inner {
        transform: rotate(45deg);
    }

    .hamburger.is-active .hamburger-inner::before {
        transform: rotate(-90deg) translateX(-6px);
    }

    .hamburger.is-active .hamburger-inner::after {
        opacity: 0;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        const navbarMobile = document.querySelector('.navbar-mobile');
        const subMenuTriggers = document.querySelectorAll('.js-arrow');
        
        // Toggle mobile menu
        if (hamburger && navbarMobile) {
            hamburger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.toggle('is-active');
                navbarMobile.classList.toggle('show');
            });
        }
        
        // Toggle submenus with improved touch handling
        subMenuTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parent = this.closest('.has-sub');
                const allSubMenus = document.querySelectorAll('.has-sub');
                
                // Close other submenus
                allSubMenus.forEach(menu => {
                    if (menu !== parent && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                    }
                });
                
                // Toggle current submenu
                parent.classList.toggle('show');
            });
            
            // Prevent touch events from interfering
            trigger.addEventListener('touchstart', function(e) {
                e.stopPropagation();
            });
            
            trigger.addEventListener('touchend', function(e) {
                e.stopPropagation();
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navbarMobile.contains(e.target) && !hamburger.contains(e.target)) {
                navbarMobile.classList.remove('show');
                hamburger.classList.remove('is-active');
            }
        });
        
        // Handle submenu item clicks properly
        const subMenuItems = document.querySelectorAll('.mobile-menu-subitem');
        subMenuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Allow normal navigation for submenu items
                // Close mobile menu after clicking a submenu item
                setTimeout(() => {
                    navbarMobile.classList.remove('show');
                    hamburger.classList.remove('is-active');
                }, 100);
            });
        });
    });
    </script>
</body>
</html>