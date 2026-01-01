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

// get tax rate names
$tax_rate_names = [];
$response = $DB->query(" SELECT * FROM tax_rates ");
if(!empty($response)){
  foreach($response as $row){
    $tax_rate_names[$row['tax_rate_id']] = $row['name'];
  }
}

$user_details = $DB->query(" SELECT * FROM users WHERE id=?", [$user_id])[0];

// Check access permissions
$has_access = false;
if ($user_details['admin'] >= 1) {
    $has_access = true;
} else {
    $has_access = hasPermission($user_details['id'], 'magento_merchandiser', $DB);
}

if (!$has_access) {
    exit('Access denied');
}

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

if(isset($_POST['magento_enabled']) && !empty($_POST['magento_enabled']) && $_POST['magento_enabled']=='true'){
  if(!empty($where_str)) $where_str .= ' AND ';
  $where_str .= ' export_to_magento=? ';
  $where_query[] = 'y';
}

$sort_by = '';
if(isset($_POST['sort_col']) && !empty($_POST['sort_col'])){
  $sort_by = $_POST['sort_col'];
} else {
  $sort_by = 'ORDER BY sku DESC';
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

$ex_vat_price = $row['price'];
$inc_vat_price = ($row['price'] * $tax_rate) + $row['price'];
$inc_vat_price = round($inc_vat_price, 2);

$tax_rate_name = isset($tax_rate_names[$row['tax_rate_id']]) ? $tax_rate_names[$row['tax_rate_id']] : 'N/A';

// Get existing instruction
$instruction_result = $DB->query("SELECT instruction FROM product_instructions WHERE sku = ?", [$row['sku']]);
$existing_instruction = !empty($instruction_result) ? $instruction_result[0]['instruction'] : '';

// Get all existing questions for this product
$questions_result = $DB->query("
    SELECT mq.question, mq.created_at, u.username, mq.status, mq.answer, mq.answered_at
    FROM merchandiser_questions mq 
    LEFT JOIN users u ON mq.user_id = u.id 
    WHERE mq.product_sku = ? 
    ORDER BY mq.created_at DESC
", [$row['sku']]);

$existing_questions = '';
if (!empty($questions_result)) {
    $question_lines = [];
    foreach ($questions_result as $q) {
        $date = date('M j, H:i', strtotime($q['created_at']));
        $status_text = '';
        if ($q['status'] == 'answered' && $q['answer']) {
            $answer_date = $q['answered_at'] ? date('M j, H:i', strtotime($q['answered_at'])) : '';
            $status_text = "\nANSWER ({$answer_date}): {$q['answer']}";
        }
        $question_lines[] = "Q ({$date}) {$q['username']}: {$q['question']}{$status_text}";
    }
    $existing_questions = implode("\n\n", $question_lines);
}
?>
<tr>
  <td><strong><?=$row['sku']?></strong></td>
  <td><?=$row['ean']?></td>
  <td><?=$row['manufacturer']?></td>
  <td><?=$row['mpn']?></td>
  <td style="max-width:250px;min-width:250px;">
    <div style="font-size:13px;white-space: normal;word-break: break-word;width: 100%; user-select: text; cursor: text;"><?=$row['name']?></div>
  </td>
  <td><?=$row['pos_category']?></td>
  <td class="text-right">£<?=number_format($inc_vat_price, 2)?></td>
  <td class="text-right">£<?=number_format($ex_vat_price, 2)?></td>
  <td><?=$tax_rate_name?></td>
  <td style="padding: 3px;">
    <div style="display: flex; gap: 8px; min-width: 480px;">
      <!-- Instructions section -->
      <div style="flex: 1; border: 1px solid #ddd; padding: 3px;">
        <?php if ($user_details['admin'] >= 1): ?>
          <textarea class="instruction-field" data-sku="<?=$row['sku']?>" style="width: 100%; height: 60px; border: 1px solid #ccc; font-size: 0.7em; resize: none;" placeholder="Enter instructions..."><?=htmlspecialchars($existing_instruction)?></textarea>
        <?php else: ?>
          <div class="instruction-display" style="min-height: 60px; font-size: 0.7em; color: #333; padding: 2px; word-wrap: break-word;"><?=!empty($existing_instruction) ? htmlspecialchars($existing_instruction) : 'No instructions set'?></div>
        <?php endif; ?>
      </div>
      
      <!-- Question section -->
      <div style="flex: 1; border: 1px solid #ddd; padding: 3px;">
        <form class="product-question-form" data-sku="<?=$row['sku']?>">
          <textarea class="question-textarea" name="question" rows="3" style="width: 100%; height: 60px; border: 1px solid #ccc; font-size: 0.7em; resize: none;" placeholder="Add new question for SKU <?=$row['sku']?>..."><?=htmlspecialchars($existing_questions)?></textarea>
        </form>
      </div>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<tr style="display:none;">
  <td colspan="10"><?php require 'pagination.php'; ?></td>
</tr>
<?php } else { ?>
<tr>
  <td colspan="10">
		<p class="text-center"><b class="text-danger">No records to show.</b></p>
	</td>
</tr>
<?php } ?>