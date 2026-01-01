<?php

$records_cout = count($all_records);
$pages_num = ceil($records_cout / $limit);
$current_page = ($offset / $limit) + 1;
$pagination_shown_pages = 5;
$pagination_start = $current_page - $pagination_shown_pages;
$pagination_end = $current_page + $pagination_shown_pages;
if ($pagination_start <= 0) {
    $pagination_start = 1;
    $pagination_end = ($pagination_shown_pages * 2) + 1;
}
if ($pages_num < $pagination_end) {
    $pagination_end = $pages_num;
    $pagination_start = $pagination_end - ($pagination_shown_pages * 2);
}
if($pagination_start<1){
	$pagination_start = 1;
}
$showing_to = $offset + $limit;
if ($showing_to > $records_cout) {
  $showing_to = $records_cout;
}

?>

<div id="<?=isset($pagination_id)?$pagination_id:'PaginationInfoResponse'?>">
	<hr>
	<div class="text-center" >
	  <div class="paginationInfo">
	    <p>Showing <?= $offset + 1 ?> to <?= $showing_to ?> from <?= $records_cout ?></p>
	  </div>
	  <nav aria-label="Page navigation" class="d-block mx-auto paginationPages">
	      <ul class="pagination" style="justify-content: center;">
	          	<?php if ($pagination_start > 1): ?>
	            <li class="page-item">
	                  <a href="#" aria-label="Previous" class="recordsPage page-link" data-offset="0" data-limit="<?= $limit ?>"
	                     data-page="1">
	                      <span aria-hidden="true"><i class="fas fa-angle-double-left"></i></span>
	                  </a>
	            </li>
	        	<li class="page-item">
	                  <a href="#" aria-label="Previous" class="recordsPage page-link"
	                     data-offset="<?= (($current_page - 1) * $limit) - $limit ?>" data-limit="<?= $limit ?>"
	                     data-page="<?= $current_page - 1 ?>">
	                      <span aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
	                  </a>
	            </li>
	            <li class="page-item"><a class="page-link">...</a></li>
	          	<?php endif; ?>
	          	<?php for ($i = $pagination_start; $i <= $pagination_end; $i++): ?>
	              <li class="page-item <?php if ($current_page == $i) echo 'active'; ?>" ><a  href="#" class="icon recordsPage page-link" data-offset="<?= ($i * $limit) - $limit ?>" data-limit="<?= $limit ?>" data-page="<?= $i ?>"><?= $i ?></a>
	              </li>
	          	<?php endfor; ?>

	          	<?php if ($pagination_end != $pages_num): ?>
	              <li class="page-item"><a class="page-link">...</a></li>
	              <li class="page-item">
	                  <a href="#" aria-label="Next" class="recordsPage page-link"
	                     data-offset="<?= (($current_page + 1) * $limit) - $limit ?>" data-limit="<?= $limit ?>"
	                     data-page="<?= $current_page + 1 ?>">
	                      <span aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
	                  </a>
	              </li>
	        		<li class="page-item">
	                  <a href="#" aria-label="Next" class="recordsPage page-link"
	                     data-offset="<?= ($pages_num * $limit) - $limit ?>" data-limit="<?= $limit ?>"
	                     data-page="<?= $pages_num ?>">
	                      <span aria-hidden="true"><i class="fas fa-angle-double-right"></i></span>
	                  </a>
	              </li>
	          <?php endif; ?>
	      </ul>
	  </nav>
	  <div class="paginationـJumpToPage mt-2" style="text-align: center;">
	    <form class="form-inline jumpToPageForm" style="display: inline-flex;">
		  <div class="input-group mb-2 mr-sm-2">
		    <div class="input-group-prepend">
		      <div class="input-group-text">Jump To Page #</div>
		    </div>
		    <input type="text" class="form-control jumpToPage" data-limit="<?= $limit ?>" data-last_page="<?=$pages_num?>" placeholder="Page Number">
		  </div>
		  <button type="submit" class="btn btn-primary mb-2">Jump</button>
		</form>
	  </div>
	</div>
</div>