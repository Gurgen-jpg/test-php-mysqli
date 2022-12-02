<?php
//include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/gurgen/database.php";
include "database.php";

error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
if (
    isset($_POST["network_id"]) &&
    isset($_POST["line_id"]) &&
    isset($_POST["href"]) &&
    isset($_POST["name"])
) {
    $links = $mysqli->query("SELECT * FROM `domain_link`");
    $network_id = $conn->real_escape_string($_POST["network_id"]);
    $line_id = $conn->real_escape_string($_POST["line_id"]);
    $href = $conn->real_escape_string($_POST["href"]);
    $name = $conn->real_escape_string($_POST["name"]);

    $new_link = $mysqli->query("INSERT INTO domain_link (network_id, line_id, href, name, style, sort) VALUES ($network_id, $network_id, $line_id, $href, $name)") or die($mysqli->error());
}
?>

