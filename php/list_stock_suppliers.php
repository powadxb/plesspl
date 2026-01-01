<?php
require 'bootstrap.php';
$limit = (int)$_POST['limit'];
$offset = (int)$_POST['offset'];
$next_offset = $offset + $limit;
$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];
$where_str = '';
$where_query = [];

// Handle search query
if(isset($_POST['search_query']) && !empty($_POST['search_query'])){
  $search_query = trim($_POST['search_query']);
  $search_query_list = explode(" ",$search_query);
  foreach($search_query_list as $search_word){
    if(!empty($where_str)) $where_str .= ' AND ';
    $search_word = '%'.trim($search_word).'%';
    $where_str .= ' (sku LIKE ? OR  name LIKE ? OR category LIKE ? OR manufacturer LIKE ? OR mpn LIKE ? OR ean LIKE ? OR supplier LIKE ? OR supplier_sku LIKE ?) ';
    array_push($where_query, $search_word, $search_word, $search_word, $search_word, $search_word, $search_word, $search_word, $search_word);
  }
}

// Handle quantity filter
if(isset($_POST['qty_filter']) && $_POST['qty_filter'] == 1){
  if(!empty($where_str)) {
    $where_str .= ' AND ';
  }
  $where_str .= ' qty > 0 ';
}

if(!empty($where_str)) $where_str = "WHERE ({$where_str})";
  
$sort_by = '';
if(isset($_POST['sort_col']) && !empty($_POST['sort_col'])){
  $sort_by = $_POST['sort_col'];
}

if(!empty($where_str)){
  $all_records = $DB->query(" SELECT * FROM all_supplier_stock {$where_str} {$sort_by}", $where_query);
}else{
  $all_records = $DB->query(" SELECT * FROM all_supplier_stock {$sort_by}");
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
  
  // Find cheapest price for each EAN code (only for EANs with multiple suppliers)
  $ean_prices = [];
  foreach($records as $record){
    if(!empty($record['ean']) && $record['cost'] > 0){
      if(!isset($ean_prices[$record['ean']])){
        $ean_prices[$record['ean']] = [];
      }
      $ean_prices[$record['ean']][] = floatval($record['cost']);
    }
  }
  
  // Calculate minimum cost for each EAN (only if there are multiple suppliers)
  $ean_min_cost = [];
  foreach($ean_prices as $ean => $costs){
    if(count($costs) > 1){ // Only track EANs with multiple suppliers
      $ean_min_cost[$ean] = min($costs);
    }
  }
  
  foreach($records as $row): 
    // Check if this is the cheapest supplier for this EAN
    $is_cheapest = false;
    if(!empty($row['ean']) && isset($ean_min_cost[$row['ean']])){
      if(floatval($row['cost']) == $ean_min_cost[$row['ean']]){
        $is_cheapest = true;
      }
    }
  ?>
<?php
$time_recorded = strtotime($row['time_recorded']);
$row_class = $is_cheapest ? 'cheapest-supplier' : '';
?>
<tr class="<?=$row_class?>">
  <!--<td class="text-center">
    <div class="form-check">
      <div class="checkbox">
        <label class="form-check-label ">
          <input type="checkbox" class="form-check-input recordCheckBox" data-id="<?=$row['id']?>">
        </label>
      </div>
    </div>
  </td>-->
  <td><?=$row['sku']?></td>
  <td style="max-width:300px;min-width:300px;">
    <!--<span data-toggle="tooltip" data-placement="top" title='<?=$row['name']?>'><?=substr($row['name'], 0, 15)?>...</span>-->
    <div style="font-size:13px;white-space: normal;word-break: break-word;width: 100%;"><?=$row['name']?></div>
  </td>
  <td><?=$row['category']?></td>
  <td><?=$row['manufacturer']?></td>
  <td><?=$row['mpn']?></td>
  <td><?=$row['ean']?></td>
  <td class="text-right"><?=$row['qty']?></td>
  <td class="text-right text-danger">£<?=number_format($row['cost'], 2)?></td>
  <td><?=$row['supplier']?></td>
  <td><?=$row['supplier_sku']?></td>
  <td>
    <?php if ((time() - $time_recorded)>60*60*24): ?>
    <span style="color:red"><?=$row['time_recorded']?></span>
    <?php else: ?>
      <?=$row['time_recorded']?>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <i class="fas fa-edit fa-2x text-info updateProduct" data-id="<?=$row['id']?>"></i>
  </td>
</tr>
<?php endforeach; ?>
<tr style="display:none;">
  <td colspan="11"><?php require 'pagination.php'; ?></td>
</tr>
<?php }else{ ?>
  <td colspan="11">
		<p class="text-center"><b class="text-danger">No records to show.</b></p>
	</td>
<?php } ?>