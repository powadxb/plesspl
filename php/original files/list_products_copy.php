<?php
require 'bootstrap.php';

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

$where_str = '';
$where_query = [];


if(isset($_POST['search_query']) && !empty($_POST['search_query']) && $_POST['search_type']=='general'){
  $search_query = trim($_POST['search_query']);
  $search_query_list = explode(" ",$search_query);
  foreach($search_query_list as $search_word){
    if(!empty($where_str)) $where_str .= ' AND ';
    $search_word = '%'.trim($search_word).'%';
    $where_str .= ' (sku LIKE ? OR  name LIKE ? OR manufacturer LIKE ? OR mpn LIKE ? OR ean LIKE ? OR pos_category LIKE ?) ';
    array_push($where_query, $search_word, $search_word, $search_word, $search_word, $search_word, $search_word);
  }
}

if(isset($_POST['sku_search_query']) && !empty($_POST['sku_search_query']) && $_POST['search_type']=='sku'){
  $search_query = '%'.trim($_POST['sku_search_query']).'%';
  $where_str = ' sku LIKE ? ';
  $where_query[] = $search_query;
}
  
if(isset($_POST['enabled_products']) && !empty($_POST['enabled_products']) && $_POST['enabled_products']=='true'){
  if(!empty($where_str)) $where_str .= ' AND ';
  $where_str .= ' enable=? ';
  $where_query[] = 'y';
}

if(isset($_POST['export_to_magento']) && !empty($_POST['export_to_magento']) && $_POST['export_to_magento']=='true'){
  if(!empty($where_str)) $where_str .= ' AND ';
  $where_str .= ' export_to_magento=? ';
  $where_query[] = 'y';
}

$sort_by = '';
if(isset($_POST['sort_col']) && !empty($_POST['sort_col'])){
  $sort_by = $_POST['sort_col'];
}

if(!empty($where_str)){
  $all_records = $DB->query(" SELECT * FROM master_products WHERE {$where_str} {$sort_by}", $where_query);
}else{
  $all_records = $DB->query(" SELECT * FROM master_products {$sort_by}");
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
  foreach($records as $row): ?>
<?php 
$tax_rate = 0;
if(isset($tax_rates[$row['tax_rate_id']])) $tax_rate = $tax_rates[$row['tax_rate_id']];

$row['price'] = ($row['price'] * $tax_rate) + $row['price'];
$row['price'] = round($row['price'], 2);
$row['trade'] = ($row['trade'] * $tax_rate) + $row['trade'];
$row['trade'] = round($row['trade'], 2)
?>
<tr>
  <td><?=$row['sku']?></td>
  <td style="max-width:300px;min-width:300px;">
    <!--<span data-toggle="tooltip" data-placement="top" title='<?=$row['name']?>'><?=substr($row['name'], 0, 30)?>...</span>-->
    <div style="font-size:13px;white-space: normal;word-break: break-word;width: 100%;"><?=$row['name']?></div>
  </td>
  <td><?=$row['manufacturer'] ?></td>
  <td><?=$row['mpn']?></td>
  <td><?=$row['pos_category']?></td>
  <td><?=$row['ean']?></td>
  <td class="text-right text-primary">£<?=number_format($row['price'], 2)?></td>
  <td class="text-right text-success">£<?=number_format($row['trade'], 2)?></td>
  <?php if ($user_details['admin'] != 0): ?>
  <td>
    <?php if($row['pricing_method']==0): ?>
    Markup on cost
    <?php elseif($row['pricing_method']==1): ?>
    Markup on P'Cost
    <?php elseif($row['pricing_method']==2): ?>
    Fixed Price
    <?php endif; ?>
  <td class="text-right text-danger">£<?=number_format($row['cost'], 2)?></td>
  <td class="text-right">£<?=number_format($row['pricing_cost'], 2)?></td>
  <td class="text-right"><?=number_format($row['retail_markup'], 2)?>%</td>
  <td class="text-right"><?=number_format($row['trade_markup'], 2)?>%</td>
  <td class="text-center">
    <i class="fas fa-edit fa-2x text-info updateRecord" data-sku="<?=$row['sku']?>"></i>
  </td>
  <?php endif; ?>
</tr>
<?php endforeach; ?>
<tr style="display:none;">
  <td colspan="<?=($user_details['admin']!=0)?12:5?>"><?php require 'pagination.php'; ?></td>
</tr>
<?php }else{ ?>
  <td colspan="<?=($user_details['admin']!=0)?12:5?>">
		<p class="text-center"><b class="text-danger">No records to show.</b></p>
	</td>
<?php } ?>

