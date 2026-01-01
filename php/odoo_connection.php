<?php
require_once(__DIR__ . '/../lib/ripcord/ripcord.php');

function getOdooConnection() {
    $odoo_url = 'http://192.168.1.133:8069';
    $odoo_db = 'commercest_v7';
    $odoo_username = 'odooreadonly';
    $odoo_password = 'Amjadis$2';

    try {
        $common = ripcord::client("$odoo_url/xmlrpc/2/common");
        $uid = $common->authenticate($odoo_db, $odoo_username, $odoo_password, array());
        $models = ripcord::client("$odoo_url/xmlrpc/2/object");

        return [
            'db' => $odoo_db,
            'uid' => $uid,
            'password' => $odoo_password,
            'models' => $models
        ];
    } catch (Exception $e) {
        error_log("Odoo connection error: " . $e->getMessage());
        return null;
    }
}

function getOdooQuantities($skus, $location_id = 12) {
    $connection = getOdooConnection();
    if (!$connection) {
        error_log("DEBUG: Odoo connection failed");
        return [];
    }

    try {
        $models = $connection['models'];
        $skus = array_map('strval', $skus); // Convert SKUs to strings

        // Fetch product IDs for the given SKUs
        $products = $models->execute_kw(
            $connection['db'],
            $connection['uid'],
            $connection['password'],
            'product.product',
            'search_read',
            array(array(array('default_code', 'in', $skus))),
            array('fields' => array('id', 'default_code'))
        );

        if (empty($products)) {
            error_log("DEBUG: No matching products found for SKUs: " . implode(', ', $skus));
            return [];
        }

        $product_ids = array_column($products, 'id');
        $sku_map = array_column($products, 'default_code', 'id');

        // Fetch stock quantities for the product IDs at the specified location
        $quants = $models->execute_kw(
            $connection['db'],
            $connection['uid'],
            $connection['password'],
            'stock.quant',
            'search_read',
            array(array(
                array('product_id', 'in', $product_ids),
                array('location_id', '=', $location_id)
            )),
            array('fields' => array('product_id', 'quantity', 'reserved_quantity'))
        );

        // Map quantities to SKUs
        $quantities = array_fill_keys($skus, 0); // Initialize all SKUs to 0
        foreach ($quants as $quant) {
            $product_id = $quant['product_id'][0];
            if (isset($sku_map[$product_id])) {
                $sku = $sku_map[$product_id];
                $quantities[$sku] += ($quant['quantity'] - ($quant['reserved_quantity'] ?? 0));
            }
        }

        return $quantities;
    } catch (Exception $e) {
        error_log("DEBUG: Error getting Odoo quantities: " . $e->getMessage());
        return [];
    }
}
