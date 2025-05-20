<?php
session_start();
include('conflogin.php');
include('db.php');
include('../ws-manager.php'); // Include WebSocket manager functions

// Debug file to log issues
$debug_file = __DIR__ . '/../message-debug.log';

// Write to debug log
function debug_log($message) {
    global $debug_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_file, "[ADMIN][$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

debug_log("=== New admin message attempt ===");
debug_log("POST data: " . json_encode($_POST));

// Check if user is logged in
if (!isset($_SESSION['admin_email'])) {
    debug_log("Error: Admin not logged in");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Admin not logged in']);
    exit;
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
debug_log("Is AJAX request: " . ($isAjax ? "Yes" : "No"));

// Check for required parameters
if (!isset($_POST['keyid']) || !isset($_POST['id']) || !isset($_POST['message'])) {
    debug_log("Error: Missing required parameters");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    } else {
        header('Location: consultar_tickets.php?error=1');
    }
    exit;
}

$keyid = $_POST['keyid'];
$id = $_POST['id'];
$message = trim($_POST['message']);
$user = $_SESSION['admin_email'];
$date = date('Y-m-d H:i:s');

debug_log("Message data: keyid=$keyid, id=$id, user=$user, message_length=" . strlen($message));

// Get device ID if provided
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;

// Check if this is from WebSocket origin
$fromWebSocket = isset($_POST['ws_origin']) && $_POST['ws_origin'] == '1';

// Admin messages are always type 0
$messageType = 0;
debug_log("Message type: $messageType (2=admin)");

try {
    // Check if this message already exists (to prevent duplicates)
    if ($fromWebSocket) {
        debug_log("Checking for duplicate message (from WebSocket)");
        $check_stmt = $pdo->prepare("SELECT id FROM comments_xdfree01_extrafields
                                    WHERE XDFree01_KeyID = :keyid
                                    AND Message = :message
                                    AND user = :user
                                    AND ABS(TIMESTAMPDIFF(SECOND, Date, :date)) < 5");

        $check_stmt->bindParam(':keyid', $keyid);
        $check_stmt->bindParam(':message', $message);
        $check_stmt->bindParam(':user', $user);
        $check_stmt->bindParam(':date', $date);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            // Message already exists, don't create duplicate
            debug_log("Duplicate message found, skipping");
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Already exists',
                    'isDuplicate' => true
                ]);
            }
            exit;
        }
    }

    // Begin transaction for database consistency
    $pdo->beginTransaction();
    debug_log("Database transaction started");

    try {
        // Direct insert without transaction to isolate any issues
        $insert_sql = "INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type)
                        VALUES (:keyid, :message, :date, :user, :type)";
        debug_log("SQL: $insert_sql with params: keyid=$keyid, message=$message, date=$date, user=$user, type=$messageType");

        $stmt = $pdo->prepare($insert_sql);

        $stmt->bindParam(':keyid', $keyid);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':user', $user);
        $stmt->bindParam(':type', $messageType);

        $success = $stmt->execute();
        debug_log("Insert statement executed: " . ($success ? "SUCCESS" : "FAILED"));

        if (!$success) {
            $error_info = $stmt->errorInfo();
            debug_log("SQL Error: " . json_encode($error_info));
            throw new PDOException("Database error: " . $error_info[2]);
        }

        $messageId = $pdo->lastInsertId();
        debug_log("Message saved with ID: $messageId");

        // Prepare the message data for WebSocket
        $messageData = [
            'Message' => $message,
            'user' => $user,
            'type' => $messageType,
            'CommentTime' => $date,
            'deviceId' => $deviceId,
            'messageId' => 'db_' . $messageId
        ];

        // Update status from "Aguarda resposta" to "Em análise" when admin responds
        $updateStatus = false;

        // Get current status
        $stmtStatus = $pdo->prepare("SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid");
        $stmtStatus->bindParam(':keyid', $keyid);
        $stmtStatus->execute();
        $currentStatus = $stmtStatus->fetchColumn();
        debug_log("Current ticket status: $currentStatus");

        // Update status if necessary - when admin responds to user message
        if ($currentStatus == 'Aguarda resposta' || $currentStatus == 'Pendente') {
            $newStatus = 'Em análise';
            $updateStatus = true;
        }

        if ($updateStatus) {
            debug_log("Updating ticket status to: $newStatus");
            $stmtUpdateStatus = $pdo->prepare("UPDATE info_xdfree01_extrafields SET Status = :status, dateu = NOW() WHERE XDFree01_KeyID = :keyid");
            $stmtUpdateStatus->bindParam(':status', $newStatus);
            $stmtUpdateStatus->bindParam(':keyid', $keyid);
            $stmtUpdateStatus->execute();
        }

        // Commit the transaction
        $pdo->commit();
        debug_log("Database transaction committed");

        // Broadcast the message via WebSocket
        debug_log("Broadcasting message to WebSocket");
        broadcastMessageToWebSocket($keyid, $messageData);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'messageId' => $messageId,
                'websocketBroadcast' => true,
                'statusUpdated' => $updateStatus,
                'newStatus' => $updateStatus ? $newStatus : null
            ]);
            debug_log("JSON response sent (success)");
        } else {
            header('Location: detalhes_ticket.php?keyid=' . urlencode($id));
            debug_log("Redirect to detalhes_ticket.php?keyid=$id");
        }

    } catch (PDOException $e) {
        // Rollback the transaction if an error occurs
        $pdo->rollBack();
        debug_log("ERROR: Transaction rolled back. PDO exception: " . $e->getMessage());

        // Log the error for troubleshooting
        error_log("Error saving message: " . $e->getMessage());

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'ticketId' => $keyid
            ]);
            debug_log("JSON error response sent");
        } else {
            header('Location: consultar_tickets.php?error=2');
            debug_log("Redirect to consultar_tickets.php?error=2");
        }
    }
} catch (Exception $e) {
    debug_log("CRITICAL ERROR: " . $e->getMessage());

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Critical error: ' . $e->getMessage()
        ]);
    } else {
        echo "An error occurred: " . $e->getMessage();
    }
}
?>
