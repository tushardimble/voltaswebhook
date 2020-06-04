<?php
	error_reporting(E_ALL);
  	date_default_timezone_set('Asia/Calcutta');

  	$request    	= file_get_contents('php://input');
    $requestDecode  = json_decode($request);
    
    $intent     	= $requestDecode  ->  queryResult ->  intent  ->  displayName;
    $userQueryText  = $requestDecode  ->  queryResult ->  queryText;

    if($intent == "SRstatus"){
        // Here We integrate Voltas API to check SR Status
        $ticket_number = $requestDecode -> queryResult -> parameters -> ticketno;
        if($ticket_number != ""){
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://qavcare.voltasworld.com/siebel-rest/v1.0/service/VoltasRestAPISRQuery/GetStatus?PageSize=2&ViewMode=All",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS =>"{\r\n    \"body\": {\r\n        \"SRNumber\": \"$ticket_number\"\r\n    }\r\n}\r\n",
              CURLOPT_HTTPHEADER => array(
                "Authorization: Basic Q09OTkVRVDpVSSgzMzAyMzB0",
                "Content-Type: application/json"
              ),
            ));

            $response = curl_exec($curl);
            //echo"<pre>";print_r($response);exit;
            curl_close($curl);
            $response = json_decode($response,true);
            if (array_key_exists("Status",$response)){
                $message = "The status of ticket no ".$ticket_number." is ". $response['Status'];
            }else{
                $message = "Invalid SR Number";
            }
            
        }
    }else if($intent == "Raising Service Request"){
      $mobile_number =   $requestDecode -> queryResult -> parameters -> mobile_number;  
      // If Mobile Number is not blank then check Customer is exist or not in our system by calling Voltas API
      $message = "Hi Tushar, we have sent an OTP to your mobile number";

      // Send OTP on Mobile Number
      // $curl = curl_init();
      // $url = "http://2factor.in/API/V1/066901d9-a62e-11ea-9fa5-0200cd936042/SMS/".$mobile_number."/".$otp;
      // curl_setopt_array($curl, array(
      //   CURLOPT_URL => $url,
      //   CURLOPT_RETURNTRANSFER => true,
      //   CURLOPT_ENCODING => "",
      //   CURLOPT_MAXREDIRS => 10,
      //   CURLOPT_TIMEOUT => 0,
      //   CURLOPT_FOLLOWLOCATION => true,
      //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      //   CURLOPT_CUSTOMREQUEST => "POST",
      // ));

      // $response = curl_exec($curl);
      // $err = curl_error($curl);

      // curl_close($curl);
    }
    $data = array (
      'fulfillmentText' => $message
    );

    $aFinalDialogflowResponse = json_encode($data,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    echo $aFinalDialogflowResponse;
?>
