<?php
// Load Brevo classes
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;

require 'vendor/autoload.php';
session_start();
include('config.php');

// 1. Security: Ensure user is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    $id = $_POST['reject_id'];

    // 2. Fetch from scout_new_register to get email
    $stmt = mysqli_prepare($conn, "SELECT * FROM scout_new_register WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $scout = mysqli_fetch_assoc($result);

    if ($scout) {
        // 3. Send Rejection Email
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', BREVO_API_KEY);
        $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

        $emailContent = "
        <html>
        <body>
            <h1>Scout Registration Update</h1>
            <p>Hello " . htmlspecialchars($scout['name']) . ",</p>
            <p>We regret to inform you that your scout registration request has been rejected by the administrator.</p>
            <p>If you have any questions, please contact your Scout Leader.</p>
        </body>
        </html>";

        $sendSmtpEmail = new SendSmtpEmail([
            'subject' => 'Scout Registration Rejected',
            'sender' => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
            'to' => [['email' => $scout['email'], 'name' => $scout['name']]],
            'htmlContent' => $emailContent
        ]);

        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            error_log("Rejection email sent to " . $scout['email']);
        } catch (Exception $e) {
            // Log detailed error if needed
            error_log("Failed to send rejection email: " . $e->getMessage());
            if (method_exists($e, 'getResponseBody')) {
                error_log("Brevo Response: " . $e->getResponseBody());
            }
        }

        // 4. Delete from scout_new_register
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $id);
        mysqli_stmt_execute($delete_stmt);

        // Log this action
        logActivity($conn, $_SESSION['user_id'], 'Reject New Scout', "Rejected registration for '{$scout['name']}'.");

        $_SESSION['success'] = "Registration rejected and email sent.";
    } else {
        $_SESSION['error'] = "Registration not found.";
    }
}

header("Location: view_new_registrations.php");
exit();
?>