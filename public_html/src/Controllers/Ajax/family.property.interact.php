<?PHP

use src\Business\UserService;
use src\Business\FamilyService;
use src\Business\FamilyPropertyService;
use src\Business\RedLightDistrictService;
use src\Business\Logic\Pagination;

require_once __DIR__ . '/.inc.head.ajax.php';

$hasRights = FALSE;
if($userData->getFamilyBoss() === true || $userData->getFamilyBankmanager() === true) $hasRights = TRUE;

$allowedProperties = array('bullet-factory', 'brothel');
$propertySet = (isset($_POST['property']) && in_array($_POST['property'], $allowedProperties)) ? true : null;
$buyCheck = (isset($_POST['buy']) && $propertySet && $hasRights);
$upgradeCheck = (isset($_POST['upgrade']) && $propertySet && $hasRights);
$produceCheck = (isset($_POST['produce']) && $hasRights);
$donateCheck = (isset($_POST['donate']) && isset($_POST['bullets']));
$sendCheck = (isset($_POST['send']) && isset($_POST['bullets']) && $hasRights);
$addCheck = (isset($_POST['add']));
$takeCheck = (isset($_POST['take-away']));

$acceptPost = false;
if($buyCheck || $upgradeCheck || $produceCheck || $donateCheck || $sendCheck || $addCheck || $takeCheck)
    $acceptPost = true;

$famID = $userData->getFamilyID();
if(!empty($_POST['security-token']) && $famID > 0 && $acceptPost)
{
    $userService = new UserService();
    $family = new FamilyService();
    $famProperty = new FamilyPropertyService();
    
    if($buyCheck)
        $response = $famProperty->buyFamilyProperty($_POST);
    elseif($upgradeCheck)
        $response = $famProperty->upgradeFamilyProperty($_POST);
    elseif($produceCheck)
        $response = $famProperty->produceBullets($_POST);
    elseif($donateCheck)
        $response = $famProperty->donateBulletsToFamily($_POST);
    elseif($sendCheck)
        $response = $famProperty->sendBulletsToUser($_POST);
    elseif($addCheck)
        $response = $famProperty->addWhoresToFamilyBrothel($_POST);
    elseif($takeCheck)
        $response = $famProperty->takeAwayWhoresFromFamilyBrothel($_POST);
    
    $propertyAction = $buyCheck || $upgradeCheck || $produceCheck ? true : null;
    if($propertyAction)
    {
        if(!isset($_POST['property'])) $_POST['property'] = 'bullet-factory';
        switch($_POST['property'])
        {
            default:
            case 'bullet-factory':
                $pagination = new Pagination($famProperty, 15, 15);
                $pageInfo = $famProperty->getFamilyBulletFactoryPageInfo($pagination->from, $pagination->to);
                $capacity = $famProperty->bfCapacities[$pageInfo['bf']->getBulletFactory()];
                $productions = $famProperty->getBulletFactoryProductionsByLevel($pageInfo['bf']->getBulletFactory());
                $productionCosts = $famProperty->bfProductionPrices;
                $statusData = $userService->getStatusPageInfo();
                $familyMembers = $family->getFamilyMembersByFamilyId($userData->getFamilyID());
                break;
            case 'brothel':
                $rld = new RedLightDistrictService();
                $pageInfo = $famProperty->getFamilyBrothelPageInfo();
                $capacity = $famProperty->brothelCapacities[$pageInfo['brothel']->getBrothel()];
                $streetWhores = $rld->getRedLightDistrictPageInfo()->getWhoresStreet();
                break;
        }
    }
    
    require_once __DIR__ . '/.inc.foot.ajax.php';
    $twigVars['response'] = $response;
    if($propertyAction)
    {
        $twigVars['langs'] = array_merge($twigVars['langs'], $language->familyPropertiesLangs()); // Extend base langs
        $twigVars['pageInfo'] = $pageInfo;
        $twigVars['hasRights'] = $hasRights;
        $twigVars['price'] = $famProperty->price;
        $twigVars['capacity'] = $capacity;
        $twigVars['upgradePrices'] = $famProperty->upgradePrices;
        if(isset($pagination)) $twigVars['pagination'] = $pagination;
        if(isset($productions)) $twigVars['productions'] = $productions;
        if(isset($productionCosts)) $twigVars['productionCosts'] = $productionCosts;
        if(isset($statusData)) $twigVars['statusData'] = $statusData;
        if(isset($familyMembers)) $twigVars['familyMembers'] = $familyMembers;
        if(isset($streetWhores)) $twigVars['streetWhores'] = $streetWhores;
    }
    
    if($propertyAction)
        echo $twig->render('/src/Views/game/Ajax/family-properties/' . strtolower($_POST['property']) . '/property.twig', $twigVars);
    else
        echo $twig->render('/src/Views/game/Ajax/.default.response.twig', $twigVars);
}
