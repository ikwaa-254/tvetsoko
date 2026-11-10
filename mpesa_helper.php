<?php
function triggerMpesaSTK($phone, $amount, $orderCode) {
    // --- 1. Daraja API Credentials ---
    // Note: Use sandbox credentials for testing, and live credentials for production
    $consumerKey = 'YOUR_CONSUMER_KEY'; 
    $consumerSecret = 'YOUR_CONSUMER_SECRET'; 
    $businessShortCode = '174379'; // Sandbox Paybill
    $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Sandbox Passkey
    $callbackUrl = 'https://yourdomain.com/mpesa_callback.php'; // Must be a live, HTTPS URL
    
    // --- 2. Format Phone Number ---
    // Safaricom requires the number to start with 254
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);

    // --- 3. Get Access Token ---
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_response = curl_exec($curl);
    $token_data = json_decode($curl_response);
    
    if (!isset($token_data->access_token)) {
        return false; // Failed to get token
    }
    $accessToken = $token_data->access_token;

    // --- 4. Initiate STK Push ---
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);
    
    $stkUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $curl_post_data = array(
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => round($amount), // Must be a whole number
        'PartyA' => $phone,
        'PartyB' => $businessShortCode,
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $orderCode,
        'TransactionDesc' => 'Payment for Order ' . $orderCode
    );

    $data_string = json_encode($curl_post_data);
    
    curl_setopt($curl, CURLOPT_URL, $stkUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer ' . $accessToken));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}
?>