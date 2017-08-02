<?php

/**
 * Class for connecting and working with DB
 */
class Model
{
    private $_conn;

    /**
     * Connecting to MySql with data from config.php
     */
    public function __construct()
    {
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config.php';
        try {
            $this->_conn = new PDO("mysql:host=" . $config['mysql']['servername'] . ";dbname=" . $config['mysql']['db_name'], $config['mysql']['username'], $config['mysql']['password']);
            // set the PDO error mode to exception
            $this->_conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e)
        {
            echo "Connection failed: " . $e->getMessage();
        }
    }

    /**
     * Get data about products and warehouses for index table
     *
     * @return array
     */
    public function getData()
    {
        // Get all rows from `wh_quantity` table for calculate quantity
        $sql = "SELECT p.id as product_id, wq.wh_id as wh_id, p.name as product_name, w.name as wh_name, quantity  FROM `wh_quantity` wq";
        $sql .= " LEFT JOIN  `product` p ON p.id=wq.product_id";
        $sql .= " LEFT JOIN `warehouse` w ON wq.wh_id=w.id";
        $sql .= " WHERE wq.wh_id != ''";

        $data = $this->_conn->prepare($sql);
        $data->execute();

        $result = $whItems = [];
        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {

            // If we already have item with current product_id, then just update the product quantity information and also write the warehouses of current product
            if (key_exists($row['product_id'], $result)) {
                $result[$row['product_id']]['quantity'] += $row['quantity'];

                // If product have current warehouse then just update quantity of that warehouse
                if (isset($result[$row['product_id']]['warehouses']) && key_exists($row['wh_id'], $result[$row['product_id']]['warehouses'])) {
                    $result[$row['product_id']]['warehouses'][$row['wh_id']]['quantity'] += $row['quantity'];
                // and if product have not yet current warehouse - add it quantity and name
                } else {
                    $result[$row['product_id']]['warehouses'][$row['wh_id']] = [
                        'quantity' => $row['quantity'],
                        'wh_name' => $row['wh_name'],
                    ];
                }
            // If we have not current product then just write to $result information
            } else {
                $result[$row['product_id']] = $row;
                $result[$row['product_id']]['warehouses'][$row['wh_id']] = [
                    'quantity' => $row['quantity'],
                    'wh_name' => $row['wh_name'],
                ];
            }

        }

        // Parse every products warehouses. If warehouse has 0 or < 0 items - we don`t need to show this warehouse
        foreach ($result as $key => $item) {

            foreach ($item['warehouses'] as $wh) {
                // What warehouses we will show
                if ($wh['quantity'] > 0)
                    $result[$key]['wh_names'][] = $wh['wh_name'];
            }

            // If product have no warehosuses - just unset him
            if (empty($result[$key]['wh_names']))
                unset($result[$key]);

        }

        return $result;
    }

    /**
     * Parse CSV file and insert data from file to DB
     *
     * @return array
     */
    public function parseCSV()
    {
        $result = [];
        // Read every line from file and write data to $result
        if (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $result[] = [
                    'product_name' => $data[0],
                    'quantity' => $data[1],
                    'warehouse' => $data[2],
                ];
            }
            fclose($handle);

            // Check if we have data so we can continue our script
            if ($result)
                $this->insert($result);
            // And if not - show user error
            else
                return ['error' => 'Вы загрузили пустой файл.'];
        }

        return $this->getData();
    }

    /**
     * Insert data to DB
     * Method will parse all data, check if we already have Products & Warehouses in DB and if not - add it
     *
     * @param $data
     */
    private function insert($data)
    {
        // Prepare all products & warehouses for SELECT
        $products = $warehouses = [];
        foreach ($data as $item) {
            if (!in_array('\'' . $item['product_name'] . '\'', $products))
                $products[] = '\'' . $item['product_name'] . '\'';

            if (!in_array('\'' . $item['warehouse'] . '\'', $warehouses))
                $warehouses[] = '\'' . $item['warehouse'] . '\'';
        }

        // Execute queries
        $productsResultDB = $this->_conn->prepare("SELECT `name`,`id` FROM product WHERE `name` IN (" . implode(',', $products) . ")");
        $productsResultDB->execute();

        $warehousesResultDB = $this->_conn->prepare("SELECT `name`,`id` FROM warehouse WHERE `name` IN (" . implode(',', $warehouses) . ")");
        $warehousesResultDB->execute();

        // Write to arrays the results of Products & Warehouses queries
        $productsResult = $warehousesResult = [];
        while ($row = $productsResultDB->fetch(PDO::FETCH_ASSOC)) {
            $productsResult[$row['id']] = $row['name'];
        }

        while ($row = $warehousesResultDB->fetch(PDO::FETCH_ASSOC)) {
            $warehousesResult[$row['id']] = $row['name'];
        }

        // Main parse data
        $sqlData = "";
        foreach ($data as $key => $item) {
            // We don`t need write to DB scopes
            $item['product_name'] = str_replace('"', '', $item['product_name']);

            // If product already exist in DB - just get his ID
            $productKey = array_search($item['product_name'], $productsResult);
            if ($productKey !== false)
                $productID = $productKey;
            else {
            //If we have no such product - we just write his name to data, that we will insert data to DB later
                $productID = false;
                $productsInsert[] = $item['product_name'];
            }

            // Same actions with products above
            $warehouseKey = array_search($item['warehouse'], $warehousesResult);
            if ($warehouseKey !== false)
                $warehouseID = $warehouseKey;
            else {
                $warehouseID = false;
                $warehousesInsert[] = $item['warehouse'];
            }

            // If we have no productID or warehouseID then we cant insert current line to DB, so we will just skipp current step
            if ($productID === false || $warehouseID === false)
                continue;

            // Add sql type data to string
            $sqlData .= '(' . implode(',', [(int)$productID, (int)$warehouseID, (int)$item['quantity'], time()]) . ')';
            if (next($data))
                $sqlData .= ',';

            // If we successfully added current line to sql string, we just removing current item from data
            unset($data[$key]);
        }

        // Insert new rows to DB
        if ($sqlData) {
            $sql = "INSERT INTO wh_quantity (product_id, wh_id, quantity, created_at) VALUES " . $sqlData;
            $this->_conn->prepare($sql)->execute();
        }

        // Flag for checking if we need to start curret method "insert" again
        $flag = false;
        if (isset($productsInsert)) {
            $flag = true;

            // Add new products
            $productsSQL = "";
            $productsInsert = array_unique($productsInsert);
            foreach ($productsInsert as $item) {
                $productsSQL .= '(\'' . $item . '\')';
                if (next($productsInsert))
                    $productsSQL .= ',';
            }

            $sql = "INSERT INTO product (`name`) VALUES " . $productsSQL;

            $this->_conn->prepare($sql)->execute();
        }

        // Add new warehouses
        if (isset($warehousesInsert)) {
            $flag = true;

            $warehousesSQL = "";
            $warehousesInsert = array_unique($warehousesInsert);
            foreach ($warehousesInsert as $item) {
                $warehousesSQL .= '(\'' . $item . '\')';
                if (next($warehousesInsert))
                    $warehousesSQL .= ',';
            }

            $sql = "INSERT INTO warehouse (`name`) VALUES " . $warehousesSQL;

            $this->_conn->prepare($sql)->execute();
        }

        // if new products or warehouses were added, we should start current script again
        // Also check if data is not empty, so we know for sure that we still have no parsed $data
        if ($flag && !empty($data))
            $this->insert($data);
    }

}