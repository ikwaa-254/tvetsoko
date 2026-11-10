<?php
require_once 'config.php'; // To access your database

$stkCallbackResponse = file_get_contents('php://input');
$logFile = "mpesa_response.json";
file_put_contents($logFile, $stkCallbackResponse . PHP_EOL, FILE_APPEND);

$data = json_decode($stkCallbackResponse);

if (isset($data->Body->stkCallback->ResultCode)) {
    $resultCode = $data->Body->stkCallback->ResultCode;
    
    // ResultCode 0 means the user paid successfully
    if ($resultCode == 0) {
        $callbackMetadata = $data->Body->stkCallback->CallbackMetadata->Item;
        
        $amount = '';
        $mpesaReceiptNumber = '';
        $phoneNumber = '';

        foreach ($callbackMetadata as $item) {
            if ($item->Name == 'Amount') $amount = $item->Value;
            if ($item->Name == 'MpesaReceiptNumber') $mpesaReceiptNumber = $item->Value;
            if ($item->Name == 'PhoneNumber') $phoneNumber = $item->Value;
        }

        // Example: Update your database to mark the order as paid
        // You would typically match this via the phone number, amount, or by storing the CheckoutRequestID earlier.
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Paid', transaction_id = ? WHERE buyer_phone = ? AND status = 'Pending Payment' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$mpesaReceiptNumber, $phoneNumber]);
    }
}
?>