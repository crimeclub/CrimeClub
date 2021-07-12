<?PHP

use src\Business\FamilyService;

require_once __DIR__ . '/.inc.head.ajax.php';

$famID = $userData->getFamilyID();
if(!empty($_POST['security-token']) && $famID > 0 && (isset($_POST['abolish']) || isset($_POST['abolish-confirm'])))
{
    $family = new FamilyService();
    
    $response = $family->abolishFamily($_POST);
    
    require_once __DIR__ . '/.inc.foot.ajax.php';
    $twigVars['response'] = $response;
    
    echo $twig->render('/src/Views/game/Ajax/.default.response.twig', $twigVars);
}
