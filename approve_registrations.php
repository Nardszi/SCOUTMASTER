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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $id = $_POST['approve_id'];

    // Start Transaction
    mysqli_begin_transaction($conn);

    try {
    // 2. Fetch from scout_new_register
    $stmt = mysqli_prepare($conn, "SELECT * FROM scout_new_register WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $scout = mysqli_fetch_assoc($result);

    if (!$scout) {
        throw new Exception("Registration not found.");
    }

        // 3. Generate a new random password for the user to login with
        $raw_password = bin2hex(random_bytes(4)); // 8 characters
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        // 4. Insert into users table
        // We map 'grade_level' to 'status' as per your system's convention
        $insert_user = "INSERT INTO users (name, email, password, school, role, status, approved, email_verified, scout_leader_id, rank, membership_card) VALUES (?, ?, ?, ?, 'scout', ?, 1, 1, ?, 'New Scout', ?)";
        $stmt_user = mysqli_prepare($conn, $insert_user);
        mysqli_stmt_bind_param($stmt_user, "sssssis", $scout['name'], $scout['email'], $hashed_password, $scout['school'], $scout['grade_level'], $scout['registered_by_leader_id'], $scout['membership_card']);
        
        if (!mysqli_stmt_execute($stmt_user)) {
            throw new Exception("Error creating user: " . mysqli_error($conn));
        }
        
        $user_id = mysqli_insert_id($conn);

            // 5. Insert into scout_profiles table
            $insert_profile = "INSERT INTO scout_profiles (user_id, gender, birthday, age, grade_level, paid_status, membership_card) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_profile = mysqli_prepare($conn, $insert_profile);
            mysqli_stmt_bind_param($stmt_profile, "ississs", $user_id, $scout['gender'], $scout['birthday'], $scout['age'], $scout['grade_level'], $scout['paid_status'], $scout['membership_card']);
            mysqli_stmt_execute($stmt_profile);

            // Log this action
            logActivity($conn, $_SESSION['user_id'], 'Approve New Scout', "Approved registration for '{$scout['name']}' (New User ID: $user_id).");

            // 6. Send Email with Credentials
            $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', BREVO_API_KEY);
            $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

            $emailContent = "
            <html>
            <body>
                <h1>Welcome to Scout Master!</h1>
                <p>Hello " . htmlspecialchars($scout['name']) . ",</p>
                <p>Your scout registration has been approved by the administrator.</p>
                <p>You can now login using the following credentials:</p>
                <p><strong>Email:</strong> " . htmlspecialchars($scout['email']) . "</p>
                <p><strong>Password:</strong> " . $raw_password . "</p>
                <p>Please login and change your password as soon as possible.</p>
            </body>
            </html>";

            $sendSmtpEmail = new SendSmtpEmail([
                'subject' => 'Scout Registration Approved',
                'sender' => ['name' => 'Scout Master', 'email' => SMTP_USER],
                'to' => [['email' => $scout['email'], 'name' => $scout['name']]],
                'htmlContent' => $emailContent
            ]);

            try {
                $apiInstance->sendTransacEmail($sendSmtpEmail);
            } catch (Exception $e) {
                // Log error if needed, but continue
                error_log("Failed to send approval email: " . $e->getMessage());
            }

            // 7. Delete from scout_new_register
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE id = ?");
            mysqli_stmt_bind_param($delete_stmt, "i", $id);
            mysqli_stmt_execute($delete_stmt);

            mysqli_commit($conn);
            $_SESSION['success'] = "Scout approved and credentials sent to email.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: view_new_registrations.php");
exit();
?>