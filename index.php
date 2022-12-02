<?php
//include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/gurgen/database.php";
require ('database.php');
$links = $mysqli->query("SELECT * FROM `domain_link`");
$th_array = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_NAME LIKE 'domain_link'");

?>
<!---->
<!--<pre>-->
<!--    --><?php
//    print_r($th_array);
//    ?>
<!--</pre>-->
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel='stylesheet' href="style.css">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>my first php & mysql</title>
</head>
<body>
<h1>������� �1</h1>
<h3>domain_link</h3>

<table class="table">
    <thead>
    <?php
    while ($result = mysqli_fetch_assoc($th_array)) { ?>
        <th><?php echo $result['COLUMN_NAME']; ?></th>
        <?php
    }
    ?>
    </thead>
    <tbody>

    <?php
    foreach ($links as $row) {?>
        <tr> <?php
    foreach ($row as $value) { ?>
    <td><?php echo $value; ?></td>
    <?php
    } ?>
    </tr><br> <?php
    }
?>
    </tbody>
</table>
<form method="post" action="create.php">
    <div class="newLink">
        <div class="text-field">
            <label class="text-field__label" for="network_id">Network id</label>
            <input class="text-field__input" type="text" name="network_id" id="network_id" placeholder="network id"
                   value="">
        </div>
        <div class="text-field">
            <label class="text-field__label" for="line_id">Line id</label>
            <input class="text-field__input" type="text" name="line_id" id="line_id" placeholder="Line id"
                   value="">
        </div>
        <div class="text-field">
            <label class="text-field__label" for="href">Href</label>
            <input class="text-field__input" type="text" name="href" id="href" placeholder="Link"
                   value="">
        </div>
        <div class="text-field">
            <label class="text-field__label" for="name">Name</label>
            <input class="text-field__input" type="text" name="name" id="name" placeholder="Name"
                   value="">
        </div>
        <div class="text-field">
            <label class="text-field__label" for="style">Style</label>
            <input class="text-field__input" type="text" name="style" id="style" placeholder="Style"
                   value="">
        </div>
        <div class="text-field">
            <label class="text-field__label" for="sort">Sort</label>
            <input class="text-field__input" type="text" name="sort" id="sort" placeholder="Sort"
                   value="">
        </div>
    </div>
    <button type="submit">Submit</button>
</form>


</body>
</html>
