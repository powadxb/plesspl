<?php
require 'bootstrap.php';
require 'odoo_connection.php';

// Ensure user is logged in and has admin permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['dins_user_id'] ?? 0;
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0] ?? [];

if (empty($user_details) || $user_details['admin'] == 0) {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Initialize parameters
$limit = (int)($_POST['limit'] ?? 20);
$offset = (int)($_POST['offset'] ?? 0);

// Fetch tax rates
$tax_rates = [];
$response = $DB->query("SELECT tax_rate_id, tax_rate FROM tax_rates");
foreach ($response as $row) {
    $tax_rates[$row['tax_rate_id']] = floatval($row['tax_rate']);
}

// Build WHERE clause
$where_conditions = [];
$where_params = [];

// General text search
if (!empty($_POST['search_query']) && $_POST['search_type'] === 'general') {
    $search_words = array_filter(explode(" ", trim($_POST['search_query'])));
    foreach ($search_words as $word) {
        $search_pattern = '%' . trim($word) . '%';
        $where_conditions[] = "(sku LIKE ? OR name LIKE ? OR manufacturer LIKE ? OR mpn LIKE ? OR ean LIKE ? OR pos_category LIKE ?)";
        array_push($where_params, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern);
    }
}

// SKU-specific search
if (!empty($_POST['sku_search_query']) && $_POST['search_type'] === 'sku') {
    $where_conditions[] = "sku LIKE ?";
    $where_params[] = '%' . trim($_POST['sku_search_query']) . '%';
}

// Enabled filter
if (!empty($_POST['enabled_products']) && $_POST['enabled_products'] === 'true') {
    $where_conditions[] = "enable = ?";
    $where_params[] = 'y';
}

// WWW filter
if (!empty($_POST['www_filter']) && $_POST['www_filter'] === 'true') {
    $where_conditions[] = "export_to_magento = ?";
    $where_params[] = 'y';
}

// Category filter
if (!empty($_POST['category'])) {
    $where_conditions[] = "pos_category = ?";
    $where_params[] = $_POST['category'];
}

// Build the query
$query = "SELECT * FROM master_products";
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(' AND ', $where_conditions);
}

// Add sorting
$sort_by = $_POST['sort_col'] ?? '';
if (!empty($sort_by)) {
    $query .= " " . $sort_by;
}

// Fetch all records for filtering
$all_records = $DB->query($query, $where_params);

// Update the colspan for "no records found"
$total_columns = $user_details['admin'] >= 1 ? 22 : 21; // Add 1 for admin checkbox column

if (empty($all_records)) {
    echo '<tr><td colspan="'.$total_columns.'" class="text-center"><b class="text-danger">No records found.</b></td></tr>';
    exit();
}

// Get Odoo quantities for all SKUs
$skus = array_column($all_records, 'sku');
$cs_quantities = getOdooQuantities($skus, 12); // CS warehouse
$as_quantities = getOdooQuantities($skus, 19); // AS warehouse

// Apply stock filters
$filtered_records = [];
foreach ($all_records as $row) {
    $cs_qty = floatval($cs_quantities[$row['sku']] ?? 0);
    $as_qty = floatval($as_quantities[$row['sku']] ?? 0);
    
    // Default to including the record
    $include = true;

    // CS Stock Filters
    if (!empty($_POST['cs_negative_stock']) && $_POST['cs_negative_stock'] === 'true') {
        if ($cs_qty >= 0) $include = false;
    }
    if (!empty($_POST['cs_zero_stock']) && $_POST['cs_zero_stock'] === 'true') {
        if ($cs_qty !== 0) $include = false;
    }
    if (!empty($_POST['cs_above_stock']) && $_POST['cs_above_stock'] === 'true') {
        $threshold = floatval($_POST['cs_above_value'] ?? 0);
        if ($cs_qty < $threshold) $include = false;
    }
    if (!empty($_POST['cs_below_stock']) && $_POST['cs_below_stock'] === 'true') {
        $threshold = floatval($_POST['cs_below_value'] ?? 0);
        if ($cs_qty > $threshold) $include = false;
    }

    // AS Stock Filters
    if (!empty($_POST['as_negative_stock']) && $_POST['as_negative_stock'] === 'true') {
        if ($as_qty >= 0) $include = false;
    }
    if (!empty($_POST['as_zero_stock']) && $_POST['as_zero_stock'] === 'true') {
        if ($as_qty !== 0) $include = false;
    }
    if (!empty($_POST['as_above_stock']) && $_POST['as_above_stock'] === 'true') {
        $threshold = floatval($_POST['as_above_value'] ?? 0);
        if ($as_qty < $threshold) $include = false;
    }
    if (!empty($_POST['as_below_stock']) && $_POST['as_below_stock'] === 'true') {
        $threshold = floatval($_POST['as_below_value'] ?? 0);
        if ($as_qty > $threshold) $include = false;
    }

    if ($include) {
        $filtered_records[] = $row;
    }
}

// Pagination
$total_filtered = count($filtered_records);
$total_pages = ceil($total_filtered / $limit);
$current_page = floor($offset / $limit) + 1;
$records = array_slice($filtered_records, $offset, $limit);

// Display records
foreach ($records as $row) {
    // Calculate stock values
    $cs_qty = floatval($cs_quantities[$row['sku']] ?? 0);
    $as_qty = floatval($as_quantities[$row['sku']] ?? 0);
    
    // Ensure all values are properly typed
    $row['cost'] = floatval($row['cost']);
    $row['price'] = floatval($row['price']);
    $row['trade'] = floatval($row['trade']);
    
    // Calculate stock values
    $cs_total = round($cs_qty * $row['cost'], 2);
    $as_total = round($as_qty * $row['cost'], 2);
    $combined_total = round($cs_total + $as_total, 2);

    // Apply VAT calculations
    $tax_rate = floatval($tax_rates[$row['tax_rate_id']] ?? 0);
    $price_with_vat = round($row['price'] * (1 + $tax_rate), 2);
    $trade_with_vat = round($row['trade'] * (1 + $tax_rate), 2);
    ?>
    <tr>
        <?php if ($user_details['admin'] >= 1): ?>
        <td class="text-center">
            <input type="checkbox" class="count-checkbox" value="<?= htmlspecialchars($row['sku']) ?>">
        </td>
        <?php endif; ?>
        <td class="text-center">
            <input type="checkbox" class="enable-toggle" 
                   data-sku="<?= htmlspecialchars($row['sku']) ?>" 
                   <?= ($row['enable'] === 'y') ? 'checked' : '' ?> 
                   disabled>
        </td>
        <td class="text-center">
            <input type="checkbox" class="www-toggle" 
                   data-sku="<?= htmlspecialchars($row['sku']) ?>" 
                   <?= ($row['export_to_magento'] === 'y') ? 'checked' : '' ?> 
                   disabled>
        </td>
        <td><?= htmlspecialchars($row['sku']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['manufacturer']) ?></td>
        <td><?= htmlspecialchars($row['mpn']) ?></td>
        <td><?= htmlspecialchars($row['pos_category']) ?></td>
        <td><?= htmlspecialchars($row['ean']) ?></td>
        <td class="text-right">£<?= number_format($price_with_vat, 2) ?></td>
        <td class="text-right">£<?= number_format($trade_with_vat, 2) ?></td>
        <td class="text-right <?= $cs_qty < 0 ? 'text-danger' : '' ?>"><?= number_format($cs_qty) ?></td>
        <td class="text-right <?= $as_qty < 0 ? 'text-danger' : '' ?>"><?= number_format($as_qty) ?></td>
        <td><?= getPricingMethodText($row['pricing_method']) ?></td>
        <td class="text-right">£<?= number_format($row['cost'], 2) ?></td>
        <td class="text-right">£<?= number_format($row['pricing_cost'], 2) ?></td>
        <td class="text-right"><?= number_format($row['retail_markup'], 2) ?>%</td>
        <td class="text-right"><?= number_format($row['trade_markup'], 2) ?>%</td>
        <td class="text-right">£<?= number_format($cs_total, 2) ?></td>
        <td class="text-right">£<?= number_format($as_total, 2) ?></td>
        <td class="text-right">£<?= number_format($combined_total, 2) ?></td>
        <td class="text-center">
            <i class="fas fa-edit updateRecord" data-sku="<?= htmlspecialchars($row['sku']) ?>"></i>
        </td>
    </tr>
    <?php
}

// Helper function for pricing method text
function getPricingMethodText($method) {
    switch ($method) {
        case 0: return "Markup on cost";
        case 1: return "Markup on P'Cost";
        case 2: return "Fixed Price";
        default: return "Unknown";
    }
}

// Pagination info
?>
<tr id="PaginationInfoResponse" style="display:none;">
    <td colspan="<?= $total_columns ?>">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_filtered) ?> 
                of <?= $total_filtered ?> filtered entries (Total: <?= count($all_records) ?>)
            </div>
            <?php if ($total_pages > 1): ?>
            <ul class="pagination mb-0">
                <?php
                // Previous button
                if ($current_page > 1): ?>
                    <li class="page-item">
                        <a href="#" class="page-link recordsPage" 
                           data-limit="<?= $limit ?>" 
                           data-offset="<?= ($current_page - 2) * $limit ?>">
                            &laquo;
                        </a>
                    </li>
                <?php endif;

                // Calculate range of pages to show
                $range = 2;
                $start_page = max($current_page - $range, 1);
                $end_page = min($current_page + $range, $total_pages);

                // First page
                if ($start_page > 1) {
                    echo '<li class="page-item">
                            <a href="#" class="page-link recordsPage" 
                               data-limit="'.$limit.'" 
                               data-offset="0">1</a>
                          </li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // Page numbers
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item '.($current_page == $i ? 'active' : '').'">
                            <a href="#" class="page-link recordsPage" 
                               data-limit="'.$limit.'" 
                               data-offset="'.(($i - 1) * $limit).'">'.$i.'</a>
                          </li>';
                }

                // Last page
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item">
                            <a href="#" class="page-link recordsPage" 
                               data-limit="'.$limit.'" 
                               data-offset="'.(($total_pages - 1) * $limit).'">'.$total_pages.'</a>
                          </li>';
                }

                // Next button
                if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a href="#" class="page-link recordsPage" 
                           data-limit="<?= $limit ?>" 
                           data-offset="<?= $current_page * $limit ?>">
                            &raquo;
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>
    </td>
</tr>