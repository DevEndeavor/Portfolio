<?php
    require_once("UserController.php");
    $userController = new UserController();
    $users = $userController->getAllUsers();
?>

<html>

<head>
    <title>Canfield - Challenge</title>
    <link rel="stylesheet" href="app.css">
</head>

<body>
    
    <div class="container">

        <table>
            <tr>
                <th>user_id</th>
                <th>name</th>
                <th>access_count</th>
                <th>modify_dt</th>
                <th>incr</th>
            </tr>

            <?php
                foreach($users as $user) {
                    echo "<tr>
                        <td>$user[user_id]</td>
                        <td>$user[name]</td>
                        <td>$user[access_count]</td>
                        <td>$user[modify_dt]</td>
                        <td>
                            <button class='btn' data-id='$user[user_id]'>+</button>
                        </td>
                    </tr>";
                }
            ?>

        </table>

    </div>



    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

    <script src="app.js"></script>

</body>

</html>