<?php
require 'bootstrap.php';

// Add the permission checking function
if (!function_exists('hasPermission')) {
    function hasPermission($user_id, $permission_name, $DB) {
        $result = $DB->query(
            "SELECT COUNT(*) as count FROM user_permissions WHERE user_id = ? AND page = ? AND has_access = 1", 
            [$user_id, $permission_name]
        );
        return $result[0]['count'] > 0;
    }
}

$limit = (int)$_POST['limit'];
$offset = (int)$_POST['offset'];
$next_offset = $offset + $limit;

// get tax rates
$tax_rates = [];
$response = $DB->query(" SELECT * FROM tax_rates ");
if(!empty($response)){
  foreach($response as $row){
    $tax_rates[$row['tax_rate_id']] = $row['tax_rate'];
  }
}

$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check if user has permission to edit stock status
$can_edit_stock_status = hasPermission($user_details['id'], 'edit_stock_status', $DB);

// Check if user has permission to view supplier prices
$can_view_supplier_prices = hasPermission($user_details['id'], 'view_supplier_prices', $DB);

$where_str = '';
$where_query = [];

if(isset($_POST['search_query']) && !empty($_POST['search_query']) && $_POST['search_type']=='general'){
  $search_query = trim($_POST['search_query']);
  $search_query_list = explode(" ",$search_query);
  foreach($search_query_list as $search_word){
    if(!empty($where_str)) $where_str .= ' AND ';
    $search_word = '%'.trim($search_word).'%';
    $where_str .= ' (mp.sku LIKE ? OR mp.name LIKE ? OR mp.manufacturer LIKE ? OR mp.mpn LIKE ? OR mp.ean LIKE ? OR mp.pos_category LIKE ?) ';
    array_push($where_query, $search_word, $search_word, $search_word, $search_word, $search_word, $search_word);
  }
}

if(isset($_POST['sku_search_query']) && !empty($_POST['sku_search_query']) && $_POST['search_type']=='sku'){
  $search_query = '%'.trim($_POST['sku_search_query']).'%';
  $where_str = ' mp.sku LIKE ? ';
  $where_query[] = $search_query;
}
  
if(isset($_POST['enabled_products']) && !empty($_POST['enabled_products']) && $_POST['enabled_products']=='true'){
  if(!empty($where_str)) $where_str .= ' AND ';
  $where_str .= ' mp.enable=? ';
  $where_query[] = 'y';
}

if(isset($_POST['export_to_magento']) && !empty($_POST['export_to_magento']) && $_POST['export_to_magento']=='true'){
  if(!empty($where_str)) $where_str .= ' AND ';
  $where_str .= ' mp.export_to_magento=? ';
  $where_query[] = 'y';
}

// Handle category filtering
$category_filters = isset($_POST['category_filters']) && is_array($_POST['category_filters']) ? $_POST['category_filters'] : [];

if (!empty($category_filters)) {
    $category_filters = array_map('trim', $category_filters);
    $category_filters = array_filter($category_filters, function($cat) { return !empty($cat); });
    
    if (!empty($category_filters)) {
        if (!empty($where_str)) $where_str .= ' AND ';
        $placeholders = str_repeat('?,', count($category_filters) - 1) . '?';
        $where_str .= "mp.pless_main_category IN ($placeholders)";
        $where_query = array_merge($where_query, $category_filters);
    }
}

// Handle stock status filtering
$in_stock_filter = isset($_POST['in_stock_filter']) ? $_POST['in_stock_filter'] === 'true' : true;
$out_of_stock_filter = isset($_POST['out_of_stock_filter']) ? $_POST['out_of_stock_filter'] === 'true' : false;

if (!$in_stock_filter && !$out_of_stock_filter) {
  if (!empty($where_str)) $where_str .= ' AND ';
  $where_str .= '1 = 0';
} elseif ($in_stock_filter && !$out_of_stock_filter) {
  if (!empty($where_str)) $where_str .= ' AND ';
  $where_str .= 'mp.stock_status = 1';
} elseif (!$in_stock_filter && $out_of_stock_filter) {
  if (!empty($where_str)) $where_str .= ' AND ';
  $where_str .= 'mp.stock_status = 0';
}

// Handle supplier price comparison toggle - only if user has permission
$show_supplier_prices = false;
if ($can_view_supplier_prices && isset($_POST['show_supplier_prices']) && $_POST['show_supplier_prices'] === 'true') {
    $show_supplier_prices = true;
}

// Build query based on whether supplier prices are requested
if ($show_supplier_prices) {
    // Query WITH supplier comparison (LEFT JOIN to get best supplier price by EAN)
    $select_clause = "mp.*, 
        MIN(ass.cost) as best_supplier_cost,
        COUNT(DISTINCT ass.supplier) as supplier_count,
        GROUP_CONCAT(DISTINCT ass.supplier ORDER BY ass.cost SEPARATOR ', ') as suppliers_with_stock";
    $from_clause = "master_products mp 
        LEFT JOIN all_supplier_stock ass ON mp.ean = ass.ean 
            AND mp.ean != '' 
            AND mp.ean IS NOT NULL 
            AND ass.ean != '' 
            AND ass.ean IS NOT NULL";
    $group_by = " GROUP BY mp.sku";
} else {
    // Regular query without supplier data
    $select_clause = "mp.*";
    $from_clause = "master_products mp";
    $group_by = "";
}

$sort_by = '';
if(isset($_POST['sort_col']) && !empty($_POST['sort_col'])){
  $sort_by = $_POST['sort_col'];
}

if(!empty($where_str)){
  $all_records = $DB->query(" SELECT {$select_clause} FROM {$from_clause} WHERE {$where_str} {$group_by} {$sort_by}", $where_query);
}else{
  $all_records = $DB->query(" SELECT {$select_clause} FROM {$from_clause} {$group_by} {$sort_by}");
}

if(!empty($all_records)){
  $count = 0;
	$records = [];
	foreach ($all_records as $record_key => $record) {
		if($count<$limit && $record_key>=$offset && $record_key<$next_offset){
			$records[] = $record;
			$count++;
		}
	}
  
  // Price alert threshold - only show colored alerts if difference is 50p or more
  $PRICE_ALERT_THRESHOLD = 0.50;
  
  foreach($records as $row): ?>
<?php 
$tax_rate = 0;
if(isset($tax_rates[$row['tax_rate_id']])) $tax_rate = $tax_rates[$row['tax_rate_id']];

$row['price'] = ($row['price'] * $tax_rate) + $row['price'];
$row['price'] = round($row['price'], 2);
$row['trade'] = ($row['trade'] * $tax_rate) + $row['trade'];
$row['trade'] = round($row['trade'], 2);
?>
<tr>
  <?php if ($user_details['admin'] != 0): ?>
  <td class="text-center admin-column">
    <i class="fas fa-edit fa-2x text-info updateRecord" data-sku="<?=$row['sku']?>"></i>
  </td>
  <?php endif; ?>
  <td class="text-center">
    <?php if ($can_edit_stock_status): ?>
      <input type="checkbox" class="stock-checkbox" 
             data-sku="<?=$row['sku']?>" 
             <?=($row['stock_status']==1)?'checked':''?>
             style="transform: scale(1.2); cursor: pointer;">
    <?php else: ?>
      <span style="color: <?=($row['stock_status']==1)?'#28a745':'#dc3545'?>; font-weight: 600;">
        <?=($row['stock_status']==1)?'Yes':'No'?>
      </span>
    <?php endif; ?>
  </td>
  <td><?=$row['sku']?></td>
  <td style="max-width:300px;min-width:300px;">
    <div style="font-size:13px;white-space: normal;word-break: break-word;width: 100%;"><?=$row['name']?></div>
  </td>
  <td><?=$row['manufacturer'] ?></td>
  <td><?=$row['mpn']?></td>
  <td><?=$row['pos_category']?></td>
  <td><?=$row['ean']?></td>
  <td class="text-right text-primary">&pound;<?=number_format($row['price'], 2)?></td>
  <td class="text-right text-success">&pound;<?=number_format($row['trade'], 2)?></td>
  <?php if ($user_details['admin'] != 0): ?>
  <td class="admin-column">
    <?php if($row['pricing_method']==0): ?>
    Markup on cost
    <?php elseif($row['pricing_method']==1): ?>
    Markup on P'Cost
    <?php elseif($row['pricing_method']==2): ?>
    Fixed Price
    <?php endif; ?>
  </td>
  <td class="text-right text-danger admin-column">&pound;<?=number_format($row['cost'], 2)?></td>
  
  <?php if ($show_supplier_prices): ?>
  <td class="text-right supplier-price-column admin-column" style="min-width: 110px;">
    <?php if (!empty($row['best_supplier_cost']) && $row['best_supplier_cost'] > 0): 
        $price_diff = $row['cost'] - $row['best_supplier_cost'];
        $price_diff_percent = ($row['cost'] > 0) ? (($price_diff / $row['cost']) * 100) : 0;
        
        // Only apply color coding if difference exceeds threshold
        if ($price_diff > $PRICE_ALERT_THRESHOLD) {
            // Your cost is significantly HIGHER - RED ALERT
            $color_class = 'text-danger';
            $icon = '⬆';
            $font_weight = '700';
            $bg_color = 'background-color: #fee;';
        } elseif ($price_diff < -$PRICE_ALERT_THRESHOLD) {
            // Your cost is significantly LOWER - GREEN (good deal)
            $color_class = 'text-success';
            $icon = '⬇';
            $font_weight = '700';
            $bg_color = 'background-color: #efe;';
        } else {
            // Difference is within threshold - neutral gray (no action needed)
            $color_class = 'text-secondary';
            $icon = '';
            $font_weight = '400';
            $bg_color = '';
        }
    ?>
      <div style="<?=$bg_color?> padding: 2px;">
        <span class="<?=$color_class?>" style="font-weight: <?=$font_weight?>;">
          &pound;<?=number_format($row['best_supplier_cost'], 2)?>
          <?php if (!empty($icon)): ?><span style="font-size: 11px;"><?=$icon?></span><?php endif; ?>
        </span>
        <?php if (abs($price_diff) > $PRICE_ALERT_THRESHOLD): ?>
        <br>
        <small class="<?=$color_class?>" style="font-size: 9px; font-weight: 600;">
          <?=($price_diff > 0) ? '+' : ''?>&pound;<?=number_format($price_diff, 2)?> (<?=number_format($price_diff_percent, 1)?>%)
        </small>
        <?php endif; ?>
        <br>
        <small style="font-size: 8px; color: #666;" title="<?=htmlspecialchars($row['suppliers_with_stock'] ?? '')?>">
          <?php 
          $supplier_text = $row['suppliers_with_stock'] ?? '';
          echo substr($supplier_text, 0, 15);
          echo (strlen($supplier_text) > 15) ? '...' : '';
          ?>
          <?php if (!empty($row['supplier_count']) && $row['supplier_count'] > 1): ?>
          (<?=$row['supplier_count']?>)
          <?php endif; ?>
        </small>
      </div>
    <?php else: ?>
      <span style="color: #999; font-size: 10px;">No match</span>
    <?php endif; ?>
  </td>
  <?php endif; ?>
  
  <td class="text-right admin-column">&pound;<?=number_format($row['pricing_cost'], 2)?></td>
  <td class="text-right admin-column"><?=number_format($row['retail_markup'], 2)?>%</td>
  <td class="text-right admin-column"><?=number_format($row['trade_markup'], 2)?>%</td>
  <?php endif; ?>
</tr>
<?php endforeach; ?>
<tr style="display:none;">
  <td colspan="<?=($show_supplier_prices ? 1 : 0) + ($user_details['admin']!=0)?14:9?>"><?php require 'pagination.php'; ?></td>
</tr>
<?php } else { ?>
<tr>
  <td colspan="<?=($show_supplier_prices ? 1 : 0) + ($user_details['admin']!=0)?14:9?>">
		<p class="text-center"><b class="text-danger">No records to show.</b></p>
	</td>
</tr>
<?php } ?>