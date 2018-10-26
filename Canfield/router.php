<?php

require_once("UserController.php");

$userController = new UserController();

if (isset($_POST['id']) && !empty($_POST['id'])) {

    $id = $_POST['id'];
    $userController->incrementCount($id);
    echo json_encode($userController->getUser($id));

} else {

    echo json_encode($userController->getAllUsers());

}