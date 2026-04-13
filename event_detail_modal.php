<?php
session_start();
include('config.php');
$id = intval($_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM events WHERE id=$id");
$event = mysqli_fetch_assoc($query);
if($event){
    echo "<p><strong>Title:</strong> ".htmlspecialchars($event['event_title'])."</p>";
    echo "<p><strong>Date:</strong> ".htmlspecialchars($event['event_date'])."</p>";
    echo "<p><strong>Description:</strong> ".htmlspecialchars($event['event_description'])."</p>";
}else{
    echo "<p>Event not found.</p>";
}
?>
