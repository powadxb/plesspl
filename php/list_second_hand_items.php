<?php
require '../php/bootstrap.php';

$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
$status_filter = isset($_POST['status_filter']) ? trim($_POST['status_filter']) : '';
$sort_col = isset($_POST['sort_col']) ? $_POST['sort_col'] : 'ORDER BY id DESC';

// Get user permissions
$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];
$is_admin = $user_details['admin'] != 0;

// Build WHERE clause
$where = [];
$params = [];

if(!empty($search_query)) {
    $where[] = "(item_name LIKE ? OR serial_number LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if(!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM second_hand_items $where_clause";
$total_records = $DB->query($count_query, $params)[0]['total'];

// Get records
$query = "SELECT * FROM second_hand_items $where_clause $sort_col LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$records = $DB->query($query, $params);

$html = '';
if (empty($records)) {
    $html .= '<tr><td colspan="' . ($is_admin ? 9 : 5) . '" class="text-center">No records found</td></tr>';
} else {
    foreach($records as $record) {
        $status_class = $record['status'] === 'in_stock' ? 'text-success' : 'text-danger';
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($record['id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($record['item_name']) . '</td>';
        $html .= '<td>' . ucfirst(htmlspecialchars($record['condition'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($record['serial_number']) . '</td>';
        $html .= '<td class="'.$status_class.'">' . ucfirst(str_replace('_', ' ', $record['status'])) . '</td>';
        
        if($is_admin) {
            $html .= '<td>£' . number_format($record['purchase_price'], 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['customer_id']) . '</td>';
            $html .= '<td>' . ($record['notes'] ? '<i class="fas fa-sticky-note text-info" data-toggle="tooltip" title="'.htmlspecialchars($record['notes']).'"></i>' : '') . '</td>';
            $html .= '<td>
                <button class="btn btn-sm btn-primary editItem" data-id="'.$record['id'].'">
                    <i class="fas fa-edit"></i>
                </button>
            </td>';
        }
        
        $html .= '</tr>';
    }
}

// Add pagination info only if there are records
if ($total_records > 0) {
    $total_pages = ceil($total_records / $limit);
    $current_page = floor($offset / $limit) + 1;

    $pagination = '<div class="pagination-info" id="PaginationInfoResponse">';
    $pagination .= '<ul class="pagination justify-content-end">';

    // Previous button
    if($current_page > 1) {
        $prev_offset = $offset - $limit;
        $pagination .= '<li class="page-item">
            <a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$prev_offset.'">Previous</a>
        </li>';
    }

    // Page numbers
    for($i = 1; $i <= $total_pages; $i++) {
        $page_offset = ($i - 1) * $limit;
        $active_class = ($i == $current_page) ? 'active' : '';
        $pagination .= '<li class="page-item '.$active_class.'">
            <a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$page_offset.'">'.$i.'</a>
        </li>';
    }

    // Next button
    if($current_page < $total_pages) {
        $next_offset = $offset + $limit;
        $pagination .= '<li class="page-item">
            <a class="page-link recordsPage" href="#" data-limit="'.$limit.'" data-offset="'.$next_offset.'">Next</a>
        </li>';
    }

    $pagination .= '</ul>';
    $pagination .= '</div>';

    $html .= $pagination;
}

echo $html;
?>