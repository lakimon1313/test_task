<?php
require 'models/model.php';

ini_set('display_errors', 1);

$model = new Model();
if (isset($_FILES['csv_file'])) {
    $tableData = $model->parseCSV();
    if (isset($tableData['error'])) {
        $error = $tableData['error'];
    }
}

$tableData = $model->getData();

?>

<html>
<head>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
</head>
<body>
<div class="container" style="padding-top: 40px">
    <?php if (isset($error)) { ?>
        <h1 class="bg-danger"><?= $error ?></h1>
    <?php } ?>
    <form class="form-inline" enctype="multipart/form-data" id="add_csv" name="add_csv" action="" method="post">
        <div class="form-group">
            <label class="sr-only" for="email">Загрузите CSV файл</label>
            <input type="file" required class="form-control" name="csv_file" id="csv_file">
        </div>
        <button type="submit" class="btn btn-default">Submit</button>
    </form>

    <div>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Продукт</th>
                <th>Количество</th>
                <th>Склады</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tableData as $item) { ?>
                <tr>
                    <td><?= $item['product_name'] ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= $item['wh_name'] ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
