<?php
// Middleware: authorizeLandlord.php
// Usage: include_once __DIR__ . '/authorizeLandlord.php';
// Call ensureLandlordOrAdmin($db, $userIdToCheck) early in controller.

function ensureLandlordOrAdmin($db, $userIdToCheck) {
    // Try to read Authorization header user id first (if client includes it)
    $headers = getallheaders();
    $callerId = null;
    if (isset($headers['X-User-Id'])) {
        $callerId = intval($headers['X-User-Id']);
    } elseif (isset($headers['x-user-id'])) {
        $callerId = intval($headers['x-user-id']);
    }

    if (!$callerId) {
        // Fall back to supplied parameter
        $callerId = intval($userIdToCheck ?? 0);
    }

    if ($callerId <= 0) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized: missing caller id."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Fetch role of caller
    $q = "SELECT id, Role FROM Users WHERE id = :id AND IsDeleted = 0 LIMIT 1";
    $stmt = $db->prepare($q);
    $stmt->bindParam(':id', $callerId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized: caller not found."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $role = $user['Role'] ?? '';

    if ($role === 'ChuTro' || $role === 'Admin') {
        // Return caller info for controllers to adjust behavior (e.g., Admin can view all landlords)
        return $user;
    }
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: requires ChuTro or Admin role."], JSON_UNESCAPED_UNICODE);
    exit();
}

?>
