<?php

/**
 * Class for connecting and working with DB
 */
class Model
{
    private $_conn;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config.php';
        try {
            $this->_conn = new PDO("mysql:host=" . $config['mysql']['servername'] . ";dbname=" . $config['mysql']['db_name'], $config['mysql']['username'], $config['mysql']['password']);
            // set the PDO error mode to exception
            $this->_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e)
        {
            echo "Connection failed: " . $e->getMessage();
        }
    }

    public function getData()
    {
        $sql = "SELECT p.id as product_id, wq.wh_id as wh_id, p.name as product_name, w.name as wh_name, quantity  FROM `wh_quantity` wq";
        $sql .= " LEFT JOIN  `product` p ON p.id=wq.product_id";
        $sql .= " LEFT JOIN `warehouse` w ON wq.wh_id=w.id";
        $sql .= " WHERE wq.wh_id != ''";

        $data = $this->_conn->prepare($sql);
        $data->execute();
        $result = [];
        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            if (key_exists($row['product_id'], $result)) {
                $result[$row['product_id']]['quantity'] += $row['quantity'];
                if (stristr($result[$row['product_id']]['wh_name'], $row['wh_name']) === false)
                    $result[$row['product_id']]['wh_name'] .= ',' . $row['wh_name'];
            } else {
                $result[$row['product_id']] = $row;
            }
        }

        return $result;
    }

    public function parseCSV()
    {
        $result = [];
        if (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $result[] = [
                    'product_name' => $data[0],
                    'quantity' => $data[1],
                    'warehouse' => $data[2],
                ];
            }
            fclose($handle);

            if ($result)
                $this->insert($result);
            else
                return ['error' => 'Вы загрузили пустой файл.'];
        }

        return $this->getData();
    }

    private function insert($data)
    {
        if ($data) {
            $sqlData = "";
            foreach ($data as $item) {
                $item['product_name'] = str_replace('"', '', $item['product_name']);

                $productData = $this->_conn->prepare("SELECT id FROM product WHERE `name` LIKE '" . $item['product_name'] . "'");
                $warehouseData = $this->_conn->prepare("SELECT id FROM warehouse WHERE `name` LIKE '" . $item['warehouse'] . "'");

                $productData->execute();
                $warehouseData->execute();

                if ($row = $productData->fetch(PDO::FETCH_ASSOC)) {
                    $productID = $row['id'];
                } else {
                    $this->_conn->prepare("INSERT INTO product (`name`) VALUES ('" . $item['product_name'] . "')")->execute();
                    $productID = $this->_conn->lastInsertId();
                }
                if ($row = $warehouseData->fetch(PDO::FETCH_ASSOC)) {
                    $warehouseID = $row['id'];
                } else {
                    $warehouseID = $this->_conn->prepare("INSERT INTO warehouse (`name`) VALUES ('" . $item['warehouse'] . "')")->execute();
                }

                $tmpItem = implode(',', [$productID, $warehouseID, $item['quantity']]);
                $sqlData .= '(' . $tmpItem . ')';
                if (next($data))
                    $sqlData .= ',';
            }

            $sql = "INSERT INTO wh_quantity (product_id, wh_id, quantity) VALUES " . $sqlData;
            $this->_conn->prepare($sql)->execute();
        }
    }
}