
<?php 

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "curopayment";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if(isset($_POST['submit'])){
   
   
    $prev_transaction = $_POST['prev_transaction'];
    // $prev_transaction ="T20545177338";

    $SITE_ID = "28096";
    $CURRENCYID = "USD";
    $item_id = "123";
    $item_price = "100";
    $referance = "Reference Recurring 1";
    $description = "Description Recurring 1";
    $ip = "0.0.0.0";


        
    $sMerchant = '15786';
    $sAPI_Key = 'tSswm8d4nc!!rsIgpZjyNky~AvVO!Ie6B7oGKQWe7Wp^a2Y9ZhEfOO$O.h8$PhrR';

    $sql = "SELECT customer_id from tbl_transactions where transaction_num='$prev_transaction'";

    $result = mysqli_query($conn, $sql);

    if($result){
        $row = mysqli_fetch_assoc($result);
        $userid = $row['customer_id'];
            
        // $sAPI_URL = 'https://secure.curopayments.net/rest/v1/curo/'; // Real
        $sAPI_URL = 'https://secure-staging.curopayments.net/rest/v1/curo/'; // Test

        // Debug flag
        $bDEBUG = TRUE;
        // $bDEBUG = FALSE;

       
        // NB: for test transactions you need test-issuers with different ids!
        
        $sXML = "<?xml version='1.0' encoding='UTF-8'?>
        <request>
            <site_id>$SITE_ID</site_id>
            <referenced_transaction_id>$prev_transaction</referenced_transaction_id>
            <amount>$item_price</amount>
            <currency_id>$CURRENCYID</currency_id>
            <reference>$referance</reference>
            <description>$description</description>
            <ip>$ip</ip>
        </request> 
    ";





        // echo $sXML;

        // create a new cURL resource
        $hCurl = curl_init();

        // set URL and other appropriate options
        curl_setopt($hCurl, CURLOPT_URL, $sAPI_URL . 'recurring/creditcard/');
        curl_setopt($hCurl, CURLOPT_HEADER, 0);

        // set HTTP Basic Authentication
        curl_setopt($hCurl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($hCurl, CURLOPT_USERPWD, $sMerchant . ':' . $sAPI_Key);

        // send and receive XML
        curl_setopt($hCurl, CURLOPT_HTTPHEADER, array(
            'Accept: application/xml',
            'Content-Type: application/xml'
        ));
        curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($hCurl, CURLOPT_TIMEOUT, 60);
        curl_setopt($hCurl, CURLOPT_POSTFIELDS, $sXML);



        // get server response
        $sResponse = curl_exec($hCurl);
        $aCurlInfo = curl_getinfo($hCurl);
        $iError = curl_errno($hCurl);
        $sError = curl_error($hCurl);

        

        // close cURL resource, and free up system resources
        curl_close($hCurl);

        // print response for debug

        if( $bDEBUG) {
            echo "error: $iError $sError\r\n";
            echo "cURL information:\n".
                    print_r($aCurlInfo,1).
                    "\nPost data:\n".
                    print_r($sXML,1).
                    "\nResponse:\n".
                    substr($sResponse,0,500).(strlen($sResponse)>500 ? '...':'');
        }

        try{

            $oXML = simplexml_load_string($sResponse);

            if( $bDEBUG ) {
                // echo "XML = " . print_r($oXML,1) . "\r\n";
            }

            // Check the http response to make sure the request was successfull
            if ( $aCurlInfo['http_code'] >= 200 && $aCurlInfo['http_code'] < 300 ) {

                if( !empty( $oXML->recurring->transaction_id ) && !empty( $oXML->recurring->url ) ) {
                    // Store the transaction_id as reference in our own system
                    $iTransactionID = $oXML->recurring->transaction;
                    $status = $oXML->success;
                    $created_date = date('Y-m-d H:i:s');
                    $sql_addToTransation = "INSERT INTO tbl_transactions (transaction_num, customer_id,item_id,item_price,created_date,tbl_transactions.status,tbl_transactions.description) 
                    VALUES (
                        '$iTransactionID',
                        '$userid',
                        '$item_id',
                        '$item_price',
                        '$created_date',
                        '$status',
                        'recuring'
                        )";
                        // echo $sql_addToTransation;
                    $res = mysqli_query($conn,$sql_addToTransation);
                    
                    if($res){
                        echo "insert done";
                    }
                    else{
                        echo "insert failed";
                    }

                    // Redirect user to the bank to complete the payment
                    $sHeader = "Location: {$oXML->recurring->url}";
                    if( $bDEBUG ) {
                        echo "Header( $sHeader )\r\n";
                    } else {
                        header( $sHeader );
                    }
                    echo "<a href='{$oXML->url}'>Click here to continue</a>\r\n";
                    exit( );
                    /**
                    EXIT when the transaction was finished the user will be redirected to the given return_url
                    **/
                }

            } else {
                // Something went wrong, you problably don't want to show the error to the user!
                echo "Error {$oXML->error->message}";
            }

        } catch( Exception $e) {
            // Bad XML? we should mail ourselves some details
            echo "Transaction problem, please try again later.";
        }
    }
    mysqli_close($conn);

}
?> 

<!DOCTYPE html>
<html>
<body>
<form method="post" >
<div id="first-name-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="prev_transaction" id="prev_transaction" placeholder="Transaction" value=""  autocomplete="off">
        </div>
    <br>
    
    <div id="button-row" class="row">
        <div class="col sm-2-2">
            <button id="survey-button" type="submit" name= "submit" class="btn btn-1">Rush My Order<br><span>Order your package today!</span></button>
        </div>
    </div>
</form>
</body>
</html>
<!-- 
<request>
    <site_id>'.$SITE_ID.'/site_id>
    <referenced_transaction_id>'.$prev_transaction.'</referenced_transaction_id>
    <amount>'.$item_price.'</amount>
    <currency_id>'.$CURRENCYID.'</currency_id>
    <reference>'.$referance.'</reference>
    <description>'.$description.'</description>
    <ip>'.$ip.'</ip>
</request> 
        
        -->