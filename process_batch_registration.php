<?php
session_start();
include('config.php');

// Security: Ensure user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = [];
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
    } elseif (isset($_POST['id'])) {
        $ids[] = intval($_POST['id']);
    }

    $action = $_POST['action'] ?? '';
    $success_count = 0;
    $error_count = 0;

    // Helper function to archive registration
    function archiveRegistration($conn, $scout, $status, $admin_id) {
        $query = "INSERT INTO scout_registration_archive 
        (original_id, name, registration_status, age, sex, membership_card, highest_rank, years_in_scouting, school, paid_status, registered_by_leader_id, registration_date, archive_status, archived_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ississsississi", 
            $scout['id'], $scout['name'], $scout['registration_status'], $scout['age'], $scout['sex'], 
            $scout['membership_card'], $scout['highest_rank'], $scout['years_in_scouting'], $scout['school'], 
            $scout['paid_status'], $scout['registered_by_leader_id'], $scout['created_at'], $status, $admin_id
        );
        mysqli_stmt_execute($stmt);

        // Insert into admin_scout_archive (Admin's Copy)
        $query_admin = "INSERT INTO admin_scout_archive 
        (original_id, name, registration_status, age, sex, membership_card, highest_rank, years_in_scouting, school, paid_status, registered_by_leader_id, registration_date, archive_status, archived_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_admin = mysqli_prepare($conn, $query_admin);
        mysqli_stmt_bind_param($stmt_admin, "ississsississi", 
            $scout['id'], $scout['name'], $scout['registration_status'], $scout['age'], $scout['sex'], 
            $scout['membership_card'], $scout['highest_rank'], $scout['years_in_scouting'], $scout['school'], 
            $scout['paid_status'], $scout['registered_by_leader_id'], $scout['created_at'], $status, $admin_id
        );
        mysqli_stmt_execute($stmt_admin);
    }

    if (!empty($ids) && $action === 'approve') {
        foreach ($ids as $id) {
            mysqli_begin_transaction($conn);
            try {
                // Fetch registration details
                $stmt = mysqli_prepare($conn, "SELECT * FROM scout_new_register WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $scout = mysqli_fetch_assoc($result);

                if ($scout) {
                    // Archive before deleting
                    archiveRegistration($conn, $scout, 'Approved', $_SESSION['user_id']);

                    // Delete from pending table
                    $delete_stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE id = ?");
                    mysqli_stmt_bind_param($delete_stmt, "i", $id);
                    
                    if (mysqli_stmt_execute($delete_stmt)) {
                        $success_count++;
                        // Log activity
                        if (function_exists('logActivity')) {
                            logActivity($conn, $_SESSION['user_id'], 'Approve Batch Scout', "Approved {$scout['name']} (Ready for account creation)");
                        }
                    } else {
                        throw new Exception("Error deleting registration");
                    }
                    mysqli_commit($conn);
                } else {
                    throw new Exception("Registration not found");
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_count++;
            }
        }
        $_SESSION['success'] = "Approved $success_count scouts. $error_count failed (invalid cards or errors).";

    } elseif (!empty($ids) && $action === 'reject') {
        foreach ($ids as $id) {
            // Fetch registration details first for archiving
            $stmt = mysqli_prepare($conn, "SELECT * FROM scout_new_register WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $scout = mysqli_fetch_assoc($result);

            if ($scout) {
                // Archive before deleting
                archiveRegistration($conn, $scout, 'Rejected', $_SESSION['user_id']);

                // Delete from pending table
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE id = ?");
                mysqli_stmt_bind_param($delete_stmt, "i", $id);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    $success_count++;
                    if (function_exists('logActivity')) {
                        logActivity($conn, $_SESSION['user_id'], 'Reject Batch Scout', "Rejected registration ID: $id");
                    }
                } else {
                    $error_count++;
                }
            }
        }
        $_SESSION['success'] = "Rejected $success_count registrations.";
    } elseif ($action === 'delete_archive') {
        // Delete single archive record
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM admin_scout_archive WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            logActivity($conn, $_SESSION['user_id'], 'Delete Archive', "Deleted archive ID $id");
            $_SESSION['success'] = "History record deleted.";
        } else {
            $_SESSION['error'] = "Error deleting record.";
        }
    } elseif ($action === 'clear_archive') {
        // Clear all archive history
        if (mysqli_query($conn, "DELETE FROM admin_scout_archive")) {
            logActivity($conn, $_SESSION['user_id'], 'Clear Archive', "Cleared all archive history");
            $_SESSION['success'] = "All archive history cleared successfully.";
        } else {
            $_SESSION['error'] = "Error clearing history: " . mysqli_error($conn);
        }
    }
}

header("Location: admin_view_batch_registrations.php?tab=" . ($action == 'delete_archive' || $action == 'clear_archive' ? 'archive' : 'pending'));
exit();
?>
