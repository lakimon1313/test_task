<?php

/**
 * Class for connecting and working with DB
 */
class Model
{
    private $_conn;
    /**
     * dbConnection constructor.
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
        $data = $this->_conn->prepare("SELECT * FROM `product`");
        $data->execute();
        $result = [];
        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['id']] = $row['name'];
        }

        return $result;
    }
}