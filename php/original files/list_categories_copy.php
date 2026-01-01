<?php
require 'bootstrap.php';

$limit = (int)$_POST['limit'];
$offset = (int)$_POST['offset'];
$next_offset = $offset + $limit;

$where_str = '';
$where_query = [];

if(isset($_POST['search_query']) && !empty($_POST['search_query']) && $_POST['search_type']=='general'){
  $search_query = trim($_POST['search_query']);
  $search_query_list = explode(" ",$search_query);
  foreach($search_query_list as $search_word){
    if(!empty($where_str)) $where_str .= ' AND ';
    $search_word = '%'.trim($search_word).'%';
    $where_str .= ' (pless_main_category LIKE ? OR  pos_category LIKE ?) ';
    array_push($where_query, $search_word, $search_word);
  }
}

if(!empty($where_str)){
  $all_records = $DB->query(" SELECT * FROM master_categories {$where_str} ORDER BY pless_main_category ASC" , $where_query);
}else{
  $all_records = $DB->query(" SELECT * FROM master_categories ORDER BY pless_main_category ASC");
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
  <tr>
    <td><?=$row['id']?></td>
    <td><?=$row['pless_main_category']?></td>
    <td><?=$row['pos_category']?></td>
    <td class="text-center">
      <i class="fas fa-edit fa-2x text-info updateRecord" data-id="<?=$row['id']?>"></i>
    </td>
  </tr>
  <?php endforeach; ?>
<tr style="display:none;">
  <td colspan="4"><?php require 'pagination.php'; ?></td>
</tr>
<?php } ?>