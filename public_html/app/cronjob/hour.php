<?PHP

// Mafiasource online mafia RPG, this software is inspired by Crimeclub.
// Copyright © 2016 Michael Carrein, 2006 Crimeclub.nl
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the “Software”),
// to deal in the Software without restriction, including without limitation
// the rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR
// THE USE OR OTHER DEALINGS IN THE SOFTWARE.

/** CRON HOUR RUNS EVERY HOUR **/

use Doctrine\Common\ClassLoader;
use app\config\Security;
use src\Business\Logic\game\Statics\Lottery AS LotteryStatics;
use src\Business\Logic\game\Statics\FamilyProperty AS FamilyPropertyStatics;
use src\Business\Logic\game\Statics\BulletFactory AS BulletFactoryStatics;
use src\Business\Logic\game\Ground\IncomeCalculation AS GroundIncomeCalculation;
use src\Data\config\DBConfig;

/* Error reporting (debugging) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

/* Set correct timezone */
ini_set('date.timezone', 'Europe/Amsterdam');

/* Define game_doc_root & database credentials (included in config) */
require_once __DIR__ . '/../config/config.php';

/* Enable Autoloading with doctrine */
require_once GAME_DOC_ROOT . '/vendor/autoload.php';
$srcLoader = new ClassLoader('src'   ,  GAME_DOC_ROOT);
$srcLoader->register();

/* Open db connection */
$con = new DBConfig();

/* Security class (for true randomness) */
require_once GAME_DOC_ROOT.'/app/config/security.php';
$security = new Security();

/** ALL CRON HOUR RELATED CODE START FROM HERE **/

/* User whores income (street & RLD) */
$players = $con->getData("SELECT `id`, `donatorID` FROM `user` WHERE `statusID`<= 7 AND `health`> 0 AND `active`='1' AND `deleted`='0'");
foreach($players AS $p)
{
    $streetProfits = $p['donatorID'] >= 10 ? $security->randInt(13, 23) : $security->randInt(10, 20);
    $windowProfits = $p['donatorID'] >= 10 ? $security->randInt(18, 28) : $security->randInt(15, 25);
    $con->setData("UPDATE `user` SET `bank`=`bank`+ (`whoresStreet` * ".$streetProfits."), `pimpProfit`=`pimpProfit`+ (`whoresStreet` * ".$streetProfits.") WHERE `id`='".$p['id']."' ");
    $con->setData("
        UPDATE `user`, `rld_whore`
        SET `user`.`bank`=`user`.`bank`+ (
            (SELECT SUM(`rld_whore`.`whores`) FROM `rld_whore` WHERE `user`.`id`=`rld_whore`.`userID`) * ".$windowProfits."
        ), `user`.`pimpProfit`=`user`.`pimpProfit`+ (
            (SELECT SUM(`rld_whore`.`whores`) FROM `rld_whore` WHERE `user`.`id`=`rld_whore`.`userID`) * ".$windowProfits."
        )
        WHERE `user`.`id`='".$p['id']."' AND `rld_whore`.`userID`=`user`.`id`
    ");
} // /CHECKED & OK

/* Reset Possess profit_hour BEFORE 'Pay out RLD owners'! */
$con->setData("UPDATE `possess` SET `profit_hour`='0' WHERE `profit_hour`!='0' AND `active`='1' AND `deleted`='0'"); // /CHECKED & OK

/* Pay out RLD owners */
$con->setData("
    UPDATE `user`, `possess`
    SET `bank`=`bank`+ ((SELECT COALESCE(sum(`whores`), 0) AS whores FROM `rld_whore` WHERE `stateID`=`possess`.`stateID` AND `active`='1' AND `deleted`='0') * 10),
        `profit`=`profit`+((SELECT COALESCE(sum(`whores`), 0) AS whores FROM `rld_whore` WHERE `stateID`=`possess`.`stateID` AND `active`='1' AND `deleted`='0') * 10),
        `profit_hour`=((SELECT COALESCE(sum(`whores`), 0) AS whores FROM `rld_whore` WHERE `stateID`=`possess`.`stateID` AND `active`='1' AND `deleted`='0') * 10)
    WHERE `possess`.`userID`=`user`.`id` AND `possess`.`id` IN(7, 8, 9, 10, 11, 12) AND `user`.`active`='1' AND `user`.`deleted`='0' AND `possess`.`active`='1' AND `possess`.`deleted`='0'
"); // /CHECKED & OK

/* Family Brothel whores income */
$families = $con->getData("SELECT `id` FROM `family` WHERE `brothel`>'0' AND `active`='1' AND `deleted`='0'");
foreach($families  AS $f)
{
    $con->setData("
        UPDATE `family`, `family_brothel_whore`
        SET `family`.`money`=`family`.`money`+ (
            (SELECT SUM(`family_brothel_whore`.`whores`) FROM `family_brothel_whore` WHERE `family`.`id`=`family_brothel_whore`.`familyID`) * ROUND((RAND() * (26-15))+15)
        )
        WHERE `family`.`id`='".$f['id']."' AND `family_brothel_whore`.`familyID`=`family`.`id`
    ");
} // /CHECKED & OK

/* Scorepoints */
$con->setData("
    UPDATE `user` u
    SET `score`=`score`+ (
        `honorPoints` * 3 + `whoresStreet` * 2 + `kills` * 10 +
        (SELECT COALESCE(SUM(`whores`),0) FROM `rld_whore` WHERE `userID`=u.`id`) * 2 +
        (SELECT COUNT(`id`) FROM `ground` WHERE `userID`=u.`id`) * 100
    ), `score` = IF(`rankpoints`> 1500, `score`+ 1500, `score`+`rankpoints`)
    WHERE `statusID`<='7' AND `statusID`>'2' AND `health`>'0' AND `active`='1' AND `deleted`='0'
"); // /CHECKED & OK

/* User bullet factories */
$bulletFactoryStatics = new BulletFactoryStatics();
$productionCosts = $bulletFactoryStatics->productionCosts;
$bulletFactories = $con->getData("
    SELECT bf.`id`, bf.`production`, p.`userID`, p.`stateID`, u.`bank`
    FROM `bullet_factory` AS bf
    LEFT JOIN `possess` AS p
    ON(bf.`possessID`=p.`id`)
    LEFT JOIN `user` AS u
    ON(p.`userID`=.u.`id`)
    WHERE bf.`id`> 0 AND bf.`id`<= 6
");
foreach($bulletFactories AS $bf)
{
    if($bf['userID'] == '0')
        $con->setData("UPDATE `bullet_factory` SET `production`='0' WHERE `id`='".$bf['id']."' AND `production`!='0'");
    
    foreach($productionCosts AS $p => $c)
    {
        if($bf['production'] == $p && $bf['bank'] < $c)
            $con->setData("UPDATE `bullet_factory` SET `production`='0' WHERE `id`='".$bf['id']."'");
        elseif($bf['production'] == $p && $p != 0)
            $con->setData("
                UPDATE `bullet_factory` SET `bullets`=`bullets`+'".$p."' WHERE `id`='".$bf['id']."';
                UPDATE `user` SET `bank`=`bank`-'".$c."' WHERE `id`='".$bf['userID']."' AND `active`='1' AND `deleted`='0'
            ");
    }
} // /CHECKED & OK

/* Family bullet factories */
$propertyStatics = new FamilyPropertyStatics();
$productionCosts = $propertyStatics->bfProductionCosts;
$familyBulletFactories = $con->getData("
    SELECT `id`, `bullets`, `bulletFactory`, `bfProduction`, `money`
    FROM `family`
    WHERE `id`> 0 AND `bulletFactory`> 0 AND `active`='1' AND `deleted`='0'
");
foreach($familyBulletFactories AS $bf)
{
    foreach($productionCosts AS $p => $c)
    {
        $capacity = $propertyStatics->bfCapacities[$bf['bulletFactory']];
        if($bf['bfProduction'] == $p && ($capacity < ($bf['bullets'] + $p) || $bf['money'] < $c))
            $con->setData("UPDATE `family` SET `bfProduction`='0' WHERE `id`='".$bf['id']."' AND `bfProduction`!='0'");
        elseif($bf['bfProduction'] == $p)
            $con->setData("UPDATE `family` SET `bullets`=`bullets`+'".$p."', `money`=`money`-'".$c."' WHERE `id`='".$bf['id']."'");
    }
} // /CHECKED & OK

/* Ground buildings income */
$baseIncome = $con->getData("SELECT `income` FROM `ground_building` WHERE `id` > '0' AND `id` <= '5' AND `active`='1' AND `deleted`='0' ORDER BY `id` ASC");
$buildingLvIncome = array();
for($i = 1; $i <= 5; $i++)
{
    $buildingLvIncome[$i] = array();
    for($j = 1; $j <= 5; $j++)
        $buildingLvIncome[$i][$j] = GroundIncomeCalculation::getIncomeByLevel($baseIncome[$i - 1]['income'], $j);
}
$ground = $con->getData("SELECT `userID`, `building1`, `building2`, `building3`, `building4`, `building5` FROM `ground` WHERE `userID`>'0' AND `active`='1' AND `deleted`='0'");
foreach($ground AS $g)
{
    $total = 0;
    foreach($buildingLvIncome AS $b => $v)
        foreach($v AS $lv => $income)
            if($g['building'.$b] == $lv)
                $total = $total + $income;
    
    if($total > 0)
        $con->setData("UPDATE `user` SET `bank`=`bank`+'".$total."' WHERE `id`='".$g['userID']."' AND `active`='1' AND `deleted`='0'");
} // / CHECKED & OK

/* Business stocks announce news */
if(date('H') == 7)
{
    $con->setData("
        TRUNCATE TABLE `business_news`;
        DELETE FROM `business_history` WHERE `date`< :date
    ", array(':date' => date('Y-m-d', strtotime('-16 days'))));
    $comps =
    array(
        1 => array(
            "comp1" => 5,
            "comp2" => 22
        ),
        2 => array(
            "comp1" => 16,
            "comp2" => 19
        ),
        3 => array(
            "comp1" => 12,
            "comp2" => 30
        ),
        4 => array(
            "comp1" => 10,
            "comp2" => 21
        ),
        5 => array(
            "comp1" => 22,
            "comp2" => 1
        ),
        7 => array(
            "comp1" => 9,
            "comp2" => 0
        ),
        8 => array(
            "comp1" => 15,
            "comp2" => 0
        ),
        9 => array(
            "comp1" => 7,
            "comp2" => 0
        ),
        10 => array(
            "comp1" => 4,
            "comp2" => 21
        ),
        11 => array(
            "comp1" => 18,
            "comp2" => 0
        ),
        12 => array(
            "comp1" => 3,
            "comp2" => 30
        ),
        14 => array(
            "comp1" => 17,
            "comp2" => 0
        ),
        15 => array(
            "comp1" => 8,
            "comp2" => 0
        ),
        16 => array(
            "comp1" => 2,
            "comp2" => 19
        ),
        17 => array(
            "comp1" => 14,
            "comp2" => 0
        ),
        18 => array(
            "comp1" => 11,
            "comp2" => 0
        ),
        19 => array(
            "comp1" => 2,
            "comp2" => 16
        ),
        21 => array(
            "comp1" => 4,
            "comp2" => 10
        ),
        22 => array(
            "comp1" => 1,
            "comp2" => 5
        ),
        30 => array(
            "comp1" => 3,
            "comp2" => 12
        )
    );
    $bDescr = array(1 =>
        //Positives
        array("nl" => "pakt uit met verleidelijke acties", "en" => "selling with tempting actions"),
        array("nl" => "laat de consument niet in de steek!", "en" => "doesn't let the consumer down"),
        array("nl" => "viert hun stijg percentage in stijl", "en" => "celebrates winnings in style"),
        array("nl" => "wacht niet en onderneemt meteen actie", "en" => "doesn't wait to undertake actions"),
        array("nl" => "record aantal stocks op de markt", "en" => "record amount of stocks on the exchange"),
        array("nl" => "heeft geen enkele schulden", "en" => "has no debts anywehere"),
        array("nl" => "blijft stijgen op de aandelen markt", "en" => "keeps growing on the stock exchange"),
        array("nl" => "faillissement van concurent in hun voordeel!", "en" => "bankruptcy of competitor to their advantage"),
        array("nl" => "is zeer tevreden over de vooruitgang", "en" => "is very satisfied with their progress"),
        array("nl" => "honderden jobs werden opgevuld en er is nood aan meer", "en" => "hundreds of jobs supplemented, more positions still open"),
        array("nl" => "voorspelt een stijging van minstens 10%", "en" => "predicts a gain of at least 10%"),
        array("nl" => "aandeel houders kozen dit gouden bedrijf", "en" => "stake holders have choosen this golden company"),
        array("nl" => "ziet vooruitgang bij het klanten aanwerven", "en" => "noticing progress with acquiring customers"),
        array("nl" => "speelt op zeker en onderhandeld met de concurent", "en" => "playing safe by negotiating with competitors"),
        array("nl" => "stocks blijven zich versprijden", "en" => "stocks keep on spreading"),
        //Negatives
        array("nl" => "problemen bij de productie zorgen voor vertraging", "en" => "problems with production cause delays"),
        array("nl" => "dringend grote sponsors nodig om de zaken lopende te houden", "en" => "needs sponsors urgently to keep the business rolling"),
        array("nl" => "aandeel houders boos op nieuw bestuur", "en" => "stake holders not happy with the new board"),
        array("nl" => "de moord op de CEO zorgt voor een keerpunt in de zaken!", "en" => "murder on a CEO creates turning point in business"),
        array("nl" => "steeds minder kapitaal zorgt voor hoge spanningen", "en" => "less and less capital creates high tensions"),
        array("nl" => "niemand had dit voorspeld, topman verlaat het bedrijf", "en" => "nobody predicted the resignation of beloved topman"),
        array("nl" => "steeds meer stocks worden verkocht wegens ongelooflijke daling!", "en" => "mass sell-off of stocks makes price crash"),
        array("nl" => "personeel komt in opstand", "en" => "employees revolt against bad practices in their company"),
        array("nl" => "dreigt te sluiten als er niet snel verandering plaatsvind", "en" => "threatens to close if there is not a quick change"),
        array("nl" => "de consument heeft geen vertrouwen meer", "en" => "average consumer lost all faith in this company"),
        array("nl" => "product prijzen stijgen voor een onbekende reden", "en" => "product prices raised for no known reasons"),
        array("nl" => "Topman geeft toe dat personeelslid fraude heeft gepleegd", "en" => "Topman admits that staff member has committed fraud"),
        array("nl" => "geen goede toekomst voorspeld door adviseurs", "en" => "no good future predicted by advisors"),
        array("nl" => "zelf de banken kunnen ons niet meer redden", "en" => "even banks cannot save us anymore"),
        array("nl" => "zal record laagste prijs doorbreken", "en" => "will break their low price record")
    );
    $b1 = $security->randInt(1, 10);
    $b2 = $security->randInt(11, 20);
    $b3 = $security->randInt(21, 30);
    $businesses = array($b1, $b2, $b3);
    foreach($businesses as $value)
    {
        $exists = $con->getDataSR("SELECT `businessID` FROM `business_news` WHERE `businessID`= :bid", array(':bid' => $value));
        if(!isset($exists['businessID']))
        {
            if($security->randInt(1, 2) === 2) // Business will make profit all day
            {
                $type = 1;
                $compType = 4;
                $bid = $value;
                $desc = $bDescr[$security->randInt(1, 15)];
                if(array_key_exists($value, $comps)) // Competitor 1 loss
                {
                    $comp1bid = $comps[$value]['comp1'];
                    $comp1desc = $bDescr[$security->randInt(16, 30)];
                }
                if(array_key_exists($value, $comps)) // Competitor 2 loss
                {
                    $comp2bid = $comps[$value]['comp2'];
                    $comp2desc = $bDescr[$security->randInt(16, 30)];
                }
            }
            else // Business will lose profit all day
            {
                $type = 2;
                $compType = 3;
                $bid = $value;
                $desc = $bDescr[$security->randInt(16, 30)];
                if(array_key_exists($value, $comps)) // Competitor 1 gainz
                {
                    $comp1bid = $comps[$value]['comp1'];
                    $comp1desc = $bDescr[$security->randInt(1, 15)];
                }
                if(array_key_exists($value, $comps)) // Competitor 2 gainz
                {
                    $comp2bid = $comps[$value]['comp2'];
                    $comp2desc = $bDescr[$security->randInt(1, 15)];
                }
            }
            $con->setData("
                INSERT INTO `business_news` (`description_nl`, `description_en`, `businessID`, `type`) VALUES (:dNL, :dEN, :bid, :type)
            ", array(':dNL' => $desc['nl'], ':dEN' => $desc['en'], ':bid' => $bid, ':type' => $type));
            if(isset($comp1bid) && $comp1bid != 0 && $comp1bid != "")
                $con->setData("
                    INSERT INTO `business_news` (`description_nl`, `description_en`, `businessID`, `type`) VALUES (:dNL, :dEN, :bid, :cType)
                ", array(':dNL' => $comp1desc['nl'], ':dEN' => $comp1desc['en'], ':bid' => $comp1bid, ':cType' => $compType));
            if(isset($comp2bid) && $comp2bid != 0 && $comp2bid != "")
                $con->setData("
                    INSERT INTO `business_news` (`description_nl`, `description_en`, `businessID`, `type`) VALUES (:dNL, :dEN, :bid, :cType)
                ", array(':dNL' => $comp2desc['nl'], ':dEN' => $comp2desc['en'], ':bid' => $comp2bid, ':cType' => $compType));
        }
        $exists = $type = $compType = $bid = $desc = $comp1bid = $comp2bid = $comp1desc = $comp2desc = null;
    }
} // /CHECKED & OK

/* Daily & Weekly lottery drawing 19h servertime */
if(date('H') == 19)
{
    $lotteryStatics = new LotteryStatics();
    $superpot = $lotteryStatics->weeklyDrawDay == true ? true : false;
    $type = $superpot == true ? 1 : 0;
    $ratios = $superpot == true ? $lotteryStatics->weeklyPotRatios : $lotteryStatics->dailyPotRatios;
    $ticketPrice = $superpot == true ? $lotteryStatics->weekPrice : $lotteryStatics->dayPrice;
    $ticketsSold = $con->getDataSR("SELECT COUNT(`id`) AS `tickets` FROM `lottery` WHERE `type`= :t", array(':t' => $type))['tickets'];
    $pot = $ticketsSold * ($ticketPrice * 16);
    $lotteryPossessionId = 18; //Lottery | Possession logic
    $possess = $con->getDataSR("SELECT `id`, `userID` FROM `possess` WHERE `pID`= :id AND `active`='1' AND `deleted`='0'", array(':id' => $lotteryPossessionId)); //Country poss!
    $lotteryPossessId = isset($possess['id']) ? $possess['id'] : 0;
    $lotteryOwnerID = isset($possess['userID']) ? $possess['userID'] : 0;
    $prizes = array();
    foreach($ratios AS $r)
        array_push($prizes, ($pot * $r));
    
    $con->setData("DELETE FROM `lottery_winner` WHERE `type`= :t", array(':t' => $type));
    $winners = $con->getData("SELECT `userID` FROM `lottery` WHERE `type`= :t ORDER BY RAND() LIMIT " . count($ratios), array(':t' => $type));
    $i = 0;
    foreach($winners AS $winner)
    {
        $con->setData("UPDATE `user` SET `bank`=`bank`+ :prize WHERE `id`= :uid AND `active`='1' AND `deleted`='0'", array(':prize' => $prizes[$i], ':uid' => $winner['userID']));
        $costs = ($prizes[$i] / 100);
        $check = $con->getDataSR("SELECT `bank` FROM `user` WHERE `id`= :owner AND `bank`< :costs LIMIT 1", array(':owner' => $lotteryOwnerID, ':costs' => $costs));
        if(isset($check['bank']) && $check['bank'] < $costs) //Lottery Possession logic OWNER CANNOT PAY THIS CYCLE AND ALL NEXT ONES
        {
            $con->setData("
                UPDATE `possess` SET `userID`='0' WHERE `id`= :possessID AND `userID`= :oid AND `active`='1' AND `deleted`='0'
            ", array(':possessID' => $lotteryPossessId, ':oid' => $lotteryOwnerID));
            if($i == (count($ratios) - 1)) //Notification, owner couldn't keep lottery runnin' LAST CYCLE
                $con->setData("
                    INSERT INTO `notification` (`userID`, `notification`, `params`, `date`) VALUES (:wid, :note, :params, NOW())
                ", array(':wid' => $lotteryOwnerID, ':note' => 'OWNER_CANNOT_KEEP_LOTTERY', ':params' => $params));
        }
        else //Lottery Possession logic OWNER PAYS COSTS
            $con->setData("
                UPDATE `possess` SET `profit`=`profit`- :c, `profit_hour`=`profit_hour`- :c WHERE `id`= :possessID;
                UPDATE `user` SET `bank`=`bank`- :c WHERE `id`= :oid AND `active`='1' AND `deleted`='0'
            ", array(
                ':c' => $costs, ':possessID' => $lotteryPossessId,
                ':oid' => $lotteryOwnerID
            ));
        
        $con->setData("
            INSERT INTO `lottery_winner` (`type`, `userID`, `prize`, `place`) VALUES (:t, :wid, :pr, :pl)
        ", array(':t' => $type, ':wid' => $winner['userID'], ':pr' => $prizes[$i], ':pl' => ($i + 1)));
        //User won lottery notification
        $params = "prize=" . number_format($prizes[$i], 0, '', ',') . "&place=" . ($i + 1);
        $note = $superpot == true ? 'USER_WON_WEEKLY_LOTTERY' : 'USER_WON_DAILY_LOTTERY';
        $con->setData("
            INSERT INTO `notification` (`userID`, `notification`, `params`, `date`) VALUES (:wid, :note, :params, NOW())
        ", array(':wid' => $winner['userID'], ':note' => $note, ':params' => $params));
        
        $i++;
    }
    $con->setData("DELETE FROM `lottery` WHERE `type`= :t", array(':t' => $type));
} // /CHECKED & OK

/* Gym stats drainage /TO DO */
