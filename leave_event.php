<?php
// Load Brevo classes
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;

require 'vendor/autoload.php';
session_start();
include('config.php');

// 1. Security: Ensure user is logged in and is a scout
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $scout_id = $_SESSION['user_id'];

    // 2. Fetch event title and waiver file for cleanup/logging
    $info_query = "
        SELECT 
            e.event_title, 
            ea.waiver_file,
            sl.email AS leader_email,
            sl.name AS leader_name,
            s.name AS scout_name
        FROM event_attendance ea
        JOIN events e ON ea.event_id = e.id
        JOIN users sl ON e.scout_leader_id = sl.id
        JOIN users s ON ea.scout_id = s.id
        WHERE ea.event_id = ? AND ea.scout_id = ?
    ";
    $stmt_info = mysqli_prepare($conn, $info_query);
    mysqli_stmt_bind_param($stmt_info, "ii", $event_id, $scout_id);
    mysqli_stmt_execute($stmt_info);
    $result_info = mysqli_stmt_get_result($stmt_info);
    $info = mysqli_fetch_assoc($result_info);

    if ($info) {
        $event_title = $info['event_title'];
        $waiver_file = $info['waiver_file'];

        // 3. Delete from event_attendance table
        $delete_query = "DELETE FROM event_attendance WHERE event_id = ? AND scout_id = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, "ii", $event_id, $scout_id);

        if (mysqli_stmt_execute($stmt_delete)) {
            // 4. Delete waiver file if it exists
            if (!empty($waiver_file) && file_exists($waiver_file)) {
                unlink($waiver_file);
            }

            // 5. Notify Scout Leader via Email
            $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', BREVO_API_KEY);
            $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

            $emailContent = "
            <html>
            <body>
                <h3>Event Update: Scout Left Event</h3>
                <p>Hello " . htmlspecialchars($info['leader_name']) . ",</p>
                <p>This is to notify you that scout <strong>" . htmlspecialchars($info['scout_name']) . "</strong> has left the event:</p>
                <p><strong>Event:</strong> " . htmlspecialchars($info['event_title']) . "</p>
                <p>Please update your records accordingly.</p>
            </body>
            </html>";

            $sendSmtpEmail = new SendSmtpEmail([
                'subject' => 'Scout Left Event: ' . $info['event_title'],
                'sender' => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
                'to' => [['email' => $info['leader_email'], 'name' => $info['leader_name']]],
                'htmlContent' => $emailContent
            ]);

            try {
                $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
                error_log("Event leave notification sent to " . $info['leader_email']);
            } catch (Exception $e) {
                error_log("Failed to send event leave notification: " . $e->getMessage());
                if (method_exists($e, 'getResponseBody')) {
                    error_log("Brevo Response: " . $e->getResponseBody());
                }
            }

            // 6. Log the activity
            logActivity($conn, $scout_id, 'Leave Event', "Left event: '$event_title' (ID: $event_id)");

            $_SESSION['success'] = "Successfully left the event: " . htmlspecialchars($event_title);
        } else {
            $_SESSION['error'] = "Error leaving event: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "You are not joined to this event.";
    }

    header("Location: view_events.php");
    exit();

}
 else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: view_events.php");
    exit();
}
?>