<?php
session_start();
require __DIR__.'/bootstrap.php';

if(!isset($_SESSION['dins_user_id'])){
  exit();
}

$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';

// Build the query
$where_clause = '';
$params = [];

if(!empty($search_query)){
  $where_clause = " WHERE manufacturer_id LIKE ? OR manufacturer_name LIKE ?";
  $search_param = "%{$search_query}%";
  $params = [$search_param, $search_param];
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM master_pless_manufacturers" . $where_clause;
$total_records = $DB->query($count_query, $params)[0]['total'];

// Get records
$query = "SELECT * FROM master_pless_manufacturers" . $where_clause . " ORDER BY manufacturer_name ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$records = $DB->query($query, $params);

$output = '';
if(!empty($records)){
  foreach($records as $record){
    $output .= '<tr>';
    $output .= '<td>'.htmlspecialchars($record['manufacturer_id']).'</td>';
    $output .= '<td>'.htmlspecialchars($record['manufacturer_name']).'</td>';
    $output .= '<td>';
    $output .= '<button class="btn btn-primary btn-sm updateRecord" data-id="'.htmlspecialchars($record['manufacturer_id']).'" data-toggle="tooltip" title="Update"><i class="zmdi zmdi-edit"></i></button>';
    $output .= '</td>';
    $output .= '</tr>';
  }
}else{
  $output = '<tr><td colspan="3" class="text-center">No records found</td></tr>';
}

echo $output;

// Pagination
$total_pages = ceil($total_records / $limit);
$current_page = ($offset / $limit) + 1;

$pagination = '<div id="PaginationInfoResponse" style="display:none;">';
$pagination .= '<nav aria-label="Page navigation">';
$pagination .= '<ul class="pagination justify-content-center">';

// Previous button
if($current_page > 1){
  $prev_offset = $offset - $limit;
  $pagination .= '<li class="page-item">';
  $pagination .= '<a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$prev_offset.'">Previous</a>';
  $pagination .= '</li>';
}

// Page numbers
$start_page = max(1, $current_page - 2);
$end_page = min($total_pages, $current_page + 2);

if($start_page > 1){
  $pagination .= '<li class="page-item">';
  $pagination .= '<a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="0">1</a>';
  $pagination .= '</li>';
  if($start_page > 2){
    $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
  }
}

for($i = $start_page; $i <= $end_page; $i++){
  $page_offset = ($i - 1) * $limit;
  $active = ($i == $current_page) ? 'active' : '';
  $pagination .= '<li class="page-item '.$active.'">';
  $pagination .= '<a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$page_offset.'">'.$i.'</a>';
  $pagination .= '</li>';
}

if($end_page < $total_pages){
  if($end_page < $total_pages - 1){
    $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
  }
  $last_offset = ($total_pages - 1) * $limit;
  $pagination .= '<li class="page-item">';
  $pagination .= '<a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$last_offset.'">'.$total_pages.'</a>';
  $pagination .= '</li>';
}

// Next button
if($current_page < $total_pages){
  $next_offset = $offset + $limit;
  $pagination .= '<li class="page-item">';
  $pagination .= '<a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$next_offset.'">Next</a>';
  $pagination .= '</li>';
}

$pagination .= '</ul>';
$pagination .= '<div class="text-center mt-2">';
$pagination .= '<small class="text-muted">Showing '.($offset + 1).' to '.min($offset + $limit, $total_records).' of '.$total_records.' entries</small>';
$pagination .= '</div>';

// Jump to page
$pagination .= '<div class="text-center mt-3">';
$pagination .= '<form class="form-inline justify-content-center jumpToPageForm">';
$pagination .= '<label class="mr-2">Jump to page:</label>';
$pagination .= '<input type="number" class="form-control form-control-sm jumpToPage" min="1" max="'.$total_pages.'" data-last_page="'.$total_pages.'" data-limit="'.$limit.'" style="width:80px;">';
$pagination .= '<button type="submit" class="btn btn-sm btn-primary ml-2">Go</button>';
$pagination .= '</form>';
$pagination .= '</div>';

$pagination .= '</nav>';
$pagination .= '</div>';

echo $pagination;
?>
