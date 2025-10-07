<?php
// api/bookings.php - Enhanced with proper queue and date blocking
session_start();
require_once '../config.php';

$conn = getDBConnection();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'create':
        createBooking($conn);
        break;
    case 'getUserBookings':
        getUserBookings($conn);
        break;
    case 'checkAvailability':
        checkAvailability($conn);
        break;
    case 'getRooms':
        getRooms($conn);
        break;
    case 'getBlockedDates':
        getBlockedDates($conn);
        break;
    case 'getRoomCapacityByDate':
        getRoomCapacityByDate($conn);
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function getRoomCapacityByDate($conn) {
    $roomId = intval($_GET['roomId'] ?? 0);
    $startDate = sanitize($_GET['startDate'] ?? '');
    $endDate = sanitize($_GET['endDate'] ?? '');
    
    if (empty($roomId) || empty($startDate) || empty($endDate)) {
        sendResponse(false, 'Missing parameters');
    }
    
    // Get room capacity
    $stmt = $conn->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    if (!$room) {
        sendResponse(false, 'Room not found');
    }
    
    // Get all bookings that overlap with date range
    $stmt = $conn->prepare("
        SELECT check_in_date, check_out_date
        FROM bookings 
        WHERE room_id = ? 
        AND status IN ('Confirmed', 'Queued')
        AND check_in_date < ? 
        AND check_out_date > ?
        ORDER BY check_in_date
    ");
    $stmt->bind_param("iss", $roomId, $endDate, $startDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Count bookings per date
    $dateCapacity = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    // Initialize all dates
    for ($date = clone $start; $date < $end; $date->modify('+1 day')) {
        $dateCapacity[$date->format('Y-m-d')] = [
            'booked' => 0,
            'available' => $room['capacity'],
            'isFull' => false
        ];
    }
    
    // Count bookings for each date
    while ($row = $result->fetch_assoc()) {
        $checkin = new DateTime($row['check_in_date']);
        $checkout = new DateTime($row['check_out_date']);
        
        for ($date = clone $checkin; $date < $checkout; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            if (isset($dateCapacity[$dateStr])) {
                $dateCapacity[$dateStr]['booked']++;
                $dateCapacity[$dateStr]['available'] = $room['capacity'] - $dateCapacity[$dateStr]['booked'];
                $dateCapacity[$dateStr]['isFull'] = $dateCapacity[$dateStr]['booked'] >= $room['capacity'];
            }
        }
    }
    
    sendResponse(true, 'Capacity data retrieved', [
        'capacity' => $room['capacity'],
        'dates' => $dateCapacity
    ]);
}

function getBlockedDates($conn) {
    $roomId = intval($_GET['roomId'] ?? 0);
    
    if (empty($roomId)) {
        sendResponse(false, 'Room ID required');
    }
    
    // Get room capacity
    $stmt = $conn->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    if (!$room) {
        sendResponse(false, 'Room not found');
    }
    
    // Get all confirmed and queued bookings
    $stmt = $conn->prepare("
        SELECT check_in_date, check_out_date
        FROM bookings 
        WHERE room_id = ? 
        AND status IN ('Confirmed', 'Queued')
        AND check_out_date >= CURDATE()
        ORDER BY check_in_date
    ");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Count bookings per date
    $dateBookings = [];
    while ($row = $result->fetch_assoc()) {
        $checkin = new DateTime($row['check_in_date']);
        $checkout = new DateTime($row['check_out_date']);
        
        for ($date = clone $checkin; $date < $checkout; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            if (!isset($dateBookings[$dateStr])) {
                $dateBookings[$dateStr] = 0;
            }
            $dateBookings[$dateStr]++;
        }
    }
    
    // Find fully booked dates
    $blockedDates = [];
    foreach ($dateBookings as $date => $count) {
        if ($count >= $room['capacity']) {
            $blockedDates[] = $date;
        }
    }
    
    sendResponse(true, 'Blocked dates retrieved', [
        'blockedDates' => $blockedDates,
        'capacity' => $room['capacity']
    ]);
}

function createBooking($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $roomId = intval($data['roomId'] ?? 0);
    $checkin = sanitize($data['checkin'] ?? '');
    $checkout = sanitize($data['checkout'] ?? '');
    $adults = intval($data['adults'] ?? 1);
    $children = intval($data['children'] ?? 0);
    $provider = sanitize($data['provider'] ?? '');
    
    if (empty($roomId) || empty($checkin) || empty($checkout)) {
        sendResponse(false, 'Missing required fields');
    }
    
    // Validate dates
    $today = date('Y-m-d');
    if ($checkin < $today) {
        sendResponse(false, 'Check-in date cannot be in the past');
    }
    
    if ($checkout <= $checkin) {
        sendResponse(false, 'Check-out must be after check-in date');
    }
    
    // Get room details
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    if (!$room) {
        sendResponse(false, 'Room not found');
    }
    
    // Calculate nights and total price
    $checkinDate = new DateTime($checkin);
    $checkoutDate = new DateTime($checkout);
    $nights = $checkinDate->diff($checkoutDate)->days;
    $totalPrice = $nights * $room['price_per_night'];
    
    // Check if dates are fully booked
    $fullyBookedDates = checkDateAvailability($conn, $roomId, $checkin, $checkout, $room['capacity']);
    
    if (!empty($fullyBookedDates)) {
        sendResponse(false, 'Selected dates are fully booked. Please choose different dates.', [
            'fullyBookedDates' => $fullyBookedDates
        ]);
    }
    
    // Check overlapping bookings for queue determination
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM bookings 
        WHERE room_id = ? 
        AND status IN ('Confirmed', 'Queued')
        AND check_in_date < ? 
        AND check_out_date > ?
    ");
    $stmt->bind_param("iss", $roomId, $checkout, $checkin);
    $stmt->execute();
    $overlap = $stmt->get_result()->fetch_assoc();
    
    $isQueued = $overlap['count'] >= $room['capacity'];
    $status = $isQueued ? 'Queued' : 'Confirmed';
    $queuePosition = null;
    
    if ($isQueued) {
        // Get next queue position
        $stmt = $conn->prepare("
            SELECT COALESCE(MAX(queue_position), 0) + 1 as position 
            FROM bookings 
            WHERE room_id = ? 
            AND status = 'Queued'
        ");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $queuePosition = $stmt->get_result()->fetch_assoc()['position'];
    }
    
    // Get user details
    $stmt = $conn->prepare("SELECT name, phone, address FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Generate IDs
    $bookingId = 'BK-' . time() . '-' . substr(md5(uniqid()), 0, 6);
    $transactionId = isset($data['transactionId']) ? $data['transactionId'] : strtoupper($provider) . '-' . time();
    
    // Insert booking
    $stmt = $conn->prepare("
        INSERT INTO bookings 
        (booking_id, user_id, room_id, room_name, guest_name, phone, address, 
         check_in_date, check_out_date, nights, adults, children, total_price, 
         status, queue_position) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "siissssssiiidsi",
        $bookingId, $userId, $roomId, $room['name'], $user['name'], 
        $user['phone'], $user['address'], $checkin, $checkout, $nights, 
        $adults, $children, $totalPrice, $status, $queuePosition
    );
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Booking failed: ' . $stmt->error);
    }
    
    $bookingDbId = $conn->insert_id();
    
    // Insert payment
    $stmt = $conn->prepare("
        INSERT INTO payments (booking_id, transaction_id, payment_provider, amount) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("issd", $bookingDbId, $transactionId, $provider, $totalPrice);
    $stmt->execute();
    
    sendResponse(true, 'Booking created successfully', [
        'bookingId' => $bookingId,
        'transactionId' => $transactionId,
        'status' => $status,
        'queuePosition' => $queuePosition,
        'totalPrice' => $totalPrice
    ]);
}

function checkDateAvailability($conn, $roomId, $checkin, $checkout, $capacity) {
    $stmt = $conn->prepare("
        SELECT check_in_date, check_out_date
        FROM bookings 
        WHERE room_id = ? 
        AND status IN ('Confirmed', 'Queued')
        AND check_in_date < ? 
        AND check_out_date > ?
    ");
    $stmt->bind_param("iss", $roomId, $checkout, $checkin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Count bookings per date
    $dateBookings = [];
    $checkinDate = new DateTime($checkin);
    $checkoutDate = new DateTime($checkout);
    
    while ($booking = $result->fetch_assoc()) {
        $bCheckin = new DateTime($booking['check_in_date']);
        $bCheckout = new DateTime($booking['check_out_date']);
        
        for ($date = clone $bCheckin; $date < $bCheckout; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            if (!isset($dateBookings[$dateStr])) {
                $dateBookings[$dateStr] = 0;
            }
            $dateBookings[$dateStr]++;
        }
    }
    
    // Check each day in requested range
    $fullyBookedDates = [];
    for ($date = clone $checkinDate; $date < $checkoutDate; $date->modify('+1 day')) {
        $dateStr = $date->format('Y-m-d');
        if (isset($dateBookings[$dateStr]) && $dateBookings[$dateStr] >= $capacity) {
            $fullyBookedDates[] = $dateStr;
        }
    }
    
    return $fullyBookedDates;
}

function getUserBookings($conn) {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT 
            b.booking_id,
            b.room_name,
            b.check_in_date as checkin,
            b.check_out_date as checkout,
            b.nights,
            b.total_price as totalPrice,
            b.status,
            b.queue_position as queuePosition,
            b.created_at,
            p.transaction_id,
            p.payment_provider
        FROM bookings b
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    sendResponse(true, 'Bookings retrieved', $bookings);
}

function checkAvailability($conn) {
    $roomId = intval($_GET['roomId'] ?? 0);
    $checkin = sanitize($_GET['checkin'] ?? '');
    $checkout = sanitize($_GET['checkout'] ?? '');
    
    if (empty($roomId) || empty($checkin) || empty($checkout)) {
        sendResponse(false, 'Missing parameters');
    }
    
    $stmt = $conn->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    if (!$room) {
        sendResponse(false, 'Room not found');
    }
    
    $fullyBookedDates = checkDateAvailability($conn, $roomId, $checkin, $checkout, $room['capacity']);
    $isFull = !empty($fullyBookedDates);
    
    sendResponse(true, 'Availability checked', [
        'isFull' => $isFull,
        'fullyBookedDates' => $fullyBookedDates,
        'capacity' => $room['capacity']
    ]);
}

function getRooms($conn) {
    $stmt = $conn->query("
        SELECT r.*, GROUP_CONCAT(rf.facility_name) as facilities
        FROM rooms r
        LEFT JOIN room_facilities rf ON r.id = rf.room_id
        GROUP BY r.id
        ORDER BY r.price_per_night ASC
    ");
    
    $rooms = [];
    while ($row = $stmt->fetch_assoc()) {
        $row['facilities'] = $row['facilities'] ? explode(',', $row['facilities']) : [];
        $rooms[] = $row;
    }
    
    sendResponse(true, 'Rooms retrieved', $rooms);
}

$conn->close();
?>