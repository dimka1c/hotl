<?php

namespace Classes;

class DB
{
    protected $pdo;

    protected function connectDB()
    {
        $db = require BASE_DIR . '/config/db.php';
        $option = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        $this->pdo = new \PDO($db['dsn'], $db['user'], $db['psw'], $option);
    }

    public function execute($sql)
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute();
    }


    public function insertProduct($category, $product)
    {
        $this->connectDB();
        $findProductinDB = $this->findProduct($product['name']);
        if (!empty($findProductinDB)) {
            return $findProductinDB[0]['id'];
        }
        $sql = "INSERT INTO products (category, product_name, href) VALUES ('" . $category . "', '" . $product['name'] . "', '" . $product['href'] . "')";
        $result = $this->execute($sql);
        if ($result) {
            $lastID = (int)$this->pdo->lastInsertId();
        }
        $this->closeConnectionDB();
        return $lastID;
    }


    public function closeConnectionDB()
    {
        $this->pdo = null;
    }


    /**
     * Find in DB product on product_name and return array id product, where is finded
     * @param $name - name product
     * @return array
     */
    public function findProduct($name)
    {
        $sql = "SELECT id FROM products WHERE product_name = '" . $name . "'";
        $find = [];
        $stmt = $this->pdo->query($sql);
        while ($row = $stmt->fetch())
        {
            $find[] = $row;
        }

        return $find;
    }

    /**
     * Return products where not have characteristics
     * @return array
     */
    public function getProductNotCharacteristics($category, $limit)
    {
        $this->connectDB();
        $find = [];
        $sql = "select * from products where category = '" . $category . "' AND characteristics is NULL LIMIT " . $limit;
        $stmt = $this->pdo->query($sql);
        while ($row = $stmt->fetch())
        {
            $find[] = $row;
        }

        $this->closeConnectionDB();

        return $find;
    }

    public function insetCharacteristics($id, $product)
    {
        $this->connectDB();
        $char = json_encode($product['attr']);
        $photos = json_encode($product['images']);
        $sql = "UPDATE products SET characteristics=?, photos=? WHERE id=" . $id;
        $stmt= $this->pdo->prepare($sql);
        $result = $stmt->execute([$char, $photos]);
        $this->closeConnectionDB();

        return $result;
    }


    /**
     * Create array product
     *
     * @param $category - name category (notebook, ibp, monitor etc.
     * @return array
     */
    public function getProductInCategory($category)
    {
        $this->connectDB();
        $sql = "select count(*) as all_products from products where category='" . $category . "' AND characteristics is not NULL";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetchAll();
        $countProductsChar = (int) $row[0]['all_products'];

        $sql = "select count(*) as all_products from products where category='" . $category . "'";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetchAll();
        $countProducts = (int) $row[0]['all_products'];

        $find = [];
        $sql = "select * from products where category = '" . $category . "' AND characteristics is not NULL";
        $stmt = $this->pdo->query($sql);
        //$find = $stmt->fetchAll();

        while ($row = $stmt->fetch()) {
            $char = json_decode($row['characteristics'], true);
            $photo = json_decode($row['photos'], true);
            $res['id'] = (int)$row['id'];
            $res['category'] = $row['category'];
            $res['name'] = $row['product_name'];
            $res['href'] = $row['href'];
            $res['char'] = $char;
            $res['photo'] = $photo;
            $find[] = $res;
        }
        $this->closeConnectionDB();

        $find['count'] = $countProducts;
        $find['count_char'] = $countProductsChar;

        return $find;
    }

}