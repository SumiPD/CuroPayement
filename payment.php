
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
   
    $first_name=$_POST['ship_first_name'];
    $last_name=$_POST['ship_last_name'];
    $email=$_POST['ship_email'];
    $phone=$_POST['ship_phone'];
    $ship_address=$_POST['ship_address'];
    $city=$_POST['ship_city'];
    $country=$_POST['ship_country_iso'];
    $zipcode=$_POST['ship_zipcode'];
    $state=$_POST['ship_state_iso'];



    $quantity = 1;
    $sku = 321; //Article number/SKU
    $name = "medicine";//Article name/description
    $price = 10;//Article price per item in cents or decimals (999.99)
    $vat = 10;//VAT percentage
    $vat_inc = 1;//VAT amount included/excluded in the price

    // Specify type of item:
        // 1 normal product default
        // 2 shipping costs
        // 3 administrative costs not used by Klarna
        // 4 discounts
        // 5 handling costs
    $type = 1; 
        
    $sMerchant = '15786';
    $sAPI_Key = 'tSswm8d4nc!!rsIgpZjyNky~AvVO!Ie6B7oGKQWe7Wp^a2Y9ZhEfOO$O.h8$PhrR';

    $sql = "INSERT INTO users (first_name, last_name,email,phone,street,city,country,zipcode,users.state) VALUES ('$first_name','$last_name','$email','$phone','$ship_address','$city','$country','$zipcode','$state')";
    if(mysqli_query($conn,$sql)){
        $userid =  mysqli_insert_id($conn);
            
        // $sAPI_URL = 'https://secure.curopayments.net/rest/v1/curo/'; // Real
        $sAPI_URL = 'https://secure-staging.curopayments.net/rest/v1/curo/'; // Test

        // Debug flag
        // $bDEBUG = TRUE;
        $bDEBUG = FALSE;

        $SITE_ID = "28096";
        $CURRENCYID = "USD";
        $item_price = "100";
        $url_success ="cannwell.co.uk" ;

        $item_id = 123;

        // NB: for test transactions you need test-issuers with different ids!
        $sXML = '<?xml version="1.0" encoding="UTF-8"?>
        <payment>
            <recurring>1</recurring>
            <site_id>'.$SITE_ID.'</site_id> 
            <currency_id>'.$CURRENCYID.'</currency_id>
            <amount>'.$item_price.'</amount>
            <reference>Reference 1</reference>
            <description>Description 1</description>
            <url_success>https://www.dictionary.com/browse/success</url_success>
            <url_pending>https://www.dictionary.com/browse/pending</url_pending>
            <url_failure>https://www.dictionary.com/browse/failure</url_failure>
            <url_callback>https://www.dictionary.com/browse/callback</url_callback>
            <issuer_id>RABONL2U</issuer_id>
            <country_id>NL</country_id>
            <language_id>nl</language_id>
            <ip>192.168.0.1</ip>
            <consumer>
                <firstname>'.$first_name.'</firstname>
                <lastname>'.$last_name.'</lastname>
                <email>'.$email.'</email>
                <address>'.$ship_address.'</address>
                <city>'.$city.'</city>
                <zipcode>'.$zipcode.'</zipcode>
                <country_id>'.$country.'</country_id>
                
            </consumer>
           
            <cartitems>
                <item>
                    <quantity>1</quantity>
                    <sku>'.$item_id.'</sku>
                    <name>'.$item_price.'</name>
                    <price>'.$price.'</price>
                    <vat>'.$vat.'</vat>
                    <vat_inc>'.$vat_inc.'</vat_inc>
                    <type>'.$type.'</type>
                </item>
            </cartitems>
        </payment>
        ';

        // create a new cURL resource
        $hCurl = curl_init();

        // set URL and other appropriate options
        curl_setopt($hCurl, CURLOPT_URL, $sAPI_URL . 'payment/creditcard/');
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
                echo "XML = " . print_r($oXML,1) . "\r\n";
            }

            // Check the http response to make sure the request was successfull
            if ( $aCurlInfo['http_code'] >= 200 && $aCurlInfo['http_code'] < 300 ) {

                if(
                    !empty( $oXML->payment->transaction_id )
                && !empty( $oXML->payment->url )
                ) {
                    // Store the transaction_id as reference in our own system
                    $iTransactionID = $oXML->payment->transaction;
                    $status = $oXML->success;
                    $created_date = date('Y-m-d H:i:s');
                    $sql_addToTransation = "INSERT INTO tbl_transactions (transaction_num, customer_id,item_id,item_price,created_date,tbl_transactions.status) 
                    VALUES (
                        '$iTransactionID',
                        '$userid',
                        '$item_id',
                        '$item_price',
                        '$created_date',
                        '$status'
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
                    $sHeader = "Location: {$oXML->payment->url}";
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
}
?> 

<!DOCTYPE html>
<html>
<body>
<form method="post" >
<div id="first-name-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="ship_first_name" id="ship_first_name" placeholder="First Name" value=""  autocomplete="off">
        </div>
    </div>
    <br>
    <div id="last-name-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="ship_last_name" id="ship_last_name" placeholder="Last Name" value="" title="" >
        </div>
    </div>
    <br>
    <div id="email-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="email" name="ship_email" id="ship_email" placeholder="Email" title="" >
        </div>
    </div>
    <br>
    <div id="phone-number-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="tel" name="ship_phone" id="ship_phone" placeholder="Phone" value="" title="" >
        </div>
    </div>
    <br>
    <div id="address-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="ship_address" id="ship_address" placeholder="Street Address" value="" title="" maxlength="35" >
        </div>
    </div>
    <br>
    <div id="city-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="ship_city" id="ship_city" placeholder="City" value="" title="" >
        </div>
    </div>
    <br>
    <div id="country-row" class="row">
        <div class="col sm-2-2">
            <select id="ship_country_iso" name="ship_country_iso" placeholder="Country" >
                <option value="US" selected="selected">United States</option>
                <option value="CA">Canada</option>
            </select>
        </div>
    </div>
    <br>
    <div id="zip-code-row" class="row">
        <div class="col sm-2-2">
            <input class="" type="text" name="ship_zipcode" id="ship_zipcode" placeholder="Zip Code" value="" title="" >

        </div>
    </div>
    <br>
    <div id="state-row" class="row">
        <div class="col sm-2-2">
            <select class="js-est-tax-state" id="ship_state_iso" name="ship_state_iso" >
                <option value="" selected="">State</option>
                <option value="AL" class="US form_states_hide">Alabama</option>
                <option value="AK" class="US form_states_hide">Alaska</option>
                <option value="AZ" class="US form_states_hide">Arizona</option>
                <option value="AR" class="US form_states_hide">Arkansas</option>
                <option value="CA" class="US form_states_hide">California</option>
                <option value="CO" class="US form_states_hide">Colorado</option>
                <option value="CT" class="US form_states_hide">Connecticut</option>
                <option value="DE" class="US form_states_hide">Delaware</option>
                <option value="DC" class="US form_states_hide">District of Columbia</option>
                <option value="FL" class="US form_states_hide">Florida</option>
                <option value="GA" class="US form_states_hide">Georgia</option>
                <option value="HI" class="US form_states_hide">Hawaii</option>
                <option value="ID" class="US form_states_hide">Idaho</option>
                <option value="IL" class="US form_states_hide">Illinois</option>
                <option value="IN" class="US form_states_hide">Indiana</option>
                <option value="IA" class="US form_states_hide">Iowa</option>
                <option value="KS" class="US form_states_hide">Kansas</option>
                <option value="KY" class="US form_states_hide">Kentucky</option>
                <option value="LA" class="US form_states_hide">Louisiana</option>
                <option value="ME" class="US form_states_hide">Maine</option>
                <option value="MD" class="US form_states_hide">Maryland</option>
                <option value="MA" class="US form_states_hide">Massachusetts</option>
                <option value="MI" class="US form_states_hide">Michigan</option>
                <option value="MN" class="US form_states_hide">Minnesota</option>
                <option value="MS" class="US form_states_hide">Mississippi</option>
                <option value="MO" class="US form_states_hide">Missouri</option>
                <option value="MT" class="US form_states_hide">Montana</option>
                <option value="NE" class="US form_states_hide">Nebraska</option>
                <option value="NV" class="US form_states_hide">Nevada</option>
                <option value="NH" class="US form_states_hide">New Hampshire</option>
                <option value="NJ" class="US form_states_hide">New Jersey</option>
                <option value="NM" class="US form_states_hide">New Mexico</option>
                <option value="NY" class="US form_states_hide">New York</option>
                <option value="NC" class="US form_states_hide">North Carolina</option>
                <option value="ND" class="US form_states_hide">North Dakota</option>
                <option value="OH" class="US form_states_hide">Ohio</option>
                <option value="OK" class="US form_states_hide">Oklahoma</option>
                <option value="OR" class="US form_states_hide">Oregon</option>
                <option value="PA" class="US form_states_hide">Pennsylvania</option>
                <option value="RI" class="US form_states_hide">Rhode Island</option>
                <option value="SC" class="US form_states_hide">South Carolina</option>
                <option value="SD" class="US form_states_hide">South Dakota</option>
                <option value="TN" class="US form_states_hide">Tennessee</option>
                <option value="TX" class="US form_states_hide">Texas</option>
                <option value="UT" class="US form_states_hide">Utah</option> 
                <option value="VT" class="US form_states_hide">Vermont</option>
                <option value="VA" class="US form_states_hide">Virginia</option>
                <option value="WA" class="US form_states_hide">Washington</option>
                <option value="WV" class="US form_states_hide">West Virginia</option>
                <option value="WI" class="US form_states_hide">Wisconsin</option>
                <option value="WY" class="US form_states_hide">Wyoming</option>
                <option value="AA" class="US form_states_hide">Armed Forces Americas</option>
                <option value="AF" class="US form_states_hide">Armed Forces Africa</option>
                <option value="AC" class="US form_states_hide">Armed Forces Canada</option>
                <option value="AE" class="US form_states_hide">Armed Forces Europe</option>
                <option value="AM" class="US form_states_hide">Armed Forces Middle East</option>
                <option value="AP" class="US form_states_hide">Armed Forces Pacific</option>
                <option value="AS" class="US form_states_hide">American Samoa</option>
                <option value="FM" class="US form_states_hide">Federated States of Micronesia</option>
                <option value="GU" class="US form_states_hide">Guam</option>
                <option value="MH" class="US form_states_hide">Marshall Islands</option>
                <option value="MP" class="US form_states_hide">Northern Mariana Islands</option>
                <option value="PW" class="US form_states_hide">Palau</option>
                <option value="PR" class="US form_states_hide">Puerto Rico</option>
                <option value="VI" class="US form_states_hide">Virgin Islands</option>
                <option value="AB" class="CA">Alberta</option>
                <option value="BC" class="CA">British Columbia</option>
                <option value="MB" class="CA">Manitoba</option>
                <option value="NB" class="CA">New Brunswick</option>
                <option value="NL" class="CA">Newfoundland and Labrador</option>
                <option value="NT" class="CA">Northwest Territories</option>
                <option value="NS" class="CA">Nova Scotia</option>
                <option value="NU" class="CA">Nunavut</option>
                <option value="ON" class="CA">Ontario</option>
                <option value="PE" class="CA">Prince Edward Island</option>
                <option value="QC" class="CA">Quebec</option>
                <option value="SK" class="CA">Saskatchewan</option>
                <option value="YT" class="CA">Yukon Territory</option>
            </select>
          
        </div>
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
