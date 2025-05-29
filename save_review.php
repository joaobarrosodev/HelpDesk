<?php
session_start();
include('db.php');

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id']) && isset($_POST['rating'])) {
    
    $ticket_id = $_POST['ticket_id'];
    $rating = $_POST['rating'];
    
    // Convert rating text to number
    $review_value = null;
    switch($rating) {
        case 'positive':
            $review_value = 1;
            break;
        case 'neutral':
            $review_value = 2;
            break;
        case 'negative':
            $review_value = 3;
            break;
    }
    
    if ($review_value !== null) {
        try {
            // Update the review column in info_xdfree01_extrafields
            $sql = "UPDATE info_xdfree01_extrafields SET Review = :review WHERE XDFree01_KeyID = :ticket_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':review', $review_value);
            $stmt->bindParam(':ticket_id', $ticket_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Review saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save review']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
