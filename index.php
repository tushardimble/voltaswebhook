<?php
  error_reporting(E_ALL);
  date_default_timezone_set('Asia/Calcutta');

  // DB Credentials which is hosting on Plesk Server of 66
  $servername = "66.45.232.178";
  $username   = "voltas_webhook";
  $password   = "voltas_webhook";
  $dbname     = "voltas_webhook";

  // $servername = "localhost";
  // $username   = "root";
  // $password   = "";
  // $dbname     = "voltas_webhook";
  // Connect with Db 
  try{
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Connected";exit;
  } catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
  }

  $request      = file_get_contents('php://input');
  $requestDecode  = json_decode($request);
  
  $intent       = $requestDecode  ->  queryResult ->  intent  ->  displayName;
  $userQueryText  = $requestDecode  ->  queryResult ->  queryText;

  // Get Session Id
  $outputContexts     =   $requestDecode -> queryResult -> outputContexts[0] -> name;
  $outputContextsArray  =   explode("/", $outputContexts);
  $sessionId        =   $outputContextsArray[4];
    
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
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://qavcare.voltasworld.com/siebel/v1.0/service/UPBGAccountSRQueryAPI/QuerySRWithContact",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS =>"{\r\n    \"body\": {\r\n        \"Phone Number\": \"$mobile_number\"\r\n    }\r\n}\r\n",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Basic Q09OTkVRVDpVSSgzMzAyMzB0",
        "Content-Type: application/json"
      ),
    ));

    $aAccountInforesponse = curl_exec($curl);

    curl_close($curl);
    $user_account_info = json_decode($aAccountInforesponse,true);

    $iCount_Account = $user_account_info['Count_Account'];
    $iCount_Account = 1;
    $mobile_number = "9579123744";
    $user_account_info['SiebelMessage']['UPBGAccountRestAPIBC']['Account_Name']= "Tushar Dimble";
    if($iCount_Account != 0){

      //Send OTP on Mobile Number
      $otp = rand(1000,9999);
      $curl = curl_init();
      $url = "http://2factor.in/API/V1/066901d9-a62e-11ea-9fa5-0200cd936042/SMS/".$mobile_number."/".$otp;
      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
      ));

      $response = curl_exec($curl);
      $err = curl_error($curl);

      curl_close($curl);
      // Delete Previous All OTP of that mobile number
      $sDeleteOtpsql  = "DELETE FROM otp WHERE mobile_number = ?";        
      $statement      = $conn->prepare($sDeleteOtpsql);
      $response       = $statement->execute(array($mobile_number)); 

      // Store OTP in DB
      $insertotpsql = 'INSERT INTO otp(session_id,mobile_number,otp,otp_date)VALUES (:session_id, :mobile_number, :otp,:otp_date)';
      $statement = $conn->prepare($insertotpsql);
      $statement->execute([
          'session_id' => $sessionId,
          'mobile_number' => $mobile_number,
          'otp' => $otp,
          'otp_date' => date("Y-m-d")
      ]);
      

      // Store User Data in Our DB
      $account_id = $user_account_info['SiebelMessage']['UPBGAccountRestAPIBC']['Id'];
      $key_account_name = $user_account_info['SiebelMessage']['UPBGAccountRestAPIBC']['Account_Sub_Type'];
      $insertusersql = 'INSERT INTO  user_data(session_id,account_id,key_account_name,created_datetime)VALUES (:session_id, :account_id, :key_account_name,:created_datetime)';
      $statement = $conn->prepare($insertusersql);
      $statement->execute([
          'session_id' => $sessionId,
          'account_id' => $account_id,
          'key_account_name' => $key_account_name,
          'created_datetime' => date("Y-m-d")
      ]);


      // Store Address in DB against this Session which is get from Account Listing API
      $aAddressArray = $user_account_info['SiebelMessage']['UPBGAccountRestAPIBC']['UPBGAddressRestAPIBC'];
      
      $i = 1;
      foreach($aAddressArray as $addValue){
        
          $insertaddresssql = 'INSERT INTO customer_address(sequence,customer_mobile,session_id,address,location_id)VALUES (:sequence,:mobile_number,:session_id,:address,:location_id)';

          $statement = $conn->prepare($insertaddresssql);
          $statement->execute([
              'sequence' => $i,
              'mobile_number' => $mobile_number,
              'session_id' => $sessionId,
              'address' => $addValue['Address_Name'],
              'location_id' => $addValue['Id']
          ]);
          //echo  $insertaddresssql;
          $i++;
      }

      $message = "Hi ".$user_account_info['SiebelMessage']['UPBGAccountRestAPIBC']['Account_Name'].", we have sent an OTP to your mobile number";

    }else{
      // Account not found

    }
  }else if($intent == "validate OTP"){
    $userOTP = $requestDecode -> queryResult -> parameters -> OTP;

    // Check user entered OTP is correct or not
    $checkOTPSql  = "SELECT * FROM otp where session_id='$sessionId' AND otp='$userOTP'";
    $data     = $conn->prepare($checkOTPSql);
    $data->execute();
    $aOTPData = $data->fetchAll();
    if(count($aOTPData) > 0){
      // Delete Previous All OTP of that mobile number
      $sDeleteOtpsql  = "DELETE FROM otp WHERE session_id = ? AND otp=?";        
      $statement      = $conn->prepare($sDeleteOtpsql);
      $response       = $statement->execute(array($sessionId,$userOTP)); 

      $aEventdata['followupEventInput']['name'] = "existingaddress";
      $aEventdata['languageCode'] = "en-US";
      $aBlankDetails = json_encode($aEventdata);
      echo $aBlankDetails;exit;
    }else{
      $message = "Sorry Incorrect OTP.We request you to register your request at 9650694555 or visit https://voltasservice.com for further queries. Thank you";
    }
  }else if($intent == "existing address selection or not - yes"){
    // Get All Address of Customer
    $getAddressSql  = "SELECT * FROM customer_address where session_id='$sessionId'";
    $data     = $conn->prepare($getAddressSql);
    $data->execute();
    $aAddressData = $data->fetchAll();
    $iAddressCount = count($aAddressData);
    if($iAddressCount != 0){
      $address = "";
      foreach($aAddressData as $addValue){
        $address .= $addValue['sequence'].":-".$addValue['address']." ";
      }
    }
    $message = "Please Select Your address: " .$address;
  }else if($intent == "existing address selection or not - yes - select.number"){
    $selected_address = $requestDecode -> queryResult -> parameters -> address_sequence;

    // Get location Id From Our DB 
    $getLocationSql  = "SELECT * FROM customer_address where session_id='$sessionId' AND sequence='$selected_address'";
    $data     = $conn->prepare($getLocationSql);
    $data->execute();
    $aLocationIdData = $data->fetchAll();
    $iLocationCount = count($aLocationIdData);
    if($iLocationCount != 0){
      // Insert Location Id In SR Request Table
      $insertSrRequestsql = 'INSERT INTO sr_request(session_id,location_id,sr_type,sr_request_date)VALUES (:session_id, :location_id, :sr_type,:sr_request_date)';
      $statement = $conn->prepare($insertSrRequestsql);
      $statement->execute([
          'session_id' => $sessionId,
          'location_id' => $aLocationIdData[0]['location_id'],
          'sr_type' => "Technical",
          'sr_request_date' => date("Y-m-d H:i:s")
      ]);
      $aEventdata['followupEventInput']['name'] = "select_product";
      $aEventdata['followupEventInput']['parameters']['selected_product'] = '';
      $aEventdata['languageCode'] = "en-US";
      $aBlankDetails = json_encode($aEventdata);
      echo $aBlankDetails;exit;
    }else{
      $message = "Invalid Address selection";
    }
  }else if($intent == "select_product"){
    // Store product in SR Request Table
    $selected_product = $requestDecode -> queryResult -> parameters -> selected_product;

    if($selected_product != ""){
      // Get location Id From Our DB 
      $getProductSql  = "SELECT * FROM product where  sequence='$selected_product'";
      $data     = $conn->prepare($getProductSql);
      $data->execute();
      $aProductData = $data->fetchAll();
      $iProductCount = count($aProductData);
      if($iProductCount != 0){
        // Update Product name in SR Request Table
        $product_name = $aProductData[0]['product_name'];
        $sUpdateProductSql = "Update sr_request SET product_name='$product_name' WHERE session_id= '$sessionId'";
        //echo $sUpdateProductSql;exit;
        $data     = $conn->prepare($sUpdateProductSql);
        $data->execute();

        $aEventdata['followupEventInput']['name'] = "select_sub_type";
        $aEventdata['followupEventInput']['parameters']['sub_type'] = '';
        $aEventdata['languageCode'] = "en-US";
        $aBlankDetails = json_encode($aEventdata);
        echo $aBlankDetails;exit;
      }else{
        $message = "Invalid product selected";
      }
    }
  }else if($intent == "sub_type"){
    // Store product in SR Request Table
    $sub_type = $requestDecode -> queryResult -> parameters -> sub_type;

    if($sub_type != ""){
      // Get location Id From Our DB 
      $getSubTypeSql  = "SELECT * FROM sr_sub_type where  sequence='$sub_type'";
      $data     = $conn->prepare($getSubTypeSql);
      $data->execute();
      $aSubTypeData = $data->fetchAll();
      $iSubTypeCount = count($aSubTypeData);
      if($iSubTypeCount != 0){
        // Update Product name in SR Request Table
        $sub_type_name = $aSubTypeData[0]['sub_type'];
        $sUpdateProductSql = "Update sr_request SET sr_sub_type='$sub_type_name' WHERE session_id= '$sessionId'";
        //echo $sUpdateProductSql;exit;
        $data     = $conn->prepare($sUpdateProductSql);
        $data->execute();

        $aEventdata['followupEventInput']['name'] = "create_sr_request";
        $aEventdata['languageCode'] = "en-US";
        $aBlankDetails = json_encode($aEventdata);
        echo $aBlankDetails;exit;
      }else{
        $message = "Invalid product selected";
      }
    }
  }else if($intent == "create_sr_request"){

  }
  $data = array (
    'fulfillmentText' => $message
  );

  $aFinalDialogflowResponse = json_encode($data,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

  echo $aFinalDialogflowResponse;
?>
