<!-- Edit User -->
        <button class="btn btn-info btn-sm updateRecord" data-id="<?=$row['id']?>" title="Edit User">
          <i class="fas fa-edit"></i>
        </button>
        
        <!-- Location Assignment -->
        <button class="btn btn-primary btn-sm" 
                onclick="showAssignmentModal(<?=$row['id']?>, '<?=addslashes($row['username'])?>', '<?=addslashes(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))?>')" 
                title="Assign Location">
          <i class="fas fa-map-marker-alt"></i>
        </button>
        
        <!-- Clear Temporary Assignment (only if has valid temp assignment) -->
        <?php if($hasValidTempAssignment): ?>
        <button class="btn btn-warning btn-sm" 
                onclick="clearTemporaryAssignment(<?=$row['id']?>)" 
                title="Clear Temporary Assignment">
          <i class="fas fa-times"></i>
        </button>
        <?php endif; ?>
        
        <!-- Enable/Disable Toggle -->
        <div class="form-check">
          <input type="checkbox" class="form-check-input controlUser" 
                 name="enabled" 
                 data-id="<?=$row['id']?>" 
                 <?=($row['enabled']==1)?'checked':''?>>
        </div><?php
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
  $all_records = $DB->query(" SELECT * FROM users WHERE {$where_str} ORDER BY username ASC" , $where_query);
}else{
  $all_records = $DB->query(" SELECT * FROM users ORDER BY username ASC");
}

// Get current user details for permission checking
$current_user_id = $_SESSION['dins_user_id'] ?? $_COOKIE['dins_user_id'] ?? 0;
$current_user = $DB->query("SELECT admin FROM users WHERE id=?", [$current_user_id])[0] ?? null;
$is_level_2_admin = $current_user && $current_user['admin'] == 2; // Only level 2 can access

// Helper functions
function getLocationBadge($location, $isTemp = false) {
    if (!$location) {
        return '<span class="badge badge-unassigned">Not Assigned</span>';
    }
    
    $tempClass = $isTemp ? ' badge-temp' : '';
    $tempText = $isTemp ? ' (Temp)' : '';
    
    switch ($location) {
        case 'cs':
            return '<span class="badge badge-cs' . $tempClass . '">CS' . $tempText . '</span>';
        case 'as':
            return '<span class="badge badge-as' . $tempClass . '">AS' . $tempText . '</span>';
        default:
            return '<span class="badge badge-unassigned">' . htmlspecialchars($location) . $tempText . '</span>';
    }
}

function formatDateShort($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    $now = new DateTime();
    
    if ($date < $now) {
        return '<span class="text-danger">Expired</span>';
    }
    
    return $date->format('M j, g:i A');
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
  
  foreach($records as $row): 
    // Determine effective location and temporary assignment status
    $hasValidTempAssignment = $row['temp_location'] && 
                             $row['temp_location_expires'] && 
                             strtotime($row['temp_location_expires']) > time();
    
    $hasExpiredTempAssignment = $row['temp_location'] && 
                               $row['temp_location_expires'] && 
                               strtotime($row['temp_location_expires']) <= time();
    
    $effectiveLocation = $hasValidTempAssignment ? $row['temp_location'] : $row['user_location'];
    
    // Row classes for styling
    $rowClass = '';
    if ($hasValidTempAssignment) $rowClass = 'temp-assignment';
    elseif ($hasExpiredTempAssignment) $rowClass = 'expired-assignment';
  ?>
  <tr class="<?=$rowClass?>">
    <td><?=$row['id']?></td>
    <td><strong><?=htmlspecialchars($row['username'])?></strong></td>
    <td><?=htmlspecialchars($row['email'])?></td>
    <td><?=htmlspecialchars($row['first_name'] ?? '')?></td>
    <td><?=htmlspecialchars($row['last_name'] ?? '')?></td>
    <td>
      <?php if($row['admin']==0): ?>
        <span class="badge badge-secondary">User</span>
      <?php elseif($row['admin']==1): ?>
        <span class="badge badge-info">Manager</span>
      <?php elseif($row['admin']==2): ?>
        <span class="badge badge-danger">Admin</span>
      <?php endif; ?>
    </td>
    <td>
      <div class="location-info">
        <!-- Primary Location -->
        <div><?=getLocationBadge($row['user_location'])?></div>
        
        <!-- Temporary Assignment -->
        <?php if ($hasValidTempAssignment): ?>
          <div style="margin-top: 2px;">
            <?=getLocationBadge($row['temp_location'], true)?>
            <br><small class="text-muted">Until: <?=formatDateShort($row['temp_location_expires'])?></small>
          </div>
        <?php elseif ($hasExpiredTempAssignment): ?>
          <div style="margin-top: 2px;">
            <span class="badge badge-danger">Temp Expired</span>
          </div>
        <?php endif; ?>
      </div>
    </td>
    <td class="text-center">
      <?php if($row['enabled']==1): ?>
        <span class="badge badge-success">Active</span>
      <?php else: ?>
        <span class="badge badge-secondary">Disabled</span>
      <?php endif; ?>
    </td>
    <td>
      <div class="btn-group">
        <!-- Edit User -->
        <button class="btn btn-info btn-sm updateRecord" data-id="<?=$row['id']?>" title="Edit User">
          <i class="fas fa-edit"></i>
        </button>
        
        <!-- Location Assignment -->
        <button class="btn btn-primary btn-sm" 
                onclick="showAssignmentModal(<?=$row['id']?>, '<?=addslashes($row['username'])?>', '<?=addslashes(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))?>')" 
                title="Assign Location">
          <i class="fas fa-map-marker-alt"></i>
        </button>
        
        <!-- Clear Temporary Assignment (only if has valid temp assignment) -->
        <?php if($hasValidTempAssignment): ?>
        <button class="btn btn-warning btn-sm" 
                onclick="clearTemporaryAssignment(<?=$row['id']?>)" 
                title="Clear Temporary Assignment">
          <i class="fas fa-times"></i>
        </button>
        <?php endif; ?>
        
        <!-- Enable/Disable Toggle -->
        <div class="form-check">
          <input type="checkbox" class="form-check-input controlUser" 
                 name="enabled" 
                 data-id="<?=$row['id']?>" 
                 <?=($row['enabled']==1)?'checked':''?>>
        </div>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
<tr style="display:none;">
  <td colspan="9"><?php require 'pagination.php'; ?></td>
</tr>
<?php } else { ?>
  <tr>
    <td colspan="9" class="text-center">No users found</td>
  </tr>
<?php } ?>