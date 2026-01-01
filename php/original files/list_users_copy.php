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
    $where_str .= ' (username LIKE ? OR  email LIKE ? OR  first_name LIKE ? OR last_name LIKE ? ) ';
    array_push($where_query, $search_word, $search_word, $search_word , $search_word);
  }
}

if(!empty($where_str)){
  $all_records = $DB->query(" SELECT * FROM users {$where_str} ORDER BY username ASC" , $where_query);
}else{
  $all_records = $DB->query(" SELECT * FROM users ORDER BY username ASC");
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
    <td><?=$row['username']?></td>
    <td><?=$row['email']?></td>
    <td><?=$row['first_name']?></td>
    <td><?=$row['last_name']?></td>
    <td>
    <?php if($row['admin']==0): ?>
      User
    <?php elseif($row['admin']==1): ?>
      Manager
    <?php elseif($row['admin']==2): ?>
      Admin
    <?php endif; ?>
    </td>
    <td class="text-center">
      <i class="fas fa-edit fa-2x text-info updateRecord" data-id="<?=$row['id']?>"></i>
    </td>
    <td class="text-center">
      <input type="checkbox" class="controlUser" name="enabled" data-id="<?=$row['id']?>" <?=($row['enabled']==1)?'checked':''?>>
    </td>
  </tr>
  <?php endforeach; ?>
<tr style="display:none;">
  <td colspan="4"><?php require 'pagination.php'; ?></td>
</tr>
<?php } ?>