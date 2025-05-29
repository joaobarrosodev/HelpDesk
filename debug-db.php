<?php
// Test database connection and insertion
header('Content-Type: text/plain');
echo "Database Connection Test\n";
echo "------------------------\n";

try {
    // Include database configuration
    require_once __DIR__ . '/db.php';
    
    // Check if database connection is valid
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        echo "ERROR: Database connection not available\n";
        exit;
    }
    
    // Test database connection
    $testStmt = $pdo->query("SELECT 1");
    if ($testStmt === false) {
        echo "ERROR: Database connection test failed\n";
        exit;
    }
    
    echo "SUCCESS: Database connection working\n";
    
    // Test message insertion (test data that won't affect your system)
    $ticketId = '#TEST-'.uniqid();
    $message = 'Test message from debug script at ' . date('Y-m-d H:i:s');
    $user = 'debug-script';
    $type = 1;
    $date = date('Y-m-d H:i:s');
    
    // Insert test message
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type) 
                          VALUES (:keyid, :message, :date, :user, :type)");
    
    $stmt->bindParam(':keyid', $ticketId);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':user', $user);
    $stmt->bindParam(':type', $type);
    
    $result = $stmt->execute();
    
    if ($result) {
        echo "SUCCESS: Test message inserted with ID: " . $pdo->lastInsertId() . "\n";
        
        // Try to select the message back to confirm it's there
        $checkStmt = $pdo->prepare("SELECT * FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid");
        $checkStmt->bindParam(':keyid', $ticketId);
        $checkStmt->execute();
        $foundMessage = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($foundMessage) {
            echo "SUCCESS: Message retrieved from database\n";
            echo "Message ID: " . $foundMessage['id'] . "\n";
            echo "Message: " . $foundMessage['Message'] . "\n";
        } else {
            echo "ERROR: Message was not found in database after insert\n";
        }
        
        // Clean up test data
        $pdo->prepare("DELETE FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid")
            ->execute([':keyid' => $ticketId]);
        echo "Test data cleaned up\n";
    } else {
        echo "ERROR: Failed to insert test message\n";
        echo "Error info: " . print_r($stmt->errorInfo(), true) . "\n";
    }
    
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}