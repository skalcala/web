<?php
// PayMaya checkout page with queue system
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}

// Extract booking parameters
$roomId = $_GET['room_id'] ?? '0';
$checkin = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';
$adults = $_GET['adults'] ?? '1';
$children = $_GET['children'] ?? '0';
$price = $_GET['price'] ?? '0';
$nights = $_GET['nights'] ?? '1';

// Validate required parameters
if (empty($roomId) || empty($checkin) || empty($checkout)) {
    die('<script>alert("Invalid booking parameters"); window.close();</script>');
}

$amount = number_format(floatval($price) * intval($nights), 2, '.', '');
$transactionId = 'PAYMAYA-' . time() . '-' . substr(md5(uniqid()), 0, 8);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>PayMaya Payment - Sunset Beach Resort</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="../images/logo.png">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #00b140 0%, #00d659 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .payment-card {
      background: white;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      max-width: 500px;
      width: 100%;
      overflow: hidden;
      animation: slideUp 0.5s ease-out;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .header {
      background: linear-gradient(135deg, #00b140 0%, #00d659 100%);
      padding: 32px 32px 24px;
      text-align: center;
      color: white;
    }
    .paymaya-logo {
      font-size: 32px;
      font-weight: 800;
      letter-spacing: -1px;
      margin-bottom: 8px;
    }
    .header-subtitle {
      font-size: 14px;
      opacity: 0.9;
      font-weight: 400;
    }
    .content { padding: 32px; }
    .qr-section {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 20px;
      padding: 24px;
      text-align: center;
      margin-bottom: 24px;
      border: 2px solid #e9ecef;
    }
    .qr-title {
      font-size: 15px;
      color: #495057;
      margin-bottom: 16px;
      font-weight: 600;
    }
    .qr-code {
      background: white;
      padding: 16px;
      border-radius: 16px;
      display: inline-block;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-bottom: 16px;
    }
    .qr-code img {
      width: 220px;
      height: 220px;
      display: block;
      border-radius: 8px;
    }
    .amount-display {
      background: linear-gradient(135deg, #00b140 0%, #00d659 100%);
      color: white;
      padding: 16px;
      border-radius: 12px;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }
    .amount-label {
      font-size: 13px;
      font-weight: 500;
      opacity: 0.9;
      margin-bottom: 4px;
    }
    .booking-details {
      background: #f8f9fa;
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 24px;
    }
    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #e9ecef;
    }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-label {
      color: #6c757d;
      font-size: 14px;
      font-weight: 500;
    }
    .detail-value {
      color: #212529;
      font-size: 14px;
      font-weight: 600;
      text-align: right;
    }
    .button-group {
      display: flex;
      gap: 12px;
      margin-bottom: 16px;
    }
    .btn {
      flex: 1;
      padding: 16px;
      border-radius: 12px;
      border: none;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-primary {
      background: linear-gradient(135deg, #00b140 0%, #00d659 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(0, 177, 64, 0.3);
    }
    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 177, 64, 0.4);
    }
    .btn-secondary {
      background: white;
      color: #495057;
      border: 2px solid #dee2e6;
    }
    .btn-secondary:hover:not(:disabled) {
      background: #f8f9fa;
      border-color: #adb5bd;
    }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .status-box {
      background: linear-gradient(135deg, #e7f5ff 0%, #d0ebff 100%);
      border: 2px solid #339af0;
      border-radius: 12px;
      padding: 16px;
      margin-top: 16px;
      animation: fadeIn 0.4s ease-out;
    }
    .status-box.success {
      background: linear-gradient(135deg, #d3f9d8 0%, #b2f2bb 100%);
      border-color: #51cf66;
    }
    .status-box.error {
      background: linear-gradient(135deg, #ffe0e0 0%, #ffc9c9 100%);
      border-color: #ff6b6b;
    }
    .status-box.queue {
      background: linear-gradient(135deg, #fff4e6 0%, #ffe8cc 100%);
      border-color: #fd7e14;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .status-title {
      font-weight: 700;
      font-size: 15px;
      margin-bottom: 8px;
      color: #212529;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .status-text {
      font-size: 13px;
      color: #495057;
      line-height: 1.6;
    }
    .disclaimer {
      text-align: center;
      color: #6c757d;
      font-size: 12px;
      padding: 16px;
      background: #f8f9fa;
      border-radius: 12px;
      margin-top: 16px;
    }
    .spinner {
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      display: inline-block;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .hidden { display: none; }
    .booking-id {
      background: #fff;
      padding: 10px;
      border-radius: 8px;
      margin-top: 10px;
      font-family: monospace;
      font-size: 14px;
      color: #00b140;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="payment-card">
    <div class="header">
      <div class="paymaya-logo">PayMaya</div>
      <div class="header-subtitle">Secure Payment Gateway</div>
    </div>

    <div class="content">
      <div class="qr-section">
        <div class="qr-title">Scan QR Code with PayMaya App</div>
        <div class="qr-code">
          <img id="qrImg" alt="PayMaya QR Code" src="../images/QR.png" />
        </div>
        <div class="amount-display">
          <div class="amount-label">Total Amount</div>
          ₱<?= htmlspecialchars($amount) ?>
        </div>
      </div>

      <div class="booking-details">
        <div class="detail-row">
          <span class="detail-label">Room</span>
          <span class="detail-value" id="roomName">Loading...</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Price per Night</span>
          <span class="detail-value">₱<?= htmlspecialchars($price) ?> × <?= htmlspecialchars($nights) ?> nights</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Check-in</span>
          <span class="detail-value"><?= htmlspecialchars(date('M d, Y', strtotime($checkin))) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Check-out</span>
          <span class="detail-value"><?= htmlspecialchars(date('M d, Y', strtotime($checkout))) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Guests</span>
          <span class="detail-value"><?= htmlspecialchars($adults) ?> Adult(s), <?= htmlspecialchars($children) ?> Child(ren)</span>
        </div>
      </div>

      <div class="button-group">
        <button id="payBtn" class="btn btn-primary">
          <span id="payBtnText">Confirm Payment</span>
        </button>
        <button id="cancelBtn" class="btn btn-secondary">Cancel</button>
      </div>

      <div id="status" class="hidden"></div>

      <div class="disclaimer">
        Secure demo payment page. Your booking will be processed immediately.
      </div>
    </div>
  </div>

  <script>
  const API_BASE = '../api/';
  const bookingData = {
    roomId: <?= json_encode($roomId) ?>,
    checkin: <?= json_encode($checkin) ?>,
    checkout: <?= json_encode($checkout) ?>,
    adults: <?= json_encode($adults) ?>,
    children: <?= json_encode($children) ?>,
    price: <?= json_encode($price) ?>,
    nights: <?= json_encode($nights) ?>,
    transactionId: <?= json_encode($transactionId) ?>,
    provider: 'PayMaya'
  };

  const payBtn = document.getElementById('payBtn');
  const payBtnText = document.getElementById('payBtnText');
  const cancelBtn = document.getElementById('cancelBtn');
  const statusEl = document.getElementById('status');

  fetch(API_BASE + 'bookings.php?action=getRooms')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const room = data.data.find(r => r.id == bookingData.roomId);
        if (room) {
          document.getElementById('roomName').textContent = room.name;
        }
      }
    });

  function showStatus(title, text, type = 'info', icon = '') {
    statusEl.className = 'status-box ' + type;
    statusEl.innerHTML = '<div class="status-title">' + 
      (icon ? '<span>' + icon + '</span>' : '') + 
      title + '</div><div class="status-text">' + text + '</div>';
    statusEl.classList.remove('hidden');
  }

 payBtn.addEventListener('click', async function() {
    payBtn.disabled = true; 
    cancelBtn.disabled = true;
    payBtnText.innerHTML = '<span class="spinner"></span> Processing...';
    
    try {
      // First check availability and queue status
      showStatus('Checking Availability', 'Verifying room availability for your dates...', 'info', '🔍');
      
      const checkResponse = await fetch(
        API_BASE + `bookings.php?action=checkAvailability&roomId=${bookingData.roomId}&checkin=${bookingData.checkin}&checkout=${bookingData.checkout}`
      );
      const checkText = await checkResponse.text();
      
      let checkResult;
      try {
        checkResult = JSON.parse(checkText);
      } catch (e) {
        console.error('Parse error:', e);
        console.error('Response:', checkText.substring(0, 500));
        throw new Error('Server error. Please check browser console.');
      }
      
      if (!checkResult.success) {
        throw new Error(checkResult.message || 'Unable to check availability');
      }
      
      if (checkResult.data.isFull) {
        showStatus(
          'Room at Capacity - Joining Queue', 
          'This room is currently at maximum capacity for your selected dates. Your booking will be added to the queue. You will be notified when a spot becomes available.',
          'queue'
        );
        await new Promise(resolve => setTimeout(resolve, 2000));
      } else {
        showStatus('Room Available', 'Great! The room is available for your dates.', 'success');
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
      
      showStatus('Processing Payment', 'Confirming your PayMaya payment and creating booking...', 'info');
      
      const bookingResult = await fetch(API_BASE + 'bookings.php?action=create', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(bookingData)
      }).then(r => r.json());
      
      if (bookingResult.success) {
        const data = bookingResult.data;
        
        if (data.status === 'Queued') {
          showStatus(
            'Booking Queued Successfully!',
            `<strong>Your booking has been added to the queue.</strong><br><br>
            <strong>Queue Position:</strong> #${data.queuePosition}<br>
            <strong>Booking ID:</strong> <div class="booking-id">${data.bookingId}</div>
            <strong>Transaction ID:</strong> ${data.transactionId}<br><br>
            You will be notified via email when your booking is confirmed. Thank you for your patience!`,
            'queue'
          );
        } else {
          showStatus(
            'Booking Confirmed!',
            `<strong>Your payment was successful!</strong><br><br>
            <strong>Booking ID:</strong> <div class="booking-id">${data.bookingId}</div>
            <strong>Transaction ID:</strong> ${data.transactionId}<br>
            <strong>Total Paid:</strong> ₱${parseFloat(data.totalPrice).toLocaleString()}<br><br>
            Thank you for booking with Sunset Beach Resort! A confirmation email has been sent to your registered email address.`,
            'success'
          );
        }
        
        setTimeout(() => {
          if (window.opener) {
            window.opener.location.reload();
          }
          window.close();
        }, 5000);
        
      } else {
        throw new Error(bookingResult.message || 'Booking failed');
      }
      
    } catch (error) {
      console.error('Payment error:', error);
      showStatus(
        'Payment Failed',
        `An error occurred: ${error.message}<br><br>Please try again or contact support if the problem persists.`,
        'error'
      );
      payBtn.disabled = false;
      cancelBtn.disabled = false;
      payBtnText.textContent = 'Try Again';
    }
  });

  cancelBtn.addEventListener('click', function() {
    if (confirm('Are you sure you want to cancel this payment?')) {
      window.close();
    }
  });
  </script>
</body>
</html>