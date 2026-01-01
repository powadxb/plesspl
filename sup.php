<?php 
ob_start();
session_start();
$page_title = 'Stock Suppliers Control Panel';
require 'assets/header.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
    header("Location: login.php");
    exit();
}
require __DIR__.'/php/bootstrap.php';
$user_details = $DB->query("SELECT * FROM users WHERE id=?", [$user_id])[0];
if($user_details['admin'] == 0){
    header("Location: index.php");
    exit();
}

// settings
require 'php/settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$page_title?></title>
    
    <!-- Core Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.0.18/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <style>
        .incVatPrice, .fixedPriceMethod { display: none; }
    </style>
</head>
<body>
    <?php require 'assets/navbar.php' ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-4"><?=$page_title?></h1>
        
        <!-- Search Box -->
        <div class="mb-6">
            <input type="text" 
                   id="searchQuery" 
                   class="w-full p-2 border rounded" 
                   placeholder="Search Stock Suppliers">
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead>
                    <tr>
                        <th class="border p-2">SKU</th>
                        <th class="border p-2">Name</th>
                        <th class="border p-2">Category</th>
                        <th class="border p-2">Actions</th>
                    </tr>
                </thead>
                <tbody id="records">
                    <!-- Records will be loaded here -->
                </tbody>
            </table>
        </div>
        
        <div id="pagination" class="mt-4">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    <!-- Hidden inputs -->
    <input type="hidden" id="limit" value="<?=$settings['table_lines']?>">
    <input type="hidden" id="offset" value="0">
    <input type="hidden" id="sortCol">

    <script>
        $(document).ready(function(){
            // Load records function
            function loadRecords(limit, offset){
                var searchQuery = $("#searchQuery").val();
                $.ajax({
                    type: 'POST',
                    url: 'php/list_stock_suppliers.php',
                    data: {
                        limit: limit,
                        offset: offset,
                        search_query: searchQuery
                    },
                    success: function(response){
                        if(response.length > 0){
                            $("#records").html(response);
                            $("#pagination").html($("#PaginationInfoResponse").html());
                        }
                    }
                });
            }

            // Initial load
            loadRecords($("#limit").val(), $("#offset").val());

            // Search handler
            $("#searchQuery").on('keyup', function(){
                loadRecords($("#limit").val(), $("#offset").val());
            });

            // Pagination click handler
            $(document).on('click', '.recordsPage', function(e){
                e.preventDefault();
                var limit = $(this).data("limit"),
                    offset = $(this).data("offset");
                loadRecords(limit, offset);
            });
        });
    </script>
</body>
</html>