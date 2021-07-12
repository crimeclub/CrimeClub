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

/** CRON FIVE MINUTES RUNS EVERY FIVE MINUTES **/

use Doctrine\Common\ClassLoader;
use app\config\Security;
use src\Data\config\DBConfig;

/* Error reporting (debugging) */
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

/* Set correct timezone */
ini_set('date.timezone', 'Europe/Amsterdam');

/* Define game_doc_root & database credentials (included in config) */
require_once __DIR__ . '/../config/config.php';

/* Enable Autoloading with doctrine */
require_once GAME_DOC_ROOT . '/vendor/Doctrine/Common/ClassLoader.php';
$srcLoader = new ClassLoader('src'   ,  GAME_DOC_ROOT);
$srcLoader->register();

/* Open db connection */
$con = new DBConfig();

/* Security class (for true randomness) */
require_once GAME_DOC_ROOT.'/app/config/security.php';
$security = new Security();

/** ALL CRON FIVE MINUTES RELATED CODE START FROM HERE **/

/* Business stocks up/down according to news large amounts */
if(date('H') >= 8 && date('H') <= 18)
{
    for($i = 1; $i <= 4; $i++)
    {
        $businesses = $con->getData("SELECT `businessID` FROM `business_news` WHERE `type`= :i", array(':i' => $i));
        foreach($businesses AS $b)
        {
            $moneyRand = $security->randInt(20, 40) / 100;
            $backupRand = $security->randInt(10, 30) / 100;
            $bckpBackupRand = $security->randInt(5, 20) / 100;
            if($i == 1 || $i == 3) // Climbing businesses
            {
                $con->setData("
                    UPDATE `business` SET `last_price`=`last_price`+ :moneyRand WHERE `id`= :bid AND `last_price`<='500' LIMIT 1;
                    UPDATE `business` SET `last_price`=`last_price`+ :backupRand WHERE `id`= :bid AND `last_price`<='525' AND `last_price`>'500' LIMIT 1;
                    UPDATE `business` SET `last_price`=`last_price`+ :bckp WHERE `id`= :bid AND `last_price`<='550' AND `last_price`>'525' LIMIT 1;
                    UPDATE `business` SET `high_price`=`last_price` WHERE `high_price`<`last_price`
                ", array(':moneyRand' => $moneyRand, ':bid' => $b['businessID'], ':backupRand' => $backupRand, ':bckp' => $bckpBackupRand));
            }
            elseif($i == 2 || $i == 4) // Falling businesses
            {
                $con->setData("
                    UPDATE `business` SET `last_price`=`last_price`- :moneyRand WHERE `id`= :bid AND `last_price`>='30' LIMIT 1;
                    UPDATE `business` SET `last_price`=`last_price`- :backupRand WHERE `id`= :bid AND `last_price`>='20' AND `last_price`<'30' LIMIT 1;
                    UPDATE `business` SET `last_price`=`last_price`- :bckp WHERE `id`= :bid AND `last_price`>'3' AND `last_price`<'20' LIMIT 1;
                    UPDATE `business` SET `low_price`=`last_price` WHERE `low_price`>`last_price`
                ", array(':moneyRand' => $moneyRand, ':bid' => $b['businessID'], ':backupRand' => $backupRand, ':bckp' => $bckpBackupRand));
            }
        }
    }
} // /CHECKED & OK
