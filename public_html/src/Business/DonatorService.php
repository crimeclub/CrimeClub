<?PHP

namespace src\Business;

use src\Business\Logic\game\Statics\Donator AS DonatorStatics;
use app\config\Routing;
use src\Business\FamilyService;
use src\Data\DonatorDAO;
 
class DonatorService extends DonatorStatics
{
    private $data;
    
    public $statuses = array();
    
    public function __construct()
    {
        $this->data = new DonatorDAO();
        
        global $lang;
        $dName = "donator";
        if($lang == 'nl') $dName = "donateur";
        $this->statuses = array(
            1 =>  ucfirst($dName),
            5 => "VIP",
            10 => "Gold Member"
        );
    }
    
    public function __destruct()
    {
        $this->data = null;
    }
    
    public function getRecordsCount($type)
    {
        return $this->data->getRecordsCount($type);
    }
    
    public function interactDonationShop($post)
    {
        global $security;
        global $language;
        global $langs;
        $l = $language->donationShopLangs();
        global $userData;
        $family = new FamilyService();
        
        $donator = isset($post['donator']) ? true : null;
        $vip = isset($post['vip']) ? true : null;
        $goldMember = isset($post['gold-member']) ? true : null;
        $vipFamily = isset($post['vip-family']) ? true : null;
        
        $familyData = $family->getFamilyDataByName($userData->getFamily());
        if(is_object($familyData)) $familyVip = $familyData->getVip();
        $hasStatus = $hasFamilyStatus = false;
        if(isset($donator))
        {
            $donatorID = 1;
            $creditsNeeded = 100;
        }
        elseif(isset($vip))
        {
            $donatorID = 5;
            $creditsNeeded = 500;
        }
        elseif(isset($goldMember))
        {
            $donatorID = 10;
            $creditsNeeded = 1250;
        }
        elseif(isset($vipFamily))
            $creditsNeeded = 500;
        
        switch($userData->getDonatorID())
        {
            case 1:
                if(isset($vip))
                    $creditsNeeded = 400;
                elseif(isset($goldMember))
                    $creditsNeeded = 1400;
                
                $hasStatus = isset($donator) && !isset($vipFamily) ? true : false;
                break;
            case 5:
                if(isset($goldMember))
                    $creditsNeeded = 1000;
                
                $hasStatus = (isset($donator) || isset($vip)) && !isset($vipFamily) ? true : false;
                break;
            case 10:
                $hasStatus = !isset($vipFamily) ? true : false;
                break;
        }
        $hasFamilyStatus = isset($familyVip) && $familyVip == true && isset($vipFamily) ? true : false;

        if($security->checkToken($post['security-token']) == FALSE)
        {
            $error = $langs['INVALID_SECURITY_TOKEN'];
        }
        if($userData->getCredits() < $creditsNeeded)
        {
            $error = $l['NOT_ENOUGH_CREDITS'];
        }
        if($hasStatus === true)
        {
            $error = $l['USER_ALREADY_HAS_STATUS'];
        }
        if($hasFamilyStatus)
        {
            $error = $l['FAMILY_ALREADY_VIP'];
        }
        if(isset($vipFamily) && !is_object($familyData))
        {
            $error = $l['NO_FAMILY'];
        }
        if(isset($error))
        {
            return Routing::errorMessage($error);
        }
        else
        {
            global $route;
            if(isset($donatorID))
            {
                $this->data->buyStatus($donatorID, $creditsNeeded);
                
                $replaces = array(
                    array('part' => $this->statuses[$donatorID], 'message' => $l['BOUGHT_STATUS_SUCCESS'], 'pattern' => '/{status}/'), //
                    array('part' => number_format($creditsNeeded, 0, '', ','), 'message' => FALSE, 'pattern' => '/{credits}/')
                );
                $replacedMessage = $route->replaceMessageParts($replaces);
            }
            else
            {
                $this->data->buyFamilyVip($familyData->getId(), $creditsNeeded);
                
                $replacedMessage = $l['BOUGHT_FAMILY_VIP_SUCCESS'];
            }
            
            return Routing::successMessage($replacedMessage);
        }
    }
}
