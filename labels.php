<?php
session_start();
$page_title = 'Generate Labels';
require 'php/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Check if user has permission to access the labels page
$has_access = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'labels'", 
    [$user_id]
);

if (empty($has_access) || !$has_access[0]['has_access']) {
    header('Location: no_access.php');
    exit;
}

// Check specific permissions for different label types
$can_generate_hanging = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'hanging_labels'", 
    [$user_id]
);
$can_generate_placeholder = $DB->query(
    "SELECT has_access FROM user_permissions WHERE user_id = ? AND page = 'placeholder_labels'", 
    [$user_id]
);

require 'assets/header.php';
require 'assets/navbar.php';
?>

<div class="page-container3">
    <section class="welcome p-t-20">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="title-4">Generate Product Labels</h1>
                    <hr class="line-seprate">
                </div>
            </div>
        </div>
    </section>

    <section class="p-t-20">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card">
                        <div class="card-body">
                            <?php if ($user_details['admin'] >= 2): ?>
                            <div class="admin-controls mb-4">
                                <button class="btn btn-success mr-2" onclick="loadLastBatch()">
                                    <i class="fas fa-history"></i> Load Last Batch
                                </button>
                                <button class="btn btn-info" onclick="loadFromOrderList()">
                                    <i class="fas fa-list"></i> Load from Order List
                                </button>
                            </div>
                            <?php endif; ?>

                            <form action="generate_labels.php" method="POST" target="_blank" onsubmit="openLabelsWindow(this.action); return true;">
                                <div class="form-group">
                                    <label for="sku_list" class="form-label">Enter SKUs or EANs, one per line:</label>
                                    <textarea 
                                        name="sku_list" 
                                        id="sku_list" 
                                        class="form-control" 
                                        rows="10" 
                                        style="font-family: monospace;"
                                        required
                                    ></textarea>
                                </div>
                                
                                <div class="button-group mt-4">
                                    <button type="submit" formaction="generate_labels.php" class="btn btn-primary mr-2">
                                        <i class="fas fa-tag"></i> Shelf Labels
                                    </button>
                                    
                                    <?php if (!empty($can_generate_hanging) && $can_generate_hanging[0]['has_access']): ?>
                                    <button type="submit" formaction="hanging_labels.php" class="btn btn-secondary mr-2">
                                        <i class="fas fa-hashtag"></i> Hanging Labels
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($can_generate_placeholder) && $can_generate_placeholder[0]['has_access']): ?>
                                    <button type="submit" formaction="placeholder_labels.php" class="btn btn-info">
                                        <i class="fas fa-bookmark"></i> Placeholder Labels
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require 'assets/footer.php'; ?>

<script>
function openLabelsWindow(url) {
    window.open(url, '_blank', 'width=800,height=600');
}

// Super admin only functions
function loadLastBatch() {
    $.get('get_last_batch.php', function(response) {
        if (response.success) {
            $('#sku_list').val(response.skus.join('\n'));
        } else {
            Swal.fire('Error', 'Could not load last batch', 'error');
        }
    });
}

function loadFromOrderList() {
    $.get('get_order_list_skus.php', function(response) {
        if (response.success) {
            $('#sku_list').val(response.skus.join('\n'));
        } else {
            Swal.fire('Error', 'Could not load from order list', 'error');
        }
    });
}
</script>

<style>
.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.button-group .btn {
    min-width: 150px;
}

textarea#sku_list {
    resize: vertical;
    min-height: 200px;
}

.admin-controls {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

@media (max-width: 768px) {
    .button-group {
        flex-direction: column;
    }
    
    .button-group .btn {
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>