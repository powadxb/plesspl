<?php 
ob_start();
session_start();
$page_title = 'Products Control Panel';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];

// all manufacturers
$all_manufacturers = $DB->query(" SELECT * FROM master_pless_manufacturers ORDER BY manufacturer_name ASC");

// tax_rates
$tax_rates = $DB->query(" SELECT * FROM tax_rates ORDER BY name ASC");

// master_categories - for the modal dropdown (all fields needed)
$categories = $DB->query(" SELECT id, pless_main_category, pos_category FROM master_categories WHERE pless_main_category IS NOT NULL AND pless_main_category != '' ORDER BY pless_main_category ASC");

// Build categories with proper grouping - show second level (storage, memory, etc.) as groups
$categories_grouped = [];
$categories_for_sidebar = $DB->query(" SELECT DISTINCT pless_main_category FROM master_categories WHERE pless_main_category IS NOT NULL AND pless_main_category != '' ORDER BY pless_main_category ASC");

foreach($categories_for_sidebar as $cat) {
    $full_path = trim($cat['pless_main_category']);
    $parts = explode('/', $full_path);
    
    if(count($parts) < 2) continue;
    
    $main_category = trim($parts[0]);
    $main_category_key = strtolower($main_category);
    
    if(!isset($categories_grouped[$main_category_key])) {
        $categories_grouped[$main_category_key] = [
            'display_name' => $main_category,
            'second_level' => []
        ];
    }
    
    if(count($parts) >= 2) {
        $second_level = trim($parts[1]);
        
        if(!isset($categories_grouped[$main_category_key]['second_level'][$second_level])) {
            $categories_grouped[$main_category_key]['second_level'][$second_level] = [];
        }
        
        // If there's a third level, add it under the second level
        if(count($parts) >= 3) {
            $categories_grouped[$main_category_key]['second_level'][$second_level][] = [
                'full_path' => $full_path,
                'display_name' => trim($parts[2])
            ];
        } else {
            // This IS the second level only (like "components/internal cards")
            $categories_grouped[$main_category_key]['second_level'][$second_level][] = [
                'full_path' => $full_path,
                'display_name' => $second_level
            ];
        }
    }
}

// DEBUG OUTPUT - Check what's in components and rendering
if(isset($categories_grouped['components'])) {
    echo "<!-- DEBUG: Components second_level categories:\n";
    foreach($categories_grouped['components']['second_level'] as $key => $items) {
        echo "  - '$key' has " . count($items) . " items\n";
        $will_show_header = (count($items) > 1 || (count($items) === 1 && $items[0]['display_name'] !== $key));
        echo "    Will show as group header: " . ($will_show_header ? 'YES' : 'NO') . "\n";
        foreach($items as $item) {
            echo "    * {$item['full_path']} (display: {$item['display_name']})\n";
        }
    }
    echo "-->\n";
}

// Sort second level categories and their items
foreach($categories_grouped as $key => $data) {
    ksort($categories_grouped[$key]['second_level']);
    foreach($categories_grouped[$key]['second_level'] as $second_key => $items) {
        usort($categories_grouped[$key]['second_level'][$second_key], function($a, $b) {
            return strcmp($a['display_name'], $b['display_name']);
        });
    }
}

// Check if user has permission to view supplier prices
$can_view_supplier_prices = $DB->query(
    "SELECT COUNT(*) as count FROM user_permissions WHERE user_id = ? AND page = 'view_supplier_prices' AND has_access = 1", 
    [$user_id]
);
$has_supplier_price_permission = !empty($can_view_supplier_prices) && $can_view_supplier_prices[0]['count'] > 0;

// settings
require 'php/settings.php';
?>

<!-- External Stylesheets -->
<link rel="stylesheet" href="assets/css/tables.css?<?=time()?>">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/products.css?<?=time()?>">

<style>
.supplier-price-header {
    background-color: #7c3aed !important;
    color: white !important;
}

.supplier-price-column {
    font-size: 10px !important;
    vertical-align: middle !important;
}

.second-level-group {
    font-weight: 600;
    padding-left: 0.8rem;
    margin-top: 0.2rem;
    color: #374151;
}
</style>

<div class="page-wrapper">
    <?php require 'assets/navbar.php' ?>
    
    <div class="page-layout">
        <!-- Category Sidebar -->
        <div class="category-sidebar" id="categorySidebar">
            <div class="sidebar-header">
                <h3 class="sidebar-title">Categories</h3>
            </div>
            
            <div class="selected-categories" id="selectedCategories"></div>
            
            <div class="category-filters">
                <button class="clear-filters">
                    <i class="fas fa-times"></i> Clear All Filters
                </button>

                <?php foreach($categories_grouped as $main_category_key => $category_data): ?>
                <div class="category-group">
                    <div class="main-category" data-category="<?=str_replace(' ', '_', strtolower($category_data['display_name']))?>">
                        <i class="fas fa-chevron-right category-icon"></i>
                        <?=htmlspecialchars($category_data['display_name'])?>
                    </div>
                    <div class="subcategories" id="<?=str_replace(' ', '_', strtolower($category_data['display_name']))?>">
                        <?php 
                        $counter = 0;
                        foreach($category_data['second_level'] as $second_name => $items): 
                            $counter++;
                            echo "<!-- Rendering #$counter: $second_name with " . count($items) . " items -->\n";
                        ?>
                            <?php if(count($items) > 1 || (count($items) === 1 && $items[0]['display_name'] !== $second_name)): ?>
                                <!-- This second level has children - show as a group header -->
                                <div class="second-level-group"><?=htmlspecialchars($second_name)?></div>
                                <?php foreach($items as $item): ?>
                                    <div class="subcategory" data-subcategory="<?=htmlspecialchars($item['full_path'])?>" style="padding-left: 1.3rem;">
                                        <input type="checkbox" class="subcategory-checkbox" id="cat_<?=md5($item['full_path'])?>" value="<?=htmlspecialchars($item['full_path'])?>">
                                        <label for="cat_<?=md5($item['full_path'])?>"><?=htmlspecialchars($item['display_name'])?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- This second level has no children - show it directly as a checkbox -->
                                <div class="subcategory" data-subcategory="<?=htmlspecialchars($items[0]['full_path'])?>" style="padding-left: 0.8rem;">
                                    <input type="checkbox" class="subcategory-checkbox" id="cat_<?=md5($items[0]['full_path'])?>" value="<?=htmlspecialchars($items[0]['full_path'])?>">
                                    <label for="cat_<?=md5($items[0]['full_path'])?>"><?=htmlspecialchars($second_name)?></label>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <!-- Total rendered: <?=$counter?> groups -->
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Mobile category toggle -->
            <div class="mobile-category-toggle">
                <button class="category-toggle-btn" onclick="toggleMobileSidebar()">
                    <i class="fas fa-list"></i> Categories
                </button>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><?=$page_title?></h1>
            </div>

        <!-- Tool Section -->
        <div class="table-data__tool">
            <!-- Top Row - Search Fields -->
            <div class="table-data__tool-search">
                <input type="text" class="au-input au-input--xs search-sku" id="skuSearchQuery" 
                       placeholder="SKU Search" style="background-color: #fed8b1;">
                <input type="text" class="au-input au-input--xs search-main" id="searchQuery" 
                       placeholder="Search Products">
            </div>

            <!-- Bottom Row - Buttons and Filters -->
            <div class="table-data__tool-bottom">
                <div class="table-data__tool-left">
                    <?php if ($user_details['admin'] != 0): ?>
                    <button class="btn btn-success btn-xs" id="newItemBtn">
                        <i class="zmdi zmdi-plus"></i> Add
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($user_details['admin'] != 0): ?>
                <div class="table-data__tool-center">
                    <button class="btn btn-primary btn-xs" id="exportToCsv">
                        <i class="fas fa-file-export"></i> CSV
                    </button>
                    <button class="btn btn-success btn-xs" id="exportToPos">
                        <i class="fas fa-cash-register"></i> POS
                    </button>
                    <button class="btn btn-warning btn-xs" id="exportToMagentoBtn">
                        <i class="fas fa-shopping-cart"></i> MAG
                    </button>
                </div>
                <?php endif; ?>

                <div class="table-data__tool-right">
                    <div class="form-check">
                        <label for="inStockFilter" class="form-check-label">
                            <input type="checkbox" id="inStockFilter" value="in_stock" 
                                   class="form-check-input filterRecords" checked> In Stock
                        </label>
                    </div>
                    <div class="form-check">
                        <label for="outOfStockFilter" class="form-check-label">
                            <input type="checkbox" id="outOfStockFilter" value="out_of_stock" 
                                   class="form-check-input filterRecords" checked> Out of Stock
                        </label>
                    </div>
                    <div class="form-check">
                        <label for="enabledProducts" class="form-check-label">
                            <input type="checkbox" id="enabledProducts" value="enabled" 
                                   class="form-check-input filterRecords" checked> Active
                        </label>
                    </div>
                    <div class="form-check">
                        <label for="exportToMagentoFilter" class="form-check-label">
                            <input type="checkbox" id="exportToMagentoFilter" value="enabled" 
                                   class="form-check-input filterRecords"> WWW
                        </label>
                    </div>
                    <?php if ($user_details['admin'] != 0): ?>
                    <div class="form-check">
                        <label for="showAdminColumns" class="form-check-label">
                            <input type="checkbox" id="showAdminColumns" class="form-check-input"> Admin
                        </label>
                    </div>
                    <?php endif; ?>
                    <?php if ($has_supplier_price_permission): ?>
                    <div class="form-check">
                        <label for="showSupplierPrices" class="form-check-label">
                            <input type="checkbox" id="showSupplierPrices" class="form-check-input filterRecords"> 
                            Supplier $
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive table-responsive-data2">
            <table class="table table-condensed table-striped table-bordered table-hover table-sm pb-2<?=($user_details['admin'] >= 1) ? ' admin-editable' : ''?>">
                <thead>
                    <tr>
                        <?php if ($user_details['admin'] != 0): ?>
                        <th class="admin-column">Edit</th>
                        <?php endif; ?>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="stock_status" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="stock_status" data-order="DESC"></i>
                            </div>
                            Stock Status
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="sku" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="sku" data-order="DESC"></i>
                            </div>
                            SKU
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="name" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="name" data-order="DESC"></i>
                            </div>
                            Name
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="manufacturer" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="manufacturer" data-order="DESC"></i>
                            </div>
                            Manufacturer
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="mpn" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="mpn" data-order="DESC"></i>
                            </div>
                            MPN
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="pos_category" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="pos_category" data-order="DESC"></i>
                            </div>
                            Category
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="ean" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="ean" data-order="DESC"></i>
                            </div>
                            EAN
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="price" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="price" data-order="DESC"></i>
                            </div>
                            Retail inc
                        </th>
                        <th>
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="trade" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="trade" data-order="DESC"></i>
                            </div>
                            Trade inc
                        </th>
                        <?php if ($user_details['admin'] != 0): ?>
                        <th class="admin-column">
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="pricing_method" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="pricing_method" data-order="DESC"></i>
                            </div>
                            Pricing Method
                        </th>
                        <th class="admin-column">
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="cost" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="cost" data-order="DESC"></i>
                            </div>
                            Cost
                        </th>
                        <?php if ($has_supplier_price_permission): ?>
                        <th class="supplier-price-header admin-column" style="display:none; min-width: 110px;">
                            Best Supplier Price
                        </th>
                        <?php endif; ?>
                        <th class="admin-column">
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="pricing_cost" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="pricing_cost" data-order="DESC"></i>
                            </div>
                            P'Cost
                        </th>
                        <th class="admin-column">
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="retail_markup" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="retail_markup" data-order="DESC"></i>
                            </div>
                            Retail %
                        </th>
                        <th class="admin-column">
                            <div class="sortWrap">
                                <i class="fas fa-sort-up sortIcon sortCol sortUp" 
                                   data-col="trade_markup" data-order="ASC"></i>
                                <i class="fas fa-sort-down sortIcon sortCol sortDown" 
                                   data-col="trade_markup" data-order="DESC"></i>
                            </div>
                            Trade %
                        </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="records"></tbody>
            </table>
            <div id="pagination"></div>
        </div>
    </div>
</div>

<!-- Mobile overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileSidebar()"></div>

<!-- Include Modals -->
<?php 
include 'php/modals/add-item-modal.php';
include 'php/modals/update-item-modal.php';
?>

<!-- Hidden form values -->
<input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
<input type="hidden" id="offset" value="0">
<input type="hidden" id="sortCol">

<?php require 'assets/footer.php'; ?>

<!-- External JavaScript -->
<script src="assets/js/products.js?<?=time()?>"></script>