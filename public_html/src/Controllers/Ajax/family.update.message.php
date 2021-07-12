<?PHP

use src\Business\FamilyService;

require_once __DIR__ . '/.inc.head.ajax.php';

$famID = $userData->getFamilyID();
if(isset($_POST['message']) && isset($_POST['security-token']) && $famID > 0)
{
    $family = new FamilyService();
    
    $response = $family->updateFamilyMessage($_POST);
    
    require_once __DIR__ . '/.inc.foot.ajax.php';
    $twigVars['response'] = $response;
    
    echo $twig->render('/src/Views/game/Ajax/.default.response.twig', $twigVars);
}
