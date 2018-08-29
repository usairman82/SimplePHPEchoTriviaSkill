<?php
include('valid_request.php');
date_default_timezone_set('America/Los_Angeles');
header('Access-Control-Allow-Origin: *');  
/*
{
  "session": {
    "sessionId": "SessionId.***",
    "application": {
      "applicationId": "amzn1.echo-sdk-ams.app.***"
    },
    "user": {
      "userId": "amzn1.echo-sdk-account.***"
    },
    "new": true
  },
  "request": {
    "type": "IntentRequest",
    "requestId": "EdwRequestId.***",
    "timestamp": 1450942864311,
    "intent": {
      "name": "SpeakName",
      "slots": {
        "name": {
          "name": "name",
          "value": "daniel"
        }
      }
    }
  }
}
*/
//Attribution
//http://pastebin.com/d0W9nX60
function validateKeychainUri($keychainUri)
{
 
    $uriParts = parse_url($keychainUri);

	if (!isset($uriParts["host"]))
	{
		fail("No host in url.");
	}
		
    if (strcasecmp($uriParts['host'], 's3.amazonaws.com') != 0)
	{
        fail('The host for the Certificate provided in the header is invalid');
	}
 
    if (strpos($uriParts['path'], '/echo.api/') !== 0)
	{
        fail('The URL path for the Certificate provided in the header is invalid');
	}
 
    if (strcasecmp($uriParts['scheme'], 'https') != 0)
	{
        fail('The URL is using an unsupported scheme. Should be https');
	}
 
    if (array_key_exists('port', $uriParts) && $uriParts['port'] != '443')
	{
        fail('The URL is using an unsupported https port');
	}
 
}
 
//
// Fail - die() replacement with error logging
//
function fail($message) 
{
 	header('HTTP/1.1 400 Bad Request');
    error_log($message);
    die();
}

function validateEchoRequest($data, $jsonRequest)
{
	global $applicationIdValidation;
	global $echoServiceDomain;
	
	//
	// Parse out key variables
	//
	$sessionId          = $data['session']['sessionId'];
	$applicationId      = $data['session']['application']['applicationId'];
	$userId             = $data['session']['user']['userId'];
	$requestTimestamp   = $data['request']['timestamp'];
	$requestType        = $data['request']['type'];
	 
	// Die if applicationId isn't valid
	if ($applicationId != $applicationIdValidation) fail('Invalid Application id: ' . $applicationId);
	 
	// Determine if we need to download a new Signature Certificate Chain from Amazon
	if (!isset($_SERVER['HTTP_SIGNATURECERTCHAINURL']))
	{
		fail("No HTTP_SIGNATURECERTCHAINURL Attached To Irish Trivia Request...");
	}
	
	$md5pem = md5($_SERVER['HTTP_SIGNATURECERTCHAINURL']);
	$md5pem = $md5pem . '.pem';
	 
	// If we haven't received a certificate with this URL before, store it as a cached copy
	if (!file_exists($md5pem)) 
	{
		file_put_contents($md5pem, file_get_contents($_SERVER['HTTP_SIGNATURECERTCHAINURL']));
	}
	 
	// Validate proper format of Amazon provided certificate chain url
	validateKeychainUri($_SERVER['HTTP_SIGNATURECERTCHAINURL']);
	 
	// Validate certificate chain and signature
	$pem = file_get_contents($md5pem);
	$ssl_check = openssl_verify($jsonRequest, base64_decode($_SERVER['HTTP_SIGNATURE']), $pem);
	if ($ssl_check != 1)
	{
		fail(openssl_error_string());
	}
	 
	// Parse certificate for validations below
	$parsedCertificate = openssl_x509_parse($pem);
	if (!$parsedCertificate)
	{
		fail('x509 parsing failed');
	}
	 
	// Check that the domain echo-api.amazon.com is present in the Subject Alternative Names (SANs) section of the signing certificate
	if(strpos($parsedCertificate['extensions']['subjectAltName'], $echoServiceDomain) === false)
	{
		fail('subjectAltName Check Failed');
	}
	 
	// Check that the signing certificate has not expired (examine both the Not Before and Not After dates)
	$validFrom = $parsedCertificate['validFrom_time_t'];
	$validTo   = $parsedCertificate['validTo_time_t'];
	$time      = time();
	if (!($validFrom <= $time && $time <= $validTo))
	{
		fail('certificate expiration check failed');
	}
	 
	// Check the timestamp of the request and ensure it was within the past minute
	if (time() - strtotime($requestTimestamp) > 60)
	{
		fail('timestamp validation failure.. Current time: ' . time() . ' vs. Timestamp: ' . $requestTimestamp);	
	}
	

	return true;
}

$applicationIdValidation = "amzn1.ask.skill.***";
$echoServiceDomain       = 'echo-api.amazon.com';

$EchoJArray = file_get_contents('php://input');
error_log("---------------------------------Important-------------------------------------------------");
error_log($EchoJArray); 
error_log("---------------------------------Important----------------------------------------");
$jsonRequest = $EchoJArray;
$data        = json_decode($EchoJArray, true);
$EchoJArray  = json_decode($EchoJArray);
error_log(json_encode($EchoJArray));
error_log("----------------------------------------------------------------------------------");

$NextNumber = 0;
$EndSession = true;

$SpeakPhrase = "";
//$Name 		 = $EchoJArray->request->intent->slots->name->value;

$valid          = false;

if (isset($EchoJArray->session->user))
{
	$valid          = validateEchoRequest($data,$jsonRequest);
	//validate_request( $EchoJArray->session->application->applicationId, $EchoJArray->session->user->userId);
	//$validTimeStamp = (time() - $EchoJArray->request->timestamp)/1000 <= 150;
	error_log("valid=>".var_export($valid,true));
}

if (($EchoJArray->request->type == "LaunchRequest" || ($EchoJArray->request->intent->name == "GetTrivia" ||  $EchoJArray->request->intent->name == "Open")) 
	 && ($valid)
    )
{
	$irishTrivia = array(	"The Harp is the official Emblem of Ireland, not the Shamrock. The handheld Harp was played by our Celtic Forefathers.",
				"It was Saint Patrick who made the Shamrock so popular.",
				"The potato\"e\" Potato is not native to Ireland. It was orginally brought to Ireland from the American Continent.",
				"Ireland is not the only place Gaelic is spoken. It is also spoken on the Isle of Man, and in Scotland.",
				"The Book of Kells, an ancient illustration of the Bible, is over1000 years old. Beside it at Trinity College, Dublin The Book of Durrow. It was created by Irish Monks.",
				"Over 40% of the United States Presidents had Irish ancestors.",
				"The average height of Irish men is 5 foot 8 inches",
				"The average height of Irish women is 5 foot 5 inches",
				"90% of Irish nationals are Catholic, but only 30% ever attend church",
				"The Irish report the lowest annual number of UFO sightings in Europe",
				"73% of Americans are unable to locate Ireland on a map bereft of country names",
				"Raymond OBrien was the shortest person in Irish history. The dwarf, who died in 1795, was one foot eleven inches tall",
				"Only 9% of the Irish population are natural redheads",
				"May is generally the driest month of the year in Ireland",
				"RTE, The Late Late Show, is the worlds longest running talk show",
				"57% of Irish people wear glasses or contact lenses",
				"Cats now outnumber dogs by two to one as Irelands most popular pet",
				"Dublin boasts one pub for every 100 head of populatio",
				"A song only needs to sell 5,000 copies to top the Irish music charts",
				"A book only needs to sell 3,000 copies to top the Irish bestseller list",
				"The Canary Islands are the most popular sunshine holiday destination with retired Irish citizens",
				"The River Shannon is the longest river in Ireland or Britain",
				"Halloween was derived from an Irish festival called Samhain",
				"Irishman James Hoban  designed white house",
				"St. Patrick was not actually Irish, he was Roman",
				"The longest place name in Ireland is Muckanaghederdauhaulia",
				"There are more mobile phones in Ireland than there are people",
				"In Ireland, lakes are called as loughs (pronounced as locks)",
				"Gaelic is the commonly spoken language in Ireland, next to Irish and English.",
				"There are nearly 8 times more Polish speakers in Ireland than Gaelic speakers",
				"The average Irish consumes 131.1 liters per year. The second highest per-capita consumption in the world, behind Czech",
				"Titanic, the Unsinkable ship, which sunk in its maiden voyage, was made in Ireland",
				"The Tara mine near Narvan is the largest zinc mine in Europe, and fifth largest in the world",
				"Irishman John Tyndall was the first scientist to ever be specifically referred to as a physicist",
				"Ireland has had its own Olympics since the Bronze Age, called the Tailteann Games",
				"Ireland’s oldest pub, Seans Bar, was founded some 900 years ago",
				"There are more Irish people living OUTside of Ireland than in",
				"Irelands most famous include Michael Fassbender, Pierce Brosnan, Cillian Murphy and Colin Farrell",				
				"Eamon De Valera was the first President of the Irish Republic. He was born in Manhattan, New York City.",
				"Hibernia (Latin) and Éire (Gaelic) mean \"Ireland\". [i.e. Ancient Order of Hibernians (AOH)]",
				"Irish & Irish-Americans laid the ground work for America\'s Bridges, Tunnels, and Subways. Many lost their lives as Sandhogs.",
				"Mike Quill (b.1905, d.1966) born in County Kerry, Ireland was the founding president of the Transport Workers Union of America. During his tenure the U.S. labor movement made great strides.",
				"Irish Triads are the arrangement of ideas in groups of three. Many of these triads are witty, with an amusing climax - or anticlimax - in the third item.",
				"Ceide Fields is the most extensive Stone Age Monument in the world. It is in, North Mayo, a farming community that is fifty centuries old.",
				"70 Million people, worldwide, can claim Irish ancestry.",
				"St. Brendan, an Irish Monk, was a 5th century sailor. It is alleged that he discovered America before Christopher Columbus.",
				"St. Patrick\'s Day, the way we celebrate it, is more American than Irish. In Ireland, St. Patrick\'s Day is a religious holiday-shops and businesses are closed to give everyone a day off to be spent with family and friends.",
				"Catholics begin their day by attending Mass. Families gather for celebratory meals and spend the day at popular sporting events-Gaelic games, championship rugby matches or a steeplechase. There are big parades in Dublin and Belfast to celebrate national pride.",
				"It is said there are more Americans of Irish descent in America than there are Irishmen in Ireland. Americans celebrate St. Patrick\'s Day with such fun and wild abandon that many people in Ireland tune in their televisions to watch celebrations and parades in the U.S..",
				"The first St. Patrick\'s Day celebration in America was in 1737 hosted by the Charitable Irish Society of Boston. The second was established in 1780 by the Friendly Sons of St. Patrick in Philadelphia.",
				"It is not known if March 17 is celebrated because it is the date of St. Patrick\'s birth or his death. Some claim it is both, others say neither. As to St. Patrick\'s birthplace, the only definite statement is that he most certainly was not born in Ireland. He founded 165 churches and started a school with each one. St. Patrick is widely acknowledged as the patron saint of Ireland.",
				"There are no snakes in all of Ireland thanks to St. Patrick. Of all the legends surrounding this popular figure, the most long-lived is the story of St. Patrick driving the snakes from Ireland. As the population of Ireland looked on, St. Patrick pounded a drum and banished the snakes.",
				"The shamrock is seen everywhere on St. Patrick\'s Day. St. Patrick used the shamrock when he preached the doctrine of the Trinity as a symbol of its great mystery. Today, it is widely worn in Ireland and America to celebrate Irish heritage. In fact, several million shamrock plants are grown in County Cork, Ireland, and shipped all over the world for St. Patrick\'s Day.");


	$SpeakPhrase .= "Did you know that, ".str_replace("'","",str_replace("\"","",$irishTrivia[array_rand($irishTrivia,1)]));
}
else
{
	error_log("Invalid Request For Alexa Skill.");
	header('HTTP/1.1 400 Bad Request');
	die();
}

		$ReturnValue= '
		{
		  "version": "1.0",
		  "sessionAttributes": {
			"countActionList": {
			  "read": true,
			  "category": true,
			  "currentTask": "none",
			  "currentStep": '.$NextNumber.'
			}
		  },
		  "response": {
			"outputSpeech": {
			  "type": "PlainText",
			  "text": "' . $SpeakPhrase . '"
			},
			"reprompt": {
			  "outputSpeech": {
				"type": "PlainText",
				"text": "Say next item to continue."
			  }
			},
			"shouldEndSession": ' . $EndSession . '
		  }
		}';

error_log("Alexa->".$SpeakPhrase);
$size 		= strlen($ReturnValue);
header('Content-Type: application/json');
header("Content-length: $size");
echo $ReturnValue;


?>
