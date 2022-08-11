<?php
/* Purpose:
            To authenticate with Creatio. This function will return a 
            bpmcsrf and cookie which should be used with a oData call.
    Usage:
            list($bpmcsrf, $cookie) = authenticate_with_creatio("company.creatio.com", "username", "password");
            echo $bpmcsrf;
            echo $cookie;
            
    Tyler Rainey
*/
function authenticate_with_creatio($url, $username, $password) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $data = array('UserName'=>$username,'UserPassword'=>$password);
    $data_json = json_encode($data);
    
    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json; charset=utf-8';
    $headers[] = 'ForceUseSession: true';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    
    $result = curl_exec($ch);
    
    curl_close($ch);
    
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
       parse_str($item, $cookie);
       $cookies = array_merge($cookies, $cookie);
    }
    
    $bpmcsrf = "BPMCSRF: ".$cookies['BPMCSRF'];
    $cookie = "Cookie: BPMLOADER={$cookies['BPMLOADER']}; .ASPXAUTH={$cookies['_ASPXAUTH']}; BPMCSRF={$cookies['BPMCSRF']}; UserName={$cookie['UserName']}";
    
    return array($bpmcsrf, $cookie);
}
/* Purpose:
            Check if a contact exists by email.
    Usage:
            if (email_exists_in_creatio("test@test.com")) {} else {}
            
    Tyler Rainey
*/
function email_exists_in_creatio($email) {
    list($bpmcsrf, $cookie) = authenticate_with_creatio('https://dev-twinfoldcapital.creatio.com/ServiceModel/AuthService.svc/Login', 'Supervisor', 'vtJust4Now!');
    $curlId = curl_init();
	curl_setopt_array($curlId, array(
	CURLOPT_URL => 'https://dev-twinfoldcapital.creatio.com/0/DataService/json/reply/SelectQuery',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS =>"{
		\"RootSchemaName\": \"Contact\",
		\"OperationType\": \"Select\",
		\"Columns\": {
		   \"Items\": {
			  \"Email\": {
				 \"Expression\":{
					\"ExpressionType\": \"SchemaColumn\",
					\"ColumnPath\": \"Email\"
				 }
			  }
			  
		   }
		},
		\"AllColumns\": false,
		\"IsPageable\": false,
		\"Filters\": {
		   \"RootSchemaName\": \"Contact\",
		   \"FilterType\": \"CompareFilter\",
		   \"LogicalOperation\": \"And\",
		   \"LeftExpression\": {
			  \"ExpressionType\": \"SchemaColumn\",
			  \"ColumnPath\": \"Email\"
		   },
		   \"ComparisonType\": \"Equal\",
		   \"RightExpression\": {
			  \"ExpressionType\": \"Parameter\",
			  \"Parameter\" : {
					\"DataValueType\" : 1,
					\"Value\" : \"{$email}\"
				 }
		   },
		   \"LeftExpressionCaption\": \"test\",
		   \"IsAggregative\": false,
		   \"Key\": \"Title filter\"
		}
	 }",
	CURLOPT_HTTPHEADER => array(
	   'Content-Type: application/json',
	   $bpmcsrf,
	   $cookie
	 ),
	));
	$response = curl_exec($curlId);
	curl_close($curlId);
	$length = count(json_decode($response, true)["rows"]);
	
	return $length > 0;
}
/* Purpose:

            To create the long concatenated string that the CURLOPT_POSTFIELDS
            uses. The post fields that are created are specifically for update
            calls.
            
    Params:
            $fields : associative array - e.g. array("fieldName" => "fieldValue", ... "fieldName3" => "fieldValue3");
            $columnPath : string - e.g. "Id"
            $columnPathValue : string - e.g. "b9c76906-132d-40f9-b3e8-d969e382bcb3"
            
    Usage:
            $postFields = create_update_post_fields($fields, $columnPath, $columnPathValue)
            
    Tyler Rainey
*/
function createUpdatePostFields($fields, $columnPath, $columnPathValue) {
    $output = "{\"RootSchemaName\": \"Contact\",
                    \"OperationType\": \"Update\",
                    \"ColumnValues\": {
                        \"Items\": {";
    $isFirst = true;
    foreach ($fields as $field => $value) {
        if (!$isFirst) {
            $output = $output . ",";
        }
        $isFirst = false;
        
        
        if ($field == "Owner") {
            $output = $output . "\"{$field}\": {
    							\"ExpressionType\": \"Parameter\",
    							\"Parameter\": {
    								\"DataValueType\": \"GUID\",
    								\"Value\": \"{$value}\"
    							}
    						}";
        }
        else {
            $output = $output . "\"{$field}\": {
    							\"ExpressionType\": \"Parameter\",
    							\"Parameter\": {
    								\"DataValueType\": \"TEXT\",
    								\"Value\": \"{$value}\"
    							}
    						}";
        }
        
        
    }
    $output = $output ."}
                    },
                    \"Filters\": {
                        \"RootSchemaName\": \"Contact\",
                        \"FilterType\": \"CompareFilter\",
                        \"LogicalOperation\": \"And\",
                        \"LeftExpression\": {
                        \"ExpressionType\": \"SchemaColumn\",
                        \"ColumnPath\": \"$columnPath\"
                        },
                        \"ComparisonType\": \"Equal\",
                        \"RightExpression\": {
                        \"ExpressionType\": \"Parameter\",
                        \"Parameter\" : {
                                \"DataValueType\" : 1,
                                \"Value\" : \"{$columnPathValue}\"
                            }
                        },
                        \"LeftExpressionCaption\": \"test\",
                        \"IsAggregative\": false,
                        \"Key\": \"Title filter\"
                    }
                }";
                    
                    
    return $output;
}
/* Purpose:

            To create the long concatenated string that the CURLOPT_POSTFIELDS
            uses. The post fields that are created are specifically for insert
            calls.
            
    Params:
            $fields : associative array - e.g. array("fieldName" => "fieldValue", ... "fieldName3" => "fieldValue3");
            $columnPath : string - e.g. "Id"
            $columnPathValue : string - e.g. "b9c76906-132d-40f9-b3e8-d969e382bcb3"
            
    Usage:
            $postFields = create_update_post_fields($fields, $columnPath, $columnPathValue)
            
    Tyler Rainey
*/
function createInsertPostFields($fields) {
    $output = "{
			\"RootSchemaName\": \"Contact\",
			\"OperationType\": \"Insert\",
			\"ColumnValues\": {
				\"Items\": {";
	$isFirst = true;
    foreach ($fields as $field => $value) {
        if (!$isFirst) {
            $output = $output . ",";
        }
        $isFirst = false;
        
        
        if ($field == "Owner") {
            $output = $output . "\"{$field}\": {
    							\"ExpressionType\": \"Parameter\",
    							\"Parameter\": {
    								\"DataValueType\": \"GUID\",
    								\"Value\": \"{$value}\"
    							}
    						}";
        }
        else {
            $output = $output . "\"{$field}\": {
    							\"ExpressionType\": \"Parameter\",
    							\"Parameter\": {
    								\"DataValueType\": \"TEXT\",
    								\"Value\": \"{$value}\"
    							}
    						}";
        }
        
        
    }
    $output = $output ."}
                    }
                }";
                    
    return $output;
}
/* Purpose:

            To create the long concatenated string that the CURLOPT_POSTFIELDS
            uses. The post fields that are created are specifically for update
            calls.
            
    Params:
            $bpmcsrf : string - e.g. comes from the authenticate_with_creatio call
            $cookie : string - e.g. comes from the authenticate_with_creatio call
            $fields : associative array - e.g. array("fieldName" => "fieldValue", ... "fieldName3" => "fieldValue3");
            $columnPathValue : string - e.g. "b9c76906-132d-40f9-b3e8-d969e382bcb3"
            $hash : string
    Usage:
            $response = update_creatio_with_hash($bpmcsrf, $cookie, $fieldsToUpdate, $hash);
            
    Tyler Rainey
*/
function create_contact($bpmcsrf, $cookie, $fields) {
    // var_dump($fields);
    $postFields = createInsertPostFields($fields);
    $cr = curl_init();
    curl_setopt_array($cr, array(
      CURLOPT_URL => 'https://dev-twinfoldcapital.creatio.com/0/DataService/json/reply/InsertQuery',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $postFields,
    			CURLOPT_HTTPHEADER => array(
    				'Content-Type: application/json',
    				$bpmcsrf,
    				$cookie
    				),
    			));
    $response = curl_exec($cr);
    
    return $response;
}
/* Purpose:

            To create the long concatenated string that the CURLOPT_POSTFIELDS
            uses. The post fields that are created are specifically for update
            calls.
            
    Params:
            $bpmcsrf : string - e.g. comes from the authenticate_with_creatio call
            $cookie : string - e.g. comes from the authenticate_with_creatio call
            $fields : associative array - e.g. array("fieldName" => "fieldValue", ... "fieldName3" => "fieldValue3");
            $columnPathValue : string - e.g. "b9c76906-132d-40f9-b3e8-d969e382bcb3"
            $hash : string
    Usage:
            $response = update_creatio_with_hash($bpmcsrf, $cookie, $fieldsToUpdate, $hash);
            
    Tyler Rainey
*/
function update_contact_with_email($bpmcsrf, $cookie, $fields, $email) {
    $postFields = createUpdatePostFields($fields, "Email", $email);
    $cr = curl_init();
    curl_setopt_array($cr, array(
      CURLOPT_URL => 'https://dev-twinfoldcapital.creatio.com/0/DataService/json/reply/UpdateQuery',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $postFields,
    			CURLOPT_HTTPHEADER => array(
    				'Content-Type: application/json',
    				$bpmcsrf,
    				$cookie
    				),
    			));
    $response = curl_exec($cr);
    return $response;
}

function insert_contact($fields) {
    list($bpmcsrf, $cookie) = authenticate_with_creatio('https://dev-twinfoldcapital.creatio.com/ServiceModel/AuthService.svc/Login', 'Supervisor', 'vtJust4Now!');
    $response = create_contact($bpmcsrf, $cookie, $fields);
    return $response;
}

function update_contact($email, $fields) {
    list($bpmcsrf, $cookie) = authenticate_with_creatio('https://dev-twinfoldcapital.creatio.com/ServiceModel/AuthService.svc/Login', 'Supervisor', 'vtJust4Now!');
    $response = update_contact_with_email($bpmcsrf, $cookie, $fields, $email);
    return $response;
}



/* Purpose:
            To authenticate with Creatio. This function will return a 
            bpmcsrf and cookie which should be used with a oData call.
    Usage:
            list($bpmcsrf, $cookie) = authenticate_with_creatio("company.creatio.com", "username", "password");
            echo $bpmcsrf;
            echo $cookie;
            
    Tyler Rainey
*/
function authenticate_with_creatio($url, $username, $password) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $data = array('UserName'=>$username,'UserPassword'=>$password);
    $data_json = json_encode($data);
    
    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json; charset=utf-8';
    $headers[] = 'ForceUseSession: true';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    
    $result = curl_exec($ch);
    
    curl_close($ch);
    
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
       parse_str($item, $cookie);
       $cookies = array_merge($cookies, $cookie);
    }
    
    $bpmcsrf = "BPMCSRF: ".$cookies['BPMCSRF'];
    $cookie = "Cookie: BPMLOADER={$cookies['BPMLOADER']}; .ASPXAUTH={$cookies['_ASPXAUTH']}; BPMCSRF={$cookies['BPMCSRF']}; UserName={$cookie['UserName']}";
    
    return array($bpmcsrf, $cookie);
}
/* Purpose:
            Check if a contact exists by hash.
    Usage:
            if (hash_exists_in_creatio("4191ef5l2c1576762869ac49281130c9")) {} else {}
            
    Tyler Rainey
*/
function hash_exists_in_creatio($hash) {
    list($bpmcsrf, $cookie) = authenticate_with_creatio('https://dev-twinfoldcapital.creatio.com/ServiceModel/AuthService.svc/Login', 'Supervisor', 'vtJust4Now!');
    $curlId = curl_init();
	curl_setopt_array($curlId, array(
	CURLOPT_URL => 'https://dev-twinfoldcapital.creatio.com/0/DataService/json/reply/SelectQuery',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS =>"{
		\"RootSchemaName\": \"Contact\",
		\"OperationType\": \"Select\",
		\"Columns\": {
		   \"Items\": {
			  \"Email\": {
				 \"Expression\":{
					\"ExpressionType\": \"SchemaColumn\",
					\"ColumnPath\": \"Email\"
				 }
			  }
			  
		   }
		},
		\"AllColumns\": false,
		\"IsPageable\": false,
		\"Filters\": {
		   \"RootSchemaName\": \"Contact\",
		   \"FilterType\": \"CompareFilter\",
		   \"LogicalOperation\": \"And\",
		   \"LeftExpression\": {
			  \"ExpressionType\": \"SchemaColumn\",
			  \"ColumnPath\": \"Email\"
		   },
		   \"ComparisonType\": \"UsrHash\",
		   \"RightExpression\": {
			  \"ExpressionType\": \"Parameter\",
			  \"Parameter\" : {
					\"DataValueType\" : 1,
					\"Value\" : \"{$hash}\"
				 }
		   },
		   \"LeftExpressionCaption\": \"test\",
		   \"IsAggregative\": false,
		   \"Key\": \"Title filter\"
		}
	 }",
	CURLOPT_HTTPHEADER => array(
	   'Content-Type: application/json',
	   $bpmcsrf,
	   $cookie
	 ),
	));
	$response = curl_exec($curlId);
	curl_close($curlId);
	$length = count(json_decode($response, true)["rows"]);
	
	return $length > 0;
}
/* Purpose:

            To create the long concatenated string that the CURLOPT_POSTFIELDS
            uses. The post fields that are created are specifically for update
            calls.
            
    Params:
            $bpmcsrf : string - e.g. comes from the authenticate_with_creatio call
            $cookie : string - e.g. comes from the authenticate_with_creatio call
            $fields : associative array - e.g. array("fieldName" => "fieldValue", ... "fieldName3" => "fieldValue3");
            $columnPathValue : string - e.g. "b9c76906-132d-40f9-b3e8-d969e382bcb3"
            $hash : string
    Usage:
            $response = update_creatio_with_hash($bpmcsrf, $cookie, $fieldsToUpdate, $hash);
            
    Tyler Rainey
*/
function update_contact_with_hash($bpmcsrf, $cookie, $fields, $hash) {
    $postFields = createUpdatePostFields($fields, "UsrHash", $hash);
    $cr = curl_init();
    curl_setopt_array($cr, array(
      CURLOPT_URL => 'https://dev-twinfoldcapital.creatio.com/0/DataService/json/reply/UpdateQuery',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $postFields,
    			CURLOPT_HTTPHEADER => array(
    				'Content-Type: application/json',
    				$bpmcsrf,
    				$cookie
    				),
    			));
    $response = curl_exec($cr);
    return $response;
}

?>
