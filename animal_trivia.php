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
          "value": "casey daniel"
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
	$animalTrivia = array(	"A 1,200-pound horse eats about seven times it's own weight each year.",
"A bird requires more food in proportion to its size than a baby or a cat.",
"A capon is a castrated rooster.",
"A chameleon can move its eyes in two directions at the same time.",
"A chameleon's tongue is twice the length of its body.",
"A chimpanzee can learn to recognize itself in a mirror, but monkeys can't.",
"A Cornish game hen is really a young chicken, usually 5 to 6 weeks of age, that weighs no more than 2 pounds.",
"A cow gives nearly 200,000 glasses of milk in her lifetime.",
"A father Emperor penguin withstands the Antarctic cold for 60 days or more to protect his eggs, which he keeps on his feet, covered with a feathered flap. During this entire time he doesn't eat a thing. Most father penguins lose about 25 pounds while they wait for their babies to hatch. Afterward, they feed the chicks a special liquid from their throats. When the mother penguins return to care for the young, the fathers go to sea to eat and rest.",
"A father sea catfish keeps the eggs of his young in his mouth until they are ready to hatch. He will not eat until his young are born, which may take several weeks.",
"A female mackerel lays about 500,000 eggs at one time.",
"A Hindu temple dedicated to the rat goddess Karni Mata in Deshnoke, India, houses more than 20,000 rats.",
"A Holstein's spots are like a fingerprint or snowflake. No two cows have exactly the same pattern of spots.",
"A leech is a worm that feeds on blood. It will pierce its victim's skin, fill itself with three to four times its own body weight in blood, and will not feed again for months. Leeches were once used by doctors to drain \"bad blood\" from sick patients.",
"A newborn kangaroo is about 1 inch in length.",
"A normal cow's stomach has four compartments: the rumen, the recticulum (storage area), the omasum (where water is absorbed), and the abomasum ( the only compartment with digestive juices).",
"A polecat is not a cat. It is a nocturnal European weasel.",
"A quarter of the horses in the US died of a vast virus epidemic in 1872.",
"A rat can last longer without water than a camel can.",
"A single little brown bat can catch 1,200 mosquitoes-sized insects in just one hour.",
"A woodpecker can peck twenty times a second.",
"A zebra is white with black stripes.",
"After mating, the male Surinam Toad affixes the female's eggs to her back, where her spongy flesh will swell and envelope them. When the froglets hatch, they leave behind holes in their mother's flesh that they will remain sheltered in until large enough to fend for themselves.",
"All clams start out as males; some decide to become females at some point in their lives.",
"All pet hamsters are descended from a single female wild golden hamster found with a litter of 12 young in Syria in 1930.",
"An adult lion's roar can be heard up to five miles away, and warns off intruders or reunites scattered members of the pride.",
"An albatross can sleep while it flies. It apparently dozes while cruising at 25 mph.",
"An electric eel can produce a shock of up to 650 volts.",
"An iguana can stay under water for 28 minutes.",
"An ostrich's eye is bigger than its brain.",
"Ancient Egyptians believed that \"Bast\" was the mother of all cats on Earth. They also believed that cats were sacred animals.",
"Animal gestation periods: the shortest is the American opossum, which bears its young 12 to 13 days after conception; the longest is the Asiatic elephant, taking 608 days, or just over 20 months.",
"At nearly 50 percent fat, whale milk has around 10 times the fat content of human milk, which helps calves achieve some serious growth spurts - as much as 200 pounds per day.",
"At the end of the Beatles' song \"A Day in the Life\", an ultrasonic whistle, audible only to dogs, was recorded by Paul McCartney for his Shetland sheepdog.",
"Beaver teeth are so sharp that Native Americans once used them as knife blades.",
"Between 260 and 300 million turkeys are slaughtered annually in the United States, according to USDA statistics. Of these, approximately 45 million are killed for Thanksgiving, and 22 million are killed for Christmas.",
"Bird eggs come in a wide variety of sizes. The largest egg from a living bird belongs to the ostrich. It is more than 2,000 times larger than the smallest bird egg, which is produced by the hummingbird. Ostrich eggs are about 7.1 inches long, 5.5 inches wide and typically weigh 2.7 pounds. Hummingbird eggs are half an inch long, a third of an inch wide and weigh half a gram, or less than one-fifty-fifth of an ounce.",
"Brown eggs come from hens with red feathers and red ear lobes; white eggs come from hens with white feathers and white ear lobes. Shell color is determined by the breed of hen and has no effect on its quality, nutrients or flavor.",
"By feeding hens certain dyes they can be made to lay eggs with varicolored yolks.",
"Camel milk does not curdle.",
"Camels have three eyelids to protect themselves from blowing sand.",
"Carnivorous animals will not eat another animal that has been hit by a lightning strike.",
"Cat scratch disease, a benign but sometimes painful disease of short duration, is caused by a bacillus. Despite its name, the disease can be transmitted by many kinds of scratches besides those of cats.",
"Catfish have 100,000 taste buds.",
"Catnip can affect lions and tigers as well as house cats. It excites them because it contains a chemical that resembles an excretion of the dominant female's urine.",
"Certain frogs can be frozen solid then thawed and continue living.",
"Chameleons can move their eyes in two different directions at the same time.",
"Chameleons can reel in food from a distance as far away as more than two and a half times their body lengths.",
"Cheetahs make a chirping sound that is much like a bird's chirp or a dog's yelp. The sound is so an intense, it can be heard a mile away.",
"Cojo, the 1st gorilla born in captivity, was born at the Columbus Zoo, in Ohio, in 1956 and weighed 3 1/4 pounds.",
"Crocodiles swallow large stones that stay permanently in their bellies. It's been suggested these are used for ballast in diving.",
"Despite its reputation for being finicky, the average cat consumes about 127,750 calories a year, nearly 28 times its own weight in food and the same amount again in liquids. In case you were wondering, cats cannot survive on a vegetarian diet.",
"Developed in Egypt about 5,000 years ago, the greyhound breed was known before the ninth century in England, where it was bred by aristocrats to hunt such small game as hares.",
"Dolphins sleep at night just below the surface of the water. They frequently rise to the surface for air.",
"Domesticated turkeys (farm raised) cannot fly. Wild turkeys can fly for short distances at up to 55 miles per hour. Wild turkeys are also fast on the ground, running at speeds of up to 25 miles per hour.",
"Dragonflies are one of the fastest insects, flying 50 to 60 mph.",
"During World War II, the very first bomb dropped on Berlin by the Allies killed the only elephant in the Berlin Zoo.",
"Elephant tusks grow throughout an elephant's life and can weigh more than 200 pounds. Among Asian elephants, only the males have tusks. Both sexes of African elephants have tusks.",
"Elephants can communicate using sounds that are below the human hearing range: between 14 and 35 hertz.",
"Every year, $1.5 billion is spent on pet food. This is four times the amount spent on baby food.",
"Felix the Cat is the first cartoon character to ever have been made into a balloon for a parade.",
"Female chickens, or hens, need about 24 to 26 hours to produce one egg. Thirty minutes later they start the process all over again. In addition to the half-hour rests, some hens rest every three to five days and others rest every 10 days.",
"George Washington's favorite horse was named Lexington. Napoleon's favorite was Marengo. U.S. Grant had three favorite horses: Egypt, Cincinnati, and Jeff Davis.",
"German Shepherds bite humans more than any other breed of dog.",
"Goldfish lose their color if they are kept in dim light or are placed in a body of running water, such as a stream.",
"Hippos have killed more than 400 people in Africa - more than any other wild animal.",
"Howler monkeys are the noisiest land animals. Their calls can be heard over 2 miles away.",
"Human tapeworms can grow up to 22.9m.",
"Hummingbirds are the smallest birds - so tiny that one of their enemies is an insect, the praying mantis.",
"In its entire lifetime, the average worker bee produces 1/12th teaspoon of honey.",
"Infant beavers are called kittens.",
"It takes 35 to 65 minks to produce the average mink coat. The numbers for other types of fur coats are: beaver - 15; fox - 15 to 25; ermine - 150; chinchilla - 60 to 100.",
"It takes a lobster approximately seven years to grow to be one pound.",
"It takes forty minutes to hard boil an ostrich egg.",
"Korea's poshintang - dog meat soup - is a popular item on summertime menus, despite outcry from other nations. The soup is believed to cure summer heat ailments, improve male virility, and improve women's complexions.",
"Large kangaroos cover more than 30 feet with each jump.",
"Lassie was played by several male dogs, despite the female name, because male collies were thought to look better on camera. The main \"actor\" was named Pal.",
"Lassie, the TV collie, first appeared in a 1930s short novel titled Lassie Come-Home written by Eric Mowbray Knight. The dog in the novel was based on Knight's real life collie, Toots.",
"Lions are the only truly social cat species, and usually every female in a pride, ranging from 5 to 30 individuals, is closely related.",
"Lovebirds are small parakeets who live in pairs. Male and female lovebirds look alike, but most other male birds have brighter colors than the females.",
"Macaroni, Gentoo, Chinstrap and Emperor are types of penguins.",
"Mockingbirds can imitate any sound from a squeaking door to a cat meowing.",
"Molerats are the only eusocial vertebrates known to man. This means that these mammals live in colonies similar to those of ants and termites, with a single fertile queen giving birth to nonreproductive workers and soldiers. Molerats are also famous for their incredibly powerful jaws, the muscles of which constitute 25% of their body mass. Baby molerats are raised on a diet of their older sibling's fecal pellets, emitting a special cry when hungry to summon a worker.",
"Moles are able to tunnel through 300 feet of earth in a day.",
"Of all known forms of animals life ever to inhabit the Earth, only about 10 percent still exist today.",
"On average, pigs live for about 15 years.",
"Owls have eyeballs that are tubular in shape, because of this, they cannot move their eyes.",
"Parrots, most famous of all talking birds, rarely acquire a vocabulary of more than twenty words, however Tymhoney Greys and African Greys have been know to carry vocabularies in excess of 100 words.",
"Pet parrots can eat virtually any common \"people-food\" except for chocolate and avocados. Both of these are highly toxic to the parrot and can be fatal.",
"Pigs, walruses and light-colored horses can be sunburned.",
"Prairie dogs are not dogs. A prairie dog is a kind of rodent.",
"Rats are omnivorous, eating nearly any type of food, including dead and dying members of their own species.",
"Rats can't throw-up.",
"Sharks apparently are the only animals that never get sick. As far as is known, they are immune to every known disease including cancer.",
"Snails produce a colorless, sticky discharge that forms a protective carpet under them as they travel along. The discharge is so effective that they can crawl along the edge of a razor without cutting themselves.",
"Snakes are immune to their own poison.",
"Some baby giraffes are more than six feet tall at birth.",
"Swans are the only birds with penises.",
"Tapeworms range in size from about 0.04 inch to more than 50 feet in length.",
"The \"caduceus\" the classical medical symbol of two serpents wrapped around a staff - comes from an ancient Greek legend in which snakes revealed the practice of medicine to human beings.",
"The 1st buffalo ever born in captivity was born at Chicago's Lincoln Park Zoo in 1884.",
"The American Society for Prevention of Cruelty to Animals (ASPCA) was formed in 1866.",
"The anaconda, one of the world's largest snakes, gives birth to its young instead of laying eggs.",
"The average adult male ostrich, the world's largest living bird, weighs up to 345 pounds.",
"The biggest members of the cat family are Siberian and Bengal tigers, which can reach over 600 pounds.",
"The blood of mammals is red, the blood of insects is yellow, and the blood of lobsters is blue.",
"The bloodhound is the only animal whose evidence is admissible in an American court.",
"The blue whale is the loudest animal on Earth. The call of the blue whale reaches levels up to 188 decibels. This extraordinarily loud whistle can be heard for hundreds of miles underwater. The second-loudest animal on Earth is the Howler Monkey.",
"The bones of a pigeon weigh less than its feathers.",
"The calories burned daily by the sled dogs running in Alaska's annual Iditarod race average 10,000. The 1,149-mile race commemorates the 1925 \"Race for Life\" when 20 volunteer mushers relayed medicine from Anchorage to Nome to battle a children's diphtheria epidemic.",
"The Canary Islands were not named for a bird called a canary. They were named after a breed of large dogs. The Latin name was Canariae insulae - \"Island of Dogs.\"",
"The cat lover is an ailurophile, while a cat hater is an ailurophobe.",
"The catgut formerly used as strings in tennis rackets and musical instruments does not come from cats. Catgut actually comes from sheep, hogs, and horses.",
"The chameleon has several cell layers beneath its transparent skin. These layers are the source of the chameleon's color change. Some of the layers contain pigments, while others just reflect light to create new colors. Several factors contribute to the color change. A popular misconception is that chameleons change color to match their environment. This isn't true. Light, temperature, and emotional state commonly bring about a chameleon's change in color. The chameleon will most often change between green, brown and gray, which coincidently, often matches the background colors of their habitat.",
"The cheetah is the only cat in the world that can't retract its claws.",
"The Chinese, during the reign of Kublai Khan, used lions on hunting expeditions. They trained the big cats to pursue and drag down massive animals - from wild bulls to bears - and to stay with the kill until the hunter arrived.",
"The elephant, as a symbol of the US Republican Party, was originated by cartoonist Thomas Nast and first presented in 1874.",
"The English Romantic poet Lord Byron was so devastated upon the death of his beloved Newfoundland, whose name was Boatswain, that he had inscribed upon the dog's gravestone the following: \"Beauty without vanity, strength without insolence, courage without ferocity, and all the virtues of man without his vices.\"",
"The expression \"three dog night\" originated with the Eskimos and means a very cold night - so cold that you have to bed down with three dogs to keep warm.",
"The fastest bird is the Spine-tailed swift, clocked at speeds of up to 220 miles per hour.",
"The fastest -moving land snail, the common garden snail, has a speed of 0.0313 mph.",
"The first house rats recorded in America appeared in Boston in 1775.",
"The giant squid is the largest creature without a backbone. It weighs up to 2.5 tons and grows up to 55 feet long. Each eye is a foot or more in diameter.",
"The harmless Whale Shark, holds the title of largest fish, with the record being a 59 footer captured in Thailand in 1919.",
"The hummingbird is the only bird that can hover and fly straight up, down, or backward!",
"The hummingbird, the loon, the swift, the kingfisher, and the grebe are all birds that cannot walk.",
"The Kiwi, national bird of New Zealand, can't fly. It lives in a hole in the ground, is almost blind, and lays only one egg each year. Despite this, it has survived for more than 70 million years.",
"The largest animal ever seen alive was a 113.5 foot, 170-ton female blue whale.",
"The largest bird egg in the world today is that of the ostrich. Ostrich eggs are from 6 to 8 inches long. Because of their size and the thickness of their shells, they take 40 minutes to hard-boil.",
"The largest Great White Shark ever caught measured 37 feet and weighed 24,000 pounds. It was found in a herring weir in New Brunswick in 1930.",
"The largest pig on record was a Poland-China hog named Big Bill, who weighed 2,552 lbs.",
"The last member of the famous Bonaparte family, Jerome Napoleon Bonaparte, died in 1945, of injuries sustained from tripping over his dog's leash.",
"The male penguin incubates the single egg laid by his mate. During the two month period he does not eat, and will lose up to 40% of his body weight.",
"The most frequently seen birds at feeders across North America last winter were the Dark-eyed Junco, House Finch and American goldfinch, along with downy woodpeckers, blue jays, mourning doves, black-capped chickadees, house sparrows, northern cardinals and european starlings.",
"The mouse is the most common mammal in the US.",
"The name of the dog from \"The Grinch Who Stole Christmas\" is Max.",
"The name of the dog on the Cracker Jack box is Bingo.",
"The only dog to ever appear in a Shakespearean play was Crab in The Two Gentlemen of Verona",
"The only domestic animal not mentioned in the Bible is the cat.",
"The Pacific Giant Octopus, the largest octopus in the world, grows from the size of pea to a 150 pound behemoth potentially 30 feet across in only two years, its entire life-span.",
"The penalty for killing a cat, 4,000 years ago in Egypt, was death.",
"The phrase \"raining cats and dogs\" originated in 17th Century England. During heavy downpours of rain, many of these poor animals unfortunately drowned and their bodies would be seen floating in the rain torrents that raced through the streets. The situation gave the appearance that it had literally rained \"cats and dogs\" and led to the current expression.",
"The pigmy shrew - a relative of the mole - is the smallest mammal in North America. It weighs 1/14 ounce - less than a dime.",
"The poison-arrow frog has enough poison to kill about 2,200 people.",
"The poisonous copperhead snake smells like fresh cut cucumbers.",
"The Smithsonian National Museum of Natural History houses the world's largest shell collection, some 15 million specimens. A smaller museum in Sanibel, Florida owns a mere 2 million shells and claims to be the worlds only museum devoted solely to mollusks.",
"The term \"dog days\" has nothing to do with dogs. It dates back to Roman times, when it was believed that Sirius, the Dog Star, added its heat to that of the sun from July3 to August 11, creating exceptionally high temperatures. The Romans called the period dies caniculares, or \"days of the dog.\"",
"The turbot fish lays approximately 14 million eggs during its lifetime.",
"The turkey was named for what was wrongly thought to be its country of origin.",
"The underside of a horse's hoof is called a frog. The frog peels off several times a year with new growth.",
"The viscera of Japanese abalone can harbor a poisonous substance which causes a burning, stinging, prickling and itching over the entire body. It does not manifest itself until exposure to sunlight - if eaten outdoors in sunlight, symptoms occur quickly and suddenly.",
"The world record frog jump is 33 feet 5.5 inches over the course of 3 consecutive leaps, achieved in May 1977 by a South African sharp-nosed frog called Santjie.",
"The world's largest mammal, the blue whale, weighs 50 tons at birth. Fully grown, it weighs as much as 150 tons.",
"The world's largest rodent is the Capybara. An Amazon water hog that looks like a guinea pig, it can weigh more than 100 pounds.",
"The world's smallest mammal is the bumblebee bat of Thailand, weighing less than a penny.",
"There are around 2,600 different species of frogs. They live on every continent except Antarctica.",
"There are more than 100 million dogs and cats in the United States. Americans spend more than 5.4 billion dollars on their pets each year.",
"There is no single cat called the panther. The name is commonly applied to the leopard, but it is also used to refer to the puma and the jaguar. A black panther is really a black leopard.",
"Tigers have striped skin, not just striped fur.",
"Turkeys originated in North and Central America, and evidence indicates that they have been around for over 10 million years.",
"Unlike most fish, electric eels cannot get enough oxygen from water. Approximately every five minutes, they must surface to breathe, or they will drown. Unlike most fish, they can swim both backwards and forwards.",
"Whales and dolphins can literally fall half asleep. Their brain hemispheres alternate sleeping, so the animals can continue to surface and breathe.",
"When a female horse and male donkey mate, the offspring is called a mule, but when a male horse and female donkey mate, the offspring is called a hinny.",
"When the Black Death swept across England one theory was that cats caused the plague. Thousands were slaughtered. Ironically, those that kept their cats were less affected, because they kept their houses clear of the real culprits, rats.",
"Worldwide, goats provide people with more meat and milk than any other domestic animal.");


	$SpeakPhrase .= "Did you know that, ".str_replace("'","",str_replace("\"","",$animalTrivia[array_rand($animalTrivia,1)]));
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
			"reprompt": {},
			"shouldEndSession": ' . $EndSession . '
		  }
		}';

error_log("Alexa->".$SpeakPhrase);
$size 		= strlen($ReturnValue);
header('Content-Type: application/json');
header("Content-length: $size");
echo $ReturnValue;


?>
