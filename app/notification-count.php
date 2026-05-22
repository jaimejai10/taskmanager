<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    include "../DB_connection.php";
    include "Model/Notification.php";

    $count_notification = count_notification($conn, $_SESSION['user_id']);
    if ($count_notification) {
        echo "&nbsp;". $count_notification. "&nbsp;";
    }
 }else{ 
  echo "";
}
 ?>