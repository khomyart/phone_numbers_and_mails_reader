<?php
require_once __DIR__ . '/vendor/autoload.php';

use Goutte\Client;

// $client = new Client();
// $shopsHolderUrl = 'https://puhnasti.com.ua/';
// getEmailsAndPhoneNumbersFromUrl($client, $shopsHolderUrl, true);
// die();

/**
 * @param           $pageCrawler With inited $client->request('GET', $url);
 * @param string    $parentUrl Shop url
 * 
 * @return string   URL
 */
function getContactsUrl(&$pageCrawler, $parentUrl) {
    $contactsUrl = "";
    $pageCrawler->filter('a')->each(function ($node) use (&$contactsUrl) {
        if (in_array(mb_strtolower(trim($node->text())), ["impressum", "контакти", "контакты", "contacts", "contact", "contact us"])) {
            $contactsUrl = $node->attr("href");
        }
    });
    $contactsUrl = trim($contactsUrl);

    if (!str_ends_with($parentUrl, "/")) {
        $parentUrl = $parentUrl."/";
    }

    if (str_starts_with($contactsUrl, '/')) {
        $contactsUrl = sprintf("%s%s", $parentUrl, str_replace("/", "", $contactsUrl));
    }
    echo "\n".$contactsUrl.PHP_EOL;
    return $contactsUrl;
}

/**
 * @param           $pageCrawler With inited $client->request('GET', $url);
 * @param array     $phoneNumber regexes
 * @param bool      $isHrefSearchingEnabled do we want to search in href's value or not
 * 
 * @return array    phone numbers
 */
function getPhoneNumbers(&$pageCrawler, $phoneNumberRegexes, $isHrefSearchingEnabled) {
    $phoneNumbers = [];
    $tagsToCheck = ['a', "tr", "span", "li", "p"];
    $bannedNumbers = [
        "380000000000",
        "380990000000",
    ];
    $currentRegex = $phoneNumberRegexes[0];

    //check each tag for phone number in ther text
    foreach ($tagsToCheck as $key => $tag) {
        $pageCrawler->filter($tag)->each(function ($node) use (&$phoneNumbers, $currentRegex) {
            $matches = [];
            //if more than 100 - something went wront with this little tag
            $elementText = strlen($node->text()) < 100 ? $node->text() : ""; 
            
            preg_match_all($currentRegex, $elementText, $matches);
            $phoneNumbers = array_merge($phoneNumbers, $matches[0]);
        });
    }


    if (count($phoneNumbers) == 0) {
        $currentRegex = $phoneNumberRegexes[1];

        //if no matches with previous regex, try second
        foreach ($tagsToCheck as $key => $tag) {
            $pageCrawler->filter($tag)->each(function ($node) use (&$phoneNumbers, $currentRegex) {
                $matches = [];
                $elementText = strlen($node->text()) < 50 ? $node->text() : ""; 

                preg_match_all($currentRegex, $elementText, $matches);
                $phoneNumbers = array_merge($phoneNumbers, $matches[0]);
            });
        }
    }

    if (count($phoneNumbers) == 0 && $isHrefSearchingEnabled) {
        $currentRegex = $phoneNumberRegexes[0];

        //if no matches with previous regex, try again, but look for hrefs
        $pageCrawler->filter('a')->each(function ($node) use (&$phoneNumbers, $currentRegex) {
            $matches = [];
            $elementHref = $node->attr("href"); 

            preg_match_all($currentRegex, $elementHref, $matches);
            $phoneNumbers = array_merge($phoneNumbers, $matches[0]);
        });
    }
    
    foreach ($phoneNumbers as $key => $number) {
        $loopNumber = str_replace("\n", "", $number);
        $loopNumber = str_replace("\n", "", $loopNumber);
        $loopNumber = str_replace(" ", "", $loopNumber);
        $loopNumber = str_replace("(", "", $loopNumber);
        $loopNumber = str_replace(")", "", $loopNumber);
        $loopNumber = str_replace("+", "", $loopNumber);
        $loopNumber = str_replace("-", "", $loopNumber);
        $loopNumber = trim($loopNumber);
        $phoneNumbers[$key] = $loopNumber;

        if (strlen($loopNumber) < 10 ||
            in_array($loopNumber, $bannedNumbers)) {
            unset($phoneNumbers[$key]);
        }
    }
    $phoneNumbers = array_values(array_unique(array_values($phoneNumbers)));

    //if number starts with 044..., or, for example 063... add 38 to it
    foreach ($phoneNumbers as $key => $number) {
        $phoneNumbers[$key] = $number[0] === "0" && $number[1] !== "8" ? "38{$number}" : $number;
    }

    foreach ($phoneNumbers as $key => $number) {
        echo "Number:".$number." Strlen: ".strlen($number)."\n";
    }

    return $phoneNumbers;
}

/**
 * @param           $pageCrawler With inited $client->request('GET', $url);
 * @param string    $emailsRegex regex
 * @param bool      $isHrefSearchingEnabled do we want to search in href's value or not
 * 
 * @return array    emails
 */
function getEmails(&$pageCrawler, $emailsRegex, $isHrefSearchingEnabled) {
    $emails = [];
    $tagsToCheck = ["a", "tr", "li"];
    $bannedEmails = [
        "support@support.com.ua", 
        "example@support.com", 
        "support@mail.com",
        "example@mail.com"
    ];

    foreach ($tagsToCheck as $key => $tag) {
        $pageCrawler->filter($tag)->each(function ($node) use (&$emails, $emailsRegex) {
            $matches = [];
            $elementText = $node->text();
            preg_match_all($emailsRegex, $elementText, $matches);
            $emails = array_merge($emails, $matches[0]);
        });
    }

    if ($isHrefSearchingEnabled) {
        $pageCrawler->filter("a")->each(function ($node) use (&$emails, $emailsRegex) {
            $matches = [];
            $elementHref = $node->attr('href');
            preg_match_all($emailsRegex, $elementHref, $matches);
            $emails = array_merge($emails, $matches[0]);
        });
    }

    if (count($emails) == 0) {
        $pageCrawler->filter("p")->each(function ($node) use (&$emails, $emailsRegex) {
            $matches = [];
            $elementText = $node->text();
            preg_match_all($emailsRegex, $elementText, $matches);
            $emails = array_merge($emails, $matches[0]);
        });
    }

    foreach ($emails as $key => $email) {
        $emails[$key] = str_replace("%20", "", $email);
        if (strlen($email) > 40 ||
        in_array($email, $bannedEmails) ||
        str_contains($email, "png") || 
        str_contains($email, "jpg") ||
        str_contains($email, "webp")) {
            unset($emails[$key]);
        }
    }

    $emails = array_values(array_unique(array_values($emails)));

    foreach ($emails as $key => $email) {
        echo "Email:".$email." Strlen: ".strlen($email)."\n";
    }

    return $emails;
}

/**
 * @param \Goutte\Client    $crawlerClient Instance
 * @param string            $url Url
 * @param bool              $isHrefSearchingEnabled Do we want to search in href's value or not
 * 
 * @return array            phone numbers and emails
 */
function getEmailsAndPhoneNumbersFromUrl(&$crawlerClient, $url, $isHrefSearchingEnabled) {
    $scrappedData = [
        "phoneNumbers" => [],
        "emails" => [],
    ];

    $phoneNumberRegexes = 
    [
        '/[\s]*[\+][(]?[0-9\s]{1,3}[)]?[\s]?[(]?[0-9\s]{0,3}[)]?[\-\s0-9]{7,10}[\s]*/',
        '/[(]?[0][0-9]{0,2}[)]?[0-9\s-]{5,12}/'
    ];
    $emailRegex = '/[a-zA-Z0-9._%+-]{2,}@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,15}/';

    //first look at a contacts page
    $crawler = $crawlerClient->request('GET', $url);
    $contactUrl = getContactsUrl($crawler, $url);
    $crawler = $crawlerClient->request('GET', $contactUrl);

    //extract email addresses using regular expressions
    $emails = getEmails($crawler, $emailRegex, $isHrefSearchingEnabled);

    //extract phone numbers using regular expressions
    $phoneNumbers = getPhoneNumbers($crawler, $phoneNumberRegexes, $isHrefSearchingEnabled);

    //if nothing found - look for main page of site
    if (count($emails) === 0 || count($phoneNumbers) === 0) {
        $crawler = $crawlerClient->request('GET', $url);
        if (count($emails) === 0) {
            $emails = getEmails($crawler, $emailRegex, $isHrefSearchingEnabled);
        }

        if (count($phoneNumbers) === 0) {
            $phoneNumbers = getPhoneNumbers($crawler, $phoneNumberRegexes, $isHrefSearchingEnabled);
        }        
    }

    //save the extracted email addresses and phone numbers
    foreach ($emails as $email) {
        $scrappedData["emails"][] = $email;
    }

    foreach ($phoneNumbers as $phoneNumber) {
        $scrappedData["phoneNumbers"][] = $phoneNumber;
    }

    return $scrappedData;
}

$client = new Client();

//some init settings
$isHrefSearchingEnabled = true;
$shopsHolderUrl = 'https://shop-express.ua/ukr/examples/';
$reservedEmailColumns = 7;
$reservedPhoneNumbersColumns = 7;

//getting number of pages in site
$crawler = $client->request('GET', $shopsHolderUrl);
$amountOfPages = trim($crawler->filter("#menu-items-all > div.row.pagination > div > a:last-child")->text());
$pageUrlPrefix = "page-";

//get urls of shops which are necessary to be scrapped
$shopsUrlToScrap = [];
for ($i = 1; $i <= $amountOfPages; $i++) {
    $crawler = $client->request('GET', sprintf("%s%s%s", $shopsHolderUrl, $pageUrlPrefix, $i));
    $crawler->filter('div.content-item-title.link-title-container > a')->each(function ($node) use (&$shopsUrlToScrap) {
        $shopsUrlToScrap[] = $node->attr("href");
    });
}

$date           = date('d_m_Y');
$fpEmails       = fopen("emails_$date.csv", 'w');
$fpPhoneNumbers = fopen("phone_numbers_$date.csv", 'w');

//write headers
fputcsv($fpEmails, ['site', 'email'], ";");
fputcsv($fpPhoneNumbers, ['site', 'phone_number'], ";");

foreach ($shopsUrlToScrap as $key => $shopUrl) {
    $scrappedData = [];
    $csvOutputArray = [];
    echo sprintf("%s from %s (%s)", $key + 1, count($shopsUrlToScrap), $shopUrl);
    try {
        $scrappedData = getEmailsAndPhoneNumbersFromUrl($client, $shopUrl, $isHrefSearchingEnabled);
    } catch (Symfony\Component\HttpClient\Exception\TransportException $e) {
        echo "Can`t connect to server!\n";
    }
    echo "\n-------------------\n\n";

    //insert emails
    foreach ($scrappedData["emails"] as $email) {
        fputcsv($fpEmails, [$shopUrl, $email], ";");
    }
    //insert phone numbers
    foreach ($scrappedData["phoneNumbers"] as $phoneNumber) {
        fputcsv($fpPhoneNumbers, [$shopUrl, $phoneNumber], ";");
    }
}

fclose($fpEmails);
fclose($fpPhoneNumbers);

